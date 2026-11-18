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

    /**
     * Locate a property a prospect listed on the demo form (crm_profile.prospect_properties[]).
     *
     * In order: a CRM prospect with the same name and an exact OSM pin ($pins: name_key => [lat, lng]);
     * the location text (suburb/town, Nairobi assumed when only a suburb is given); a place named in the
     * property name ("Westlands Heights"); the company's own location. Offline only — nothing is sent anywhere.
     *
     * @param  array<string, array{0: float, 1: float}>  $pins
     * @return array<string, mixed> lat, lng, geo_source, geo_precision, county, sub_county, constituency (empty if unknown)
     */
    public function property(array $property, array $companyProfile, array $pins): array
    {
        $point = null;
        $key = Normalizer::companyKey($property['name'] ?? '');
        if ($key !== '' && strlen($key) >= 5 && isset($pins[$key])) {
            $point = ['lat' => $pins[$key][0], 'lng' => $pins[$key][1], 'precision' => 'exact', 'source' => 'osm_match'];
        }

        $location = trim((string) ($property['location'] ?? ''));
        if (! $point && $location !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $location))));
            $town = count($parts) > 1 ? end($parts) : null;
            // A bare suburb name: Nairobi first (most demo-form properties are there), then anywhere in Kenya.
            $near = $town ? null : $this->geocode('Nairobi', $parts[0]);
            $found = ($near && $near['precision'] === 'area') ? $near : $this->geocode($town, $parts[0] ?? $location);
            if ($found) {
                $point = $found + ['source' => 'location'];
            }
        }

        $anchor = $this->place($companyProfile['city'] ?? null) ?? $this->place('Nairobi');
        if (! $point && ($spot = $this->placeInText((string) ($property['name'] ?? ''), $anchor))) {
            $point = ['lat' => $spot[0], 'lng' => $spot[1], 'precision' => $spot[2] === 'area' ? 'area' : 'town', 'source' => 'name'];
        }

        if (! $point && ($company = $this->geocode($companyProfile['city'] ?? null, $companyProfile['area'] ?? null))) {
            // The company's suburb or town: a fair guess, not the building.
            $point = $company + ['source' => 'company'];
        }

        if (! $point) {
            return [];
        }

        $out = ['lat' => round($point['lat'], 5), 'lng' => round($point['lng'], 5), 'geo_precision' => $point['precision'], 'geo_source' => $point['source']];
        if ($admin = $this->locate($point['lat'], $point['lng'])) {
            $out['county'] = $admin['county'];
            if (in_array($point['precision'], ['exact', 'area'], true)) {
                $out['sub_county'] = $out['constituency'] = $admin['constituency'];
            }
        }

        return $out;
    }

    /**
     * name_key => [lat, lng] of CRM companies with an exact OSM pin, to place a listed property that is itself a
     * known building. Pass $keys to look up only those names.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function exactPins(?array $keys = null): array
    {
        if ($keys === []) {
            return [];
        }
        $j = fn (string $k) => "JSON_UNQUOTE(JSON_EXTRACT(crm_profile, '$.{$k}'))";

        return \App\Models\Company::crm()
            ->whereNotNull('name_key')->where('name_key', '!=', '')
            ->whereRaw($j('geo_precision')." = 'exact'")
            ->when($keys, fn ($q) => $q->whereIn('name_key', $keys))
            ->selectRaw("name_key, {$j('lat')} AS lat, {$j('lng')} AS lng")
            ->toBase()->get()
            ->mapWithKeys(fn ($r) => [$r->name_key => [(float) $r->lat, (float) $r->lng]])
            ->all();
    }

    /**
     * Google Maps: the pin when it is exact, otherwise a search for name + location in Kenya (Google finds the building).
     * $near (the company's city) is added when the property has no location of its own.
     */
    public static function googleMapsUrl(array $property, ?string $near = null): string
    {
        if (($property['geo_precision'] ?? null) === 'exact' && is_numeric($property['lat'] ?? null)) {
            return 'https://www.google.com/maps/search/?api=1&query='.$property['lat'].','.$property['lng'];
        }
        $q = collect([$property['name'] ?? null, trim((string) ($property['location'] ?? '')) ?: $near])->filter()->join(', ');
        if (! str_contains(strtolower($q), 'kenya')) {
            $q .= ($q === '' ? '' : ', ').'Kenya';
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($q);
    }

    /** A suburb or town named inside free text, within 60 km of $anchor (stops "Park" matching a far-off village). */
    private function placeInText(string $text, ?array $anchor): ?array
    {
        $words = preg_split('/[^A-Za-z\']+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $candidates = [];
        for ($i = 0; $i < count($words); $i++) {
            if (isset($words[$i + 1])) {
                $candidates[] = $words[$i].' '.$words[$i + 1];
            }
        }
        foreach ($words as $w) {
            if (strlen($w) >= 5) {
                $candidates[] = $w;
            }
        }
        foreach ($candidates as $c) {
            $spot = $this->places()[self::key($c)] ?? null;
            if ($spot && $spot[2] !== 'county' && (! $anchor || $this->km($spot, $anchor) <= 60)) {
                return $spot;
            }
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
