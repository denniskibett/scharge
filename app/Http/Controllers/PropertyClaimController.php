<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyClaim;
use App\Services\Properties\HostUnits;
use Illuminate\Http\Request;

/**
 * Claims on buildings already in scharge (e.g. registered first by a BnB host).
 * Company admins send them; the company's account manager or a sysadmin approves.
 */
class PropertyClaimController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->company_id && $user->hasAnyRole(['admin', 'property_manager']), 403, 'Only a company admin can claim a building.');
        $data = $request->validate(['property_id' => 'required|integer|exists:properties,id', 'message' => 'nullable|string|max:500']);
        $property = Property::findOrFail($data['property_id']);

        if ((int) $property->company_id === (int) $user->company_id && $property->claim_status === 'managed') {
            return response()->json(['message' => 'Your company already manages this building.'], 422);
        }
        if (PropertyClaim::where('property_id', $property->id)->where('company_id', $user->company_id)->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'You already asked to manage this building; scharge is reviewing it.'], 422);
        }

        PropertyClaim::create(['property_id' => $property->id, 'company_id' => $user->company_id, 'status' => 'pending',
            'requested_by' => $user->id, 'message' => $data['message'] ?? null]);

        return response()->json(['message' => "Thanks. Your account manager will confirm that you manage {$property->name}, usually within a working day. You can add its units once it is approved."], 201);
    }

    public function index(Request $request)
    {
        $claims = $this->visible($request)->with(['property:id,name,location,company_id,claim_status', 'company:id,name', 'requester:id,name,email,phone'])
            ->orderByRaw("status = 'pending' DESC")->latest()->limit(200)->get();

        return view('admin.property-claims.index', ['claims' => $claims]);
    }

    public function approve(Request $request, PropertyClaim $claim, HostUnits $hosts)
    {
        $this->authorizeReview($request, $claim);
        $hosts->approve($claim, $request->user(), $request->input('note'));

        return back()->with('success', "Approved: {$claim->company?->name} now manages {$claim->property?->name}. Host units stay with their hosts.");
    }

    public function reject(Request $request, PropertyClaim $claim, HostUnits $hosts)
    {
        $this->authorizeReview($request, $claim);
        $hosts->reject($claim, $request->user(), $request->input('note'));

        return back()->with('success', 'Claim rejected.');
    }

    private function visible(Request $request)
    {
        $user = $request->user();

        return PropertyClaim::query()->when(! $user->hasRole('sysadmin'), fn ($q) => $q->whereIn('company_id',
            \App\Models\Company::where('owner_id', $user->id)->pluck('id')));
    }

    private function authorizeReview(Request $request, PropertyClaim $claim): void
    {
        abort_unless($this->visible($request)->whereKey($claim->id)->exists(), 403);
    }
}
