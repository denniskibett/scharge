<?php

namespace App\Services\Listings;

use App\Services\Analytics\GeoIp;
use App\Support\SiteAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renter demand from Open listings (docs/RENTER_DEMAND.md): searches (debounced in the browser, one per visitor and
 * search per 30 minutes), SMS alerts and viewing requests. Anonymous. Read by DemandReport for the heatmaps.
 */
class ListingDemand
{
    /** Rent bands for the heatmap columns (KES per month; short stays per night). */
    public const BANDS = [10000 => 'Up to 10k', 20000 => '10k–20k', 35000 => '20k–35k', 60000 => '35k–60k', 100000 => '60k–100k', PHP_INT_MAX => 'Over 100k'];

    public const STAY_BANDS = [3000 => 'Up to 3k', 6000 => '3k–6k', 10000 => '6k–10k', 20000 => '10k–20k', PHP_INT_MAX => 'Over 20k'];

    /** Weight of each signal in the heatmap: a viewing request means more than a quick search. */
    public const WEIGHTS = ['search' => 1, 'alert' => 3, 'viewing' => 5];

    public function record(Request $request, string $source, ?string $kind, ?string $area, ?int $max, ?int $results): void
    {
        try {
            if (! Schema::hasTable('listing_searches') || ($source === 'search' && SiteAnalytics::skip($request))) {
                return;
            }
            $area = trim((string) $area);
            $area = $area === '' || strcasecmp($area, 'all') === 0 ? null : mb_substr($area, 0, 120);
            $visitor = substr(SiteAnalytics::visitor($request), 0, 16);
            if ($source === 'search' && ! Cache::add('demand:'.$visitor.':'.md5($kind.'|'.$area.'|'.(int) ($max / 5000)), 1, now()->addMinutes(30))) {
                return; // the same search again within 30 minutes
            }
            $place = app(GeoIp::class)->locate($request->ip(), $request->header('CF-IPCountry'));
            DB::table('listing_searches')->insert(['source' => $source, 'kind' => $kind ? mb_substr($kind, 0, 40) : null, 'area' => $area,
                'max_price' => $max && $max > 0 ? min($max, 100000000) : null, 'results' => $results, 'visitor' => $visitor,
                'city' => $place['city'], 'country_code' => $place['country_code'], 'created_at' => now()]);
        } catch (\Throwable) {
            // demand data must never break a page
        }
    }

    public static function band(?int $price, bool $stay = false): string
    {
        if (! $price) {
            return 'Any budget';
        }
        foreach ($stay ? self::STAY_BANDS : self::BANDS as $upTo => $label) {
            if ($price <= $upTo) {
                return $label;
            }
        }

        return 'Any budget';
    }
}
