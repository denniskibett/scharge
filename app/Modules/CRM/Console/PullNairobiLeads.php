<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\CrmImporter;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps the CRM growing in Nairobi's upmarket apartment areas (the core market). Weekly:
 *
 *  1. Backs up the companies table (JSON, storage/app/crm-backups; the last 8 are kept).
 *  2. Pulls named apartment buildings and estates, letting agents / property managers, developers' offices and
 *     serviced / short-stay apartments around each area from OpenStreetMap (Overpass API, ODbL; business and
 *     building data only).
 *  3. Drops what the CRM already has (same OSM object, company key or domain, prospects and customers alike)
 *     and imports the rest as prospects through CrmImporter, as storage/app/crm-pulls/nairobi_core_osm.csv
 *     (source "Import: nairobi_core_osm.csv"). Every OSM row is flagged needs_verification.
 *  4. Re-imports the curated sheet database/data/crm/nairobi/*.csv (agents' and developers' own websites), which
 *     merges into existing companies and fills blank emails / phones.
 *  5. --enrich=N: for up to N Nairobi prospects with a website but no email, reads the homepage and contact page
 *     (if robots.txt allows) and keeps a role address on the company's own domain (info@, sales@, lettings@ …).
 *  6. Geocodes the new rows (crm:geocode).
 *
 * Undo a run: restore from the backup, or delete crm_follow_ups and companies whose source contains
 * "nairobi_core_osm.csv" and that were created after the run started (lifecycle = prospect).
 */
class PullNairobiLeads extends Command
{
    protected $signature = 'crm:pull-nairobi
        {--areas= : Comma-separated area names to pull (default: all)}
        {--enrich=60 : Prospects whose website to check for a role email (0 = off)}
        {--no-seed : Skip the curated database/data/crm/nairobi sheets}
        {--dry-run : Fetch and match everything, then roll back (nothing saved, no backup)}';

    protected $description = 'Pull new prospects in Nairobi\'s upmarket apartment areas from OpenStreetMap, dedupe and import';

    public const LABEL_FILE = 'nairobi_core_osm.csv';

    /** Area => [lat, lng, radius m, tier]. Tier 3 = lower tier (Syokimau, Kitengela). */
    public const AREAS = [
        'Kilimani' => [-1.2910, 36.7860, 1400, 2],
        'Kileleshwa' => [-1.2802, 36.7852, 1300, 2],
        'Lavington' => [-1.2780, 36.7680, 1700, 2],
        'Valley Arcade' => [-1.2885, 36.7700, 600, 2],
        'Westlands' => [-1.2668, 36.8081, 1200, 2],
        'Parklands' => [-1.2600, 36.8200, 1400, 2],
        'Riverside' => [-1.2735, 36.7985, 800, 2],
        'Upper Hill' => [-1.2960, 36.8150, 1200, 2],
        'Hurlingham' => [-1.2960, 36.7965, 700, 2],
        'Spring Valley' => [-1.2550, 36.7920, 1200, 2],
        'Muthaiga' => [-1.2470, 36.8350, 1700, 2],
        'Gigiri' => [-1.2326, 36.8153, 1200, 2],
        'Runda' => [-1.2128, 36.8038, 1900, 2],
        'Karen' => [-1.3250, 36.7100, 3800, 2],
        "Lang'ata" => [-1.3450, 36.7650, 2400, 2],
        'South B' => [-1.3099, 36.8338, 1100, 2],
        'South C' => [-1.3170, 36.8240, 1100, 2],
        'Syokimau' => [-1.3667, 36.9333, 2500, 3],
        'Kitengela' => [-1.4761, 36.9614, 3500, 3],
    ];

