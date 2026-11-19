<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyAgent;
use App\Models\Unit;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Services\Agents\AgentAccess;
use App\Services\ListingCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The agent's dashboard: the properties landlords gave their agency, each with its vacant homes, the Open listings
 * switch (when the landlord allowed it), unit photos and renter enquiries. No tenants, money or meter data.
 */
class AgentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(AgentAccess::isAgent($user), 403);

        $grants = AgentAccess::enabled()
            ? PropertyAgent::active()->where('agency_id', $user->company_id)->with('property.company')->get()->filter(fn ($g) => $g->property)
            : collect();
        $ids = $grants->pluck('property_id')->all();
        $units = Unit::whereIn('property_id', $ids ?: [0])->selectRaw("property_id, COUNT(*) as total, SUM(status = 'vacant') as vacant, SUM(status = 'vacant' AND COALESCE(rent_amount, 0) > 0) as priced")
            ->groupBy('property_id')->get()->keyBy('property_id');

        return view('agent.dashboard', [
            'agency' => $user->company,
            'grants' => $grants->sortBy(fn ($g) => $g->property->name)->values(),
            'units' => $units,
            'enquiries' => $this->enquiries($ids)->limit(20)->get(),
            'openEnquiries' => $this->enquiries($ids)->whereIn('status', ['open', 'scheduled'])->count(),
            'isAdmin' => $user->hasRole('admin'),
        ]);
    }

    public function show(Request $request, Property $property): View
    {
        $grant = $this->grant($request, $property);

        $units = Unit::where('property_id', $property->id)->orderByRaw("status = 'vacant' desc")->orderBy('unit_number')
            ->get(['id', 'unit_number', 'unit_type', 'status', 'rent_amount', 'stay_type', 'bnb_nightly_rate', 'furnishing_status']);

        return view('agent.property', [
            'property' => $property->load('company'),
            'grant' => $grant,
            'units' => $units,
            // One photo set per unit type (photos for a type show on all its listings).
            'photoUnits' => $units->groupBy('unit_type')->map(fn ($g) => $g->first())->values(),
            'enquiries' => $this->enquiries([$property->id])->limit(50)->get(),
        ]);
    }

    /** The Open listings switch, when the landlord let this agency list the property. */
    public function listing(Request $request, Property $property): RedirectResponse
    {
        $grant = $this->grant($request, $property);
        abort_unless($grant->can_list, 403, 'The landlord has not let your agency put this property on Open listings.');
        $on = $request->boolean('listed_publicly');
        $property->forceFill(['listed_publicly' => $on, 'listed_at' => $on ? now() : null, 'listed_by' => $on ? $request->user()->id : null])->save();
        ListingCatalog::forget();

        return back()->with('success', $on ? $property->name.' is on Open listings.' : $property->name.' is off Open listings.');
    }

    private function grant(Request $request, Property $property): PropertyAgent
    {
        $grant = AgentAccess::grant($request->user(), (int) $property->id);
        abort_unless($grant, 403, 'This property has not been given to your agency.');

        return $grant;
    }

    /** Renter viewing / booking requests for these properties (from Open listings). */
    private function enquiries(array $propertyIds)
    {
        return CrmFollowUp::with('contact:id,name,phone,email')->where('type', 'viewing')
            ->whereIn(\DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(details, '$.property_id')) AS UNSIGNED)"), $propertyIds ?: [0])
            ->latest();
    }
}
