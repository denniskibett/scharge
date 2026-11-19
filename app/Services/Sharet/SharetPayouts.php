<?php

namespace App\Services\Sharet;

use App\Models\CompanyIntegration;
use App\Models\SharetPaymentRequest;
use App\Models\SharetSweep;
use App\Services\Integrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rail-mode sweeps: rent paid into scharge's Sharet wallet is paid out to each landlord
 * company at 08:00, 12:00 and 16:00 Africa/Nairobi, minus scharge's fee.
 * One sweep per company per slot (unique company_id + slot), so a re-run cannot pay twice.
 */
class SharetPayouts
{
    /** Sharet `to` object for a company's payout settings, or null when incomplete. */
    public static function destination(?CompanyIntegration $settings): ?array
    {
        if (! $settings) {
            return null;
        }
        $g = fn ($k) => $settings->get($k);

        $to = match ($g('payout_type')) {
            'mpesa' => ['type' => 'mpesa', 'phone' => $g('phone')],
            'paybill' => ['type' => 'paybill', 'paybill' => $g('paybill'), 'account' => $g('account')],
            'till' => ['type' => 'till', 'till' => $g('till')],
            'bank' => ['type' => 'bank', 'bank' => $g('bank'), 'account' => $g('bank_account')],
            'absa' => ($absa = Integrations::for($settings->company_id, 'absa_bank'))
                ? ['type' => 'bank', 'bank' => 'absa', 'account' => $absa->get('account_number')]
                : null,
            default => null,
        };

        return $to && ! in_array(null, $to, true) && ! in_array('', $to, true) ? $to : null;
    }

    /** @return array<int, array{company_id:int, status:string, net?:float, message?:string}> */
    public function sweep(?string $slot = null, bool $dryRun = false): array
    {
        $platform = Integrations::sharet();
        if (! $platform) {
            return [['company_id' => 0, 'status' => 'skipped', 'message' => 'Sharet is not configured']];
        }
        $client = SharetClient::fromIntegration($platform);
        $slot ??= now('Africa/Nairobi')->format('Y-m-d\TH');
        $feePercent = (float) $platform->get('fee_percent', config('sharet.fee_percent'));
        $minimum = (float) $platform->get('sweep_minimum', config('sharet.sweep_minimum'));
        $results = [];

        $companyIds = SharetPaymentRequest::where('mode', 'rail')->where('status', 'paid')->whereNull('sweep_id')
            ->where('environment', $client->environment)->whereNotNull('company_id')
            ->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $settings = Integrations::for($companyId, 'sharet_payouts');
            $to = self::destination($settings);
            $pending = BillPayments::unsweptCollections($companyId, $client->environment);
            $debitsQ = BillPayments::installed() ? BillPayments::unsweptDebits($companyId, $client->environment) : null;
            // Sharet pays whole shillings: round the payout down, the cents stay with the fee. Bills the company paid from
            // its scharge wallet (BillPayments) come off this payout.
            ['gross' => $gross, 'fee' => $fee, 'debits' => $debits, 'available' => $net] = BillPayments::split((float) $pending->sum('amount'), (float) ($debitsQ?->sum('amount') ?? 0), $feePercent);

            if (! $to) {
                $results[] = ['company_id' => $companyId, 'status' => 'skipped', 'message' => 'No payout destination set'];

                continue;
            }
            if ($net < $minimum) {
                $results[] = ['company_id' => $companyId, 'status' => 'skipped', 'net' => $net, 'message' => "Below the KES {$minimum} minimum; rolls over"];

                continue;
            }
            if ($dryRun) {
                $results[] = ['company_id' => $companyId, 'status' => 'would_pay', 'net' => $net, 'message' => "gross {$gross}, fee {$fee}".($debits ? ", bills paid from wallet {$debits}" : '')];

                continue;
            }

            $sweep = DB::transaction(function () use ($companyId, $slot, $pending, $debitsQ, $feePercent, $minimum, $to) {
                if (SharetSweep::where('company_id', $companyId)->where('slot', $slot)->exists()) {
                    return null;   // this slot already ran for this company
                }
                // Lock and recount: a wallet payment made since the first count must not be paid out as well.
                $ids = (clone $pending)->lockForUpdate()->pluck('id');
                $debitIds = $debitsQ ? (clone $debitsQ)->lockForUpdate()->pluck('id') : collect();
                $split = BillPayments::split((float) SharetPaymentRequest::whereIn('id', $ids)->sum('amount'),
                    (float) \App\Models\BillingPayment::whereIn('id', $debitIds)->sum('amount'), $feePercent);
                if ($split['available'] < $minimum) {
                    return false;
                }
                $sweep = SharetSweep::create([
                    'company_id' => $companyId, 'slot' => $slot, 'gross' => $split['gross'], 'fee' => $split['fee'], 'net' => $split['available'],
                    'destination' => $to, 'status' => 'paying',
                ]);
                SharetPaymentRequest::whereIn('id', $ids)->update(['sweep_id' => $sweep->id]);
                if ($debitIds->isNotEmpty()) {
                    \App\Models\BillingPayment::whereIn('id', $debitIds)->update(['sweep_id' => $sweep->id]);
                }
                $sweep->update(['requests_count' => $ids->count()]);

                return $sweep;
            });

            if (! $sweep) {
                $results[] = ['company_id' => $companyId, 'status' => 'skipped', 'message' => $sweep === false ? "Below the KES {$minimum} minimum; rolls over" : "Slot {$slot} already swept"];

                continue;
            }
            $net = (float) $sweep->net;

            $res = $client->post('/payments', [
                'amount' => number_format($net, 2, '.', ''),
                'to' => $to,
                'reference' => "scharge rent payout {$slot}",
            ], "scharge-sweep-{$companyId}-{$slot}");

            $this->record($sweep, $res->successful() ? $res->json() : ['status' => 'failed', 'failure' => $res->json('detail') ?? $res->json('title') ?? ('HTTP '.$res->status())]);
            $results[] = ['company_id' => $companyId, 'status' => $sweep->fresh()->status, 'net' => $net, 'message' => $sweep->fresh()->failure];
        }

        return $results;
    }

    /** Apply a Sharet payment object (from the payout call or a payment.* webhook) to a sweep. */
    public function record(SharetSweep $sweep, array $payment): void
    {
        $status = match ($payment['status'] ?? null) {
            'paid' => 'paid',
            'paying' => 'paying',
            default => 'failed',
        };
        $failure = $status === 'failed' ? mb_substr((string) (is_array($payment['failure'] ?? null) ? json_encode($payment['failure']) : ($payment['failure'] ?? 'Payout failed')), 0, 250) : null;

        $sweep->update(['status' => $status, 'sharet_payment_id' => $payment['id'] ?? $sweep->sharet_payment_id, 'failure' => $failure]);

        if ($status === 'failed') {
            // Release the collections so the next sweep tries again.
            SharetPaymentRequest::where('sweep_id', $sweep->id)->update(['sweep_id' => null]);
            if (BillPayments::installed()) {
                \App\Models\BillingPayment::where('sweep_id', $sweep->id)->update(['sweep_id' => null]);
            }
            Log::warning('Sharet sweep payout failed', ['sweep_id' => $sweep->id, 'company_id' => $sweep->company_id, 'failure' => $failure]);
        }
    }
}
