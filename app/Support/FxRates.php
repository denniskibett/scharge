<?php

namespace App\Support;

use App\Services\Integrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sharet's FX reference rates: the one rate source for every converted figure in scharge.
 *
 * Sharet publishes Safaricom's M-PESA rate for the day as "units of X per 1 KES" (its KES/UGX, KES/USD... pairs).
 * We read them, cache them (config currency.rates.cache_minutes) and keep the last good set on disk. If Sharet
 * can't be reached, the last known rates are used and marked stale; with none at all, figures stay in their own
 * currency rather than being guessed. Display only: what anyone is charged is never converted here.
 */
class FxRates
{
    public const CACHE_KEY = 'fx.rates.v1';

    /**
     * ['base' => 'KES', 'date' => 'Y-m-d', 'rates' => ['USD' => '0.0077', ...], 'source' => 'safaricom', 'stale' => bool]
     * or null when Sharet has never answered.
     */
    public static function snapshot(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached['snapshot'];
        }

        return self::refresh();
    }

    /** Ask Sharet now. On failure fall back to the last known rates (stale) and try again after retry_minutes. */
    public static function refresh(): ?array
    {
        $snapshot = self::fetch();
        if ($snapshot) {
            self::store($snapshot);
            Cache::put(self::CACHE_KEY, ['snapshot' => $snapshot], now()->addMinutes(config('currency.rates.cache_minutes', 360)));

            return $snapshot;
        }

        $last = self::lastKnown();
        if ($last) {
            $last['stale'] = true;
        }
        Cache::put(self::CACHE_KEY, ['snapshot' => $last], now()->addMinutes(config('currency.rates.retry_minutes', 15)));

        return $last;
    }

    /** Units of $code per 1 book currency, as a decimal string, or null if Sharet has no rate for it. */
    public static function perBook(string $code): ?string
    {
        $code = strtoupper($code);
        $snapshot = self::snapshot();
        if ($snapshot === null) {
            return $code === Currency::book() ? '1' : null;
        }
        if ($code === $snapshot['base']) {
            return '1';
        }
        $rate = $snapshot['rates'][$code] ?? null;

        return is_numeric($rate) && bccomp((string) $rate, '0', 10) > 0 ? (string) $rate : null;
    }

    /** Units of $to per 1 $from (crossed through the base), or null when either side has no rate. */
    public static function rate(string $from, string $to): ?string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return '1';
        }
        $a = self::perBook($from);
        $b = self::perBook($to);

        return $a !== null && $b !== null ? bcdiv($b, $a, 12) : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function fetch(): ?array
    {
        $url = (string) config('currency.rates.url');
        if ($url === '') {
            return null;
        }
        try {
            $http = Http::acceptJson()->timeout((int) config('currency.rates.timeout', 5));
            $platform = rescue(fn () => Integrations::sharet(), null, false);
            if ($platform) {
                $http = $http->withToken((string) $platform->get('api_key'));
            }
            $res = $http->get($url);
            if (! $res->successful()) {
                Log::info('fx: Sharet rates unavailable', ['status' => $res->status()]);

                return null;
            }

            return self::parse((array) $res->json());
        } catch (\Throwable $e) {
            Log::info('fx: Sharet rates unreachable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Accepts {"base":"KES","date":..,"rates":{"USD":"0.0077"}} or Sharet's pair form {"date":..,"pairs":{"KES/USD":"0.0077"}}
     * (also wrapped in "data"). Pairs that don't involve the book currency are ignored; X/KES pairs are inverted.
     */
    public static function parse(array $body): ?array
    {
        $body = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
        $book = Currency::book();
        $base = strtoupper((string) ($body['base'] ?? $book));
        if ($base !== $book) {
            return null; // we only cross through the book currency
        }
        $rates = [];
        foreach ((array) ($body['rates'] ?? []) as $code => $rate) {
            $rates[strtoupper((string) $code)] = $rate;
        }
        foreach ((array) ($body['pairs'] ?? []) as $pair => $rate) {
            $row = is_array($rate) ? $rate : ['rate' => $rate];
            [$b, $q] = array_pad(explode('/', strtoupper((string) $pair)), 2, '');
            $r = $row['rate'] ?? null;
            if (! is_numeric($r) || bccomp((string) $r, '0', 10) <= 0) {
                continue;
            }
            if ($b === $book) {
                $rates[$q] = (string) $r;
            } elseif ($q === $book) {
                $rates[$b] = bcdiv('1', (string) $r, 10);
            }
        }
        $rates = array_filter($rates, fn ($r, $code) => isset(config('currency.currencies')[$code]) && $code !== $book
            && is_numeric($r) && bccomp((string) $r, '0', 10) > 0, ARRAY_FILTER_USE_BOTH);
        if (! $rates) {
            return null;
        }
        $date = (string) ($body['date'] ?? $body['rate_date'] ?? now(config('app.schedule_timezone', 'Africa/Nairobi'))->toDateString());

        return [
            'base' => $book,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}/', $date) ? substr($date, 0, 10) : now()->toDateString(),
            'rates' => array_map(fn ($r) => (string) $r, $rates),
            'source' => (string) ($body['source'] ?? 'sharet'),
            'fetched_at' => now()->toIso8601String(),
            'stale' => false,
        ];
    }

    public static function lastKnown(): ?array
    {
        $path = (string) config('currency.rates.store');
        if ($path === '' || ! is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) && isset($data['base'], $data['rates']) && $data['base'] === Currency::book() ? $data : null;
    }

    protected static function store(array $snapshot): void
    {
        $path = (string) config('currency.rates.store');
        if ($path === '') {
            return;
        }
        rescue(function () use ($path, $snapshot) {
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path.'.tmp', json_encode($snapshot, JSON_PRETTY_PRINT));
            rename($path.'.tmp', $path);
        }, null, false);
    }
}
