<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * First-party website analytics helpers (tables in database/schema/2026_11_09_site_analytics.sql).
 * Privacy rules live here: no IPs or personal details are stored, Do-Not-Track / Global Privacy
 * Control are honoured, bots are skipped, and paths are stored without queries or tokens.
 */
class SiteAnalytics
{
    /** Session key for the visitor's first touch this session (source, medium, campaign, landing page). */
    public const SESSION_KEY = 'site_attribution';

    /** Website forms recorded as conversions, with their report labels. */
    public const FORMS = [
        'demo' => 'Book a demo',
        'waitlist' => 'Country waitlist',
        'api_keys' => 'Sandbox API keys',
        'listing_request' => 'Listing viewing / booking request',
        'listing_alert' => 'Listing SMS alert',
        'host_register' => 'BnB host sign-up',
    ];

    public static function ready(): bool
    {
        if (! config('analytics.first_party')) {
            return false;
        }

        return Cache::remember('site-analytics-ready', now()->addMinutes(10), function () {
            try {
                return Schema::hasTable('site_page_views') && Schema::hasTable('site_events') && Schema::hasTable('site_clicks');
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** Should this request be left out entirely? (bots, Do-Not-Track, Global Privacy Control, staff) */
    public static function skip(Request $request): bool
    {
        return ! self::ready()
            || self::isBot($request->userAgent())
            || $request->header('DNT') === '1'
            || $request->header('Sec-GPC') === '1'
            || ($request->user() && ! config('analytics.record_signed_in'));
    }

    /** Remember where this session came from (first touch only). Called from the page-view beacon. */
    public static function rememberSource(Request $request, string $path, ?string $refHost, array $utm): void
    {
        if (! $request->hasSession()) {
            return;
        }
        // Keep the first touch, except that a campaign link replaces a plain direct/referral touch.
        $current = $request->session()->get(self::SESSION_KEY);
        if ($current && (empty($utm['source']) || ! empty($current['campaign']))) {
            return;
        }
        $utm = array_map(fn ($v) => $v === null ? null : strtolower((string) $v), $utm);

        $request->session()->put(self::SESSION_KEY, [
            'source' => self::clean($utm['source'] ?? null, 120) ?? $refHost ?? 'direct',
            'medium' => self::clean($utm['medium'] ?? null, 80) ?? ($refHost ? (self::isSearch($refHost) ? 'organic' : 'referral') : null),
            'campaign' => self::clean($utm['campaign'] ?? null, 120),
            'landing_path' => $path,
        ]);
    }

    /** A form was accepted: record it as a conversion. Never throws. */
    public static function conversion(Request $request, string $form): void
    {
        self::event($request, 'conversion', $form, self::path((string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH)));
    }

    public static function event(Request $request, string $name, ?string $label = null, ?string $path = null): void
    {
        try {
            if (self::skip($request)) {
                return;
            }
            $attr = $request->hasSession() ? (array) $request->session()->get(self::SESSION_KEY, []) : [];

            DB::table('site_events')->insert([
                'name' => Str::limit($name, 40, ''),
                'label' => self::clean($label, 190),
                'path' => $path,
                'visitor' => self::visitor($request),
                'source' => $attr['source'] ?? 'direct',
                'medium' => $attr['medium'] ?? null,
                'campaign' => $attr['campaign'] ?? null,
                'landing_path' => $attr['landing_path'] ?? null,
                'device' => self::device($request->userAgent(), 0),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Site analytics event not recorded', ['event' => $name, 'error' => $e->getMessage()]);
        }
    }

    /** Daily-rotating anonymous id: hash of IP + browser + date + app key. */
    public static function visitor(Request $request): string
    {
        return substr(hash('sha256', $request->ip().'|'.$request->userAgent().'|'.now()->toDateString().'|'.config('app.key')), 0, 16);
    }

    /** Path only, no query; segments that look like tokens or long ids are masked. */
    public static function path(?string $path): string
    {
        $path = parse_url((string) $path, PHP_URL_PATH) ?: '/';
        $parts = array_map(function ($seg) {
            $seg = rawurldecode($seg);

            return preg_match('/^[A-Za-z0-9_-]{24,}$|^[0-9a-f-]{32,36}$|@/', $seg) ? ':token' : Str::limit($seg, 80, '');
        }, array_filter(explode('/', $path), 'strlen'));

        return Str::limit('/'.implode('/', $parts), 250, '');
    }

    public static function device(?string $ua, int $vw): string
    {
        $ua = strtolower((string) $ua);
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || ($vw >= 600 && $vw < 1024 && str_contains($ua, 'android'))) {
            return 'tablet';
        }

        return str_contains($ua, 'mobi') || ($vw > 0 && $vw < 600) ? 'mobile' : 'desktop';
    }

    public static function browser(?string $ua): ?string
    {
        $ua = (string) $ua;
        foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Opera Mini' => 'Opera Mini', 'SamsungBrowser' => 'Samsung', 'UCBrowser' => 'UC Browser',
            'Chrome/' => 'Chrome', 'CriOS' => 'Chrome', 'Firefox/' => 'Firefox', 'FxiOS' => 'Firefox', 'Safari/' => 'Safari'] as $needle => $name) {
            if (str_contains($ua, $needle)) {
                return $name;
            }
        }

        return null;
    }

    public static function isBot(?string $ua): bool
    {
        return $ua === null || $ua === ''
            || (bool) preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|uptime|curl|wget|python|http-client|facebookexternalhit|whatsapp/i', $ua);
    }

    public static function isSearch(string $host): bool
    {
        return (bool) preg_match('/(^|\.)(google|bing|duckduckgo|yahoo|yandex|ecosia|baidu|brave)\./i', $host);
    }

    public static function country(Request $request): ?string
    {
        $c = strtoupper((string) ($request->header('CF-IPCountry') ?? $request->header('X-Country-Code') ?? ''));

        return preg_match('/^[A-Z]{2}$/', $c) && $c !== 'XX' ? $c : null;
    }

    public static function clean(mixed $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));

        // Campaign tags must never carry personal data: drop anything that looks like an email or phone.
        if ($value === '' || preg_match('/@|\+?\d[\d\s-]{8,}/', $value)) {
            return null;
        }

        return Str::limit($value, $max, '');
    }
}
