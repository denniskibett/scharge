<?php

namespace App\Modules\CRM\Support;

use Illuminate\Support\Facades\Cache;
use PragmaRX\Countries\Package\Countries as Package;

/**
 * Country lists for the website and CRM, from the pragmarx/countries package (no table).
 * Codes are ISO 3166 alpha-2. Update LIVE / NEXT as scharge launches.
 */
class Countries
{
    /** Where scharge is live: demo requests go straight to the CRM. */
    public const LIVE = ['KE'];

    /** Launching next: listed first on the waitlist. */
    public const NEXT = ['UG', 'TZ', 'RW'];

    /** @return array<string, array{name: string, dial: string}> African countries, A-Z */
    public static function africa(): array
    {
        return Cache::rememberForever('crm.countries.africa', function () {
            return (new Package)->all()
                ->filter(fn ($c) => ($c->geo->region ?? null) === 'Africa' && ! empty($c->cca2))
                ->mapWithKeys(fn ($c) => [(string) $c->cca2 => [
                    'name' => (string) ($c->name->common ?? $c->cca2),
                    'dial' => preg_replace('/\D/', '', (string) ($c->dialling->calling_code[0] ?? '')),
                ]])
                ->sortBy('name')
                ->all();
        });
    }

    /** Live countries first, then the next launches, then the rest A-Z. */
    public static function ordered(): array
    {
        $all = self::africa();
        $first = array_intersect_key($all, array_flip(array_merge(self::LIVE, self::NEXT)));
        uksort($first, fn ($a, $b) => array_search($a, array_merge(self::LIVE, self::NEXT)) <=> array_search($b, array_merge(self::LIVE, self::NEXT)));

        return $first + $all;
    }

    /** Countries people can join the waitlist for (everything not live). */
    public static function waitlist(): array
    {
        return array_diff_key(self::ordered(), array_flip(self::LIVE));
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && isset(self::africa()[strtoupper($code)]);
    }

    public static function name(?string $code): ?string
    {
        return $code ? (self::africa()[strtoupper($code)]['name'] ?? strtoupper($code)) : null;
    }

    public static function dial(?string $code): string
    {
        return ($code ? self::africa()[strtoupper($code)]['dial'] ?? null : null) ?: '254';
    }

    public static function isLive(?string $code): bool
    {
        return in_array(strtoupper((string) $code), self::LIVE, true);
    }
}
