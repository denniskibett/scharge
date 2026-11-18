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
     * Rows: [id, name, lat, lng, segment#, stage#, precision#, county#, sub_county#, owner_id, verified]
     * where # indexes into the lookup lists sent alongside (keeps ~14k rows small).
     */
    public function data(Request $request, KenyaGeo $geo): JsonResponse
    {
        $p = fn (string $key) => "JSON_UNQUOTE(JSON_EXTRACT(companies.crm_profile, '$.{$key}'))";
        $rows = CrmAccess::companies(Company::crm(), $request->user())
            ->selectRaw("companies.id, companies.name, companies.crm_stage, companies.owner_id,
                {$p('lat')} AS lat, {$p('lng')} AS lng, {$p('segment')} AS segment, {$p('geo_precision')} AS prec,
                {$p('county')} AS county, {$p('sub_county')} AS sub_county, {$p('needs_verification')} AS nv")
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

        $out = [];
        $unlocated = 0;
        foreach ($rows as $r) {
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
                $index('county', $r->county),
                $index('sub_county', $r->sub_county),
                (int) $r->owner_id,
                in_array($r->nv, ['true', '1', 1, true], true) ? 0 : 1,
            ];
        }

        // Before 'lists' is copied below: properties can add county / sub-county names.
        $properties = $this->properties($request, $geo, $index);

        return response()->json([
            'lists' => $lists,
            'stages' => Company::CRM_STAGES,
            'rows' => $out,
            'unlocated' => $unlocated,
            'properties' => $properties,
            'property_statuses' => DemoRequestService::PROPERTY_STATUSES,
        ])->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Properties prospects listed on the demo form, as their own layer:
     * [company_id, property_id, name, lat, lng, status, precision, county#, sub_county#, units, location, maps_url, owner_id]
     * Ones crm:geocode has not reached yet are placed on the fly (same rules, not saved).
     */
    private function properties(Request $request, KenyaGeo $geo, callable $index): array
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
                $out[] = [
                    (int) $c->id, $p['id'] ?? null, $p['name'] ?? 'Property',
                    $hasPin ? (float) $p['lat'] : null, $hasPin ? (float) $p['lng'] : null,
                    $p['status'] ?? 'open', $hasPin ? ($p['geo_precision'] ?? '') : '',
                    $index('county', $p['county'] ?? null), $index('sub_county', $p['sub_county'] ?? null),
                    (int) ($p['units'] ?? 0), $p['location'] ?? null, KenyaGeo::googleMapsUrl($p, $c->crm_profile['city'] ?? null), (int) $c->owner_id,
                ];
            }
        }

        return $out;
    }
}
