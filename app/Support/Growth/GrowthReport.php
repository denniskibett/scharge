<?php

namespace App\Support\Growth;

use App\Support\SiteAnalytics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers behind Sales CRM › Growth, for one period and one site (or all six). Works on MySQL and SQLite.
 *
 * Definitions used everywhere on the hub:
 *  - a "visit" is one visitor on one site on one day (the anonymous visitor id rotates daily by design);
 *  - its landing page and source are those of the first page view of that visit;
 *  - a conversion's source / landing page is the visitor's first touch that browser session (sent by the site);
 *  - conversion rate = conversions ÷ visits.
 */
class GrowthReport
{
    public readonly Carbon $from;

    public function __construct(public readonly int $days, public readonly ?string $site = null)
    {
        $this->from = now()->subDays($days - 1)->startOfDay();
    }

    public function views(): Builder
    {
        return DB::table('site_page_views')->where('created_at', '>=', $this->from)->where('is_staff', 0)
            ->when($this->site, fn ($q) => $q->where('site', $this->site));
    }

    public function conversions(): Builder
    {
        return DB::table('site_events')->where('name', 'conversion')->where('created_at', '>=', $this->from)
            ->when($this->site, fn ($q) => $q->where('site', $this->site));
    }

    /** Visits per site, with the landing page and source of each visit, aggregated. Memoised. */
    public function landings(): Collection
    {
        return $this->landings ??= DB::table('site_page_views as v')
            ->joinSub($this->views()->selectRaw('min(id) as id')->groupBy('site', 'visitor', DB::raw('date(created_at)')), 'f', 'f.id', '=', 'v.id')
            ->selectRaw('v.site, v.path, v.utm_source, v.utm_medium, v.utm_campaign, v.referrer_host, count(*) as n')
            ->groupBy('v.site', 'v.path', 'v.utm_source', 'v.utm_medium', 'v.utm_campaign', 'v.referrer_host')
            ->get()
            ->map(function ($r) {
                $source = $r->utm_source ?: ($r->referrer_host ?: 'direct');
                $medium = $r->utm_medium ?: ($r->referrer_host ? (SiteAnalytics::isSearch($r->referrer_host) ? 'organic' : 'referral') : null);

                return (object) ['site' => $r->site, 'path' => $r->path, 'source' => $source, 'medium' => $medium, 'campaign' => $r->utm_campaign,
                    'channel' => SiteAnalytics::channel($source, $medium), 'visits' => (int) $r->n];
            });
    }

    private ?Collection $landings = null;

    private ?Collection $conversionRows = null;

    /** Conversions aggregated by site, name, first-touch source/medium/campaign and landing page. Memoised. */
    public function conversionRows(): Collection
    {
        return $this->conversionRows ??= $this->conversions()
            ->selectRaw("site, label, coalesce(source, 'direct') as source, medium, campaign, landing_path, count(*) as n")
            ->groupBy('site', 'label', 'source', 'medium', 'campaign', 'landing_path')->get()
            ->map(function ($r) {
                $r->n = (int) $r->n;
                $r->channel = SiteAnalytics::channel($r->source, $r->medium);

                return $r;
            });
    }

