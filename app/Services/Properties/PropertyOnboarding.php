<?php

namespace App\Services\Properties;

use App\Models\Company;
use App\Models\Meter;
use App\Models\Property;
use App\Models\Invoice;
use App\Models\PropertyClaim;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitManagement;
use App\Models\User;
use App\Modules\CRM\Support\Normalizer;
use App\Support\CompanyAccess;
use App\Support\PropertyPin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fast onboarding from one CSV: one row per unit, the property columns repeated on every row.
 *
 * One building exists once, whoever brings it in. Every row is matched before anything is created:
 *   property  scharge_property_id if given, else PropertyRegistry (name + location keys): score >= 95 is the same building,
 *             80-94 is "check this" (the rows wait until the file says which: an id, or "new").
 *   unit      same property + unit number, compared without spaces, dashes and "Unit/Hse/Apt" prefixes (A-1 = a1 = Unit A1).
 *   meter     utility + serial number, anywhere in scharge (one meter is on one unit).
 * Existing values are never overwritten: only blanks (empty or 0) are filled, and differences are reported.
 * The uploading company is linked in its role (unit_managements: manager | owner | developer | bnb_host). A manager
 * uploading a building someone else already has gets a pending PropertyClaim instead of a copy.
 *
 * run(..., commit: false) is the dry run: it does the same work inside a transaction and rolls it back, so the preview
 * is exactly what the confirm will do.
 */
class PropertyOnboarding
{
    public const ROLES = [
        'manager' => 'We run the building (landlord managing it, or its managing agent)',
        'owner' => 'We own units in it; someone else manages the building',
        'developer' => 'We developed it / are selling units',
        'bnb_host' => 'We run short stays (Airbnb) in units here',
    ];

    /** Template columns => what goes in them. * = required. */
    public const COLUMNS = [
        'property_name' => '* Building name, the same on every row of that building (e.g. Kings Serenity Apartments)',
        'location' => 'Area and town (e.g. Kilimani, Nairobi). Used to tell apart buildings with the same name',
        'lr_number' => 'Plot / LR number if known (used to check matches; not saved yet)',
        'gps' => 'GPS pin: "lat, lng" or a Google Maps link to the building (must be in Kenya). Saved on the building',
        'scharge_property_id' => 'Leave blank. Only if the preview asks: the building id it suggested, or "new"',
        'property_water_rate' => 'KES per m3 of water for the building',
        'property_service_charge' => 'Default monthly service charge for units without their own',
        'property_garbage_charge' => 'Default monthly garbage charge',
        'property_security_charge' => 'Default monthly security charge',
        'unit_number' => '* Unit / house number (A1, 12, Shop 3)',
        'unit_type' => 'e.g. Studio, 2 Bedroom, Shop. Blank = taken from bedrooms',
        'bedrooms' => '0 for studio, 1, 2, 3 ...',
        'rent' => 'Monthly rent (KES)',
        'deposit' => 'Security deposit (KES)',
        'service_charge' => 'Monthly service charge for this unit (KES)',
        'garbage_charge' => 'Monthly garbage charge for this unit (KES)',
        'security_charge' => 'Monthly security charge for this unit (KES)',
        'water_billing' => 'consumption (by meter) or flat',
        'water_flat_charge' => 'Monthly water amount if water_billing is flat',
        'water_meter_no' => 'Water meter serial number',
        'water_meter_reading' => 'Water meter reading today',
        'power_meter_no' => 'Electricity (KPLC / token) meter number',
        'power_meter_reading' => 'Electricity meter reading today',
        'status' => 'vacant or occupied',
        'stay_type' => 'long_stay, short_stay, bnb or mixed',
        'furnishing' => 'furnished, unfurnished or semi_furnished',
        'category' => 'residential, commercial, office, retail, industrial or showhouse',
        'nightly_rate' => 'Nightly rate for short stays (KES)',
        'owner_name' => 'Unit owner, if not you',
        'owner_phone' => 'Unit owner phone',
        'owner_email' => 'Unit owner email',
        'tenant_name' => 'Tenant living in the unit now (leave blank if vacant)',
        'tenant_phone' => 'Tenant phone (needed to add the tenant; they sign in with it)',
        'tenant_email' => 'Tenant email, if any',
        'tenant_id_number' => 'Tenant national ID / passport number',
        'move_in_date' => 'When the tenant moved in (e.g. 2025-03-01). Blank = today',
        'balance' => 'What the tenant owes today (KES), carried over as an unpaid bill. 0 or blank if nothing',
    ];

    /** Other headings clients use for the same columns. */
    private const ALIASES = [
        'property' => 'property_name', 'building' => 'property_name', 'building_name' => 'property_name', 'estate' => 'property_name',
        'area' => 'location', 'address' => 'location', 'lr_no' => 'lr_number', 'plot_number' => 'lr_number', 'plot_no' => 'lr_number', 'lr' => 'lr_number',
        'pin' => 'gps', 'coordinates' => 'gps', 'google_maps' => 'gps', 'map_link' => 'gps', 'gps_pin' => 'gps', 'lat_lng' => 'gps', 'property_id' => 'scharge_property_id',
        'unit' => 'unit_number', 'unit_no' => 'unit_number', 'house_number' => 'unit_number', 'house_no' => 'unit_number', 'house' => 'unit_number',
        'type' => 'unit_type', 'beds' => 'bedrooms', 'rent_amount' => 'rent', 'monthly_rent' => 'rent', 'rent_price' => 'rent',
        'security_deposit' => 'deposit', 'deposit_amount' => 'deposit', 'water_meter' => 'water_meter_no', 'water_meter_number' => 'water_meter_no',
        'power_meter' => 'power_meter_no', 'kplc_meter' => 'power_meter_no', 'electricity_meter' => 'power_meter_no', 'power_meter_number' => 'power_meter_no',
        'water_reading' => 'water_meter_reading', 'power_reading' => 'power_meter_reading', 'water_charge' => 'water_flat_charge',
        'furnishing_status' => 'furnishing', 'property_category' => 'category', 'bnb_nightly_rate' => 'nightly_rate',
        'tenant' => 'tenant_name', 'tenant_names' => 'tenant_name', 'tenant_full_name' => 'tenant_name', 'occupant' => 'tenant_name',
        'tenant_phone_number' => 'tenant_phone', 'tenant_mobile' => 'tenant_phone', 'tenant_tel' => 'tenant_phone', 'tenant_contact' => 'tenant_phone',
        'tenant_id' => 'tenant_id_number', 'tenant_id_no' => 'tenant_id_number', 'id_number' => 'tenant_id_number', 'id_no' => 'tenant_id_number', 'national_id' => 'tenant_id_number',
        'move_in' => 'move_in_date', 'moved_in' => 'move_in_date', 'date_moved_in' => 'move_in_date', 'start_date' => 'move_in_date', 'lease_start' => 'move_in_date',
        'arrears' => 'balance', 'balance_owed' => 'balance', 'amount_owed' => 'balance', 'opening_balance' => 'balance', 'outstanding' => 'balance', 'balance_due' => 'balance', 'arrears_kes' => 'balance',
    ];

