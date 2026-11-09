<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales CRM › Website analytics: traffic, sources, UTM campaigns, form conversions, listing views and a
 * click heatmap, all from our own tables (no third party). "Visitors" are counted per day, because the
 * anonymous visitor id changes daily by design. Signed-in users are left out.
 */
class SiteAnalyticsController extends Controller
{
    public const RANGES = [7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'];

    public function index(Request $request)
    {
        $days = $this->days($request);
        if (! SiteAnalytics::ready()) {
            return view('admin.site-analytics.index', ['ready' => false, 'days' => $days, 'ranges' => self::RANGES]);
        }

        $from = now()->subDays($days - 1)->startOfDay();
        $views = DB::table('site_page_views')->where('created_at', '>=', $from)->where('is_staff', 0);
        $events = DB::table('site_events')->where('created_at', '>=', $from);
        $conversions = (clone $events)->where('name', 'conversion');

        $totals = (clone $views)->selectRaw('count(*) as views, avg(case when duration_ms between 1000 and 1800000 then duration_ms end) as avg_ms, avg(scroll_pct) as avg_scroll')->first();
        $visitorDays = DB::query()->fromSub((clone $views)->selectRaw('visitor, date(created_at) as d, count(*) as n')->groupBy('visitor', 'd'), 'vd')
            ->selectRaw('count(*) as visitors, sum(case when n = 1 then 1 else 0 end) as bounces')->first();
        $convTotal = (clone $conversions)->count();

        // Per-day views, visitors and conversions for the bar chart
        $daily = (clone $views)->selectRaw('date(created_at) as d, count(*) as views, count(distinct visitor) as visitors')->groupBy('d')->get()->keyBy('d');
        $dailyConv = (clone $conversions)->selectRaw('date(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
        $series = collect(range($days - 1, 0))->map(function ($ago) use ($daily, $dailyConv) {
            $d = now()->subDays($ago)->toDateString();

            return ['date' => $d, 'views' => (int) ($daily[$d]->views ?? 0), 'visitors' => (int) ($daily[$d]->visitors ?? 0), 'conversions' => (int) ($dailyConv[$d] ?? 0)];
        });
        if ($days > 90) { // a year of days is unreadable: group by week
            $series = $series->chunk(7)->map(fn ($w) => ['date' => $w->first()['date'], 'views' => $w->sum('views'), 'visitors' => $w->sum('visitors'), 'conversions' => $w->sum('conversions')])->values();
        }

        // Channel = utm_source if tagged, else the referring site, else direct
        $channel = "coalesce(utm_source, referrer_host, 'direct')";
        $sourceVisits = (clone $views)->selectRaw("$channel as source, count(*) as views, count(distinct visitor, date(created_at)) as visitors")
            ->groupBy('source')->orderByDesc('views')->limit(12)->get();
        $sourceConv = (clone $conversions)->selectRaw('source, count(*) as n')->groupBy('source')->pluck('n', 'source');

        $campaigns = (clone $views)->whereNotNull('utm_campaign')
            ->selectRaw('utm_campaign as campaign, max(utm_source) as source, max(utm_medium) as medium, count(*) as views, count(distinct visitor, date(created_at)) as visitors')
            ->groupBy('utm_campaign')->orderByDesc('views')->limit(15)->get();
        $campaignConv = (clone $conversions)->whereNotNull('campaign')->selectRaw('campaign, count(*) as n')->groupBy('campaign')->pluck('n', 'campaign');

        $top = fn ($column, $limit = 8) => (clone $views)->whereNotNull($column)->selectRaw("$column as label, count(*) as views")
            ->groupBy($column)->orderByDesc('views')->limit($limit)->get();

        return view('admin.site-analytics.index', [
            'ready' => true,
            'days' => $days,
            'ranges' => self::RANGES,
            'kpi' => [
                'visitors' => (int) $visitorDays->visitors,
                'views' => (int) $totals->views,
                'per_visitor' => $visitorDays->visitors ? round($totals->views / $visitorDays->visitors, 1) : 0,
                'conversions' => $convTotal,
                'conv_rate' => $visitorDays->visitors ? round($convTotal / $visitorDays->visitors * 100, 1) : 0,
                'avg_time' => $totals->avg_ms ? $this->duration((int) $totals->avg_ms) : '—',
                'bounce' => $visitorDays->visitors ? round($visitorDays->bounces / $visitorDays->visitors * 100) : 0,
                'scroll' => $totals->avg_scroll ? round($totals->avg_scroll) : 0,
            ],
            'series' => $series,
            'pages' => (clone $views)->selectRaw('path, count(*) as views, count(distinct visitor, date(created_at)) as visitors,
                    avg(case when duration_ms between 1000 and 1800000 then duration_ms end) as avg_ms, avg(scroll_pct) as scroll')
                ->groupBy('path')->orderByDesc('views')->limit(15)->get(),
            'sources' => $sourceVisits->map(function ($s) use ($sourceConv) {
                    $s->conversions = (int) ($sourceConv[$s->source] ?? 0);

                    return $s;
                }),
            'campaigns' => $campaigns->map(function ($c) use ($campaignConv) {
                $c->conversions = (int) ($campaignConv[$c->campaign] ?? 0);

                return $c;
            }),
            'forms' => (clone $conversions)->selectRaw('label, count(*) as n')->groupBy('label')->orderByDesc('n')->get()
                ->map(fn ($r) => ['label' => SiteAnalytics::FORMS[$r->label] ?? $r->label, 'n' => (int) $r->n]),
            'formSources' => (clone $conversions)->selectRaw("label, source, coalesce(campaign, '') as campaign, count(*) as n")
                ->groupBy('label', 'source', 'campaign')->orderByDesc('n')->limit(15)->get(),
            'listings' => (clone $events)->where('name', 'listing_view')->whereNotNull('label')
                ->selectRaw('label, count(*) as views, count(distinct visitor) as visitors')->groupBy('label')->orderByDesc('views')->limit(15)->get(),
            'listingRequests' => (clone $conversions)->where('label', 'listing_request')->count(),
            'contacts' => (clone $events)->whereIn('name', ['phone_click', 'whatsapp_click', 'email_click', 'outbound'])
                ->selectRaw('name, count(*) as n')->groupBy('name')->pluck('n', 'name'),
            'devices' => $top('device', 3),
            'browsers' => $top('browser', 6),
            'countries' => $top('country', 8),
            'clicks' => DB::table('site_clicks')->where('created_at', '>=', $from)->whereNotNull('target')->where('target', '!=', '')
                ->selectRaw('target, path, count(*) as clicks')->groupBy('target', 'path')->orderByDesc('clicks')->limit(10)->get(),
            'duration' => fn ($ms) => $ms ? $this->duration((int) $ms) : '—',
            'formLabels' => SiteAnalytics::FORMS,
        ]);
    }

    public function heatmap(Request $request)
    {
        $days = $this->days($request);
        $paths = SiteAnalytics::ready()
            ? DB::table('site_clicks')->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('path, count(*) as clicks')->groupBy('path')->orderByDesc('clicks')->limit(50)->get()
            : collect();
        $path = (string) $request->query('path', $paths->first()->path ?? '/');

        return view('admin.site-analytics.heatmap', [
            'days' => $days,
            'ranges' => self::RANGES,
            'paths' => $paths,
            // Only our own site's paths can be previewed (no open frame to other hosts)
            'path' => str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : '/',
            'device' => in_array($request->query('device'), ['desktop', 'tablet', 'mobile'], true) ? $request->query('device') : 'desktop',
        ]);
    }

    /** Click points for one page/device, as fractions of page width/height (JSON for the heatmap canvas). */
    public function clicks(Request $request)
    {
        if (! SiteAnalytics::ready()) {
            return response()->json(['count' => 0, 'points' => []]);
        }
        $rows = DB::table('site_clicks')
            ->where('path', (string) $request->query('path', '/'))
            ->where('device', (string) $request->query('device', 'desktop'))
            ->where('created_at', '>=', now()->subDays($this->days($request)))
            ->latest('id')->limit(5000)->get(['x_permille', 'y', 'page_h']);

        return response()->json([
            'count' => $rows->count(),
            'points' => $rows->map(fn ($r) => [round($r->x_permille / 1000, 4), round($r->y / max(1, $r->page_h), 4)]),
        ]);
    }

    protected function days(Request $request): int
    {
        $days = (int) $request->query('days', 30);

        return array_key_exists($days, self::RANGES) ? $days : 30;
    }

    protected function duration(int $ms): string
    {
        $s = (int) round($ms / 1000);

        return $s >= 60 ? intdiv($s, 60).'m '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).'s' : $s.'s';
    }
}