    /** One row per site: visits, page views, conversions, rate, organic share, last beacon ever. */
    public function perSite(): Collection
    {
        $views = $this->views()->selectRaw('site, count(*) as views')->groupBy('site')->pluck('views', 'site');
        $visits = $this->landings()->groupBy('site')->map->sum('visits');
        $organic = $this->landings()->where('channel', 'organic')->groupBy('site')->map->sum('visits');
        $paid = $this->landings()->where('channel', 'paid')->groupBy('site')->map->sum('visits');
        $conv = $this->conversionRows()->groupBy('site')->map->sum('n');
        $last = DB::table('site_page_views')->selectRaw('site, max(created_at) as at')->groupBy('site')->pluck('at', 'site');

        return collect(Sites::all())->when($this->site, fn ($c) => $c->only([$this->site]))->map(function ($s, $key) use ($views, $visits, $organic, $paid, $conv, $last) {
            $v = (int) ($visits[$key] ?? 0);
            $c = (int) ($conv[$key] ?? 0);

            return (object) [
                'key' => $key, 'name' => $s['name'], 'url' => $s['url'], 'product' => $s['product'], 'color' => $s['color'],
                'visits' => $v, 'views' => (int) ($views[$key] ?? 0), 'conversions' => $c,
                'rate' => $v ? round($c / $v * 100, 2) : null,
                'organic' => $v ? round(($organic[$key] ?? 0) / $v * 100) : null,
                'paid' => $v ? round(($paid[$key] ?? 0) / $v * 100) : null,
                'last_seen' => isset($last[$key]) ? Carbon::parse($last[$key]) : null,
            ];
        });
    }