    public const MAX_ROWS = 5000;

    private Company $company;

    private string $role;

    private ?User $by;

    /** @var array<string, array> per building in the file */
    private array $props = [];

    /** @var array<int, array<string, Unit>> property id => unit key => unit */
    private array $units = [];

    /** @var array<int, array<string, int>> property id => unit key => file line (one unit once per file) */
    private array $seen = [];

    /** @var array<int, string> property id => the file building it was first resolved for */
    private array $byId = [];

    private array $report;

    // ---------------------------------------------------------------- template and reading

    /** Two example rows: a let unit with its tenant and balance, and a vacant BnB unit with its owner. */
    private const EXAMPLES = [
        ['Kings Serenity Apartments', 'Kilimani, Nairobi', 'Nairobi/Block 1/234', '-1.2921, 36.7856', '', '150', '3000', '300', '500',
            'A1', '', '2', '45000', '45000', '', '', '', 'consumption', '', 'WM-10023', '120.5', '37123456789', '', 'occupied', 'long_stay', 'unfurnished', 'residential', '', '', '', '',
            'John Kamau', '0722 111 222', '', '12345678', '2025-03-01', '12000'],
        ['Kings Serenity Apartments', 'Kilimani, Nairobi', 'Nairobi/Block 1/234', '-1.2921, 36.7856', '', '150', '3000', '300', '500',
            'A2', 'Studio', '0', '25000', '25000', '2500', '', '', 'flat', '800', '', '', '', '', 'vacant', 'bnb', 'furnished', 'residential', '4500', 'Mercy Mumbi', '0712 345 678', 'mercy@example.com',
            '', '', '', '', '', ''],
    ];

    public function template(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys(self::COLUMNS));
        foreach (self::EXAMPLES as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return "\u{FEFF}".stream_get_contents($out);   // BOM so Excel opens it as UTF-8
    }

    /** The same template as an Excel workbook: "Units" to fill in (phones and numbers kept as text), "How to fill" to read. */
    public function templateXlsx(): string
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->getProperties()->setCreator('scharge')->setTitle('scharge onboarding template');
        $sheet = $book->getActiveSheet()->setTitle('Units');
        $keys = array_keys(self::COLUMNS);
        foreach ($keys as $i => $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit($col.'1', $key, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getComment($col.'1')->getText()->createText(ltrim(self::COLUMNS[$key], '* '));
            $sheet->getColumnDimension($col)->setWidth(max(12, min(30, strlen($key) + 4)));
            $sheet->getStyle($col.':'.$col)->getNumberFormat()->setFormatCode('@');   // Excel must not drop the 0 of 0712...
            foreach (self::EXAMPLES as $r => $row) {
                $sheet->setCellValueExplicit($col.($r + 2), $row[$i] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            if (str_starts_with(self::COLUMNS[$key], '*')) {
                $sheet->getStyle($col.'1')->getFont()->getColor()->setRGB('B42318');
            }
        }
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($keys));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1E7');
        $sheet->getStyle('A2:'.$last.'3')->getFont()->getColor()->setRGB('8A8A8A');
        $sheet->freezePane('B2');

        $help = $book->createSheet()->setTitle('How to fill');
        $help->fromArray([['Column', 'What goes in it'], ['', 'One row per unit. Columns marked * are required. Replace the two grey example rows with your own. Extra columns are ignored.']], null, 'A1');
        $r = 3;
        foreach (self::COLUMNS as $key => $text) {
            $help->setCellValue('A'.$r, $key);
            $help->setCellValue('B'.$r++, $text);
        }
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(26);
        $help->getColumnDimension('B')->setWidth(100);
        $book->setActiveSheetIndex(0);

        $out = fopen('php://temp', 'r+');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($out);
        rewind($out);

        return stream_get_contents($out);
    }

    /**
     * Rows keyed by template column. Accepts Excel workbooks (.xlsx / .xls: the first sheet with a property_name
     * heading), comma, semicolon or tab separated files, Excel's BOM, extra columns (ignored) and the usual
     * alternative headings.
     *
     * @return array{rows: array<int, array>, unknown: array<int, string>, missing: array<int, string>}
     */
    public function read(string $path): array
    {
        $table = self::isWorkbook($path) ? $this->workbookTable($path) : $this->csvTable($path);
        $header = array_shift($table) ?? [];
        $map = [];
        $unknown = [];
        foreach ($header as $i => $h) {
            $k = self::heading($h);
            if (isset(self::COLUMNS[$k]) && ! in_array($k, $map, true)) {
                $map[$i] = $k;
            } elseif ($k !== '') {
                $unknown[] = (string) $h;
            }
        }

        $rows = [];
        $line = 1;
        foreach ($table as $cells) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = ['_line' => $line];
            foreach ($map as $i => $k) {
                $row[$k] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) {
                break;
            }
        }

        return ['rows' => $rows, 'unknown' => $unknown, 'missing' => array_values(array_diff(['property_name', 'unit_number'], $map))];
    }

    /** An Excel file, by its first bytes (zip for .xlsx, OLE for .xls), whatever it is called. */
    public static function isWorkbook(string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 4);

