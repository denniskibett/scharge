<?php

namespace App\Services\Rent;

use App\Models\CompanyIntegration;
use App\Models\Invoice;
use App\Models\PaybillPayment;
use App\Models\Payment;
use App\Modules\Properties\Models\Tenancy;
use App\Services\InvoicePaymentService;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Money tenants pay straight to a company's paybill (Safaricom C2B confirmation), matched to a tenancy and applied to
 * its oldest unpaid bills first; anything over stays as the tenant's wallet credit. What cannot be matched waits on
 * Rent collection › Paybill payments for the landlord to pick the tenant (or mark it "not rent").
 *
 * Matching, in order (within the company that owns the paybill):
 *  1. the account number is a unit number (A1, a-1, "A 1"), or building + unit (SUNRISEA1), of one active tenancy;
 *  2. the account number is a bill number (INV-123 / INV123);
 *  3. the payer's phone is one active tenant's phone (only when Safaricom sends it unmasked).
 * scharge's own paybill (no company) only matches bill numbers, since unit numbers repeat across companies.
 *
 * Idempotent: one row per M-Pesa receipt (trans_id, unique); a row is applied once.
 */
class PaybillPayments
{
    public function __construct(private InvoicePaymentService $payments)
    {
    }

    /** A Safaricom C2B confirmation. Returns the stored row (existing one for a repeat). */
    public function receive(array $c2b): PaybillPayment
    {
        return CompanyAccess::bypass(function () use ($c2b) {
            $transId = trim((string) ($c2b['TransID'] ?? ''));
            abort_if($transId === '' || ! is_numeric($c2b['TransAmount'] ?? null), 422, 'Not a C2B confirmation.');
            $shortcode = (string) ($c2b['BusinessShortCode'] ?? '');

            $row = PaybillPayment::firstOrCreate(['trans_id' => $transId], [
                'company_id' => self::companyForShortcode($shortcode),
                'shortcode' => $shortcode,
                'bill_ref' => mb_substr(trim((string) ($c2b['BillRefNumber'] ?? '')), 0, 60),
                'amount' => round((float) $c2b['TransAmount'], 2),
                'msisdn' => mb_substr((string) ($c2b['MSISDN'] ?? ''), 0, 80) ?: null,
                'payer_name' => mb_substr(trim(implode(' ', array_filter([$c2b['FirstName'] ?? null, $c2b['MiddleName'] ?? null, $c2b['LastName'] ?? null]))), 0, 120) ?: null,
                'paid_at' => self::time($c2b['TransTime'] ?? null),
                'status' => 'unmatched',
                'raw' => $c2b,
            ]);

            if ($row->wasRecentlyCreated) {
                [$tenancy, $how] = $this->match($row);
                if ($tenancy) {
                    $this->apply($row, $tenancy, $how);
                }
            }

            return $row->fresh();
        });
    }

    /** The landlord picks the tenant for an unmatched payment. */
    public function assign(PaybillPayment $row, Tenancy $tenancy, ?int $userId): PaybillPayment
    {
        return CompanyAccess::bypass(fn () => $this->apply($row, $tenancy, 'manual', $userId));
    }