    /** Per day (per week over 90 days): views, visits, conversions. */
    public function series(): Collection
    {
        $daily = $this->views()->selectRaw('date(created_at) as d, count(*) as views, count(distinct visitor) as visits')->groupBy('d')->get()->keyBy('d');
        $conv = $this->conversions()->selectRaw('date(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
        $series = collect(range($this->days - 1, 0))->map(function ($ago) use ($daily, $conv) {
            $d = now()->subDays($ago)->toDateString();

            return ['date' => $d, 'views' => (int) ($daily[$d]->views ?? 0), 'visits' => (int) ($daily[$d]->visits ?? 0), 'conversions' => (int) ($conv[$d] ?? 0)];
        });

        return $this->days > 90
            ? $series->chunk(7)->map(fn ($w) => ['date' => $w->first()['date'], 'views' => $w->sum('views'), 'visits' => $w->sum('visits'), 'conversions' => $w->sum('conversions')])->values()
            : $series->values();
    }

    /** Visits, conversions and rate per marketing channel (paid, organic, social, …). */
    public function channels(): Collection
    {
        $conv = $this->conversionRows()->groupBy('channel')->map->sum('n');

        return $this->landings()->groupBy('channel')->map(fn ($rows, $ch) => (object) [
            'channel' => $ch, 'visits' => $rows->sum('visits'), 'conversions' => (int) ($conv[$ch] ?? 0),
        ])->union($conv->map(fn ($n, $ch) => (object) ['channel' => $ch, 'visits' => 0, 'conversions' => (int) $n]))
            ->map(function ($r) {
                $r->rate = $r->visits ? round($r->conversions / $r->visits * 100, 2) : null;

                return $r;
            })->sortByDesc('visits')->values();
    }

    /** Conversions by site × conversion name. */
    public function bySiteAndName(): Collection
    {
        return $this->conversionRows()->groupBy(fn ($r) => $r->site.'|'.$r->label)
            ->map(fn ($rows) => (object) ['site' => $rows->first()->site, 'name' => $rows->first()->label, 'n' => $rows->sum('n')])
            ->sortByDesc('n')->values();
    }

    /** Visits and conversions per site + source/medium/campaign. */
    public function bySource(int $limit = 25): Collection
    {
        $key = fn ($r) => implode('|', [$r->site, $r->source, $r->medium, $r->campaign]);
        $conv = $this->conversionRows()->groupBy($key)->map->sum('n');
        $rows = $this->landings()->groupBy($key)->map(function ($rows, $k) use ($conv) {
            $f = $rows->first();

            return (object) ['site' => $f->site, 'source' => $f->source, 'medium' => $f->medium, 'campaign' => $f->campaign, 'channel' => $f->channel,
                'visits' => $rows->sum('visits'), 'conversions' => (int) ($conv[$k] ?? 0)];
        });
        // Conversions whose first touch has no matching visit in the period (e.g. landed before it started)
        foreach ($this->conversionRows()->groupBy($key) as $k => $rows) {
            if (! $rows->isEmpty() && ! $rows->first()->source === null) {
                continue;
            }
            if (! isset($rows[0]) || $rows->isEmpty() || isset($this->landings()->groupBy($key)[$k])) {
                continue;
            }
            $f = $rows->first();
            $rows2 = (object) ['site' => $f->site, 'source' => $f->source, 'medium' => $f->medium, 'campaign' => $f->campaign, 'channel' => $f->channel, 'visits' => 0, 'conversions' => $rows->sum('n')];
            $rows->put($k, $rows2);
            $extra[$k] = $rows2;
        }

        return $rows->union($extra ?? [])->map(fn ($r) => $this->withRate($r))
            ->sortBy([['conversions', 'desc'], ['visits', 'desc']])->take($limit)->values();
    }

    /** Visits and conversions per site + landing page. */
    public function byLanding(int $limit = 25): Collection
    {
        $conv = $this->conversionRows()->groupBy(fn ($r) => $r->site.'|'.$r->landing_path)->map->sum('n');

        return $this->landings()->groupBy(fn ($r) => $r->site.'|'.$r->path)->map(function ($rows, $k) use ($conv) {
            return $this->withRate((object) ['site' => $rows->first()->site, 'path' => $rows->first()->path, 'visits' => $rows->sum('visits'), 'conversions' => (int) ($conv[$k] ?? 0)]);
        })->sortBy([['conversions', 'desc'], ['visits', 'desc']])->take($limit)->values();
    }

    /** Funnel per site: visits → engaged (2+ pages or 30s+ on a page) → converted visits. */
    public function funnel(): Collection
    {
        $visits = DB::query()->fromSub($this->views()
            ->selectRaw('site, visitor, date(created_at) as d, count(*) as n, max(coalesce(duration_ms, 0)) as mx')
            ->groupBy('site', 'visitor', 'd'), 'vd')
            ->selectRaw('site, count(*) as visits, sum(case when n >= 2 or mx >= 30000 then 1 else 0 end) as engaged')
            ->groupBy('site')->get()->keyBy('site');
        $converted = DB::query()->fromSub($this->conversions()->whereNotNull('visitor')
            ->selectRaw('site, visitor, date(created_at) as d')->groupBy('site', 'visitor', 'd'), 'cd')
            ->selectRaw('site, count(*) as n')->groupBy('site')->pluck('n', 'site');

        return $this->perSite()->map(fn ($s) => (object) [
            'key' => $s->key, 'name' => $s->name, 'color' => $s->color,
            'visits' => (int) ($visits[$s->key]->visits ?? 0),
            'engaged' => (int) ($visits[$s->key]->engaged ?? 0),
            'converted' => (int) ($converted[$s->key] ?? 0),
        ])->values();
    }

    /** Pages with real traffic and no conversion that started or happened there. */
    public function leakyPages(int $minViews = 30, int $limit = 10): Collection
    {
        $converting = $this->conversionRows()->flatMap(fn ($r) => [$r->site.'|'.$r->landing_path])
            ->merge($this->conversions()->selectRaw('site, path')->distinct()->get()->map(fn ($r) => $r->site.'|'.$r->path))
            ->flip();

        return $this->views()->selectRaw('site, path, count(*) as views, avg(case when duration_ms between 1000 and 1800000 then duration_ms end) as avg_ms')
            ->groupBy('site', 'path')->having('views', '>=', $minViews)->orderByDesc('views')->limit(200)->get()
            ->reject(fn ($r) => isset($converting[$r->site.'|'.$r->path]))
            ->take($limit)->values();
    }

    /** Every site's last beacon, for "is the tracker installed?" */
    public static function lastSeen(): Collection
    {
        return DB::table('site_page_views')->selectRaw('site, max(created_at) as at')->groupBy('site')->pluck('at', 'site')
            ->map(fn ($at) => Carbon::parse($at));
    }

    private function withRate(object $r): object
    {
        $r->rate = $r->visits ? round($r->conversions / $r->visits * 100, 2) : null;

        return $r;
    }
}