        return $head === "PK\x03\x04" || $head === "\xD0\xCF\x11\xE0";
    }

    private static function heading($h): string
    {
        $k = Str::of((string) $h)->lower()->replaceMatches('/\(.*?\)|\*/', '')->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        return self::ALIASES[$k] ?? $k;
    }

    /** @return array<int, array<int, string>> */
    private function csvTable(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($raw, "\n") ?: '';
        $delim = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $table = [];
        while (($cells = fgetcsv($fh, 0, $delim, '"', '')) !== false && count($table) <= self::MAX_ROWS + 1) {
            $table[] = $cells;
        }
        fclose($fh);

        return $table;
    }

    /**
     * Cells as text, the way the client sees them: dates as Y-m-d, whole numbers without ".0" or exponents
     * (meter numbers, 712345678), formulas as their value. Only cell values are read (no macros, no styles).
     *
     * @return array<int, array<int, string>>
     */
    private function workbookTable(string $path): array
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);   // number formats are needed to tell dates apart
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            return [];
        }
        $sheet = $book->getSheet(0);
        foreach ($book->getWorksheetIterator() as $ws) {   // the sheet that has the headings, if it is not the first
            $first = $ws->rangeToArray('A1:'.$ws->getHighestDataColumn().'1', null, false, false)[0] ?? [];
            if (in_array('property_name', array_map(fn ($h) => self::heading($h), $first), true)) {
                $sheet = $ws;
                break;
            }
        }

        $table = [];
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastRow = min($sheet->getHighestDataRow(), self::MAX_ROWS + 2);
        for ($r = 1; $r <= $lastRow; $r++) {
            $cells = [];
            for ($c = 1; $c <= $lastCol; $c++) {
                $cell = $sheet->getCell([$c, $r]);
                try {
                    $v = $cell->getCalculatedValue();
                } catch (\Throwable $e) {
                    $v = $cell->getOldCalculatedValue() ?? '';
                }
                if (is_numeric($v) && ! is_string($v) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                    $v = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($v)->format('Y-m-d');
                } elseif (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
                    $v = number_format($v, 0, '', '');
                } elseif (is_bool($v)) {
                    $v = $v ? 'yes' : 'no';
                }
                $cells[] = (string) $v;
            }
            $table[] = $cells;
        }
        $book->disconnectWorksheets();

        return $table;
    }

    // ---------------------------------------------------------------- the import

    /** Dry run (commit false) or import. Returns the report: counts, buildings and one line per row. */
    public function run(Company $company, string $role, array $rows, ?User $by = null, bool $commit = false): array
    {
        abort_unless(isset(self::ROLES[$role]), 422, 'Unknown role.');
        $this->company = $company;
        $this->role = $role;
        $this->by = $by;
        $this->props = [];
        $this->units = [];
        $this->seen = [];
        $this->byId = [];
        $this->report = ['commit' => $commit, 'company' => ['id' => $company->id, 'name' => $company->name], 'role' => $role,
            'counts' => array_fill_keys(['rows', 'errors', 'properties_created', 'properties_matched', 'properties_review', 'claims',
                'units_created', 'units_matched', 'units_linked', 'units_skipped', 'meters_created', 'meters_matched', 'meters_skipped', 'fields_filled',
                'tenants_created', 'tenants_matched', 'tenants_skipped', 'balances_added', 'balances_total'], 0),
            'properties' => [], 'rows' => []];

        if (count($rows) > self::MAX_ROWS) {
            $this->report['fatal'] = 'The file has more than '.self::MAX_ROWS.' rows. Split it per building.';

            return $this->report;
        }

        CompanyAccess::bypass(function () use ($rows, $commit) {
            DB::beginTransaction();
            try {
                foreach ($rows as $row) {
                    $this->report['counts']['rows']++;
                    $this->report['rows'][] = $this->row($row);
                }
                $commit ? DB::commit() : DB::rollBack();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        });
        $list = [];
        foreach ($this->props as $gk => $p) {
            $list[$p['id'] ? 'id'.$p['id'] : $gk] ??= array_diff_key($p, ['model' => 1]);
        }
        $this->report['properties'] = array_values($list);

        return $this->report;
    }

    private function row(array $r): array
    {
        $out = ['line' => $r['_line'] ?? null, 'property' => $r['property_name'] ?? '', 'unit' => $r['unit_number'] ?? '',
            'property_action' => null, 'unit_action' => null, 'filled' => [], 'kept' => [], 'notes' => [], 'errors' => []];

        $d = $this->clean($r, $out['errors']);
        if ($d['property_name'] === null) {
            $out['errors'][] = 'property_name is empty';
        }
        if ($d['unit_number'] === null) {
            $out['errors'][] = 'unit_number is empty';
        }
        if ($out['errors']) {
            $out['unit_action'] = 'error';
            $this->report['counts']['errors']++;

            return $out;
        }
        $out['property'] = $d['property_name'];
        $out['unit'] = $d['unit_number'];

        $p = &$this->building($d);
        $out['property_action'] = $p['action'];
        if (in_array($p['action'], ['review', 'error'], true)) {
            $out['unit_action'] = 'skipped';
            $out['notes'][] = $p['action'] === 'review' ? 'Building needs a decision (see buildings above)' : $p['error'];
            $this->report['counts']['units_skipped']++;

            return $out;
        }
        /** @var Property $property */
        $property = $p['model'];

        if ($p['writable']) {
            $tmp = ['filled' => [], 'kept' => []];
            $this->fill($property, $this->propertyValues($d), $tmp, 'property ');
            $this->fillPin($property, PropertyPin::parse($d['gps']), $tmp);
            $out['filled'] = $tmp['filled'];
            foreach ($tmp['kept'] as $k) {
                $p['notes'][] = "Kept {$k['field']}: {$k['existing']} (file: {$k['file']}).";
            }
            $p['notes'] = array_values(array_unique($p['notes']));
        }
        if ($p['action'] === 'claim') {
            $out['unit_action'] = 'skipped';
            $out['notes'][] = 'Waiting for the claim on this building to be approved; upload the same file again afterwards';
            $this->report['counts']['units_skipped']++;

            return $out;
        }

        $this->unit($property, $p, $d, $out);

        return $out;
    }

    /** Find, create or flag the building for this row (once per building in the file). */
    private function &building(array $d): array
    {
        $gk = $d['scharge_property_id'] && $d['scharge_property_id'] !== 'new'
            ? 'id:'.$d['scharge_property_id']
            : 'k:'.PropertyRegistry::nameKey($d['property_name']).'|'.PropertyRegistry::locationKey($d['location']).($d['scharge_property_id'] === 'new' ? '|new' : '');
        if (isset($this->props[$gk])) {
            $p = &$this->props[$gk];
            if (($d['lr_number'] && $p['lr_number'] && Str::lower($d['lr_number']) !== Str::lower($p['lr_number']))) {
                $p['notes'][] = "Rows give different LR numbers ({$p['lr_number']} / {$d['lr_number']}): are these two buildings?";
                $p['notes'] = array_values(array_unique($p['notes']));
            }

            return $p;
        }

        $p = ['name' => $d['property_name'], 'location' => $d['location'], 'lr_number' => $d['lr_number'], 'gps' => $d['gps'],
            'action' => null, 'id' => null, 'score' => null, 'writable' => false, 'managed_by' => null, 'candidates' => [], 'crm' => [], 'notes' => [], 'error' => null, 'model' => null];
        $this->props[$gk] = &$p;

        if ($d['scharge_property_id'] && $d['scharge_property_id'] !== 'new') {
            $found = ctype_digit($d['scharge_property_id']) ? Property::find((int) $d['scharge_property_id']) : null;
            if (! $found) {
                $p['action'] = 'error';
                $p['error'] = "scharge_property_id {$d['scharge_property_id']} is not a building on scharge";
                $this->report['counts']['errors']++;

                return $p;
            }
            $r = &$this->resolved($gk, $p, $found, 100);

            return $r;
        }

        $pin = PropertyPin::parse($d['gps']);
        $matches = $d['scharge_property_id'] === 'new' ? collect() : app(PropertyRegistry::class)->matches($d['property_name'], $d['location'], null, $pin);
        $best = $matches->first();
        if ($best && $best['score'] >= 95) {
            if (isset($this->byId[$best['property']->id])) {
                $r = &$this->resolved($gk, $p, $best['property'], $best['score']);

                return $r;
            }
            $this->resolved($gk, $p, $best['property'], $best['score']);
            if ($matches->count() > 1 && $matches[1]['score'] >= 95) {
                $p['notes'][] = 'More than one building matches this name; used the closest. Put scharge_property_id on the rows if it is another one.';
                $p['candidates'] = $matches->take(5)->map(fn ($m) => PropertyRegistry::present($m['property'], $m['score']))->values()->all();
            }

            return $p;
        }
        // The same name somewhere else: maybe another building, maybe the location written differently. Ask.
        if (! $best && $d['scharge_property_id'] !== 'new' && $d['location']) {
            $best = app(PropertyRegistry::class)->matches($d['property_name'], null, null, $pin)->first(fn ($m) => $m['score'] >= 95);
            $matches = $best ? collect([$best]) : $matches;
        }
        if ($best) {
            $p['action'] = 'review';
            $p['candidates'] = $matches->take(5)->map(fn ($m) => PropertyRegistry::present($m['property'], $m['score']))->values()->all();
            $p['notes'][] = 'A similar building is already on scharge. Put its id in scharge_property_id if it is the same one, or "new" if it is a different building.';
            $this->report['counts']['properties_review']++;

            return $p;
        }

        $manager = $this->role === 'manager';
        $property = Property::forceCreate([
            'name' => $d['property_name'], 'location' => $d['location'],
            'water_rate' => $d['property_water_rate'] ?? 0, 'service_charge' => $d['property_service_charge'] ?? 0,
            'garbage_charge' => $d['property_garbage_charge'] ?? 0, 'security_charge' => $d['property_security_charge'] ?? 0,
            'company_id' => $manager ? $this->company->id : null, 'claim_status' => $manager ? 'managed' : 'unclaimed',
            'created_by_company_id' => $this->company->id,
        ] + PropertyPin::attributes($pin, 'csv'));
        PropertyRegistry::index($property);
        $p['action'] = 'created';
        $p['id'] = $property->id;
        $p['model'] = $property;
        $p['writable'] = true;
        $p['managed_by'] = $manager ? $this->company->name : null;
        $this->units[$property->id] = [];
        $this->byId[$property->id] = $gk;
        $this->report['counts']['properties_created']++;
        $p['crm'] = $this->crmMatches($d);
        if ($p['crm']) {
            $p['notes'][] = 'Also a CRM prospect: link it to this building instead of keeping a second record.';
        }

        return $p;
    }

    /** The building matched an existing property; a second spelling of the same building in the file shares its entry. */
    private function &resolved(string $gk, array &$p, Property $property, int $score): array
    {
        if (isset($this->byId[$property->id])) {
            $first = &$this->props[$this->byId[$property->id]];
            if ($p['location'] && $p['location'] !== $first['location']) {
                $first['notes'][] = "Also written as \"{$p['name']}, {$p['location']}\" in the file.";
                $first['notes'] = array_values(array_unique($first['notes']));
            }
            unset($this->props[$gk]);
            $this->props[$gk] = &$first;

            return $first;
        }
        $this->byId[$property->id] = $gk;
        $this->matched($p, $property, $score);

        return $p;
    }

    private function matched(array &$p, Property $property, int $score): void
    {
        $mine = (int) $property->company_id === (int) $this->company->id && ($property->claim_status ?? 'managed') === 'managed';
        $myUnclaimed = ! $property->company_id && (int) ($property->created_by_company_id ?? 0) === (int) $this->company->id;
        $p['id'] = $property->id;
        $p['model'] = $property;
        $p['score'] = $score;
        $p['writable'] = $mine || $myUnclaimed;
        $p['managed_by'] = $property->company_id ? Company::withoutGlobalScopes()->whereKey($property->company_id)->value('name') : null;
        $p['action'] = 'matched';
        $this->report['counts']['properties_matched']++;

        if (! $p['writable'] && $this->role === 'manager') {
            $pending = PropertyClaim::where('property_id', $property->id)->where('company_id', $this->company->id)->where('status', 'pending')->exists();
            if (! $pending) {
                PropertyClaim::create(['property_id' => $property->id, 'company_id' => $this->company->id, 'status' => 'pending',
                    'requested_by' => $this->by?->id, 'message' => 'From CSV onboarding']);
                $this->report['counts']['claims']++;
            }
            $p['action'] = 'claim';
            $p['notes'][] = ($p['managed_by'] ? "Managed on scharge by {$p['managed_by']}" : 'On scharge without a manager yet')
                .($pending ? '; your claim is already waiting for approval.' : ($this->report['commit'] ? '; a claim to manage it was sent for approval.' : '; a claim to manage it will be sent for approval.'));
        } elseif (! $p['writable']) {
            $p['notes'][] = ($p['managed_by'] ? "Managed by {$p['managed_by']}" : 'Not managed on scharge yet').": your units are linked to you as {$this->role}; building details stay with its manager.";
        }
    }

    /** CRM prospects with the same name (and near the pin when both have one): onboarding should link them, not copy them. */
    private function crmMatches(array $d): array
    {
        $key = Normalizer::companyKey($d['property_name']);
        if ($key === '') {
            return [];
        }
        [$lat, $lng] = PropertyPin::parse($d['gps']) ?? [null, null];

        return Company::withoutGlobalScope('customers')->where('lifecycle', 'prospect')->where('name_key', $key)->limit(5)
            ->get(['id', 'name', 'kind', 'crm_profile'])
            ->map(function (Company $c) use ($lat, $lng) {
                $cl = $c->crm_profile['lat'] ?? null;
                $cg = $c->crm_profile['lng'] ?? null;
                $m = ($lat !== null && $cl !== null) ? (int) round(PropertyPin::metres([$lat, $lng], [(float) $cl, (float) $cg])) : null;

                return ['id' => $c->id, 'name' => $c->name, 'kind' => $c->kind, 'metres' => $m];
            })
            ->reject(fn ($c) => $c['metres'] !== null && $c['metres'] > 2000)
            ->values()->all();
    }

    private function unit(Property $property, array &$p, array $d, array &$out): void
    {
        $units = $this->units[$property->id] ??= Unit::withoutGlobalScopes()->where('property_id', $property->id)->get()
            ->keyBy(fn (Unit $u) => self::unitKey($u->unit_number))->all();
        $key = self::unitKey($d['unit_number']);

        if (isset($this->seen[$property->id][$key])) {
            $out['unit_action'] = 'skipped';
            $out['notes'][] = "Unit {$d['unit_number']} is already on line {$this->seen[$property->id][$key]} of this file";
            $this->report['counts']['units_skipped']++;

            return;
        }
        $this->seen[$property->id][$key] = $out['line'];

        $unit = $units[$key] ?? null;
        if ($unit) {
            $canWrite = $p['writable'] || (int) $unit->company_id === (int) $this->company->id
                || UnitManagement::where('unit_id', $unit->id)->where('company_id', $this->company->id)->where('status', 'active')->exists();
            if (! $canWrite) {
                // Same rule as HostUnits: nobody attaches themselves to a unit another company runs. The manager or
                // support links them (so a stranger cannot take over a unit's bookings or bills from a spreadsheet).
                $out['unit_action'] = 'skipped';
                $out['notes'][] = 'Unit is already on scharge'.($p['managed_by'] ? " under {$p['managed_by']}" : '').'. If it is yours, ask them or support to link you; nothing was changed';
                $this->report['counts']['units_skipped']++;

                return;
            }
            $linked = $this->link($unit, $this->role, 'CSV onboarding');
            $this->fill($unit, $this->unitValues($d, $property, false), $out);
            $out['unit_action'] = 'matched';
            $out['notes'] = array_merge($out['notes'], $linked ? ['Linked to you as '.$this->role] : []);
            $this->report['counts'][$linked ? 'units_linked' : 'units_matched']++;
        } else {
            $managedByOther = $property->company_id && (int) $property->company_id !== (int) $this->company->id;
            $unit = (new Unit)->forceFill($this->unitValues($d, $property, true) + [
                'company_id' => $property->company_id && ($property->claim_status ?? 'managed') === 'managed' ? $property->company_id : $this->company->id,
                'property_id' => $property->id, 'unit_number' => $d['unit_number'],
            ]);
            $unit->save();
            $this->units[$property->id][$key] = $unit;
            $this->link($unit, $this->role, 'CSV onboarding');
            if ($managedByOther) {
                $this->link($unit, 'manager', 'Unit added to a managed building by its '.$this->role, (int) $property->company_id);
            }
            $out['unit_action'] = 'created';
            $this->report['counts']['units_created']++;
            $canWrite = true;
        }

        $this->meter($unit, $property, 'water', $d['water_meter_no'], $d['water_meter_reading'], $canWrite, $out);
        $this->meter($unit, $property, 'electricity', $d['power_meter_no'], $d['power_meter_reading'], $canWrite, $out);
        $this->owner($unit, $d, $canWrite, $out);
        $this->tenant($unit, $property, $d, $canWrite, $out);
    }

    /**
     * The tenant living in the unit now: a tenant account (signs in with the phone; default password as on the Tenants
     * page), an active tenancy from move_in_date, and what they owe today as one unpaid "balance brought forward" bill.
     * Nothing is sent to the tenant. A unit that already has a tenant on scharge keeps them: the row is only reported,
     * so uploading the same file twice never adds a second tenant or a second balance.
     */
    private function tenant(Unit $unit, Property $property, array $d, bool $canWrite, array &$out): void
    {
        if (! $d['tenant_name'] && ! $d['tenant_phone']) {
            if ($d['balance']) {
                $out['notes'][] = 'Balance '.number_format($d['balance']).' not added: the row has no tenant';
            }

            return;
        }
        $skip = function (string $why) use (&$out) {
            $out['notes'][] = $why;
            $this->report['counts']['tenants_skipped']++;
        };
        if (! $d['tenant_name'] || ! $d['tenant_phone']) {
            $skip('Tenant not added: needs both tenant_name and tenant_phone');

            return;
        }
        if (! $canWrite) {
            $skip('Tenant not added: the unit\'s manager adds tenants');

            return;
        }

        $current = Tenancy::withoutGlobalScopes()->where('unit_id', $unit->id)->where('status', 'active')->with(['tenant' => fn ($q) => $q->withoutGlobalScopes()->with('user')])->first();
        if ($current) {
            $phone = $current->tenant?->user?->phone;
            if ($phone && substr(preg_replace('/\D+/', '', $phone), -9) === substr($d['tenant_phone'], -9)) {
                $out['notes'][] = 'Tenant '.$current->tenant->user->name.' is already on this unit; balance not added again';
                $this->report['counts']['tenants_matched']++;
            } else {
                $skip('Unit already has a tenant on scharge ('.($current->tenant?->user?->name ?? 'tenancy #'.$current->id).'); nothing changed. End that tenancy first if '.$d['tenant_name'].' has replaced them');
            }

            return;
        }

        $companyId = (int) ($unit->company_id ?: $this->company->id);
        $user = $this->tenantUser($d, $companyId);
        $tenant = Tenant::withoutGlobalScopes()->where('user_id', $user->id)->first();
        if (! $tenant) {
            $tenant = (new Tenant)->forceFill(['user_id' => $user->id, 'company_id' => $companyId, 'id_number' => $d['tenant_id_number'], 'notes' => 'Added by onboarding upload']);
            $tenant->save();
        }
        $tenancy = Tenancy::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'company_id' => $companyId,
            'property_id' => $property->id, 'move_in_date' => $d['move_in_date'] ?? now()->toDateString(), 'status' => 'active']);
        if ($unit->status !== 'occupied') {
            $unit->forceFill(['status' => 'occupied'])->save();
        }
        $out['filled'][] = 'tenant';
        $out['notes'][] = 'Tenant '.$user->name.' added'.($d['move_in_date'] ? ', moved in '.$d['move_in_date'] : '');
        $this->report['counts']['tenants_created']++;

        if ($d['balance']) {
            // A "move_in" bill: reminders chase it like rent, the rent run still bills this month, and no late fee is added.
            $invoice = Invoice::withoutGlobalScopes()->create(['tenancy_id' => $tenancy->id, 'invoice_type' => 'move_in',
                'billing_month' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'total_amount' => $d['balance'], 'total_paid' => 0, 'status' => 'unpaid']);
            $invoice->forceFill(['company_id' => $companyId])->saveQuietly();
            $invoice->items()->create(['item_type' => 'other', 'description' => 'Balance brought forward (from previous records)', 'amount' => $d['balance']]
                + (\Illuminate\Support\Facades\Schema::hasColumn('invoice_items', 'company_id') ? ['company_id' => $companyId] : []));
            $out['filled'][] = 'balance';
            $out['notes'][] = 'Balance KES '.number_format($d['balance'], 2).' carried over as an unpaid bill';
            $this->report['counts']['balances_added']++;
            $this->report['counts']['balances_total'] += $d['balance'];
        }
    }

    /** The tenant's account: one this company already has for that phone, or a new one (same as the Tenants page). */
    private function tenantUser(array $d, int $companyId): User
    {
        $last9 = substr($d['tenant_phone'], -9);
        $existing = User::withoutGlobalScopes()->whereNotNull('phone')->where('phone', 'like', '%'.substr($last9, -6))
            ->whereIn('id', Tenant::withoutGlobalScopes()->where('company_id', $companyId)->select('user_id'))->get()
            ->first(fn (User $u) => substr(preg_replace('/\D+/', '', (string) $u->phone), -9) === $last9);
        if ($existing) {
            return $existing;
        }

        $email = $d['tenant_email'] && ! User::withoutGlobalScopes()->where('email', $d['tenant_email'])->exists() ? $d['tenant_email'] : null;
        if (! $email) {
            $base = Str::slug($d['tenant_name'], '.').'.'.substr($last9, -4);
            $email = $base.'@tenant.com';
            for ($i = 1; User::withoutGlobalScopes()->where('email', $email)->exists(); $i++) {
                $email = $base.$i.'@tenant.com';
            }
        }
        $role = \Spatie\Permission\Models\Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        $user = (new User)->forceFill(['name' => $d['tenant_name'], 'email' => $email, 'phone' => $d['tenant_phone'],
            'password' => \Illuminate\Support\Facades\Hash::make('00000000'), 'email_verified_at' => now()]
            + (\Illuminate\Support\Facades\Schema::hasColumn('users', 'role_id') ? ['role_id' => $role?->id] : []));
        $user->save();
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** Link a company to a unit in a role, once. Returns true if a new link was made. */
    private function link(Unit $unit, string $role, string $note, ?int $companyId = null): bool
    {
        $companyId ??= $this->company->id;
        if (UnitManagement::where('unit_id', $unit->id)->where('company_id', $companyId)->where('role', $role)->where('status', 'active')->exists()) {
            return false;
        }
        UnitManagement::create(['unit_id' => $unit->id, 'company_id' => $companyId, 'role' => $role, 'status' => 'active',
            'starts_at' => now(), 'created_by' => $this->by?->id, 'notes' => $note]);

        return true;
    }

    private function meter(Unit $unit, Property $property, string $utility, ?string $serial, ?float $reading, bool $canWrite, array &$out): void
    {
        if (! $serial) {
            return;
        }
        $label = $utility === 'water' ? 'Water meter' : 'Power meter';
        $existing = Meter::where('utility', $utility)->where('serial_number', $serial)->first();
        if ($existing && (int) $existing->unit_id === (int) $unit->id) {
            $this->report['counts']['meters_matched']++;

            return;
        }
        if ($existing) {
            $out['notes'][] = "{$label} {$serial} is already on another unit (#{$existing->unit_id}); not moved";
            $this->report['counts']['meters_skipped']++;

            return;
        }
        $other = Meter::where('unit_id', $unit->id)->where('utility', $utility)->where('status', 'active')->first();
        if ($other) {
            $out['kept'][] = ['field' => strtolower($label), 'existing' => $other->serial_number, 'file' => $serial];
            $this->report['counts']['meters_skipped']++;

            return;
        }
        if (! $canWrite) {
            $out['notes'][] = "{$label} {$serial} not added: the unit's manager adds meters";
            $this->report['counts']['meters_skipped']++;

            return;
        }
        Meter::create(['company_id' => $unit->company_id ?: ($property->company_id ?: $this->company->id), 'property_id' => $property->id, 'unit_id' => $unit->id,
            'utility' => $utility, 'kind' => 'manual', 'billing' => 'postpaid', 'serial_number' => $serial, 'unit_of_measure' => Meter::UTILITIES[$utility],
            'multiplier' => 1, 'initial_reading' => $reading ?? 0, 'last_reading' => $reading, 'last_read_at' => $reading !== null ? now() : null,
            'status' => 'active', 'notes' => 'Added by CSV onboarding']);
        $out['filled'][] = strtolower($label);
        $this->report['counts']['meters_created']++;
    }

    /** An owner already on scharge (by phone or email) is linked to the unit; anyone else is only reported. */
    private function owner(Unit $unit, array $d, bool $canWrite, array &$out): void
    {
        if (! $d['owner_name'] && ! $d['owner_phone'] && ! $d['owner_email']) {
            return;
        }
        $who = trim(implode(', ', array_filter([$d['owner_name'], $d['owner_phone'], $d['owner_email']])));
        $owner = ($d['owner_phone'] || $d['owner_email']) ? Company::withoutGlobalScopes()
            ->where(fn ($q) => $q->when($d['owner_phone'], fn ($q) => $q->orWhere('phone', $d['owner_phone']))
                ->when($d['owner_email'], fn ($q) => $q->orWhere('email', $d['owner_email'])))
            ->first(['id', 'name']) : null;
        if ($owner && (int) $owner->id !== (int) $this->company->id && $canWrite) {
            if ($this->link($unit, 'owner', 'Owner from CSV onboarding', (int) $owner->id)) {
                $out['filled'][] = 'owner';
            }
            $out['notes'][] = "Owner: {$owner->name} (on scharge)";
        } elseif (! $owner) {
            $out['notes'][] = "Owner {$who} is not on scharge yet; noted here only";
        }
    }

    /** Fill only empty or zero values; report where the file and scharge differ. */
    private function fill($model, array $values, array &$out, string $prefix = ''): void
    {
        $changes = [];
        foreach ($values as $field => $new) {
            if ($new === null || $new === '') {
                continue;
            }
            $old = $model->getAttribute($field);
            $blank = $old === null || $old === '' || (is_numeric($old) && (float) $old == 0.0);
            if ($blank) {
                if (! (is_numeric($new) && (float) $new == 0.0)) {
                    $changes[$field] = $new;
                }
            } elseif (is_numeric($old) && is_numeric($new) ? (float) $old != (float) $new : self::same((string) $old) !== self::same((string) $new)) {
                $key = $prefix.$field;
                if (! in_array($key, array_column($out['kept'], 'field'), true)) {
                    $out['kept'][] = ['field' => $key, 'existing' => (string) $old, 'file' => (string) $new];
                }
            }
        }
        if ($changes) {
            $model->forceFill($changes)->save();
            if ($model instanceof Property) {
                PropertyRegistry::index($model);
            }
            foreach (array_keys($changes) as $f) {
                $out['filled'][] = $prefix.$f;
            }
            $this->report['counts']['fields_filled'] += count($changes);
        }
    }

    /** The building's pin: set when it has none; a pin more than 100 m from the saved one is reported, not moved. */
    private function fillPin(Property $property, ?array $pin, array &$out): void
    {
        if (! $pin || ! PropertyPin::enabled()) {
            return;
        }
        $saved = PropertyPin::of($property);
        if (! $saved) {
            $property->forceFill(PropertyPin::attributes($pin, 'csv'))->save();
            $out['filled'][] = 'property gps';
            $this->report['counts']['fields_filled']++;
        } elseif (($m = PropertyPin::metres($saved, $pin)) > 100) {
            $out['kept'][] = ['field' => 'gps pin', 'existing' => implode(', ', $saved), 'file' => implode(', ', $pin).' ('.round($m).' m away)'];
        }
    }

    private function propertyValues(array $d): array
    {
        return ['location' => $d['location'], 'water_rate' => $d['property_water_rate'], 'service_charge' => $d['property_service_charge'],
            'garbage_charge' => $d['property_garbage_charge'], 'security_charge' => $d['property_security_charge']];
    }

    private function unitValues(array $d, Property $property, bool $new): array
    {
        $type = $d['unit_type'] ?? match (true) {
            $d['bedrooms'] === null => null,
            $d['bedrooms'] === 0 => 'Studio',
            default => $d['bedrooms'].' Bedroom',
        };
        $v = [
            'unit_type' => $type, 'rent_amount' => $d['rent'], 'security_deposit_amount' => $d['deposit'],
            'service_charge' => $d['service_charge'], 'garbage_charge' => $d['garbage_charge'], 'security_charge' => $d['security_charge'],
            'water_billing_type' => $d['water_billing'], 'water_charge' => $d['water_billing'] === 'flat' ? $d['water_flat_charge'] : null,
            'stay_type' => $d['stay_type'], 'furnishing_status' => $d['furnishing'], 'property_category' => $d['category'],
            'bnb_nightly_rate' => $d['nightly_rate'],
        ];
        if (! $new) {
            return $v;   // status and the like are never changed on an existing unit
        }

        // A new unit: the building's charges where the row has none, and the same defaults as the Add Unit form.
        return array_merge($v, [
            'unit_type' => $type ?? 'Unit',
            'rent_amount' => $d['rent'] ?? 0,
            'service_charge' => $d['service_charge'] ?? (float) ($d['property_service_charge'] ?? $property->service_charge ?? 0),
            'garbage_charge' => $d['garbage_charge'] ?? (float) ($d['property_garbage_charge'] ?? $property->garbage_charge ?? 0),
            'security_charge' => $d['security_charge'] ?? (float) ($d['property_security_charge'] ?? $property->security_charge ?? 0),
            'water_billing_type' => $d['water_billing'] ?? 'consumption',
            'water_charge' => $d['water_billing'] === 'flat' ? ($d['water_flat_charge'] ?? 0) : 0,
            'status' => $d['status'] ?? 'vacant',
            'stay_type' => $d['stay_type'] ?? ($this->role === 'bnb_host' ? 'bnb' : 'long_stay'),
            'furnishing_status' => $d['furnishing'] ?? 'unfurnished',
            'property_category' => $d['category'] ?? 'residential',
            'ownership_type' => 'tenant',
            'is_active' => 1,
            'bnb_cleaning_fee' => 0,
            'previous_water_reading' => $d['water_meter_no'] ? ($d['water_meter_reading'] ?? 0) : 0,
            'current_water_reading' => $d['water_meter_no'] ? ($d['water_meter_reading'] ?? 0) : 0,
        ]);
    }

    // ---------------------------------------------------------------- cleaning one row

    /** Plain, typed values for one row. Problems go to $errors (the row is then skipped). */
    private function clean(array $r, array &$errors): array
    {
        $t = fn ($k, $max = 255) => Normalizer::blank($r[$k] ?? null) ? null : Normalizer::text($r[$k], $max);
        $money = function ($k) use ($r, &$errors) {
            $v = trim((string) ($r[$k] ?? ''));
            if ($v === '' || Normalizer::blank($v) || $v === '-') {
                return null;
            }
            $n = preg_replace('/(?i)kes|ksh|kshs|\/=|[\s,]/', '', $v);
            if (! is_numeric($n) || (float) $n < 0) {
                $errors[] = "{$k} \"{$v}\" is not an amount";

                return null;
            }

            return round((float) $n, 2);
        };
        $pick = function ($k, array $options) use ($r, &$errors) {
            $v = Str::of((string) ($r[$k] ?? ''))->lower()->trim()->replaceMatches('/[\s-]+/', '_')->toString();
            if ($v === '') {
                return null;
            }
            foreach ($options as $value => $words) {
                if ($v === $value || in_array($v, $words, true)) {
                    return $value;
                }
            }
            $errors[] = "{$k} \"{$r[$k]}\" should be one of: ".implode(', ', array_keys($options));

            return null;
        };

        $unitNumber = $t('unit_number', 60);
        $unitNumber = $unitNumber ? preg_replace('/^(unit|house|hse|apt|apartment|no\.?|#)\s*[:.]?\s*(?=\S)/i', '', $unitNumber) : null;
        $bedrooms = trim((string) ($r['bedrooms'] ?? ''));
        if ($bedrooms !== '' && Str::lower($bedrooms) === 'studio') {
            $bedrooms = '0';
        }
        if ($bedrooms !== '' && (! ctype_digit($bedrooms) || (int) $bedrooms > 20)) {
            $errors[] = "bedrooms \"{$bedrooms}\" should be a number (0 for studio)";
            $bedrooms = '';
        }
        $reading = function ($k) use ($money) {
            return $money($k);
        };
        $serial = fn ($k) => ($s = $t($k, 64)) ? strtoupper(preg_replace('/\s+/', '', $s)) : null;
        $pid = Str::lower(trim((string) ($r['scharge_property_id'] ?? '')));
        if ($pid !== '' && $pid !== 'new' && ! ctype_digit($pid)) {
            $errors[] = 'scharge_property_id should be a building id or "new"';
            $pid = '';
        }
        $gps = $t('gps', 60);
        if ($gps && ! PropertyPin::parse($gps)) {
            $errors[] = "gps \"{$gps}\" should be \"lat, lng\" (or a Google Maps link) of a place in Kenya";
            $gps = null;
        }
        $email = trim((string) ($r['owner_email'] ?? ''));
        $ownerEmail = Normalizer::email($email);
        if ($email !== '' && ! $ownerEmail && ! Normalizer::blank($email)) {
            $errors[] = "owner_email \"{$email}\" is not an email address";
        }
        $tPhoneRaw = trim((string) ($r['tenant_phone'] ?? ''));
        $tenantPhone = Normalizer::phone($tPhoneRaw);
        if ($tPhoneRaw !== '' && ! $tenantPhone && ! Normalizer::blank($tPhoneRaw)) {
            $errors[] = "tenant_phone \"{$tPhoneRaw}\" is not a phone number";
        }
        $tEmailRaw = trim((string) ($r['tenant_email'] ?? ''));
        $tenantEmail = Normalizer::email($tEmailRaw);
        if ($tEmailRaw !== '' && ! $tenantEmail && ! Normalizer::blank($tEmailRaw)) {
            $errors[] = "tenant_email \"{$tEmailRaw}\" is not an email address";
        }
        $moveIn = null;
        if (($m = trim((string) ($r['move_in_date'] ?? ''))) !== '' && ! Normalizer::blank($m)) {
            try {
                // 01/03/2025 is read the Kenyan way (day first)
                $moveIn = preg_match('#^\d{1,2}[/.-]\d{1,2}[/.-]\d{4}$#', $m)
                    ? \Carbon\Carbon::createFromFormat('d/m/Y', preg_replace('#[.-]#', '/', $m))->toDateString()
                    : \Carbon\Carbon::parse($m)->toDateString();
                if ($moveIn > now()->addYear()->toDateString() || $moveIn < '1980-01-01') {
                    throw new \InvalidArgumentException;
                }
            } catch (\Throwable $e) {
                $errors[] = "move_in_date \"{$m}\" is not a date (write it like 2025-03-01)";
                $moveIn = null;
            }
        }
        $phone = trim((string) ($r['owner_phone'] ?? ''));
        $ownerPhone = Normalizer::phone($phone);
        if ($phone !== '' && ! $ownerPhone && ! Normalizer::blank($phone)) {
            $errors[] = "owner_phone \"{$phone}\" is not a phone number";
        }

        return [
            'property_name' => Normalizer::companyName($t('property_name')),
            'location' => Normalizer::companyName($t('location')),
            'lr_number' => $t('lr_number', 60),
            'gps' => $gps,
            'scharge_property_id' => $pid ?: null,
            'property_water_rate' => $money('property_water_rate'),
            'property_service_charge' => $money('property_service_charge'),
            'property_garbage_charge' => $money('property_garbage_charge'),
            'property_security_charge' => $money('property_security_charge'),
            'unit_number' => $unitNumber ?: null,
            'unit_type' => Normalizer::companyName($t('unit_type', 100)),
            'bedrooms' => $bedrooms === '' ? null : (int) $bedrooms,
            'rent' => $money('rent'),
            'deposit' => $money('deposit'),
            'service_charge' => $money('service_charge'),
            'garbage_charge' => $money('garbage_charge'),
            'security_charge' => $money('security_charge'),
            'water_billing' => $pick('water_billing', ['consumption' => ['meter', 'metered', 'by_meter', 'per_unit', 'usage'], 'flat' => ['fixed', 'flat_rate']]),
            'water_flat_charge' => $money('water_flat_charge'),
            'water_meter_no' => $serial('water_meter_no'),
            'water_meter_reading' => $reading('water_meter_reading'),
            'power_meter_no' => $serial('power_meter_no'),
            'power_meter_reading' => $reading('power_meter_reading'),
            'status' => $pick('status', ['vacant' => ['empty', 'available', 'free'], 'occupied' => ['let', 'taken', 'rented', 'tenanted']]),
            'stay_type' => $pick('stay_type', ['long_stay' => ['long', 'long_term', 'monthly'], 'short_stay' => ['short', 'short_term'],
                'bnb' => ['airbnb', 'b&b', 'bnb_unit', 'holiday'], 'mixed' => ['both']]),
            'furnishing' => $pick('furnishing', ['furnished' => ['yes', 'fully_furnished'], 'unfurnished' => ['no', 'bare'], 'semi_furnished' => ['semi', 'partly_furnished', 'part_furnished']]),
            'category' => $pick('category', ['residential' => ['house', 'home', 'apartment'], 'commercial' => [], 'office' => ['offices'],
                'retail' => ['shop', 'stall'], 'industrial' => ['warehouse', 'godown'], 'showhouse' => ['show_house']]),
            'nightly_rate' => $money('nightly_rate'),
            'owner_name' => Normalizer::anyName($t('owner_name')),
            'owner_phone' => $ownerPhone,
            'owner_email' => $ownerEmail,
            'tenant_name' => Normalizer::anyName($t('tenant_name')),
            'tenant_phone' => $tenantPhone,
            'tenant_email' => $tenantEmail,
            'tenant_id_number' => $t('tenant_id_number', 50),
            'move_in_date' => $moveIn,
            'balance' => $money('balance') ?: null,
        ];
    }

    /** "Two Bedroom" = "2 bedroom" = "2BR". */
    private static function same(string $v): string
    {
        $v = Str::lower($v);
        $v = str_replace(['one', 'two', 'three', 'four', 'five', 'six'], ['1', '2', '3', '4', '5', '6'], $v);
        $v = preg_replace('/\b(bedrooms?|bdrm|bed|br)\b/', 'br', $v);

        return preg_replace('/[^a-z0-9]+/', '', $v);
    }

    /** "A-1", "a 1", "Unit A1" are the same unit. */
    public static function unitKey(?string $number): string
    {
        $n = Str::lower(Str::ascii(trim((string) $number)));
        $n = preg_replace('/^(unit|house|hse|apt|apartment|no\.?|#)\s*[:.]?\s*(?=\S)/', '', $n);

        return preg_replace('/[^a-z0-9]+/', '', $n);
    }
}
