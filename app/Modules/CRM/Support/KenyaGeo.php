<?php

namespace App\Modules\CRM\Support;

/**
 * Where a CRM prospect is, from public data only.
 *
 * - exact: the OpenStreetMap pin the prospect was imported with ("Map: …?mlat=..&mlon=..").
 * - area / town / county: the centroid of the named suburb, town or county from the prospect's
 *   city/area fields (GeoNames populated places, CC BY 4.0). Approximate — never a street address.
 *
 * Admin areas come from OCHA COD-AB Kenya (HDX cod-ab-ken, CC BY-IGO): the 290 IEBC constituencies
 * with their county. In this app a "sub-county" is a constituency (see the subcounties table), so
 * both names are stored the same.
 */
class KenyaGeo
{
    /** Cities that span several constituencies: a town-level pin there says nothing about the constituency. */
    private const MULTI_CONSTITUENCY_TOWNS = [
        'nairobi', 'mombasa', 'kisumu', 'nakuru', 'eldoret', 'thika', 'machakos', 'kakamega', 'kisii', 'meru',
        'nyeri', 'kitale', 'kericho', 'malindi', 'kilifi', 'naivasha', 'ruiru', 'kitengela', 'athiriver', 'kiambu',
    ];

    private ?array $constituencies = null;

    private ?array $places = null;

    /** @return array{0: float, 1: float}|null lat/lng of the OSM pin in the notes. */
    public static function osmPin(?string $text): ?array
    {
        if ($text && preg_match('/mlat=(-?\d+(?:\.\d+)?)&mlon=(-?\d+(?:\.\d+)?)/', $text, $m)) {
            [$lat, $lng] = [(float) $m[1], (float) $m[2]];
            if (self::inKenya($lat, $lng)) {
                return [round($lat, 5), round($lng, 5)];
            }
        }

        return null;
    }

    public static function inKenya(float $lat, float $lng): bool
    {
        return $lat > -4.9 && $lat < 5.1 && $lng > 33.8 && $lng < 42.0;
    }

    /**
     * Approximate location from the city / area fields.
     *
     * @return array{lat: float, lng: float, precision: string}|null precision: area, town or county
     */
    public function geocode(?string $city, ?string $area): ?array
    {
        $town = $this->place($city);
        $spot = $this->place($area);

        // A suburb only counts when it is near the town (common names repeat across Kenya).
        if ($spot && $spot !== $town && (! $town || $this->km($spot, $town) <= 40)) {
            return ['lat' => $spot[0], 'lng' => $spot[1], 'precision' => $spot[2] === 'county' ? 'county' : ($spot[2] === 'area' || $town ? 'area' : 'town')];
        }
        if ($town) {
            return ['lat' => $town[0], 'lng' => $town[1], 'precision' => $town[2] === 'county' ? 'county' : 'town'];
        }

        return null;
    }

    /** Whether a town-level pin is good enough to name the constituency. */
    public function townIsOneConstituency(?string $city): bool
    {
        return ! in_array(self::key(self::clean($city)), self::MULTI_CONSTITUENCY_TOWNS, true);
    }

    /** @return array{county: string, constituency: string}|null */
    public function locate(float $lat, float $lng): ?array
    {
        $nearest = null;
        $best = INF;
        foreach ($this->constituencies() as $f) {
            [$x1, $y1, $x2, $y2] = $f['b'];
            if ($lng >= $x1 && $lng <= $x2 && $lat >= $y1 && $lat <= $y2) {
                foreach ($f['p'] as $polygon) {
                    if (self::inPolygon($lng, $lat, $polygon)) {
                        return ['county' => $f['c'], 'constituency' => $f['n']];
                    }
                }
            }
            $d = ($f['ctr'][0] - $lng) ** 2 + ($f['ctr'][1] - $lat) ** 2;
            if ($d < $best) {
                [$best, $nearest] = [$d, $f];
            }
        }

        // Just outside a (simplified) boundary, e.g. on the shoreline: take the nearest one within ~15 km.
        return $nearest && sqrt($best) < 0.15 ? ['county' => $nearest['c'], 'constituency' => $nearest['n']] : null;
    }

    /** Ray casting over the outer ring, minus holes. */
    private static function inPolygon(float $x, float $y, array $rings): bool
    {
        $inside = false;
        foreach ($rings as $i => $ring) {
            $in = false;
            $n = count($ring);
            for ($a = 0, $b = $n - 1; $a < $n; $b = $a++) {
                [$xa, $ya] = $ring[$a];
                [$xb, $yb] = $ring[$b];
                if (($ya > $y) !== ($yb > $y) && $x < ($xb - $xa) * ($y - $ya) / ($yb - $ya) + $xa) {
                    $in = ! $in;
                }
            }
            if ($i === 0) {
                if (! $in) {
                    return false;
                }
                $inside = true;
            } elseif ($in) {
                return false;
            }
        }

        return $inside;
    }

    /** @return array{0: float, 1: float, 2: string}|null */
    private function place(?string $name): ?array
    {
        $name = self::clean($name);
        if ($name === '') {
            return null;
        }
        $places = $this->places();
        foreach ([$name, explode(',', $name)[0], preg_replace('/\s*-.*$/', '', $name)] as $candidate) {
            $k = self::key($candidate);
            if ($k !== '' && isset($places[$k])) {
                return $places[$k];
            }
        }

        return null;
    }

    private static function clean(?string $name): string
    {
        $name = trim((string) $name);
        $name = preg_replace('/\b(town|city|municipality|cbd|kenya|\(k\)|\(unspecified\)|g\.?p\.?o\.?)\b/i', '', $name);

        return trim(preg_replace('/\s+/', ' ', trim($name, " ,-")));
    }

    private static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }

    private function km(array $a, array $b): float
    {
        $dx = ($a[1] - $b[1]) * 111.32 * cos(deg2rad(($a[0] + $b[0]) / 2));
        $dy = ($a[0] - $b[0]) * 110.57;

        return sqrt($dx * $dx + $dy * $dy);
    }

    private function constituencies(): array
    {
        return $this->constituencies ??= json_decode(file_get_contents(database_path('data/geo/ke_constituencies.json')), true)['features'];
    }

    private function places(): array
    {
        return $this->places ??= json_decode(file_get_contents(database_path('data/geo/ke_places.json')), true)['places'];
    }
}
