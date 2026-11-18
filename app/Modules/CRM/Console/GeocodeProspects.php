<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Support\KenyaGeo;
use Illuminate\Console\Command;

/**
 * Puts CRM companies on the Kenya map: lat/lng plus county and constituency (= sub-county),
 * all stored in companies.crm_profile:
 *   lat, lng       the pin
 *   geo_precision  exact (OSM pin) | area | town | county (approximate centroids)
 *   sub_county_id  the ward's row in subcounties (matched by ward polygon, then name); county_id = that row's county
 *   county, sub_county (= constituency), ward  names derived from those ids
 *   sub_county_match  ward | ward_in_county | constituency | none
 *   geo_at         when this ran (rows that have it are skipped unless --force)
 *
 * Properties prospects listed on the demo form (crm_profile.prospect_properties[]) get the same keys
 * plus geo_source (osm_match | location | name | company); see KenyaGeo::property().
 */
class GeocodeProspects extends Command
{
    protected $signature = 'crm:geocode
        {--force : Recompute rows that already have a location}
        {--chunk=500 : Rows per batch}
        {--limit=0 : Stop after this many rows (0 = all)}
        {--dry-run : Count only, save nothing}';

    protected $description = 'Locate CRM prospects (OSM pins, else town centroids) and assign county / sub-county';

    private const KEYS = ['lat', 'lng', 'geo_precision', 'county', 'county_id', 'sub_county', 'sub_county_id', 'constituency', 'ward', 'sub_county_match', 'geo_at'];

    public function handle(KenyaGeo $geo): int
    {
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        $dry = (bool) $this->option('dry-run');
        $stats = ['exact' => 0, 'area' => 0, 'town' => 0, 'county' => 0, 'none' => 0, 'skipped' => 0];
        $props = ['exact' => 0, 'area' => 0, 'town' => 0, 'county' => 0, 'none' => 0, 'skipped' => 0];
        $pins = KenyaGeo::exactPins();
        $matches = [];
        $unmatched = [];
        $track = function (string $what, int $companyId, string $name, array $located) use (&$matches, &$unmatched) {
            if (! isset($located['lat'])) {
                return;
            }
            $how = isset($located['sub_county_id']) ? $located['sub_county_match'] : (isset($located['county_id']) ? 'county_only' : 'none');
            $matches[$what][$how] = ($matches[$what][$how] ?? 0) + 1;
            if (in_array($how, ['none', 'constituency'], true) || ($how === 'county_only' && ! isset($located['county_id']))) {
                $unmatched[] = [$what, $companyId, $name, $located['lat'], $located['lng'], $located['geo_precision'], $located['county'] ?? '', $located['constituency'] ?? '', $how];
            }
        };
        $done = 0;
        $now = now()->toDateString();

        $query = Company::crm()->select(['id', 'name', 'crm_profile']);
        $bar = $this->output->createProgressBar($limit ?: (clone $query)->count());

        $query->chunkById((int) $this->option('chunk'), function ($companies) use ($geo, $force, $limit, $dry, $now, $pins, $track, &$stats, &$props, &$done, $bar) {
            foreach ($companies as $company) {
                if ($limit && $done >= $limit) {
                    return false;
                }
                $profile = $company->crm_profile ?? [];
                $changed = false;
                if (! $force && ! empty($profile['geo_at'])) {
                    $stats['skipped']++;
                } else {
                    $done++;
                    $located = $this->locate($geo, $profile);
                    $stats[$located['geo_precision'] ?? 'none']++;
                    $track('company', $company->id, $company->name, $located);
                    $profile = array_merge(array_diff_key($profile, array_flip(self::KEYS)), $located, ['geo_at' => $now]);
                    $changed = true;
                }

                foreach ($profile['prospect_properties'] ?? [] as $i => $property) {
                    if (! is_array($property) || (! $force && ! empty($property['geo_at']))) {
                        $props['skipped']++;

                        continue;
                    }
                    $located = $geo->property($property, $profile, $pins);
                    $props[$located['geo_precision'] ?? 'none']++;
                    $track('property', $company->id, ($property['name'] ?? '?').' ('.$company->name.')', $located);
                    $profile['prospect_properties'][$i] = array_merge(array_diff_key($property, array_flip([...self::KEYS, 'geo_source'])), $located, ['geo_at' => $now]);
                    $changed = true;
                }

                if ($changed && ! $dry) {
                    $company->timestamps = false;
                    $company->crm_profile = $profile;
                    $company->saveQuietly();
                }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine(2);

        $this->table(['Exact (OSM pin)', 'Suburb centroid', 'Town centroid', 'County centroid', 'No location', 'Skipped (already done)'],
            [[$stats['exact'], $stats['area'], $stats['town'], $stats['county'], $stats['none'], $stats['skipped']]]);
        if (array_sum($props) > 0) {
            $this->line('Prospect properties (demo form):');
            $this->table(['Exact (named OSM building)', 'Suburb', 'Town', 'County', 'No location', 'Skipped (already done)'],
                [[$props['exact'], $props['area'], $props['town'], $props['county'], $props['none'], $props['skipped']]]);
        }
        // How the located ones matched the subcounties / counties tables.
        $kinds = ['ward' => 'Ward row (polygon)', 'ward_in_county' => 'Ward row (name, same county)', 'constituency' => 'Constituency only (first row)', 'county_only' => 'County id only (town/county centre)', 'none' => 'No match'];
        foreach ($matches as $what => $counts) {
            $this->line(ucfirst($what).' matches to subcounties / counties:');
            $this->table(array_values(array_intersect_key($kinds, $counts)), [array_values(array_intersect_key(array_replace($kinds, $counts), $counts))]);
        }
        if ($unmatched) {
            $file = storage_path('logs/crm-geocode-unmatched.csv');
            $fh = fopen($file, 'w');
            fputcsv($fh, ['what', 'company_id', 'name', 'lat', 'lng', 'precision', 'county', 'constituency', 'match']);
            foreach ($unmatched as $u) {
                fputcsv($fh, $u);
            }
            fclose($fh);
            $this->warn(count($unmatched).' located rows have no ward row (listed in '.$file.'):');
            $this->table(['What', 'Company', 'Name', 'Lat', 'Lng', 'Precision', 'County', 'Constituency', 'Match'], array_slice($unmatched, 0, 25));
        }
        if ($dry) {
            $this->warn('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> the geo keys for crm_profile (empty when nothing is known) */
    private function locate(KenyaGeo $geo, array $profile): array
    {
        $pin = KenyaGeo::osmPin($profile['notes'] ?? null);
        if ($pin) {
            $point = ['lat' => $pin[0], 'lng' => $pin[1], 'precision' => 'exact'];
        } else {
            $point = $geo->geocode($profile['city'] ?? null, $profile['area'] ?? null);
        }
        if (! $point) {
            return [];
        }

        // A town or county centre only tells us the county; a pin or suburb also tells us the sub-county (ward row).
        $named = match ($point['precision']) {
            'exact', 'area' => true,
            'town' => $geo->townIsOneConstituency($profile['city'] ?? null),
            default => false,
        };
        $out = ['lat' => $point['lat'], 'lng' => $point['lng'], 'geo_precision' => $point['precision']]
            + $geo->admin($point['lat'], $point['lng'], $named);

        return $out;
    }
}
