<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A new account's first scharge bill (owner, 23 Nov 2026): "pay one month, get three months".
 *
 * The account is open from the day it is created. scharge staff set the company's price per unit (Admin › Companies ›
 * Modules). Once the company has units and a price, one month's bill is issued (units × price), due in 7 days. Paying
 * it opens three months of access from the day it is paid. Only companies created on or after billing.first_bill_from
 * get one, and only if they have no scharge bill yet; nothing is ever issued without a price, so no amount is guessed.
 *
 * Issued by `billing:first-bills` (hourly) once the last unit was added over an hour ago, so a bulk import is counted
 * whole, and straight away when staff save a price for a company whose units are already in. Schema:
 * database/schema/2026_11_23_first_bill.sql.
 */
class FirstBill
{
    public static function installed(): bool
    {
        return Schema::hasColumn('companies', 'price_per_unit') && Schema::hasTable('subscription_invoices') && Schema::hasTable('company_subscriptions');
    }

    public static function dueDays(): int
    {
        return max(1, (int) config('billing.first_bill_due_days', 7));
    }

    public static function months(): int
    {
        return max(1, (int) config('billing.first_bill_months', 3));
    }

    /** The company's units (through its properties, or the unit's own company_id). */
    public static function units(int $companyId): int
    {
        return DB::table('units')->where(function ($q) use ($companyId) {
            $q->whereIn('property_id', DB::table('properties')->where('company_id', $companyId)->select('id'));
            if (Schema::hasColumn('units', 'company_id')) {
                $q->orWhere('company_id', $companyId);
            }
        })->count();
    }

