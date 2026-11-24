<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * scharge's monthly fee for a company: units in use at the full price per unit; vacant units at a discount that grows
 * with how long the company has been a client (owner, 24 Nov 2026): none for the first 6 months, 25% off from month 7,
 * 50% off from the first anniversary (config billing.vacant_discounts). Used by the first bill and the staff price box.
 */
class UnitPricing
{
    /** When the company became a client: its first subscription, else when the company was created. */
    public static function clientSince(int $companyId): ?Carbon
    {
        if (Schema::hasTable('company_subscriptions')) {
            $col = Schema::hasColumn('company_subscriptions', 'starts_at') ? 'COALESCE(starts_at, created_at)' : 'created_at';
            $at = DB::table('company_subscriptions')->where('company_id', $companyId)->selectRaw("MIN($col) as at")->value('at');
            if ($at) {
                return Carbon::parse($at);
            }
        }
        $at = DB::table('companies')->where('id', $companyId)->value('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    /** The vacant-unit discount (%) for a client who started on $since, on the date $at. */
    public static function vacantDiscount(?Carbon $since, ?Carbon $at = null): float
    {
        if (! $since) {
            return 0.0;
        }
        $at = ($at ?? now())->copy()->startOfDay();
        $pct = 0.0;
        foreach (self::schedule() as $after => $discount) {
            if ($since->copy()->startOfDay()->addMonthsNoOverflow($after)->lte($at)) {
                $pct = $discount;
            }
        }

        return $pct;
    }

    /** @return array<int, float> months as a client => % off vacant units, ascending */
    public static function schedule(): array
    {
        $s = array_map('floatval', (array) config('billing.vacant_discounts', [6 => 25, 12 => 50]));
        ksort($s);

        return $s;
    }

    /** Units in use and vacant units (status 'vacant'), through the company's properties or the unit's own company_id. */
    public static function unitCounts(int $companyId): array
    {
        $rows = DB::table('units')->where(function ($q) use ($companyId) {
            $q->whereIn('property_id', DB::table('properties')->where('company_id', $companyId)->select('id'));
            if (Schema::hasColumn('units', 'company_id')) {
                $q->orWhere('company_id', $companyId);
            }
        })->selectRaw((Schema::hasColumn('units', 'status') ? "SUM(CASE WHEN status = 'vacant' THEN 1 ELSE 0 END)" : '0').' as vacant, COUNT(*) as total')->first();
        $vacant = (int) ($rows->vacant ?? 0);

        return ['in_use' => (int) ($rows->total ?? 0) - $vacant, 'vacant' => $vacant];
    }

    /**
     * One month's fee.
     *
     * @return array{price: float, in_use: int, vacant: int, discount: float, vacant_rate: float, amount: float, lines: array<int, array{month:string, units:int, rate:float, amount:float}>}
     */
    public static function month(int $companyId, float $price, ?Carbon $at = null): array
    {
        ['in_use' => $inUse, 'vacant' => $vacant] = self::unitCounts($companyId);
        $discount = self::vacantDiscount(self::clientSince($companyId), $at);
        $price = round($price, 2);
        $vacantRate = round($price * (100 - $discount) / 100, 2);
        $lines = [];
        if ($inUse) {
            $lines[] = ['month' => 'Units in use', 'units' => $inUse, 'rate' => $price, 'amount' => round($inUse * $price, 2)];
        }
        if ($vacant) {
            $lines[] = ['month' => 'Vacant units'.($discount > 0 ? ' ('.rtrim(rtrim(number_format($discount, 1), '0'), '.').'% off)' : ''),
                'units' => $vacant, 'rate' => $vacantRate, 'amount' => round($vacant * $vacantRate, 2)];
        }

        return ['price' => $price, 'in_use' => $inUse, 'vacant' => $vacant, 'discount' => $discount, 'vacant_rate' => $vacantRate,
            'amount' => round(array_sum(array_column($lines, 'amount')), 2), 'lines' => $lines];
    }
}
