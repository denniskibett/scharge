<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\Tenancy;

class EstateController extends Controller
{
    public function index()
    {
        $estates = Estate::withCount('units')->get();
        
        // Prepare estates data for Alpine.js
        $estatesData = $estates->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
                'location' => $estate->location,
                'units_count' => $estate->units_count
            ];
        });
        
        return view('estates.index', [
            'estates' => $estates,
            'estatesData' => $estatesData
        ]);
    }

    public function show(Estate $estate)
    {
        $estate->load(['units' => function($query) {
            $query->with(['tenancies' => function($q) {
                $q->with('tenant')->latest();
            }]);
        }]);
        
        // Calculate stats
        $occupiedCount = $estate->units->where('status', 'occupied')->count();
        $vacantCount = $estate->units->where('status', 'vacant')->count();
        $monthlyRentPotential = $estate->units->sum('rent_amount');
        
        // Prepare units data for Alpine.js
        $unitsData = $estate->units->map(function($unit) {
            $activeTenancy = $unit->tenancies->where('status', 'active')->first();
            
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'rent_amount' => (float) $unit->rent_amount,
                'status' => $unit->status,
                'active_tenancy' => $activeTenancy ? [
                    'tenancy_id' => $activeTenancy->tenancy_id,
                    'tenancy' => $activeTenancy->tenancy ? [
                        'id' => $activeTenancy->tenancy->id,
                        'name' => $activeTenancy->tenancy->name,
                        'phone' => $activeTenancy->tenancy->phone ?? '',
                    ] : null
                ] : null,
                'balance' => 0.00 // You can calculate this from your payment system
            ];
        });
        
        return view('estates.show', [
            'estate' => $estate,
            'unitsData' => $unitsData,
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'monthlyRentPotential' => $monthlyRentPotential,
            'totalUnits' => $estate->units->count()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255'
        ]);

        $estate = Estate::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Estate created successfully',
                'estate' => $estate->loadCount('units')
            ]);
        }

        return back()->with('success', 'Estate created successfully');
    }

    public function update(Request $request, Estate $estate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255'
        ]);

        $estate->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Estate updated successfully',
                'estate' => $estate->loadCount('units')
            ]);
        }

        return back()->with('success', 'Estate updated successfully');
    }

    public function destroy(Estate $estate)
    {
        $estate->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Estate deleted successfully'
            ]);
        }

        return back()->with('success', 'Estate deleted successfully');
    }
}