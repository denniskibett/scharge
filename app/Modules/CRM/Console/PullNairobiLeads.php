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
        {--elements= : OSM elements JSON (Overpass format) to use for areas Overpass cannot answer; default storage/app/private/osm/nairobi_elements.json}
        {--source=overpass : overpass (fallback to the .pbf extract for failed areas) or pbf (extract only)}
        {--prune : Remove OSM-pulled prospects that fail the current name filter (untouched rows only)}
        {--dry-run : Fetch and match everything, then roll back (nothing saved, no backup)}';

    protected $description = 'Pull new prospects in Nairobi\'s upmarket apartment areas (and Ruaka, Ruiru) from OpenStreetMap, dedupe and import';

    public const LABEL_FILE = 'nairobi_core_osm.csv';

    /** Area => [lat, lng, radius m, tier, city]. Tier 3 = lower tier (Syokimau, Kitengela). City defaults to Nairobi. */
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
        'Ruaka' => [-1.2085, 36.7775, 1800, 2, 'Kiambu'],
        'Ruiru' => [-1.1460, 36.9600, 3500, 2], // sold as Nairobi (Thika Road); the map still puts it in Kiambu County
        'Syokimau' => [-1.3667, 36.9333, 2500, 3],
        'Kitengela' => [-1.4761, 36.9614, 3500, 3],
        // Added 19 Nov 2026: south and east (Ngong Road, Mombasa Road, Lang'ata Road corridors).
        'Racecourse' => [-1.3045, 36.7585, 1200, 2],
        'Mbagathi' => [-1.3060, 36.8020, 1000, 2],
        'Madaraka' => [-1.3090, 36.8140, 1000, 2],
        'Ongata Rongai' => [-1.3960, 36.7600, 3000, 3, 'Kajiado'],
        'Ngong' => [-1.3600, 36.6560, 2500, 3, 'Kajiado'],
        'Imara Daima' => [-1.3260, 36.8780, 1500, 3],
        'Athi River' => [-1.4560, 36.9800, 3500, 3, 'Machakos'],
        // Waiyaki Way, Kiambu, Ngara.
        'Loresho' => [-1.2530, 36.7580, 1000, 2],
        'Kangemi' => [-1.2640, 36.7470, 1500, 3],
        'Uthiru' => [-1.2650, 36.7130, 1500, 3],
        'Kinoo' => [-1.2530, 36.6900, 1500, 3, 'Kiambu'],
        'Kiambu Town' => [-1.1710, 36.8350, 2500, 3, 'Kiambu'],
        'Ngara' => [-1.2740, 36.8240, 1000, 2],
        // Thika Road, end to end, with the campuses (USIU, KU, JKUAT): student hostels and rentals.
        'Pangani' => [-1.2680, 36.8370, 900, 3],
        'Ruaraka' => [-1.2470, 36.8690, 1200, 3],
        'Roysambu' => [-1.2180, 36.8890, 1500, 2],
        'Kasarani' => [-1.2230, 36.9020, 1500, 3],
        'Githurai' => [-1.2000, 36.9170, 1500, 3],
        'Kahawa Wendani' => [-1.1800, 36.9280, 1800, 2],
        'Juja' => [-1.1010, 37.0150, 2500, 2, 'Kiambu'],
        'Thika' => [-1.0390, 37.0830, 3500, 3, 'Kiambu'],
    ];

    /**
     * Campuses with a student-rental market around them: name => [lat, lng, campus half-width m, reach m]. Reach defaults to
     * CAMPUS_REACH; city-centre campuses get less, so Kilimani or Riverside blocks are not sold as student lets. Centres and sizes are the
     * amenity=university/college outlines in OpenStreetMap (Kenya extract, 19 Nov 2026). Apartments and private hostels within
     * CAMPUS_REACH of the campus edge are student-rental prospects; a hostel inside the campus with no published contact is a
     * university hall (run by the university's housing office, not a landlord) and is skipped.
     */
    public const CAMPUSES = [
        'Kenyatta University' => [-1.1800, 36.9324, 1250],
        'Kenyatta University Ruiru' => [-1.1450, 36.9667, 240],
        'JKUAT' => [-1.0951, 37.0138, 1120],
        'USIU-Africa' => [-1.2140, 36.8798, 680],
        'Pan Africa Christian University' => [-1.2160, 36.8844, 180, 1000],
        'KCA University' => [-1.2539, 36.8601, 170, 800],
        'Kenya Utalii College' => [-1.2580, 36.8560, 190, 600],
        'University of Nairobi (Main)' => [-1.2796, 36.8158, 300, 600],
        'University of Nairobi (Chiromo)' => [-1.2741, 36.8063, 420, 600],
        'Technical University of Kenya' => [-1.2920, 36.8242, 150, 500],
        'Daystar University (Nairobi)' => [-1.2971, 36.8023, 90, 500],
        'KMTC Nairobi' => [-1.3006, 36.8038, 320, 600],
        'Strathmore University' => [-1.3103, 36.8138, 290, 1000],
        'Riara University' => [-1.3147, 36.8071, 110, 700],
        'University of Nairobi (Kenya Science)' => [-1.3023, 36.7648, 360, 800],
        'Catholic University of East Africa' => [-1.3502, 36.7561, 650],
        'Co-operative University' => [-1.3672, 36.7286, 240],
        'JKUAT Karen' => [-1.3561, 36.7410, 170],
        'Multimedia University' => [-1.3832, 36.7679, 870],
        'Africa Nazarene University' => [-1.4009, 36.7901, 300],
        'Daystar University (Athi River)' => [-1.4461, 37.0485, 850],
        'University of Nairobi (Kabete)' => [-1.2534, 36.7304, 460],
        'Kabete National Polytechnic' => [-1.2648, 36.7243, 360],
        'Kiambu National Polytechnic' => [-1.1806, 36.8349, 480],
        'Mount Kenya University' => [-1.0449, 37.0848, 270],
        'Gretsa University' => [-1.0526, 37.0945, 130],
    ];

    /** How far past the campus edge a building still counts as student housing (walking distance). */
    public const CAMPUS_REACH = 1500;

    /** Built this year or later = a new building (preferred: modern fittings, owners still setting up management). */
    public const NEW_BUILD_YEAR = 2015;

    private static ?array $outlines = null;

    /**
     * Nearest campus within reach. Distance is from the campus centre less its half-width (a circle, good enough for
     * "walking distance"); -1 means inside the campus's real OSM outline (database/data/geo/ke_campuses.json), so a
     * private hostel just outside an oddly shaped campus is never mistaken for a hall.
     *
     * @return array{0: string, 1: float}|null [campus, metres from its edge]
     */
    public static function nearCampus(float $lat, float $lng): ?array
    {
        self::$outlines ??= json_decode((string) @file_get_contents(database_path('data/geo/ke_campuses.json')), true)['campuses'] ?? [];
        $best = null;
        foreach (self::CAMPUSES as $campus => $c) {
            [$clat, $clng, $half] = $c;
            $dx = ($lng - $clng) * 111320 * cos(deg2rad($lat));
            $dy = ($lat - $clat) * 110570;
            $edge = sqrt($dx * $dx + $dy * $dy) - $half;
            if ($edge > ($c[3] ?? self::CAMPUS_REACH)) {
                continue;
            }
            $inside = false;
            foreach (self::$outlines[$campus] ?? [] as $rings) {
                if (\App\Modules\CRM\Support\KenyaGeo::inPolygon($lng, $lat, $rings)) {
                    $inside = true;
                    break;
                }
            }
            $edge = $inside ? -1.0 : max(0.0, $edge);
            if (! $best || $edge < $best[1]) {
                $best = [$campus, $edge];
            }
        }

        return $best;
    }

    /** Year the building was finished (OSM start_date and friends), 'construction' while it is being built, else null. */
    public static function buildYear(array $t): int|string|null
    {
        if (($t['building'] ?? null) === 'construction' || isset($t['construction'])) {
            return 'construction';
        }
        foreach (['start_date', 'building:start_date', 'construction_date', 'building:year'] as $k) {
            if (isset($t[$k]) && preg_match('/\b(19[5-9]\d|20[0-4]\d)\b/', $t[$k], $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /** NHC estates and affordable-housing schemes anywhere in Kenya (no area circle). */
    public const NHC_AREA = 'NHC & affordable housing';

    private const ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
    ];

    /** Names that say nothing about who runs the place. */
    private const GENERIC = '/^(the )?(apartments?|flats?|estate|residential|residence|houses?|homes?|guest ?house|bnb|airbnb|block [a-z0-9]+|court|towers?|villas?|maisonettes?|townhouses?|staff quarters|quarters|hostels?|phase( [0-9a-z]+)?|student apartments?( \d+)?|[0-9 \-\/]+)$/i';

    /** Not a property business even when mapped as one. */
    private const NOT_TARGET = '/\b(school|primary|secondary|academy|kindergarten|daycare|church|ack|a\.c\.k|catholic|parish|chapel|cathedral|pcea|sda|adventist|pentecostal|gospel|ministr(y|ies)|fellowship|jumuiya|four ?square|mosque|masjid|madrasa|temple|gurdwara|hospital|clinic|dispensary|health|police|barracks|prison|university|college|polytechnic|embassy|high commission|county government|slum|informal|swimming pool|playground|cemetery|restaurant|cafe|cafeteria|eatery|hotel kibanda|kibanda|chapati|nyama choma|butchery|bar|pub|club|lounge|kinyozi|salon|shop|supermarket|market|stage|matatu|garage|car ?wash|petrol|depot|godown|warehouse|factory|plot|farm|shamba|jua ?ka(li|i)|(mens?|ladies|female|male|girls|boys) hostels?)\b|\b(centre|center|area|park|grounds?|stadium|sector)$/i';

    /** Named after a person ("Mama Tina", "Kwa Mzee …"): a home or a kiosk, and personal data either way. */
    private const PERSONAL = '/^(mama|baba|mzee|kwa|cucu|shosh|mr\.?|mrs\.?|dr\.?|hon\.?)\b|\'s (home|house|place|residence)\b|\b(homestead|home of|residence of|family)\b/i';

    /** A named estate / residential landuse must look like one (not a place name or a landmark). */
    private const ESTATE_WORDS = '/\b(estates?|court|gardens?|park ?view|villas?|residen\w*|heights|springs|apartments?|homes|ridge|views?|meadows|village|flats|towers?|phase|block|close|crescent|grove|place|terrace|mansions?|manor|haven|enclave|suites|maisonettes?|town ?houses|annex|condos?|cottages|housing|developments?|riviera|willows|drive|point|res|hostels?|nhc)\b/i';

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
        // Overpass is often busy: areas that get no answer are tried once more at the end, after a pause.
        $failed = $this->option('source') === 'pbf' ? array_keys($areas) : [];
        foreach ($this->option('source') === 'pbf' ? [] : [array_keys($areas), null] as $pass => $names) {
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

        // NHC estates and affordable-housing schemes, anywhere in Kenya (full runs, or --areas=NHC).
        $nhcFailed = false;
        $wantNhc = ! $this->option('areas') || in_array('nhc', array_map(fn ($a) => Str::lower(trim($a)), explode(',', $this->option('areas'))), true);
        if ($wantNhc && $this->option('source') === 'pbf') {
            $nhcFailed = true;
        } elseif ($wantNhc) {
            $found = $this->overpassNhc();
            if ($found === null) {
                $nhcFailed = true;
                $this->warn('Overpass gave no answer for the NHC / affordable-housing search; it is retried next run.');
            } else {
                foreach ($found as $el) {
                    $el['_nhc'] = true;
                    $elements[$el['type'].'/'.$el['id']] ??= $el;
                    $elements[$el['type'].'/'.$el['id']]['_nhc'] = true;
                }
                $this->line(sprintf('%-14s %4d OSM features', 'NHC/affordable', count($found)));
            }
        }

        // Areas Overpass could not answer (or --source=pbf): the .pbf extract.
        if (($failed || $nhcFailed) && ($extract = $this->pbfElements())) {
            [$fromPbf, $base] = $extract;
            if ($nhcFailed) {
                $found = array_filter($fromPbf, fn ($el) => ! empty($el['_nhc']));
                foreach ($found as $el) {
                    $elements[$el['type'].'/'.$el['id']] ??= $el;
                    $elements[$el['type'].'/'.$el['id']]['_nhc'] = true;
                }
                $this->line(sprintf('%-14s %4d OSM features (from the .pbf extract, %s)', 'NHC/affordable', count($found), $base));
            }
            foreach ($failed as $area) {
                [$lat, $lng, $radius] = $areas[$area];
                $found = array_filter($fromPbf, fn ($el) => ($p = $this->point($el)) && $this->metres($p[0], $p[1], $lat, $lng) <= $radius);
                foreach ($found as $el) {
                    $elements[$el['type'].'/'.$el['id']] = $el;
                }
                $this->line(sprintf('%-14s %4d OSM features (from the .pbf extract, %s)', $area, count($found), $base));
            }
            $failed = [];
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
            $pruned = $this->option('prune') ? $this->prune() : [];
            $created = Company::withoutGlobalScope('customers')->where('created_at', '>=', $started)
                ->where('lifecycle', 'prospect')->get(['id', 'name', 'email', 'phone', 'source', 'crm_profile']);
            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['File', 'Rows', 'New', 'Merged', 'Skipped', 'Need verifying'], array_map(fn ($r) => array_values($r), $results));
        $this->report($created, $enriched);
        if ($this->option('prune')) {
            $this->info(($dry ? 'Would remove ' : 'Removed ').count($pruned).' OSM-pulled prospects that fail the name filter.');
            $pruned && $this->table(['Removed', 'Area', 'Segment'], array_slice($pruned, 0, 60));
        }
        if ($failed) {
            $this->warn('Areas without an Overpass answer: '.implode(', ', $failed));
        }

        $summary = [
            'ran_at' => $started->toIso8601String(), 'dry_run' => $dry, 'failed_areas' => $failed, 'skips' => $skips,
            'imports' => $results, 'created' => $created->count(), 'enriched' => count($enriched), 'pruned' => $pruned ?? [],
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

    /** @return array{0: float, 1: float}|null */
    private function point(array $el): ?array
    {
        $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
        $lng = $el['lon'] ?? $el['center']['lon'] ?? null;

        return $lat === null || $lng === null ? null : [(float) $lat, (float) $lng];
    }

    /**
     * OSM features from a Geofabrik Kenya .pbf, for areas Overpass can't answer. With the osmium CLI installed the
     * extract is rebuilt from a fresh kenya-latest.osm.pbf once it is 6 days old; without it the last extract shipped
     * to the server is used while it is under 45 days old (scripts/crm-sites/pbf_elements.py builds one with pyosmium).
     *
     * @return array{0: array<int, array>, 1: string}|null elements and their date
     */
    private function pbfElements(): ?array
    {
        $disk = Storage::disk('local');
        $path = $this->option('elements') ?: $disk->path('osm/nairobi_elements.json');
        $age = fn () => is_file($path) ? (time() - filemtime($path)) / 86400 : INF;

        if ($age() > 6 && ! $this->option('elements') && $this->osmium()) {
            try {
                $this->buildExtract($path);
            } catch (\Throwable $e) {
                $this->warn('Could not rebuild the .pbf extract: '.$e->getMessage());
            }
        }
        if (! is_file($path) || $age() > 45) {
            $this->warn(is_file($path) ? 'The .pbf extract is over 45 days old; not used.' : 'No .pbf extract at '.$path.'.');

            return null;
        }
        $elements = json_decode((string) file_get_contents($path), true)['elements'] ?? [];

        return [$elements, date('Y-m-d', filemtime($path))];
    }

    private function osmium(): ?string
    {
        foreach (['/usr/bin/osmium', '/usr/local/bin/osmium'] as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        return null;
    }

    /** Download kenya-latest.osm.pbf (Geofabrik, ODbL) and cut the pull's features for the Nairobi metro with osmium. */
    private function buildExtract(string $path): void
    {
        $osmium = $this->osmium();
        $dir = dirname($path);
        @mkdir($dir, 0775, true);
        $pbf = $dir.'/kenya-latest.osm.pbf';
        if (! is_file($pbf) || time() - filemtime($pbf) > 6 * 86400) {
            $this->line('Downloading kenya-latest.osm.pbf from Geofabrik …');
            Http::withHeaders(['User-Agent' => self::UA])->timeout(900)->sink($pbf.'.part')
                ->get('https://download.geofabrik.de/africa/kenya-latest.osm.pbf')->throw();
            rename($pbf.'.part', $pbf);
        }
        $run = function (array $cmd) {
            exec(implode(' ', array_map('escapeshellarg', $cmd)).' 2>&1', $out, $code);
            if ($code !== 0) {
                throw new \RuntimeException(implode("\n", array_slice($out, -3)));
            }
        };
        $box = "{$dir}/metro.osm.pbf";
        $tagged = "{$dir}/metro-tagged.osm.pbf";
        $seq = "{$dir}/metro.geojsonseq";
        $run([$osmium, 'extract', '-b', '36.60,-1.52,37.02,-1.08', '-s', 'smart', '--overwrite', '-o', $box, $pbf]);
        $run([$osmium, 'tags-filter', '--overwrite', '-o', $tagged, $box, 'nwr/building=apartments,residential,flats,dormitory', 'nwr/landuse=residential',
            'nwr/residential', 'nwr/office', 'nwr/shop=estate_agent', 'nwr/tourism=apartment,guest_house,chalet,hotel']);
        $run([$osmium, 'export', '--overwrite', '-f', 'geojsonseq', '-a', 'type,id', '-o', $seq, $tagged]);

        $elements = [];
        foreach (new \SplFileObject($seq) as $line) {
            $f = json_decode(ltrim((string) $line, "\x1e"), true);
            if (! $f || empty($f['geometry']['coordinates'])) {
                continue;
            }
            $props = $f['properties'];
            [$type, $id] = [$props['@type'] ?? 'way', $props['@id'] ?? 0];
            unset($props['@type'], $props['@id']);
            $coords = [];
            array_walk_recursive($f['geometry']['coordinates'], function ($v) use (&$coords) {
                $coords[] = $v;
            });
            $lngs = array_values(array_filter($coords, fn ($k) => $k % 2 === 0, ARRAY_FILTER_USE_KEY));
            $lats = array_values(array_filter($coords, fn ($k) => $k % 2 === 1, ARRAY_FILTER_USE_KEY));
            $elements[] = ['type' => $type, 'id' => $id, 'center' => ['lat' => array_sum($lats) / count($lats), 'lon' => array_sum($lngs) / count($lngs)], 'tags' => $props];
        }
        file_put_contents($path, json_encode(['generator' => 'crm:pull-nairobi (osmium)', 'elements' => $elements]));
        @unlink($box);
        @unlink($tagged);
        @unlink($seq);
    }

    /**
     * Removes prospects this command pulled from OSM whose names fail today's filter (churches, eateries, place names,
     * people's names). Only rows nobody has worked on: still stage "new", owner-less, nothing logged except the import.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function prune(): array
    {
        $removed = [];
        Company::withoutGlobalScope('customers')->where('lifecycle', 'prospect')->where('crm_stage', 'new')->whereNull('owner_id')
            ->where('source', 'like', '%'.self::LABEL_FILE.'%')->whereNull('email')
            ->each(function (Company $c) use (&$removed) {
                // Only names that are plainly not a property (church, eatery, a person's name). Lacking an estate word is not enough:
                // "The Cycads", "Kanaro" and "One Riverside Drive" are real estates, and a wrong delete loses the row.
                $bad = preg_match(self::NOT_TARGET, $c->name) || preg_match(self::PERSONAL, $c->name);
                if (! $bad || $c->followUps()->where('type', '!=', 'import')->exists()) {
                    return;
                }
                $removed[] = [$c->name, (string) $c->profile('area'), (string) $c->profile('segment')];
                $c->followUps()->delete();
                $c->delete();
            });

        return $removed;
    }

    /** JSON dump of the whole companies table (mysqldump is not available on the live server). */
    private function backup(): string
    {
        $dir = 'crm-backups';
        $path = $dir.'/companies-'.now()->format('Ymd-His').'.json.gz';
        Storage::disk('local')->makeDirectory($dir);
        $out = gzopen(Storage::disk('local')->path($path), 'w6') ?: throw new \RuntimeException('Cannot write backup');
        $write = fn (string $s) => gzwrite($out, $s) === false && throw new \RuntimeException('Backup write failed: '.$path);
        $write("[\n");
        $first = true;
        $rows = 0;
        DB::table('companies')->orderBy('id')->chunk(1000, function ($chunk) use ($write, &$first, &$rows) {
            foreach ($chunk as $row) {
                $json = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($json === false) {
                    throw new \RuntimeException('Backup: company #'.$row->id.' could not be encoded.');
                }
                $write(($first ? '' : ",\n").$json);
                $first = false;
                $rows++;
            }
        });
        $write("\n]\n");
        gzclose($out) || throw new \RuntimeException('Backup could not be closed: '.$path);
        // Hard stop: nothing is written to the CRM unless the backup holds every company.
        $expected = DB::table('companies')->count();
        if ($rows === 0 || $rows < $expected || filesize(Storage::disk('local')->path($path)) < 100) {
            throw new \RuntimeException("Backup incomplete ({$rows} of {$expected} companies): stopping before any change.");
        }

        $old = collect(Storage::disk('local')->files($dir))->filter(fn ($f) => str_contains($f, '/companies-'))->sort()->values();
        Storage::disk('local')->delete($old->slice(0, max(0, $old->count() - 8))->all());

        return Storage::disk('local')->path($path);
    }

    /** NHC (National Housing Corporation) estates and affordable-housing projects in Kenya, by name, operator or owner. */
    private function overpassNhc(): ?array
    {
        $re = '(^|[^a-z])NHC([^a-z]|$)|national housing|affordable housing|boma yangu';
        $query = '[out:json][timeout:90];area["ISO3166-1"="KE"][admin_level=2]->.ke;('
            .'nwr[name~"'.$re.'",i][~"^(landuse|building|residential|place)$"~"."](area.ke);'
            .'nwr[~"^(operator|owner)$"~"'.$re.'",i][~"^(landuse|building|residential)$"~"."](area.ke);'
            .');out center tags;';

        return $this->overpassQuery($query);
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
            .'nwr[name][tourism=hostel]'.$a.';'
            .'nwr[name][building][name~"hostel",i]'.$a.';'
            .');out center tags;';

        return $this->overpassQuery($query);
    }

    private function overpassQuery(string $query): ?array
    {
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
        $skips = ['no usable name' => 0, 'not a property business' => 0, 'estate/building name not a property' => 0, 'outside the areas' => 0, 'same OSM object in CRM' => 0,
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
            if (preg_match(self::NOT_TARGET, $name) || preg_match(self::PERSONAL, $name) || isset($t['amenity']) || isset($t['shop']) && $t['shop'] !== 'estate_agent'
                || ($t['office'] ?? '') === 'government' || isset($t['religion'])) {
                $skips['not a property business']++;
                continue;
            }
            $flats = in_array($t['building'] ?? '', ['apartments', 'flats'], true);
            if (! isset($t['office']) && ! isset($t['shop']) && ! isset($t['tourism']) && ! $flats && ! preg_match(self::ESTATE_WORDS, $name)) {
                $skips['estate/building name not a property']++;
                continue;
            }
            [$lat, $lng] = $this->point($el) ?? [null, null];
            $hall = $lat !== null && ! isset($t['office']) && (($t['tourism'] ?? null) === 'hostel' || ($t['building'] ?? null) === 'dormitory' || preg_match('/\bhostels?\b/i', $name))
                && ($c = self::nearCampus($lat, $lng)) && $c[1] < 0 && ! $this->hasContact($t);
            if ($hall || preg_match('/universit|college/i', ($t['operator'] ?? '').' '.($t['owner'] ?? ''))) {
                $skips['university hall (inside campus, no contact)'] = ($skips['university hall (inside campus, no contact)'] ?? 0) + 1;
                continue;
            }
            $area = $lat === null ? null : $this->nearestArea($lat, $lng, $areas);
            if (! $area && $lat !== null && ! empty($el['_nhc'])) {
                $area = self::NHC_AREA;
            }
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

            $rows[] = $this->row($name, $t, $area, $areas[$area][3] ?? 2, $osmId, $lat, $lng, $website, $email, ! empty($el['_nhc']));
        }

        return [$rows, $skips];
    }

    private function row(string $name, array $t, string $area, int $tier, string $osmId, float $lat, float $lng, ?string $website, ?string $email, bool $nhc = false): array
    {
        $office = $t['office'] ?? $t['shop'] ?? null;
        $tourism = $t['tourism'] ?? null;
        $phone = $t['phone'] ?? $t['contact:phone'] ?? $t['contact:mobile'] ?? null;
        $phone = $phone ? implode(' / ', array_map('trim', preg_split('/;/', $phone))) : null;

        $hostel = ($t['tourism'] ?? null) === 'hostel' || ($t['building'] ?? null) === 'dormitory' || preg_match('/\bhostels?\b/i', $name);
        $campus = self::nearCampus($lat, $lng)[0] ?? null;
        $built = self::buildYear($t);
        $new = $built === 'construction' || (is_int($built) && $built >= self::NEW_BUILD_YEAR);
        if ($nhc && ! $office) {
            [$kind, $segment, $type, $angle, $role] = ['manager', 'Affordable Housing', 'NHC / affordable-housing estate',
                'Rent and tenant-purchase collection, service charge, water/power sub-metering and gate log for the estate', 'Estate Manager / NHC regional office'];
        } elseif ($hostel && ! $office) {
            [$kind, $segment, $type, $angle, $role] = ['manager', 'Residential Property (named building/estate)',
                $campus ? 'Private student hostel near '.$campus : 'Student hostel / rental near campus',
                'Rent per room or bed, deposits, water/power sub-metering and gate log for the hostel', 'Hostel Owner / Manager'];
        } elseif ($office) {
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
                match (true) {
                    ($t['landuse'] ?? null) === 'residential' || isset($t['residential']) => 'Named estate / gated community',
                    (bool) $campus => 'Apartments near '.$campus.' (student lets)',
                    default => 'Named apartment building',
                },
                'Rent and service-charge collection, water/power sub-metering, gate and visitor log for the managing agent',
                'Managing Agent / Caretaker / Residents\' Association',
            ];
        }

        if ($new && $kind === 'manager' && ! $office) {
            $tier = 1; // new buildings first
        } elseif ($campus && $kind === 'manager' && ! $office && ! $nhc) {
            $tier = min($tier, 2);
        }
        $facts = array_filter([
            $built === 'construction' ? 'Under construction (OSM)' : ($built ? 'Built '.$built.' (OSM)' : null),
            $campus ? 'Near '.$campus : null,
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
            'City' => $area === self::NHC_AREA ? '' : (self::AREAS[$area][4] ?? 'Nairobi'),
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
