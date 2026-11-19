<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Re-tags prospects already in the CRM with how the team sells by area:
 *   - Ruiru is sold as Nairobi (Thika Road): city "Ruiru" becomes city Nairobi, area Ruiru. The county on the map
 *     stays what the boundaries say (Kiambu); run crm:geocode --missing afterwards so town-level rows get the
 *     Ruiru sub-county.
 *   - Prospects within walking distance of a campus get crm_profile.near_campus; named apartment buildings and
 *     private hostels there get a business type that says so ("Apartments near JKUAT (student lets)"). A hostel
 *     inside a campus with no published contact is a university hall, labelled as such (not a landlord).
 *
 *   crm:tag-areas --dry-run    counts only
 *   crm:tag-areas              save
 */
class TagAreas extends Command
{
    protected $signature = 'crm:tag-areas {--dry-run : Count what would change, save nothing}';

    protected $description = 'Ruiru under Nairobi; tag apartments and hostels near campuses';

    private const RESIDENTIAL = 'Residential Property (named building/estate)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $stats = ['ruiru_to_nairobi' => 0, 'near_campus' => 0, 'campus_apartments' => 0, 'campus_hostels' => 0, 'university_halls' => 0];
        $byCampus = [];

        Company::crm()->select(['id', 'name', 'email', 'phone', 'domain', 'crm_profile'])
            ->chunkById(2000, function ($rows) use ($dry, &$stats, &$byCampus) {
                foreach ($rows as $c) {
                    $p = $c->crm_profile ?? [];
                    $before = $p;

                    if (preg_match('/^\s*ruiru(\s+town)?\s*$/i', (string) ($p['city'] ?? ''))) {
                        $p['city'] = 'Nairobi';
                        $p['area'] = trim((string) ($p['area'] ?? '')) !== '' && ! preg_match('/^hotel$/i', $p['area']) ? $p['area'] : 'Ruiru';
                        $stats['ruiru_to_nairobi']++;
                    }

                    // Rerunnable: start from the type the row had before this command labelled it.
                    if (array_key_exists('business_type_before_campus', $p)) {
                        $p['business_type'] = $p['business_type_before_campus'];
                    }
                    unset($p['near_campus'], $p['business_type_before_campus']);
                    $original = $p['business_type'] ?? null;

                    if (is_numeric($p['lat'] ?? null) && is_numeric($p['lng'] ?? null) && in_array($p['geo_precision'] ?? null, ['exact', 'area'], true)
                        && ($near = PullNairobiLeads::nearCampus((float) $p['lat'], (float) $p['lng']))) {
                        [$campus, $edge] = $near;
                        $p['near_campus'] = $campus;
                        $stats['near_campus']++;
                        $byCampus[$campus] = ($byCampus[$campus] ?? 0) + 1;
                        $type = (string) ($p['business_type'] ?? '');
                        $hostel = preg_match('/\bhostels?\b|dormitor/i', $c->name.' '.$type);
                        $contact = $c->email || $c->phone || $c->domain || ! empty($p['website']);
                        if ($hostel && $edge < 0 && ! $contact) {
                            $p['business_type'] = 'University hall inside '.$campus.' (not a landlord)';
                            $stats['university_halls']++;
                        } elseif ($hostel && in_array($p['segment'] ?? null, [self::RESIDENTIAL, 'Short-stay / Airbnb'], true)) {
                            $p['business_type'] = 'Private student hostel near '.$campus;
                            $stats['campus_hostels']++;
                        } elseif (($p['segment'] ?? null) === self::RESIDENTIAL && ! preg_match('/gated|nhc|affordable|co-?operative|hostel|dormitor/i', $type)) {
                            $p['business_type'] = 'Apartments near '.$campus.' (student lets)';
                            $stats['campus_apartments']++;
                        }
                    }

                    if (($p['business_type'] ?? null) !== $original) {
                        $p['business_type_before_campus'] = $original;
                    }

                    if ($p !== $before && ! $dry) {
                        $c->timestamps = false;
                        $c->crm_profile = $p;
                        $c->saveQuietly();
                    }
                }
            });

        arsort($byCampus);
        $this->table(['What', 'Count'], collect($stats)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());
        $this->table(['Campus', 'Prospects within reach'], collect($byCampus)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($dry) {
            $this->warn('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }
}