    /** When the company's last unit was added (null when it has none or units have no timestamps). */
    private static function lastUnitAt(int $companyId): ?Carbon
    {
        if (! Schema::hasColumn('units', 'created_at')) {
            return null;
        }
        $at = DB::table('units')->where(function ($q) use ($companyId) {
            $q->whereIn('property_id', DB::table('properties')->where('company_id', $companyId)->select('id'));
            if (Schema::hasColumn('units', 'company_id')) {
                $q->orWhere('company_id', $companyId);
            }
        })->max('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    private static function hasBill(int $companyId): bool
    {
        return DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->where('s.company_id', $companyId)->exists();
    }

    /** Whether this company gets the first-bill offer at all (new enough, no bill yet). */
    public static function eligible(object $company): bool
    {
        $from = config('billing.first_bill_from');
        if ($from && (! $company->created_at || Carbon::parse($company->created_at)->lt(Carbon::parse($from)->startOfDay()))) {
            return false;
        }

        return ! self::hasBill((int) $company->id);
    }

    /**
     * What staff see: price, units, the month's amount and where the first bill stands.
     *
     * @return array{installed: bool, eligible: bool, price: ?float, units: int, amount: ?float, bill: ?array, months: int, due_days: int, note: string}
     */
    public static function status(int $companyId): array
    {
        $base = ['installed' => self::installed(), 'eligible' => false, 'price' => null, 'units' => 0, 'amount' => null, 'bill' => null,
            'months' => self::months(), 'due_days' => self::dueDays(), 'note' => ''];
        if (! $base['installed']) {
            return $base + ['note' => 'Run database/schema/2026_11_23_first_bill.sql to set prices.'];
        }
        $c = DB::table('companies')->where('id', $companyId)->first(['id', 'created_at', 'price_per_unit']);
        if (! $c) {
            return $base;
        }
        $units = self::units($companyId);
        $price = $c->price_per_unit !== null ? (float) $c->price_per_unit : null;
        $bill = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->where('s.company_id', $companyId)->orderBy('i.id')->first(['i.id', 'i.invoice_number', 'i.amount', 'i.status', 'i.due_date', 'i.paid_at', 'i.invoice_json']);
        $first = $bill && (json_decode((string) $bill->invoice_json, true)['first_bill'] ?? false);
        $eligible = self::eligible($c);
        $note = match (true) {
            (bool) $first => $bill->status === 'paid' ? 'First bill paid: '.self::months().' months open.' : 'First bill issued, due '.Carbon::parse($bill->due_date)->format('j M Y').'.',
            (bool) $bill => 'Already billed; the first-bill offer does not apply.',
            ! $eligible => 'Created before the first-bill offer; bill it by hand.',
            $price === null => 'Set a price per unit. No bill is issued until you do.',
            $units === 0 => 'The bill is issued once they add units.',
            default => 'The bill is issued within the hour of their last unit being added.',
        };

        return ['installed' => true, 'eligible' => $eligible, 'price' => $price, 'units' => $units,
            'amount' => $price !== null ? round($price * $units, 2) : null,
            'bill' => $bill ? ['id' => $bill->id, 'number' => $bill->invoice_number, 'amount' => (float) $bill->amount, 'status' => $bill->status,
                'due_date' => $bill->due_date, 'first' => $first] : null,
            'months' => self::months(), 'due_days' => self::dueDays(), 'note' => $note];
    }

    /** Staff set (or clear) the price per unit. Issues the bill now when the units are already in and settled. */
    public static function setPrice(int $companyId, ?float $price, int $userId): array
    {
        DB::table('companies')->where('id', $companyId)->update(['price_per_unit' => $price, 'price_set_by' => $userId, 'price_set_at' => now()]);
        if ($price !== null) {
            self::issue($companyId);
        }

        return self::status($companyId);
    }

    /**
     * Issue the first bill if everything is in place. Returns the invoice id, or null when nothing is issued.
     * $settleMinutes: the last unit must be at least this old (a bulk import finishes first).
     */
    public static function issue(int $companyId, int $settleMinutes = 60): ?int
    {
        if (! self::installed()) {
            return null;
        }

        return DB::transaction(function () use ($companyId, $settleMinutes) {
            $c = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first(['id', 'name', 'created_at', 'price_per_unit']);
            if (! $c || $c->price_per_unit === null || (float) $c->price_per_unit <= 0 || ! self::eligible($c)) {
                return null;
            }
            $units = self::units($companyId);
            $last = self::lastUnitAt($companyId);
            if ($units === 0 || ($last && $last->gt(now()->subMinutes($settleMinutes)))) {
                return null;
            }
            $price = round((float) $c->price_per_unit, 2);
            $amount = round($price * $units, 2);

            $sub = DB::table('company_subscriptions')->where('company_id', $companyId)->orderByDesc('id')->first(['id']);
            $subId = $sub?->id;
            if (! $subId) {
                $plan = DB::table('subscription_plans')->orderBy('id')->value('id');
                if (! $plan) {
                    Log::warning('First bill not issued: no subscription plan exists', ['company_id' => $companyId]);

                    return null;
                }
                $row = ['company_id' => $companyId, 'plan_id' => $plan, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()];
                foreach (['billing_cycle' => 'monthly', 'starts_at' => now(), 'auto_renew' => 1] as $col => $v) {
                    if (Schema::hasColumn('company_subscriptions', $col)) {
                        $row[$col] = $v;
                    }
                }
                $subId = DB::table('company_subscriptions')->insertGetId($row);
            }

            $months = self::months();
            $invoice = [
                'company_subscription_id' => $subId,
                'invoice_number' => 'INV-FIRST-'.now()->format('Ymd').'-'.str_pad((string) $companyId, 4, '0', STR_PAD_LEFT),
                'amount' => $amount, 'currency' => 'KES', 'status' => 'pending',
                'due_date' => now()->addDays(self::dueDays())->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ];
            if (Schema::hasColumn('subscription_invoices', 'invoice_json')) {
                $invoice['invoice_json'] = json_encode([
                    'first_bill' => true, 'price_per_unit' => $price, 'unit_count' => $units, 'months' => 1, 'covers_months' => $months,
                    'note' => "Pay one month, get {$months} months: paying this bill opens {$months} months of scharge from the day it is paid.",
                    'lines' => [['month' => 'First month ('.$months.' months of access)', 'units' => $units, 'rate' => $price, 'amount' => $amount]],
                ]);
            }
            $id = DB::table('subscription_invoices')->insertGetId($invoice);
            Log::info('First bill issued', ['company_id' => $companyId, 'invoice_id' => $id, 'units' => $units, 'price' => $price, 'amount' => $amount]);

            return $id;
        });
    }

    /** A bill was paid: if it is a first bill, open the months it buys (from the day it was paid). Safe to call twice. */
    public static function paid(int $invoiceId): void
    {
        if (! self::installed()) {
            return;
        }
        $bill = DB::table('subscription_invoices')->where('id', $invoiceId)->first(['id', 'company_subscription_id', 'status', 'paid_at', 'invoice_json']);
        if (! $bill || $bill->status !== 'paid' || ! (json_decode((string) $bill->invoice_json, true)['first_bill'] ?? false)) {
            return;
        }
        $from = Carbon::parse($bill->paid_at ?? now());
        $until = $from->copy()->addMonths(self::months());
        $sub = DB::table('company_subscriptions')->where('id', $bill->company_subscription_id)->first(['id', 'company_id', 'status', 'ends_at']);
        if (! $sub || ($sub->status === 'active' && $sub->ends_at && Carbon::parse($sub->ends_at)->gte($until))) {
            return;   // already opened
        }
        DB::table('company_subscriptions')->where('id', $sub->id)->update(['status' => 'active', 'starts_at' => $from, 'ends_at' => $until, 'updated_at' => now()]);
        $company = array_filter([
            'subscription_status' => Schema::hasColumn('companies', 'subscription_status') ? 'active' : null,
            'subscription_ends_at' => Schema::hasColumn('companies', 'subscription_ends_at') ? $until : null,
        ]);
        if ($company) {
            DB::table('companies')->where('id', $sub->company_id)->update($company);
        }
        Log::info('First bill paid: months opened', ['company_id' => $sub->company_id, 'invoice_id' => $invoiceId, 'until' => $until->toDateString()]);
    }

    /** Hourly: issue due first bills and open the months of first bills paid by any route. */
    public static function run(): array
    {
        if (! self::installed()) {
            return ['issued' => 0, 'opened' => 0];
        }
        $issued = 0;
        $q = DB::table('companies')->whereNotNull('price_per_unit')->where('price_per_unit', '>', 0);
        if ($from = config('billing.first_bill_from')) {
            $q->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        foreach ($q->pluck('id') as $id) {
            $issued += self::issue((int) $id) ? 1 : 0;
        }
        $opened = 0;
        $paid = DB::table('subscription_invoices')->where('status', 'paid')->where('invoice_json', 'like', '%"first_bill":true%')->pluck('id');
        foreach ($paid as $id) {
            $before = DB::table('company_subscriptions')->where('id', DB::table('subscription_invoices')->where('id', $id)->value('company_subscription_id'))->value('ends_at');
            self::paid((int) $id);
            $after = DB::table('company_subscriptions')->where('id', DB::table('subscription_invoices')->where('id', $id)->value('company_subscription_id'))->value('ends_at');
            $opened += $before !== $after ? 1 : 0;
        }

        return ['issued' => $issued, 'opened' => $opened];
    }
}
