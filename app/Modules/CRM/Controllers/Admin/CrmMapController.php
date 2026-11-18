<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Services\DemoRequestService;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\KenyaGeo;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kenya map of the CRM: every company with a location (crm:geocode), grouped by county and
 * sub-county (constituency). The page loads one compact JSON payload and filters in the browser.
 */
class CrmMapController extends Controller
{
    public function index(Request $request): View
    {
        return view('crm.map', [
            'owners' => CrmCompanyController::staff(),
        ]);
    }

    /**
     * Rows: [id, name, lat, lng, segment#, stage#, precision#, county#, sub_county#, owner_id, verified, sub_county_id]
     * where # indexes into the lookup lists sent alongside (keeps ~14k rows small). County and sub-county names
     * come from the stored ids (county_id, sub_county_id = the ward's subcounties row), so they always agree;
     * 'county_ids' maps each county name back to its id and 'wards' each sub_county_id to its ward name.
     */
    public function data(Request $request, KenyaGeo $geo): JsonResponse
    {
        $p = fn (string $key) => "JSON_UNQUOTE(JSON_EXTRACT(companies.crm_profile, '$.{$key}'))";
        $rows = CrmAccess::companies(Company::crm(), $request->user())
            ->selectRaw("companies.id, companies.name, companies.crm_stage, companies.owner_id,
                {$p('lat')} AS lat, {$p('lng')} AS lng, {$p('segment')} AS segment, {$p('geo_precision')} AS prec,
                {$p('county')} AS county, {$p('sub_county')} AS sub_county, {$p('county_id')} AS county_id,
                {$p('sub_county_id')} AS sub_county_id, {$p('needs_verification')} AS nv")
            ->toBase()
            ->get();

        $lists = ['segment' => [], 'stage' => [], 'precision' => [], 'county' => [], 'sub_county' => []];
        $index = function (string $list, ?string $value) use (&$lists): int {
            $value = ($value === null || $value === 'null' || $value === '') ? '' : $value;
            $i = array_search($value, $lists[$list], true);
            if ($i === false) {
                $lists[$list][] = $value;
                $i = count($lists[$list]) - 1;
            }

            return $i;
        };

        $countyIds = [];
        $wards = [];
        // Names from the ids (stored names only for rows crm:geocode has not re-run on yet).
        $names = function ($countyId, $subId, $county, $sub) use ($geo, &$countyIds, &$wards): array {
            $row = $geo->subCounty(is_numeric($subId) ? (int) $subId : null);
            $countyId = $row ? $row[2] : (is_numeric($countyId) ? (int) $countyId : null);
            $county = $geo->countyName($countyId) ?? ($county === 'null' ? null : $county);
            if ($row) {
                $wards[(int) $subId] = $row[1];
            }
            if ($county && $countyId) {
                $countyIds[$county] = $countyId;
            }

            return [$county, $row ? $row[0] : ($sub === 'null' ? null : $sub), $row ? (int) $subId : 0];
        };

        $out = [];
        $unlocated = 0;
        foreach ($rows as $r) {
            [$county, $sub, $subId] = $names($r->county_id, $r->sub_county_id, $r->county, $r->sub_county);
            $hasPin = is_numeric($r->lat) && is_numeric($r->lng);
            if (! $hasPin) {
                $unlocated++;
            }
            $out[] = [
                (int) $r->id,
                $r->name,
                $hasPin ? round((float) $r->lat, 5) : null,
                $hasPin ? round((float) $r->lng, 5) : null,
                $index('segment', $r->segment),
                $index('stage', $r->crm_stage),
                $index('precision', $hasPin ? $r->prec : ''),
                $index('county', $county),
                $index('sub_county', $sub),
                (int) $r->owner_id,
                in_array($r->nv, ['true', '1', 1, true], true) ? 0 : 1,
                $subId,
            ];
        }

        // Before 'lists' is copied below: properties can add county / sub-county names.
        $properties = $this->properties($request, $geo, $index, $names);

        return response()->json([
            'lists' => $lists,
            'county_ids' => $countyIds,
            'wards' => $wards,
            'stages' => Company::CRM_STAGES,
            'rows' => $out,
            'unlocated' => $unlocated,
            'properties' => $properties,
            'property_statuses' => DemoRequestService::PROPERTY_STATUSES,
        ])->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Properties prospects listed on the demo form, as their own layer:
     * [company_id, property_id, name, lat, lng, status, precision, county#, sub_county#, units, location, maps_url, owner_id, sub_county_id]
     * Ones crm:geocode has not reached yet are placed on the fly (same rules, not saved).
     */
    private function properties(Request $request, KenyaGeo $geo, callable $index, callable $names): array
    {
        $companies = CrmAccess::companies(Company::crm(), $request->user())
            ->whereNotNull('crm_profile->prospect_properties')
            ->get(['id', 'owner_id', 'crm_profile']);

        $todo = $companies->flatMap(fn ($c) => collect($c->crm_profile['prospect_properties'] ?? [])->filter(fn ($p) => is_array($p) && empty($p['geo_at'])));
        $pins = KenyaGeo::exactPins($todo->map(fn ($p) => Normalizer::companyKey($p['name'] ?? ''))->filter()->unique()->values()->all());

        $out = [];
        foreach ($companies as $c) {
            foreach ($c->crm_profile['prospect_properties'] ?? [] as $p) {
                if (! is_array($p)) {
                    continue;
                }
                if (empty($p['geo_at'])) {
                    $p = array_merge($p, $geo->property($p, $c->crm_profile, $pins));
                }
                $hasPin = is_numeric($p['lat'] ?? null);
                [$county, $sub, $subId] = $names($p['county_id'] ?? null, $p['sub_county_id'] ?? null, $p['county'] ?? null, $p['sub_county'] ?? null);
                $out[] = [
                    (int) $c->id, $p['id'] ?? null, $p['name'] ?? 'Property',
                    $hasPin ? (float) $p['lat'] : null, $hasPin ? (float) $p['lng'] : null,
                    $p['status'] ?? 'open', $hasPin ? ($p['geo_precision'] ?? '') : '',
                    $index('county', $county), $index('sub_county', $sub),
                    (int) ($p['units'] ?? 0), $p['location'] ?? null, KenyaGeo::googleMapsUrl($p, $c->crm_profile['city'] ?? null), (int) $c->owner_id, $subId,
                ];
            }
        }

        return $out;
    }
}
