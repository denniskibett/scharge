<?php

namespace App\Http\Controllers;

use App\Support\SiteAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives the website's analytics beacons (public/assets/js/track.js): page views, time on page,
 * clicks for the heatmap and named events (e.g. a listing opened). Always answers 204 so tracking
 * can never break a page. Privacy rules: App\Support\SiteAnalytics.
 */
class TrackController extends Controller
{
    /** Events the browser may send; anything else is ignored. Conversions are recorded by the server. */
    public const BROWSER_EVENTS = ['listing_view', 'listing_filter', 'outbound', 'phone_click', 'whatsapp_click', 'email_click'];

    public function __invoke(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        if (! is_array($data) || SiteAnalytics::skip($request)) {
            return response()->noContent();
        }

        $path = SiteAnalytics::path((string) ($data['path'] ?? '/'));
        $device = SiteAnalytics::device($request->userAgent(), (int) ($data['vw'] ?? 0));

        try {
            match ($data['type'] ?? null) {
                'view' => $this->view($request, $data, $path, $device),
                'leave' => $this->leave($data),
                'click' => $this->click($data, $path, $device),
                'event' => in_array($data['name'] ?? null, self::BROWSER_EVENTS, true)
                    ? SiteAnalytics::event($request, $data['name'], $data['label'] ?? null, $path) : null,
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('Site analytics beacon failed', ['error' => $e->getMessage()]);
        }

        return response()->noContent();
    }

    protected function view(Request $request, array $d, string $path, string $device): void
    {
        $key = $this->key($d);
        if (strlen($key) !== 16) {
            return;
        }

        $refHost = parse_url((string) ($d['ref'] ?? ''), PHP_URL_HOST);
        $refHost = $refHost && $refHost !== $request->getHost() ? Str::limit(preg_replace('/^(www|m|l|lm)\./', '', strtolower($refHost)), 120, '') : null;
        $utm = array_map(fn ($v) => SiteAnalytics::clean($v, 120), array_intersect_key((array) ($d['utm'] ?? []), array_flip(['source', 'medium', 'campaign'])));

        DB::table('site_page_views')->insertOrIgnore([
            'visitor' => SiteAnalytics::visitor($request),
            'view_key' => $key,
            'path' => $path,
            'referrer_host' => $refHost,
            'utm_source' => isset($utm['source']) ? Str::limit(strtolower($utm['source']), 80, '') : null,
            'utm_medium' => isset($utm['medium']) ? Str::limit(strtolower($utm['medium']), 80, '') : null,
            'utm_campaign' => isset($utm['campaign']) ? strtolower($utm['campaign']) : null,
            'country' => SiteAnalytics::country($request),
            'device' => $device,
            'browser' => SiteAnalytics::browser($request->userAgent()),
            'viewport_w' => min(65535, max(0, (int) ($d['vw'] ?? 0))) ?: null,
            'is_staff' => $request->user() ? 1 : 0,
            'created_at' => now(),
        ]);

        SiteAnalytics::rememberSource($request, $path, $refHost, $utm);
    }

    protected function leave(array $d): void
    {
        DB::table('site_page_views')->where('view_key', $this->key($d))->where('created_at', '>', now()->subHours(6))->update([
            'duration_ms' => min(3_600_000, max(0, (int) ($d['ms'] ?? 0))),
            'scroll_pct' => min(100, max(0, (int) ($d['scroll'] ?? 0))),
        ]);
    }

    protected function click(array $d, string $path, string $device): void
    {
        $pageH = (int) ($d['ph'] ?? 0);
        $y = (int) ($d['y'] ?? -1);
        $x = (int) ($d['x'] ?? -1);
        if ($pageH <= 0 || $y < 0 || $y > $pageH || $x < 0 || $x > 1000) {
            return;
        }

        DB::table('site_clicks')->insert([
            'path' => $path,
            'device' => $device,
            'x_permille' => $x,
            'y' => $y,
            'page_h' => $pageH,
            'target' => SiteAnalytics::clean($d['t'] ?? null, 120),
            'created_at' => now(),
        ]);
    }

    private function key(array $d): string
    {
        return substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['key'] ?? '')), 0, 16);
    }
}