    public function ignore(PaybillPayment $row, ?int $userId, ?string $note): void
    {
        DB::transaction(function () use ($row, $userId, $note) {
            $row = PaybillPayment::whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'unmatched', 422, 'This payment was already handled.');
            $row->update(['status' => 'ignored', 'handled_by' => $userId, 'note' => mb_substr((string) $note, 0, 255) ?: 'Not rent']);
        });
    }

    /** @return array{0: ?Tenancy, 1: ?string} */
    public function match(PaybillPayment $row): array
    {
        $ref = self::key($row->bill_ref);

        // Bill number: INV-123, INV123, inv 123.
        if (preg_match('/^INV0*(\d+)$/', $ref, $m)) {
            $invoice = Invoice::with('tenancy')->find((int) $m[1]);
            if ($invoice?->tenancy && (! $row->company_id || (int) $invoice->owningCompanyId() === (int) $row->company_id)) {
                if (! $row->company_id) {
                    $row->update(['company_id' => $invoice->owningCompanyId()]);
                }

                return [$invoice->tenancy, 'invoice'];
            }
        }
        if (! $row->company_id) {
            return [null, null];
        }

        $tenancies = RentRun::ownedByQuery($row->company_id)->where('status', 'active')->with(['unit.property', 'tenant.user'])->get();

        if ($ref !== '') {
            $hits = $tenancies->filter(fn ($t) => $t->unit && (self::key($t->unit->unit_number) === $ref
                || self::key(($t->unit->property?->name ?? '').$t->unit->unit_number) === $ref));
            if ($hits->count() === 1) {
                return [$hits->first(), 'account'];
            }
        }

        $digits = preg_replace('/\D/', '', (string) $row->msisdn);
        if (strlen($digits) >= 9 && strlen($digits) <= 12 && $digits === preg_replace('/\s/', '', (string) $row->msisdn)) {
            $last9 = substr($digits, -9);
            $hits = $tenancies->filter(fn ($t) => ($p = preg_replace('/\D/', '', (string) $t->tenant?->user?->phone)) !== '' && substr($p, -9) === $last9);
            if ($hits->count() === 1) {
                return [$hits->first(), 'phone'];
            }
        }

        return [null, null];
    }

    /** Apply to the tenancy's unpaid bills, oldest first; the rest (or all, if nothing is owed) is wallet credit. */
    private function apply(PaybillPayment $row, Tenancy $tenancy, string $how, ?int $userId = null): PaybillPayment
    {
        return DB::transaction(function () use ($row, $tenancy, $how, $userId) {
            $row = PaybillPayment::whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'unmatched', 422, 'This payment was already handled.');
            $companyId = $row->company_id ?: RentRun::companyOf($tenancy);
            abort_unless($companyId && (int) RentRun::companyOf($tenancy) === (int) $companyId, 422, 'That tenant is not in this paybill\'s company.');
            // Never record the same M-Pesa receipt twice (e.g. it also came through an STK callback).
            if (Payment::withoutGlobalScope('company')->where('external_reference', $row->trans_id)->exists()) {
                $row->update(['status' => 'applied', 'tenancy_id' => $tenancy->id, 'matched_by' => $how, 'handled_by' => $userId, 'note' => 'Already recorded']);

                return $row;
            }

            $left = (float) $row->amount;
            $ids = [];
            $invoices = Invoice::where('tenancy_id', $tenancy->id)->whereIn('status', ['unpaid', 'partial'])
                ->whereColumn('total_paid', '<', 'total_amount')->orderBy('billing_month')->orderBy('id')->lockForUpdate()->get();
            foreach ($invoices as $i => $invoice) {
                if ($left <= 0) {
                    break;
                }
                $owed = (float) $invoice->total_amount - (float) $invoice->total_paid;
                $portion = $i === $invoices->count() - 1 ? $left : min($left, $owed); // the last bill takes any overpayment (kept as credit)
                $payment = $this->payments->apply($invoice, round($portion, 2), [
                    'method' => Payment::METHOD_MPESA_PAYBILL, 'source' => 'paybill',
                    'transaction_reference' => $ids ? $row->trans_id.'-'.(count($ids) + 1) : $row->trans_id,
                    'external_reference' => $row->trans_id, 'label' => 'M-Pesa paybill payment '.$row->trans_id,
                    'meta' => ['paybill_payment_id' => $row->id, 'bill_ref' => $row->bill_ref, 'payer' => $row->payer_name],
                ]);
                $ids[] = $payment->id;
                $left = round($left - $portion, 2);
            }

            if ($left > 0) { // nothing owed: the whole payment is credit for the next bill
                $tenant = $tenancy->tenant;
                abort_unless($tenant, 422, 'This tenancy has no tenant.');
                $tenant->deposit($left, ['description' => 'M-Pesa paybill payment '.$row->trans_id.' received (credit)', 'source' => 'paybill', 'reference' => $row->trans_id]);
            }

            $row->update(['status' => $ids ? 'applied' : 'credited', 'tenancy_id' => $tenancy->id, 'company_id' => $companyId,
                'matched_by' => $how, 'payment_ids' => $ids, 'handled_by' => $userId]);

            return $row;
        });
    }

    /** The company whose own Daraja paybill this is (Integrations › M-Pesa Daraja); null = scharge's or unknown. */
    public static function companyForShortcode(string $shortcode): ?int
    {
        if ($shortcode === '') {
            return null;
        }
        $map = Cache::remember('paybill-shortcodes', 300, fn () => CompanyIntegration::withoutGlobalScope('company')->where('provider', 'mpesa_daraja')->where('enabled', true)
            ->whereNotNull('company_id')->get()->mapWithKeys(fn ($r) => [(string) $r->get('shortcode') => (int) $r->company_id])->all());

        return $map[$shortcode] ?? null;
    }

    /** Account numbers compared without case, spaces or punctuation: "a-1", "A 1" and "A1" are the same. */
    public static function key(?string $value): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    private static function time(?string $t): ?string
    {
        return $t && preg_match('/^\d{14}$/', $t)
            ? \Carbon\Carbon::createFromFormat('YmdHis', $t, RentSettings::TZ)->utc()->toDateTimeString()
            : now()->toDateTimeString();
    }
}
