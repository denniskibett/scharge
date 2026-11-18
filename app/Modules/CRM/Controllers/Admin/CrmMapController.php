<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Support\CrmAccess;
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
    public function data(Request $request): JsonResponse
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

        return response()->json([
            'lists' => $lists,
            'stages' => Company::CRM_STAGES,
            'rows' => $out,
            'unlocated' => $unlocated,
        ])->header('Cache-Control', 'private, max-age=300');
    }
}
