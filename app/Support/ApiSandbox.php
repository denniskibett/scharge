<?php

namespace App\Support;

use App\Models\ApiKey;
use App\Models\Company;
use Illuminate\Support\Carbon;

/**
 * The API sandbox: test keys (sk_test_…) issued on the sandbox company read built-in sample data instead of the
 * database, so integrators can try every read endpoint and pay links without any real tenant, bill or payment
 * existing. Nothing is stored, nothing is billed, no SMS or email goes out.
 *
 * Sample data: 3 properties (Kilimani apartments, Ruaka apartments, Westlands serviced suites), 34 units,
 * tenants on most of them, 3 months of bills with rent, water and service charge, M-Pesa payments and
 * water readings. Same columns as the live read API (GET /api/v1/{collection}). Ids are stable; dates follow the
 * current month. Phone numbers and emails are made up (…@example.com): never text or call them.
 *
 * Issue a key: php artisan api:sandbox-key "Company name" person@example.com
 */
class ApiSandbox
{
    public const SOURCE = 'api_sandbox';

    public const NAME = 'scharge API sandbox (sample data)';

    private static ?array $data = null;

    private static Company|false|null $company = false;

    public static function company(): ?Company
    {
        if (self::$company === false) {
            self::$company = Company::withoutGlobalScope('customers')->where('source', self::SOURCE)->first();
        }

        return self::$company;
    }

    public static function companyOrCreate(): Company
    {
        return self::company() ?? self::$company = Company::withoutGlobalScope('customers')->forceCreate([
            'name' => self::NAME, 'kind' => 'company', 'is_active' => false, 'lifecycle' => 'customer', 'source' => self::SOURCE,
            'crm_profile' => ['note' => 'Anchor for API sandbox keys. Holds no records: sandbox keys read App\\Support\\ApiSandbox sample data.'],
        ]);
    }

    public static function is(?ApiKey $key): bool
    {
        return $key && $key->environment !== 'live' && ($c = self::company()) && (int) $key->company_id === (int) $c->id;
    }

    /** A page of one collection, like the live read API. */
    public static function page(string $collection, int $after = 0, int $limit = 100, ?string $updatedSince = null): array
    {
        $rows = collect(self::data()[$collection] ?? [])
            ->filter(fn ($r) => $r['id'] > $after && (! $updatedSince || ! isset($r['updated_at']) || $r['updated_at'] >= Carbon::parse($updatedSince)->toDateTimeString()))
            ->values();
        $page = $rows->take($limit)->values();
        $more = $rows->count() > $limit;

        return ['object' => 'list', 'collection' => $collection, 'sandbox' => true, 'data' => $page, 'has_more' => $more, 'next_after' => $more ? $page->last()['id'] : null];
    }

    public static function invoice(int $id): ?array
    {
        return collect(self::data()['invoices'])->firstWhere('id', $id);
    }

    /** Everything, built once per request from a fixed seed. */
    public static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        mt_srand(20261119);
        $cid = self::company()?->id ?? 0;
        $month = now(config('app.schedule_timezone', 'Africa/Nairobi'))->startOfMonth();
        $ts = fn (Carbon $d) => $d->copy()->utc()->toDateTimeString();
        $created = $ts($month->copy()->subMonths(14));

        $first = ['Achieng', 'Brian', 'Cynthia', 'David', 'Esther', 'Felix', 'Grace', 'Hassan', 'Irene', 'James', 'Joy', 'Kevin', 'Lucy', 'Mercy',
            'Njeri', 'Omar', 'Peter', 'Faith', 'Rose', 'Samuel', 'Teresa', 'Victor', 'Wanjiru', 'Yusuf', 'Zawadi', 'Allan', 'Beatrice', 'Collins'];
        $last = ['Otieno', 'Kamau', 'Wanjiku', 'Mwangi', 'Ochieng', 'Njoroge', 'Akinyi', 'Mohamed', 'Kiptoo', 'Mutua', 'Chebet', 'Kariuki', 'Omondi', 'Wambui'];

