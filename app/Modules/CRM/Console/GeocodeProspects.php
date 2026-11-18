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
 *   county, sub_county, constituency
 *   geo_at         when this ran (rows that have it are skipped unless --force)
 */
class GeocodeProspects extends Command
{
    protected $signature = 'crm:geocode
        {--force : Recompute rows that already have a location}
        {--chunk=500 : Rows per batch}
        {--limit=0 : Stop after this many rows (0 = all)}
        {--dry-run : Count only, save nothing}';

    protected $description = 'Locate CRM prospects (OSM pins, else town centroids) and assign county / sub-county';

    private const KEYS = ['lat', 'lng', 'geo_precision', 'county', 'sub_county', 'constituency', 'geo_at'];

    public function handle(KenyaGeo $geo): int
    {
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        $dry = (bool) $this->option('dry-run');
        $stats = ['exact' => 0, 'area' => 0, 'town' => 0, 'county' => 0, 'none' => 0, 'skipped' => 0];
        $done = 0;
        $now = now()->toDateString();

        $query = Company::crm()->select(['id', 'crm_profile']);
        $bar = $this->output->createProgressBar($limit ?: (clone $query)->count());

        $query->chunkById((int) $this->option('chunk'), function ($companies) use ($geo, $force, $limit, $dry, $now, &$stats, &$done, $bar) {
            foreach ($companies as $company) {
                if ($limit && $done >= $limit) {
                    return false;
                }
                $profile = $company->crm_profile ?? [];
                if (! $force && ! empty($profile['geo_at'])) {
                    $stats['skipped']++;
                    $bar->advance();

                    continue;
                }
                $done++;

                $located = $this->locate($geo, $profile);
                $stats[$located['geo_precision'] ?? 'none']++;
                $profile = array_merge(array_diff_key($profile, array_flip(self::KEYS)), $located, ['geo_at' => $now]);

                if (! $dry) {
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

        $out = ['lat' => $point['lat'], 'lng' => $point['lng'], 'geo_precision' => $point['precision']];
        if ($admin = $geo->locate($point['lat'], $point['lng'])) {
            $out['county'] = $admin['county'];
            // A town or county centroid only tells us the county; a pin or suburb also tells us the constituency.
            $named = match ($point['precision']) {
                'exact', 'area' => true,
                'town' => $geo->townIsOneConstituency($profile['city'] ?? null),
                default => false,
            };
            if ($named) {
                $out['sub_county'] = $out['constituency'] = $admin['constituency'];
            }
        }

        return $out;
    }
}
