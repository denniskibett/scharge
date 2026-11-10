<?php

namespace App\Services\Listings;

use App\Services\ListingCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Renter demand heatmaps (docs/RENTER_DEMAND.md): weighted demand per area and budget band, demand nobody serves
 * (searches with no matching listing), demand against the listings on offer, and where searchers are.
 */
class DemandReport
{
    /** @return array<string, mixed> */
    public function build(int $days, string $kind): array
    {
        $stay = $kind === 'Short stays';
        $bands = array_values($stay ? ListingDemand::STAY_BANDS : ListingDemand::BANDS);
        $rows = DB::table('listing_searches')->where('created_at', '>=', now()->subDays($days)->startOfDay())->where('kind', $kind)->get();

        $grid = [];
        $areaTotals = [];
        foreach ($rows as $r) {
            $area = $r->area ?? 'Any area';
            $w = ListingDemand::WEIGHTS[$r->source] ?? 1;
            $band = ListingDemand::band($r->max_price, $stay);
            $grid[$area][$band] = ($grid[$area][$band] ?? 0) + $w;
            $areaTotals[$area] = ($areaTotals[$area] ?? 0) + $w;
        }
        arsort($areaTotals);
        $areas = array_slice(array_keys($areaTotals), 0, 20);
        $max = 1;
        foreach ($areas as $a) {
            foreach ($grid[$a] ?? [] as $v) {
                $max = max($max, $v);
            }
        }

        // What is on offer now, per area.
        $listings = collect(app(ListingCatalog::class)->items())->where('k', $stay ? ListingCatalog::KIND_STAY : ListingCatalog::KIND_RENT);
        $supply = $listings->groupBy('area')->map->count();

        $searches = $rows->where('source', 'search');
        $unmet = $searches->where('results', 0)->filter(fn ($r) => $r->area)->groupBy('area')->map->count()->sortDesc()->take(10);

        return [
            'bands' => array_merge($bands, ['Any budget']),
            'areas' => $areas,
            'grid' => $grid,
            'max' => $max,
            'totals' => [
                'searches' => $searches->count(),
                'alerts' => $rows->where('source', 'alert')->count(),
                'viewings' => $rows->where('source', 'viewing')->count(),
                'people' => $rows->pluck('visitor')->filter()->unique()->count(),
                'unmet_share' => $searches->count() ? (int) round($searches->where('results', 0)->count() * 100 / $searches->count()) : 0,
            ],
            'unmet' => $unmet,
            'versus' => collect($areas)->filter(fn ($a) => $a !== 'Any area')->map(fn ($a) => ['area' => $a, 'demand' => $areaTotals[$a], 'listings' => (int) ($supply[$a] ?? 0)])
                ->sortByDesc(fn ($x) => $x['demand'] / max(1, $x['listings']))->values()->take(12),
            'cities' => $rows->filter(fn ($r) => $r->city)->groupBy('city')->map->count()->sortDesc()->take(10),
        ];
    }
}
