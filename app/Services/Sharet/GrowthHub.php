<?php

namespace App\Services\Sharet;

use App\Models\CompanyIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * scharge's numbers from the Sharet Africa growth hub (GET /api/v1/growth/summary, contract "growth.summary" v1).
 * The hub tracks the public website with sharet-analytics.js (data-site="scharge") and returns only the site(s)
 * owned by the Sharet business the API key acts as (scharge = SH-QPAKAK on the hub's side).
 * Uses scharge's own Sharet key from Settings › Integrations › Sharet Africa (needs the "read" scope).
 * Successful answers are cached for 5 minutes; failures become a friendly state, never an exception.
 */
class GrowthHub
{
    public const SITE = 'scharge';

    /** The Sharet business the hub maps scharge to (config/growth.php on sharet.africa). */
    public const EXPECTED_SHARET_ID = 'SH-QPAKAK';

    public const CACHE_SECONDS = 300;

    /**
     * @return array{state: string, message: string, site: ?array, from: string, to: string, account: ?string, environment: ?string, fetched_at: ?string}
     *   state: ok | empty | not_connected | unauthorized | forbidden | invalid | unavailable | timeout | error
     */
    public function summary(int $days): array
    {
        $to = now()->toDateString();
        $from = now()->subDays($days - 1)->toDateString();
        $base = ['site' => null, 'from' => $from, 'to' => $to, 'account' => null, 'environment' => null, 'fetched_at' => null];

        $row = $this->integration();
        if (! $row) {
            return array_merge($base, ['state' => 'not_connected', 'message' => 'scharge’s Sharet Africa account is not connected. A sysadmin can add the API key on Settings › Integrations › Sharet Africa.']);
        }

        $key = (string) $row->get('api_key');
        $url = rtrim((string) $row->get('base_url', config('sharet.base_url')), '/');
        $env = str_starts_with($key, 'sk_live_') ? 'live' : 'test';
        $cacheKey = 'sharet-growth:'.substr(hash('sha256', $key.'|'.$url), 0, 16).":{$from}:{$to}";

        if ($hit = Cache::get($cacheKey)) {
            return $hit;
        }

        try {
            $res = Http::withToken($key)->acceptJson()->timeout(8)->connectTimeout(4)
                ->get($url.'/growth/summary', ['from' => $from, 'to' => $to, 'site' => self::SITE]);
        } catch (ConnectionException) {
            return array_merge($base, ['environment' => $env, 'state' => 'timeout', 'message' => 'The Sharet growth hub did not answer in time. Try again in a minute.']);
        } catch (\Throwable $e) {
            report($e);

            return array_merge($base, ['environment' => $env, 'state' => 'error', 'message' => 'The Sharet growth hub could not be reached.']);
        }

        $problem = (string) ($res->json('code') ?? $res->json('type') ?? '');
        $fail = fn (string $state, string $message) => array_merge($base, ['environment' => $env, 'state' => $state, 'message' => $message]);

        if ($res->status() === 401) {
            return $fail('unauthorized', 'Sharet did not accept scharge’s API key. Check the key on Settings › Integrations › Sharet Africa.');
        }
        if ($res->status() === 403) {
            return $fail('forbidden', match (true) {
                str_contains($problem, 'growth_no_site'), str_contains($problem, 'growth_other_site') => 'The Sharet business this key belongs to does not own the scharge website in the growth hub (it expects '.self::EXPECTED_SHARET_ID.').',
                default => 'scharge’s Sharet API key needs the "read" scope to show growth numbers. Add it in Sharet › Developers › API keys.',
            });
        }
        if ($res->status() === 422) {
            return $fail('invalid', 'Sharet refused the date range.');
        }
        if ($res->status() === 503 || $res->serverError()) {
            return $fail('unavailable', 'The Sharet growth hub is temporarily unavailable. Try again later.');
        }
        if (! $res->successful() || $res->json('object') !== 'growth.summary') {
            return $fail('error', 'The Sharet growth hub sent an answer this page does not understand (HTTP '.$res->status().').');
        }

        $site = collect($res->json('sites') ?? [])->firstWhere('key', self::SITE) ?? collect($res->json('sites') ?? [])->first();
        $result = array_merge($base, [
            'environment' => $env,
            'account' => $this->account($row, $key, $url),
            'fetched_at' => now()->toIso8601String(),
            'site' => $site ? $this->normalise($site) : null,
            'state' => $site && ($site['totals']['page_views'] ?? 0) > 0 ? 'ok' : 'empty',
            'message' => $site ? '' : 'The growth hub has no scharge site for this key yet.',
        ]);
        Cache::put($cacheKey, $result, self::CACHE_SECONDS);

        return $result;
    }

    /** scharge's platform Sharet settings (company_id NULL). Read directly: Integrations::installed() memoises per process. */
    protected function integration(): ?CompanyIntegration
    {
        if (! Schema::hasTable('company_integrations')) {
            return null;
        }
        $row = CompanyIntegration::whereNull('company_id')->where('provider', 'sharet')->where('enabled', true)->latest('id')->first();

        return $row && filled($row->get('api_key')) ? $row : null;
    }

    /** The Sharet ID the key acts as (GET /accounts/me), cached an hour; null when unknown. */
    private function account(CompanyIntegration $row, string $key, string $url): ?string
    {
        return Cache::remember('sharet-growth-account:'.substr(hash('sha256', $key), 0, 16), 3600, function () use ($key, $url) {
            try {
                $me = Http::withToken($key)->acceptJson()->timeout(5)->get($url.'/accounts/me');

                return $me->successful() ? ($me->json('sharet_id') ?? $me->json('business.sharet_id')) : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /** Fill every list the page reads, so a partial answer never breaks the view. */
    private function normalise(array $site): array
    {
        $site['totals'] = array_merge(['visits' => 0, 'page_views' => 0, 'conversions' => 0, 'conversion_rate' => null, 'organic_share' => null, 'paid_share' => null], $site['totals'] ?? []);
        foreach (['daily', 'conversions', 'channels', 'sources', 'landing_pages'] as $list) {
            $site[$list] = array_values(array_filter($site[$list] ?? [], 'is_array'));
        }
        $site['seo'] = array_merge(['checked_at' => null, 'sitemap_urls' => null, 'counts' => ['red' => 0, 'amber' => 0, 'green' => 0], 'fix_first' => []], $site['seo'] ?? []);

        return $site;
    }

    /**
     * What to push on scharge.sharet.africa, from the hub's numbers. Below 100 visits or 10 conversions the
     * advice is "not enough data" rather than a guess.
     */
    public static function advice(?array $site): array
    {
        $t = $site['totals'] ?? [];
        $enough = ($t['visits'] ?? 0) >= 100 && ($t['conversions'] ?? 0) >= 10;
        $best = fn (array $rows) => collect($rows)->filter(fn ($r) => ($r['visits'] ?? 0) >= 20 && ($r['conversions'] ?? 0) > 0)
            ->sortByDesc('conversion_rate')->first();

        return [
            'enough' => $enough,
            'visits' => (int) ($t['visits'] ?? 0),
            'conversions' => (int) ($t['conversions'] ?? 0),
            'best_page' => $enough ? $best($site['landing_pages'] ?? []) : null,
            'best_source' => $enough ? $best($site['sources'] ?? []) : null,
            // Traffic without a single conversion: worth a stronger call to action (shown whenever there is traffic)
            'leaks' => collect($site['landing_pages'] ?? [])->filter(fn ($r) => ($r['visits'] ?? 0) >= 20 && ($r['conversions'] ?? 0) === 0)
                ->sortByDesc('visits')->take(5)->values()->all(),
        ];
    }
}