        $properties = [
            ['id' => 101, 'name' => 'Sandbox Gardens', 'location' => 'Kilimani, Nairobi', 'units' => 12, 'rent' => [45000, 65000], 'type' => '2 bedroom', 'water' => 150, 'service' => 5000, 'stay' => 'long'],
            ['id' => 102, 'name' => 'Sandbox Heights', 'location' => 'Ruaka, Kiambu', 'units' => 16, 'rent' => [16000, 26000], 'type' => '1 bedroom', 'water' => 120, 'service' => 2000, 'stay' => 'long'],
            ['id' => 103, 'name' => 'Sandbox Suites', 'location' => 'Westlands, Nairobi', 'units' => 6, 'rent' => [0, 0], 'type' => 'studio', 'water' => 0, 'service' => 0, 'stay' => 'short'],
        ];
        $out = array_fill_keys(['properties', 'units', 'people', 'tenants', 'tenancies', 'invoices', 'invoice_items', 'payments', 'water_readings'], []);
        $unitId = 1000; $personId = 5000; $tenantId = 3000; $tenancyId = 4000; $invoiceId = 7000; $itemId = 8000; $paymentId = 9000; $readingId = 6000;

        foreach ($properties as $p) {
            $out['properties'][] = ['id' => $p['id'], 'company_id' => $cid, 'name' => $p['name'], 'location' => $p['location'], 'water_rate' => (string) $p['water'],
                'service_charge' => (string) $p['service'], 'garbage_charge' => '300', 'security_charge' => '0', 'listed_publicly' => 0, 'created_at' => $created, 'updated_at' => $created];
            for ($n = 1; $n <= $p['units']; $n++) {
                $unitId++;
                $short = $p['stay'] === 'short';
                $rent = $short ? 0 : (int) (round(mt_rand($p['rent'][0], $p['rent'][1]) / 500) * 500);
                $occupied = $short || mt_rand(1, 100) <= 82;
                $reading = mt_rand(120, 900);
                $out['units'][] = ['id' => $unitId, 'company_id' => $cid, 'property_id' => $p['id'], 'unit_number' => chr(64 + (int) ceil($n / 4)).($n % 4 ?: 4),
                    'unit_type' => $p['type'], 'rent_amount' => number_format($rent, 2, '.', ''), 'water_charge' => '0.00', 'service_charge' => number_format($p['service'], 2, '.', ''),
                    'garbage_charge' => '300.00', 'security_charge' => '0.00', 'total_monthly_charges' => number_format($rent + $p['service'] + 300, 2, '.', ''),
                    'status' => $short ? 'vacant' : ($occupied ? 'occupied' : 'vacant'), 'stay_type' => $short ? 'short' : 'long', 'property_category' => $short ? 'bnb' : 'residential',
                    'is_active' => 1, 'bnb_nightly_rate' => $short ? number_format(mt_rand(55, 95) * 100, 2, '.', '') : null, 'bnb_cleaning_fee' => $short ? '1500.00' : null,
                    'min_stay_days' => $short ? 1 : null, 'security_deposit_amount' => number_format($rent, 2, '.', ''), 'water_billing_type' => $p['water'] ? 'metered' : null,
                    'previous_water_reading' => $p['water'] ? $reading - 9 : null, 'current_water_reading' => $p['water'] ? $reading : null,
                    'last_reading_date' => $p['water'] ? $month->copy()->subDays(3)->toDateString() : null, 'created_at' => $created, 'updated_at' => $ts($month)];
                if ($short || ! $occupied) {
                    continue;
                }
                // A tenant, their tenancy, and the last 3 months of bills.
                $personId++; $tenantId++; $tenancyId++;
                $fn = $first[array_rand($first)]; $ln = $last[array_rand($last)];
                $moveIn = $month->copy()->subMonths(mt_rand(4, 30));
                $out['people'][] = ['id' => $personId, 'name' => "$fn $ln", 'email' => strtolower($fn.'.'.$ln.$personId).'@example.com', 'phone' => '+2547000'.str_pad((string) ($personId - 5000), 5, '0', STR_PAD_LEFT),
                    'phone2' => null, 'company_id' => $cid, 'country' => 'KE', 'created_at' => $ts($moveIn), 'updated_at' => $ts($moveIn)];
                $out['tenants'][] = ['id' => $tenantId, 'user_id' => $personId, 'id_number' => null, 'emergency_contact' => null, 'notes' => null, 'company_id' => $cid, 'property_id' => $p['id'],
                    'created_at' => $ts($moveIn), 'updated_at' => $ts($moveIn)];
                $out['tenancies'][] = ['id' => $tenancyId, 'company_id' => $cid, 'property_id' => $p['id'], 'tenant_id' => $tenantId, 'unit_id' => $unitId,
                    'move_in_date' => $moveIn->toDateString(), 'move_out_date' => null, 'notes' => null, 'status' => 'active', 'created_at' => $ts($moveIn), 'updated_at' => $ts($moveIn)];
                $prevReading = $reading - 30;
                for ($m = 2; $m >= 0; $m--) {
                    $billMonth = $month->copy()->subMonths($m);
                    $used = mt_rand(6, 14);
                    $readingDate = $billMonth->copy()->subDays(3);
                    $readingId++;
                    $out['water_readings'][] = ['id' => $readingId, 'company_id' => $cid, 'unit_id' => $unitId, 'is_initial' => 0, 'previous_reading' => (string) $prevReading,
                        'current_reading' => (string) ($prevReading + $used), 'consumption' => (string) $used, 'rate_applied' => (string) $p['water'], 'charge' => number_format($used * $p['water'], 2, '.', ''),
                        'billing_type' => 'metered', 'reading_date' => $readingDate->toDateString(), 'recorded_by' => null, 'notes' => null, 'created_at' => $ts($readingDate), 'updated_at' => $ts($readingDate)];
                    $prevReading += $used;
                    $items = [['rent', 'Rent '.$billMonth->format('F Y'), $rent], ['water', 'Water: '.$used.' units × KES '.$p['water'], $used * $p['water']],
                        ['service_charge', 'Service charge', $p['service']], ['garbage', 'Garbage collection', 300]];
                    $total = array_sum(array_column($items, 2));
                    // Older months are paid; this month is a mix of paid, part paid and not yet paid.
                    $roll = mt_rand(1, 100);
                    $paid = $m > 0 ? ($roll <= 92 ? $total : (int) round($total * 0.6)) : ($roll <= 45 ? $total : ($roll <= 70 ? (int) round($total * 0.5) : 0));
                    $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                    $invoiceId++;
                    $issued = $ts($billMonth->copy()->addHours(6));
                    $out['invoices'][] = ['id' => $invoiceId, 'tenancy_id' => $tenancyId, 'invoice_type' => 'monthly', 'billing_month' => $billMonth->format('Y-m'),
                        'total_amount' => number_format($total, 2, '.', ''), 'total_paid' => number_format($paid, 2, '.', ''), 'status' => $status,
                        'company_id' => $cid, 'property_id' => $p['id'], 'created_at' => $issued, 'updated_at' => $issued];
                    $left = $paid;
                    foreach ($items as [$type, $desc, $amount]) {
                        $itemId++;
                        $itemPaid = min($amount, $left);
                        $left -= $itemPaid;
                        $out['invoice_items'][] = ['id' => $itemId, 'invoice_id' => $invoiceId, 'item_type' => $type, 'description' => $desc, 'amount' => number_format($amount, 2, '.', ''),
                            'paid_amount' => number_format($itemPaid, 2, '.', ''), 'payment_id' => null, 'water_units_used' => $type === 'water' ? (string) $used : null,
                            'company_id' => $cid, 'created_at' => $issued, 'updated_at' => $issued];
                    }
                    if ($paid > 0) {
                        $paymentId++;
                        $at = $ts($billMonth->copy()->addDays(mt_rand(1, 8))->setTime(mt_rand(7, 21), mt_rand(0, 59)));
                        $out['payments'][] = ['id' => $paymentId, 'tenant_id' => $tenantId, 'user_id' => $personId, 'invoice_id' => $invoiceId, 'invoice_item_id' => null,
                            'payment_method' => 'mpesa_stk', 'source' => 'mpesa', 'amount' => number_format($paid, 2, '.', ''), 'transaction_reference' => 'SBX'.strtoupper(substr(md5((string) $paymentId), 0, 7)),
                            'external_reference' => null, 'status' => 'completed', 'is_reconciled' => 1, 'reconciled_at' => $at, 'created_at' => $at, 'updated_at' => $at];
                    }
                }
            }
        }
        mt_srand();

        return self::$data = $out;
    }
}
