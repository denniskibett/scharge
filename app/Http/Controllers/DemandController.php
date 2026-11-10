<?php

namespace App\Http\Controllers;

use App\Services\ListingCatalog;
use App\Services\Listings\DemandReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Renter demand heatmaps (docs/RENTER_DEMAND.md): where people look for homes on Open listings and at what budget.
 * Free for every landlord (Property › Renter demand), market-wide and anonymous, with their own areas marked and a
 * nudge to list vacant units; full view for scharge staff (/admin/demand).
 */
class DemandController extends Controller
{
    public const KINDS = ['Homes to rent', 'Short stays'];

    public function index(Request $request, DemandReport $report): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90, 180], true) ? (int) $request->query('days') : 30;
        $kind = in_array($request->query('kind'), self::KINDS, true) ? $request->query('kind') : self::KINDS[0];
        $user = $request->user();
        $companyId = $user->hasAnyRole(['sysadmin', 'account_manager']) ? null : (int) $user->company_id;
        abort_if(! $companyId && ! $user->hasAnyRole(['sysadmin', 'account_manager']), 403);

        $mine = [];
        $vacantUnlisted = 0;
        $listed = 0;
        if ($companyId) {
            $propertyIds = DB::table('properties')->where('company_id', $companyId)->pluck('id')->all();
            $mine = DB::table('properties')->whereIn('id', $propertyIds ?: [0])->whereNotNull('location')->pluck('location')->unique()->values()->all();
            $listed = collect(app(ListingCatalog::class)->items())->filter(fn ($i) => in_array((int) ($i['property_id'] ?? 0), $propertyIds, true))->count();
            // Vacant units that are not advertised (no rent amount set, so Open listings cannot show them).
            $vacantUnlisted = DB::table('units')->whereIn('property_id', $propertyIds ?: [0])->where('status', 'vacant')
                ->where(fn ($q) => $q->whereNull('rent_amount')->orWhere('rent_amount', '<=', 0))->count();
        }

        return view('demand.index', [
            'ready' => Schema::hasTable('listing_searches'),
            'r' => Schema::hasTable('listing_searches') ? $report->build($days, $kind) : null,
            'days' => $days, 'kind' => $kind, 'mine' => $mine, 'listed' => $listed, 'vacantUnlisted' => $vacantUnlisted, 'isStaff' => ! $companyId,
        ]);
    }
}