    private const ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
    ];

    /** Names that say nothing about who runs the place. */
    private const GENERIC = '/^(the )?(apartments?|flats?|estate|residential|residence|houses?|homes?|guest ?house|bnb|airbnb|block [a-z0-9]+|court|towers?|villas?|maisonettes?|townhouses?|staff quarters|quarters|[0-9 \-\/]+)$/i';

    /** Not a property business even when mapped as one. */
    private const NOT_TARGET = '/\b(school|church|mosque|temple|hospital|clinic|police|barracks|prison|university|college|embassy|high commission|ministry|county government|slum|informal|swimming pool|playground|park|cemetery)\b/i';

    private const ROLE_EMAILS = ['info', 'sales', 'enquiries', 'enquiry', 'inquiries', 'inquiry', 'hello', 'contact', 'contactus', 'admin', 'office',
        'lettings', 'letting', 'rentals', 'rent', 'leasing', 'reservations', 'reservation', 'bookings', 'booking', 'stay', 'property', 'properties',
        'management', 'propertymanagement', 'customerservice', 'customercare', 'care', 'support', 'accounts', 'marketing', 'frontoffice', 'frontdesk'];

    private const UA = 'scharge-crm/1.0 (+https://scharge.sharet.africa; sales@sharet.africa)';

    public function handle(CrmImporter $importer, ContactService $contacts): int
    {
        $dry = (bool) $this->option('dry-run');
        $started = now();
        $areas = $this->areas();
        if (! $areas) {
            $this->error('No such area. Known: '.implode(', ', array_keys(self::AREAS)));

            return self::FAILURE;
        }

        if (! $dry) {
            $this->info('Backup: '.$this->backup());
        }

        // 1. Fetch.
        $elements = [];
        $failed = [];
        // Overpass is often busy: areas that get no answer are tried once more at the end, after a pause.
        foreach ([array_keys($areas), null] as $pass => $names) {
            $names ??= $failed;
            if ($pass === 1) {
                if (! $failed) {
                    break;
                }
                $failed = [];
                sleep(30);
            }
            foreach ($names as $area) {
                [$lat, $lng, $radius] = $areas[$area];
                $found = $this->overpass($lat, $lng, $radius);
                if ($found === null) {
                    $failed[] = $area;
                    $this->warn("Overpass gave no answer for {$area}".($pass ? '; it is retried next run.' : '; trying again at the end.'));
                    continue;
                }
                foreach ($found as $el) {
                    $elements[$el['type'].'/'.$el['id']] = $el;
                }
                $this->line(sprintf('%-14s %4d OSM features', $area, count($found)));
                usleep(1_500_000);
            }
        }

        // 2. Classify, assign areas, dedupe.
        [$rows, $skips] = $this->rows($elements, $areas);
        $this->table(['Dropped because', 'Count'], collect($skips)->map(fn ($n, $why) => [$why, $n])->values()->all());

        $csv = $this->writeCsv($rows);
        $this->info(count($rows).' new OSM rows → '.$csv);

        // 3. Import (all or nothing).
        $label = 'Import: '.self::LABEL_FILE;
        $seedFiles = $this->option('no-seed') ? [] : glob(database_path('data/crm/nairobi/*.csv'));
        $results = [];
        $enriched = [];
        DB::beginTransaction();
        try {
            if ($rows) {
                $results[] = [self::LABEL_FILE, ...$this->statRow($importer->import($csv))];
            }
            foreach ($seedFiles as $file) {
                $results[] = [basename($file), ...$this->statRow($importer->import($file))];
            }
            $enriched = $this->enrich((int) $this->option('enrich'), $contacts, $dry);
            $created = Company::withoutGlobalScope('customers')->where('created_at', '>=', $started)
                ->where('lifecycle', 'prospect')->get(['id', 'name', 'email', 'phone', 'source', 'crm_profile']);
            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['File', 'Rows', 'New', 'Merged', 'Skipped', 'Need verifying'], array_map(fn ($r) => array_values($r), $results));
        $this->report($created, $enriched);
        if ($failed) {
            $this->warn('Areas without an Overpass answer: '.implode(', ', $failed));
        }

        $summary = [
            'ran_at' => $started->toIso8601String(), 'dry_run' => $dry, 'failed_areas' => $failed, 'skips' => $skips,
            'imports' => $results, 'created' => $created->count(), 'enriched' => count($enriched),
            'by_area_segment' => $created->groupBy(fn ($c) => ($c->crm_profile['area'] ?? '—').' | '.($c->crm_profile['segment'] ?? '—'))->map->count(),
        ];
        Storage::disk('local')->put('crm-pulls/last-run'.($dry ? '-dry' : '').'.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($dry) {
            $this->warn('Dry run: nothing was saved.');
        } elseif ($created->isNotEmpty()) {
            $this->call('crm:geocode');
        }

        return self::SUCCESS;
    }

    /** @return array<string, array> */
    private function areas(): array
    {
        if (! $only = $this->option('areas')) {
            return self::AREAS;
        }
        $wanted = array_map(fn ($a) => Str::lower(trim($a)), explode(',', $only));

        return array_filter(self::AREAS, fn ($k) => in_array(Str::lower($k), $wanted, true), ARRAY_FILTER_USE_KEY);
    }

    /** JSON dump of the whole companies table (mysqldump is not available on the live server). */
    private function backup(): string
    {
        $dir = 'crm-backups';
        $path = $dir.'/companies-'.now()->format('Ymd-His').'.json.gz';
        Storage::disk('local')->makeDirectory($dir);
        $out = gzopen(Storage::disk('local')->path($path), 'w6') ?: throw new \RuntimeException('Cannot write backup');
        gzwrite($out, "[\n");
        $first = true;
        DB::table('companies')->orderBy('id')->chunk(1000, function ($chunk) use ($out, &$first) {
            foreach ($chunk as $row) {
                gzwrite($out, ($first ? '' : ",\n").json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $first = false;
            }
        });
        gzwrite($out, "\n]\n");
        gzclose($out);

        $old = collect(Storage::disk('local')->files($dir))->filter(fn ($f) => str_contains($f, '/companies-'))->sort()->values();
        Storage::disk('local')->delete($old->slice(0, max(0, $old->count() - 8))->all());

        return Storage::disk('local')->path($path);
    }

    /** @return array<int, array>|null Overpass elements around a point, or null when every endpoint failed. */
    private function overpass(float $lat, float $lng, int $radius): ?array
    {
        $a = "(around:{$radius},{$lat},{$lng})";
        $query = '[out:json][timeout:60];('
            .'nwr[name][building~"^(apartments|residential|flats|dormitory)$"]'.$a.';'
            .'nwr[name][landuse=residential]'.$a.';'
            .'nwr[name][residential]'.$a.';'
            .'nwr[office~"^(estate_agent|property_management|real_estate|real_estate_agent)$"]'.$a.';'
            .'nwr[shop=estate_agent]'.$a.';'
            .'nwr[name][office][name~"propert|realt|real estate|developers|homes|housing|estates",i]'.$a.';'
            .'nwr[tourism~"^(apartment|guest_house|chalet)$"]'.$a.';'
            .'nwr[name][tourism=hotel][name~"apartment|suites|residence|serviced",i]'.$a.';'
            .');out center tags;';

        foreach (self::ENDPOINTS as $attempt => $url) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::UA])->asForm()->connectTimeout(10)->timeout(75)->post($url, ['data' => $query]);
                if ($response->successful() && is_array($elements = $response->json('elements'))) {
                    return $elements;
                }
            } catch (\Throwable) {
                // next endpoint
            }
            sleep(3 * ($attempt + 1));
        }

        return null;
    }

    /** @return array{0: array<int, array>, 1: array<string, int>} CSV rows and the counts of dropped features */
    private function rows(array $elements, array $areas): array
    {
        $skips = ['no usable name' => 0, 'not a property business' => 0, 'outside the areas' => 0, 'same OSM object in CRM' => 0,
            'same name/domain in CRM' => 0, 'repeat within this pull' => 0];
        $known = $this->knownInCrm();
        $rows = [];
        $seen = [];

        // Features with contact details first, so they win name clashes inside the pull.
        uasort($elements, fn ($x, $y) => $this->hasContact($y['tags'] ?? []) <=> $this->hasContact($x['tags'] ?? []));

        foreach ($elements as $osmId => $el) {
            $t = $el['tags'] ?? [];
            $name = Normalizer::companyName(html_entity_decode(trim($t['name'] ?? ''), ENT_QUOTES));
            if (! $name || mb_strlen($name) < 4 || preg_match(self::GENERIC, $name)) {
                $skips['no usable name']++;
                continue;
            }
            if (! isset($t['office']) && ! isset($t['shop']) && ! isset($t['tourism'])) {
                // Blocks of one development are one prospect: "Staroot Residency A" / "… Block C" / "… Phase 2" -> "Staroot Residency".
                $name = preg_replace('/(?<=\S)\s+(?:(?:block|blk|wing|phase|tower|court)\s+)?(?:[A-H]|\d{1,2}|I{1,3}|IV|one|two|three)$/i', '', $name);
                if (mb_strlen($name) < 4 || preg_match(self::GENERIC, $name)) {
                    $skips['no usable name']++;
                    continue;
                }
            }
            if (preg_match(self::NOT_TARGET, $name) || isset($t['amenity']) || ($t['office'] ?? '') === 'government') {
                $skips['not a property business']++;
                continue;
            }
            $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
            $lng = $el['lon'] ?? $el['center']['lon'] ?? null;
            $area = $lat === null ? null : $this->nearestArea($lat, $lng, $areas);
            if (! $area) {
                $skips['outside the areas']++;
                continue;
            }
            if (isset($known['osm'][$osmId])) {
                $skips['same OSM object in CRM']++;
                continue;
            }
            $website = Normalizer::website($t['website'] ?? $t['contact:website'] ?? $t['url'] ?? null);
            $email = Normalizer::email($t['email'] ?? $t['contact:email'] ?? null);
            $domain = Normalizer::domain($website, $email);
            $key = Normalizer::companyKey($name);
            if (isset($known['key'][$key]) || ($domain && isset($known['domain'][$domain]))) {
                $skips['same name/domain in CRM']++;
                continue;
            }
            if (isset($seen[$key]) || ($domain && isset($seen['@'.$domain]))) {
                $skips['repeat within this pull']++;
                continue;
            }
            $seen[$key] = true;
            if ($domain) {
                $seen['@'.$domain] = true;
            }

            $rows[] = $this->row($name, $t, $area, $areas[$area][3], $osmId, $lat, $lng, $website, $email);
        }

        return [$rows, $skips];
    }

    private function row(string $name, array $t, string $area, int $tier, string $osmId, float $lat, float $lng, ?string $website, ?string $email): array
    {
        $office = $t['office'] ?? $t['shop'] ?? null;
        $tourism = $t['tourism'] ?? null;
        $phone = $t['phone'] ?? $t['contact:phone'] ?? $t['contact:mobile'] ?? null;
        $phone = $phone ? implode(' / ', array_map('trim', preg_split('/;/', $phone))) : null;

        if ($office) {
            $developer = preg_match('/develop|construction|homes\b/i', $name);
            [$kind, $segment, $type, $angle, $role] = $developer
                ? ['developer', 'Developer', 'Property developer (office)', 'Rent, service charge and handover for Nairobi projects', 'Head of Sales / Property Management']
                : ['manager', 'Property Manager / Agency', 'Estate agent / property manager (office)', 'Rent collection, tenant statements, service charge and owner reports for managed blocks', 'Property Manager'];
            $tier = $tier === 3 ? 3 : 1;
        } elseif ($tourism) {
            $serviced = preg_match('/apartment|suites|residence|serviced/i', $name) || $tourism === 'apartment';
            [$kind, $segment, $type, $angle, $role] = [
                'bnb_host',
                $serviced ? 'Serviced Apartments' : 'Short-stay / Airbnb',
                $serviced ? 'Serviced / short-stay apartments' : 'Guest house / short-stay',
                'Bookings and channel sync, guest billing, utilities sub-metering, cleaning and maintenance tickets',
                'Operations Manager / Owner',
            ];
        } else {
            [$kind, $segment, $type, $angle, $role] = [
                'manager',
                'Residential Property (named building/estate)',
                ($t['landuse'] ?? null) === 'residential' || isset($t['residential']) ? 'Named estate / gated community' : 'Named apartment building',
                'Rent and service-charge collection, water/power sub-metering, gate and visitor log for the managing agent',
                'Managing Agent / Caretaker / Residents\' Association',
            ];
        }

        $facts = array_filter([
            isset($t['building:levels']) ? $t['building:levels'].' floors' : null,
            isset($t['addr:street']) ? trim(($t['addr:housenumber'] ?? '').' '.$t['addr:street']) : null,
            isset($t['operator']) ? 'Operator: '.$t['operator'] : null,
        ]);
        $map = sprintf('https://www.openstreetmap.org/?mlat=%.5f&mlon=%.5f#map=18/%.5f/%.5f', $lat, $lng, $lat, $lng);

        return [
            'Priority' => 'Tier '.$tier,
            'Company' => $name,
            'Kind' => $kind,
            'Segment' => $segment,
            'City' => 'Nairobi',
            'Area' => $area,
            'Business Type' => $type,
            'Known Project / Portfolio' => $kind === 'manager' && ! $office ? $name.', '.$area : '',
            'Scharge Opportunity' => $angle,
            'First Target Role' => $role,
            'Public Phone' => $phone ?: 'Verify',
            'Public Email' => $email ?: 'Verify',
            'Website' => $website ?: 'Verify',
            'Verified' => 'No',
            'Needs Verification' => 'Yes',
            'Lead Status' => 'Not contacted',
            'Next Action' => $email ? 'Intro email' : 'Find the managing agent / a business contact, then intro',
            'Notes' => trim(($facts ? implode('; ', $facts)."\n" : '').'Map: '.$map),
            'Source' => 'OpenStreetMap (ODbL): https://www.openstreetmap.org/'.$osmId,
        ];
    }

    /** OSM ids, company keys and domains of every company (prospects and customers). */
    private function knownInCrm(): array
    {
        $known = ['osm' => [], 'key' => [], 'domain' => []];
        DB::table('companies')->select(['id', 'name', 'name_key', 'domain', 'email', 'crm_profile'])->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$known) {
                foreach ($chunk as $c) {
                    $known['key'][$c->name_key ?: Normalizer::companyKey($c->name)] = true;
                    if ($d = $c->domain ?: Normalizer::domain(null, $c->email)) {
                        $known['domain'][$d] = true;
                    }
                    if ($c->crm_profile && preg_match_all('#openstreetmap\.org\\\\?/(node|way|relation)\\\\?/(\d+)#', $c->crm_profile, $m, PREG_SET_ORDER)) {
                        foreach ($m as $hit) {
                            $known['osm'][$hit[1].'/'.$hit[2]] = true;
                        }
                    }
                    if ($d = Normalizer::domain(json_decode((string) $c->crm_profile, true)['website'] ?? null)) {
                        $known['domain'][$d] = true;
                    }
                }
            });
        unset($known['key']['']);

        return $known;
    }

    private function nearestArea(float $lat, float $lng, array $areas): ?string
    {
        $best = null;
        $bestScore = 1.0;
        foreach ($areas as $area => [$aLat, $aLng, $radius]) {
            $score = $this->metres($lat, $lng, $aLat, $aLng) / $radius;
            if ($score <= $bestScore) {
                [$best, $bestScore] = [$area, $score];
            }
        }

        return $best;
    }

    private function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 12_742_000 * asin(min(1, sqrt($h)));
    }

    private function hasContact(array $t): int
    {
        return (int) (bool) array_intersect_key($t, array_flip(['phone', 'contact:phone', 'email', 'contact:email', 'website', 'contact:website']));
    }

    private function writeCsv(array $rows): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('crm-pulls');
        $path = $disk->path('crm-pulls/'.self::LABEL_FILE);
        $out = fopen($path, 'w');
        fputcsv($out, array_keys($rows[0] ?? ['Company' => '']), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);
        $disk->put('crm-pulls/archive/nairobi_core_osm-'.now()->format('Ymd').'.csv', file_get_contents($path));

        return $path;
    }

    private function statRow(array $s): array
    {
        return [$s['rows'], $s['created'], $s['merged'], $s['skipped'], $s['flagged']];
    }

    /**
     * Role email from the prospect's own website (homepage and contact page), for Nairobi prospects that have a
     * website but no email. Only addresses on the company's domain with a role local part (info@, sales@ …).
     *
     * @return array<int, array{0: string, 1: string}> [company, email]
     */
    private function enrich(int $limit, ContactService $contacts, bool $dry): array
    {
        if ($limit <= 0) {
            return [];
        }
        $done = [];
        $areas = array_keys(self::AREAS);
        $candidates = Company::withoutGlobalScope('customers')->where('lifecycle', 'prospect')->whereNull('email')
            ->where(fn ($q) => $q->whereIn('crm_profile->area', $areas)->orWhere('crm_profile->city', 'Nairobi'))
            ->whereNotNull('crm_profile->website')
            ->where(fn ($q) => $q->whereNull('crm_profile->email_checked_at'))
            ->orderBy('tier')->orderBy('id')->limit($limit)->get();

        foreach ($candidates as $company) {
            $website = Normalizer::website($company->profile('website'));
            $domain = Normalizer::domain($website);
            $email = $domain ? $this->roleEmail($website, $domain) : null;
            $profile = array_merge($company->crm_profile ?? [], ['email_checked_at' => now()->toDateString()]);
            $company->forceFill(array_filter(['crm_profile' => $profile, 'email' => $email]))->save();
            if ($email) {
                $done[] = [$company->name, $email];
                $contacts->log($company, 'import', 'Email found on the company website', "{$email} (from {$website})");
            }
        }
        if ($done) {
            $this->info(($dry ? 'Would add ' : 'Added ').count($done).' role emails from company websites ('.$candidates->count().' checked).');
        }

        return $done;
    }

    private function roleEmail(string $website, string $domain): ?string
    {
        $base = rtrim($website, '/');
        $http = Http::withHeaders(['User-Agent' => self::UA])->timeout(8)->connectTimeout(5)->withOptions(['allow_redirects' => ['max' => 3]]);
        $robots = '';
        try {
            $robots = (string) $http->get($base.'/robots.txt')->body();
        } catch (\Throwable) {
        }
        foreach (['/', '/contact', '/contact-us', '/contacts'] as $page) {
            if ($this->robotsBlock($robots, $page)) {
                continue;
            }
            try {
                $response = $http->get($base.$page);
            } catch (\Throwable) {
                continue;
            }
            if (! $response->successful()) {
                continue;
            }
            $html = html_entity_decode(str_replace(['[at]', '(at)', ' at '], '@', (string) $response->body()));
            preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $html, $m);
            $found = collect($m[0])->map(fn ($e) => Normalizer::email($e))->filter()->unique()
                ->filter(fn ($e) => in_array(Str::before($e, '@'), self::ROLE_EMAILS, true)
                    && (Str::after($e, '@') === $domain || str_ends_with($domain, '.'.Str::after($e, '@')) || str_ends_with(Str::after($e, '@'), '.'.$domain)))
                ->sortBy(fn ($e) => array_search(Str::before($e, '@'), self::ROLE_EMAILS, true));
            if ($found->isNotEmpty()) {
                return $found->first();
            }
        }

        return null;
    }

    /** Minimal robots.txt check for "User-agent: *" Disallow rules. */
    private function robotsBlock(string $robots, string $path): bool
    {
        $applies = false;
        foreach (preg_split('/\R/', $robots) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $applies = trim($m[1]) === '*';
            } elseif ($applies && preg_match('/^disallow:\s*(\S+)/i', $line, $m) && str_starts_with($path, $m[1])) {
                return true;
            }
        }

        return false;
    }

    private function report($created, array $enriched): void
    {
        if ($created->isEmpty()) {
            $this->info('No new companies this run.');

            return;
        }
        $this->info($created->count().' new companies:');
        $grid = $created->groupBy(fn ($c) => $c->crm_profile['area'] ?? '—')->map(fn ($g, $area) => [
            $area, $g->count(),
            ...array_map(fn ($s) => $g->where('crm_profile.segment', $s)->count(), ['Residential Property (named building/estate)', 'Property Manager / Agency', 'Developer', 'Serviced Apartments', 'Short-stay / Airbnb', 'Short-stay / Airbnb Manager']),
        ])->sortByDesc(1)->values()->all();
        $this->table(['Area', 'All', 'Residential bldg', 'Agency', 'Developer', 'Serviced', 'Short-stay', 'S-s manager'], $grid);
        $this->table(['Sample', 'Area', 'Segment', 'Email', 'Phone'], $created->shuffle()->take(12)->map(fn ($c) => [
            $c->name, $c->crm_profile['area'] ?? '', $c->crm_profile['segment'] ?? '', $c->email, $c->phone,
        ])->all());
        if ($enriched) {
            $this->table(['Company', 'Email found on its website'], array_slice($enriched, 0, 15));
        }
    }
}
