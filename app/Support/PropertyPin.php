<?php

namespace App\Support;

use App\Models\Property;
use App\Modules\CRM\Support\KenyaGeo;
use Illuminate\Support\Facades\Schema;

/**
 * A property's GPS pin (properties.lat / lng, see database/schema/2026_11_19_property_pin.sql).
 * Accepts "-1.2921, 36.7856", "-1.2921 36.7856" or a Google Maps link (…/@-1.2921,36.7856,17z or ?q=-1.29,36.78).
 * Only pins inside Kenya are kept.
 */
class PropertyPin
{
    public const SOURCES = ['device', 'map', 'typed', 'csv'];

    public static function enabled(): bool
    {
        static $on;

        return $on ??= rescue(fn () => Schema::hasColumns('properties', ['lat', 'lng']), false, false);
    }

    /** @return array{0: float, 1: float}|null */
    public static function parse(?string $text): ?array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', $text, $m)
            || preg_match('/[?&](?:q|query|ll|destination)=(-?\d{1,2}\.\d+)(?:,|%2C)\s*(-?\d{1,3}\.\d+)/i', $text, $m)
            || preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $text, $m)) {
            return self::valid((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /** The pin rounded to ~1 cm, or null when it is not in Kenya. Swapped lat/lng (36.8, -1.3) is fixed. */
    public static function valid($lat, $lng): ?array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }
        [$lat, $lng] = [(float) $lat, (float) $lng];
        if (! KenyaGeo::inKenya($lat, $lng) && KenyaGeo::inKenya($lng, $lat)) {
            [$lat, $lng] = [$lng, $lat];
        }

        return KenyaGeo::inKenya($lat, $lng) ? [round($lat, 7), round($lng, 7)] : null;
    }

    /** Columns to save on a property (empty when the columns are not there yet). */
    public static function attributes(?array $pin, string $source): array
    {
        if (! self::enabled()) {
            return [];
        }
        if (! $pin) {
            return ['lat' => null, 'lng' => null, 'pin_source' => null, 'pinned_at' => null];
        }

        return ['lat' => $pin[0], 'lng' => $pin[1], 'pin_source' => in_array($source, self::SOURCES, true) ? $source : 'typed', 'pinned_at' => now()];
    }

    /** @return array{0: float, 1: float}|null */
    public static function of(Property $p): ?array
    {
        return self::enabled() && $p->lat !== null && $p->lng !== null ? [(float) $p->lat, (float) $p->lng] : null;
    }

    public static function metres(array $a, array $b): float
    {
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }

    /** Validation rules for lat / lng / pin_source on a property form. */
    public static function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'required_with:lng', function ($attr, $value, $fail) {
                if (! self::valid($value, request('lng'))) {
                    $fail('The pin must be inside Kenya. Use "my location", drag the pin on the map, or paste the coordinates from Google Maps.');
                }
            }],
            'lng' => ['nullable', 'numeric', 'required_with:lat'],
            'pin_source' => ['nullable', 'in:'.implode(',', self::SOURCES)],
        ];
    }
}
