<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Console\Command;

/**
 * Links building-site prospects (unnamed footprints from the sites_*.csv imports) to the published businesses found
 * inside the same footprint (Overture Maps places, imported first with crm:import as overture_places_kenya.csv).
 *
 *   crm:link-sites --from=site_places.jsonl --dry-run     counts only
 *   crm:link-sites --from=site_places.jsonl               save
 *
 * Each input line: {"bid": "<Overture building id>", "pid": "<Overture place id>", "role": "building"|"on_site", "name": "..."}
 *   building  the place is the building itself (named apartments, serviced apartments, hotel): the site gets its name and
 *             published phone/email, and crm_profile.managed_by_company_id points at that business's CRM row.
 *   on_site   an agency, developer or other business whose office is in the building: kept in crm_profile.site_contacts
 *             as a route to whoever runs the block (not claimed as the manager).
 * Nothing is guessed: only contacts the business published, and the link says where it came from.
 */
class LinkSites extends Command
{
    protected $signature = 'crm:link-sites
        {--from= : JSONL of footprint -> place links}
        {--dry-run : Count what would change, save nothing}';

    protected $description = 'Link building sites to the published businesses inside their footprint';

    /** Businesses in the building that can tell us who runs it (and may run it themselves). */
    private const ROUTES = ['Property Manager / Agency', 'Developer', 'Upmarket Agency', 'Short-stay / Airbnb Manager', 'Affordable Housing'];

    private const UUID ='/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/';

    public function handle(): int
    {
        $file = $this->option('from');
        if (! $file || ! is_readable($file)) {
            $this->error('Pass --from=<jsonl file>.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $links = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $l = json_decode($line, true);
            if (isset($l['bid'], $l['pid'])) {
                $links[$l['bid']][] = $l;
            }
        }
        $this->line(count($links).' footprints with a business inside.');

        // Place id -> CRM company (created or merged by the overture_places import; the id is in the notes).
        $places = [];
        Company::withoutGlobalScope('customers')->where('source', 'like', '%overture_places%')->select(['id', 'name', 'email', 'phone', 'crm_profile'])
            ->chunkById(2000, function ($rows) use (&$places) {
                foreach ($rows as $c) {
                    if (preg_match_all('/Overture Maps places [0-9.-]+: ('.trim(self::UUID, '/').')/', (string) ($c->crm_profile['notes'] ?? ''), $m)) {
                        foreach ($m[1] as $pid) {
                            $places[$pid] = $c;
                        }
                    }
                }
            });
        $this->line(count($places).' place ids found on CRM companies.');

        $stats = ['sites_matched' => 0, 'building' => 0, 'on_site_only' => 0, 'got_phone' => 0, 'got_email' => 0, 'renamed' => 0, 'place_not_in_crm' => 0];
        $byArea = [];
        Company::withoutGlobalScope('customers')->where('source', 'like', 'Import: sites%')->select(['id', 'name', 'email', 'phone', 'crm_profile'])
            ->chunkById(2000, function ($sites) use ($links, $places, $dry, &$stats, &$byArea) {
                foreach ($sites as $site) {
                    $profile = $site->crm_profile ?? [];
                    if (! preg_match_all(self::UUID, (string) ($profile['notes'] ?? ''), $m)) {
                        continue;
                    }
                    $found = array_merge(...array_map(fn ($bid) => $links[$bid] ?? [], $m[0]));
                    if (! $found) {
                        continue;
                    }
                    $stats['sites_matched']++;
                    $byArea[$profile['area'] ?? '—'] = ($byArea[$profile['area'] ?? '—'] ?? 0) + 1;
                    $building = null;
                    $onSite = [];
                    foreach ($found as $l) {
                        $c = $places[$l['pid']] ?? null;
                        if (! $c) {
                            $stats['place_not_in_crm']++;
                            continue;
                        }
                        if ($l['role'] === 'building' && ! $building) {
                            $building = $c;
                        } elseif (($c->phone || $c->email) && in_array($c->crm_profile['segment'] ?? null, self::ROUTES, true)) {
                            $onSite[$c->id] = ['company_id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email,
                                'type' => $c->crm_profile['segment'] ?? null, 'source' => 'Overture Maps place in this building: '.$l['pid']];
                        }
                    }
                    if (! $building && ! $onSite) {
                        continue;
                    }
                    $fill = [];
                    if ($building) {
                        $stats['building']++;
                        $profile['managed_by_company_id'] = $building->id;
                        $profile['managed_by_name'] = $building->name;
                        $profile['managed_by_source'] = 'Overture Maps place = this building';
                        $profile['building_name'] = $building->name;
                        if (blank($site->phone) && $building->phone) {
                            $fill['phone'] = Normalizer::phone($building->phone);
                            $stats['got_phone']++;
                        }
                        if (blank($site->email) && $building->email && ($e = Normalizer::email($building->email))) {
                            $fill['email'] = $e;
                            $profile['email_source'] = 'Published listing of '.$building->name;
                            $stats['got_email']++;
                        }
                        // "Apartment block 5A0149, Narayan Road, Parklands" -> "Sunrise Apartments, Narayan Road, Parklands"
                        if (preg_match('/^(apartment block|residential building) [0-9a-f]{6}(,.*)?$/i', $site->name, $nm)) {
                            $fill['name'] = Normalizer::companyName($building->name.($nm[2] ?? ''));
                            $fill['name_key'] = Normalizer::companyKey($fill['name']);
                            $stats['renamed']++;
                        }
                    } else {
                        $stats['on_site_only']++;
                    }
                    if ($onSite) {
                        $profile['site_contacts'] = array_values($onSite);
                    }
                    $profile['sites_linked_at'] = now()->toDateString();
                    if (! $dry) {
                        $site->forceFill($fill + ['crm_profile' => $profile])->saveQuietly();
                    }
                }
            });

        arsort($byArea);
        $this->table(['What', 'Count'], collect($stats)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());
        $this->table(['Area', 'Sites matched'], collect(array_slice($byArea, 0, 20, true))->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($dry) {
            $this->warn('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }
}
