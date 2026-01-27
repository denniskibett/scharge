<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Estate;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::with(['estate', 'tenancies.user'])->get(); // Use 'tenant' not 'user'
        $estates = Estate::all();
        
        // Calculate stats
        $occupiedCount = $units->where('status', 'occupied')->count();
        $vacantCount = $units->where('status', 'vacant')->count();
        $totalUnits = $units->count();
        $monthlyRentPotential = $units->sum('rent_amount');
        
        // Prepare units data for Alpine.js
        $unitsData = $units->map(function($unit) {
            $activeTenant = $unit->tenancies->where('status', 'active')->first();
            
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'rent_amount' => (float) $unit->rent_amount,
                'status' => $unit->status,
                'estate_id' => $unit->estate_id,
                'estate_name' => $unit->estate->name ?? null,
                'active_tenancy' => $activeTenant ? [
                    'tenant_id' => $activeTenant->id,
                    'tenant' => $activeTenant->tenancy ? [ // Changed from user to tenant
                        'id' => $activeTenant->tenancy->id,
                        'name' => $activeTenant->tenancy->name,
                        'phone' => $activeTenant->tenancy->phone ?? '',
                    ] : null
                ] : null,
                'balance' => 0.00
            ];
        });
        
        return view('units.index', [
            'units' => $units,
            'unitsData' => $unitsData,
            'estates' => $estates,
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'monthlyRentPotential' => $monthlyRentPotential,
            'totalUnits' => $totalUnits
        ]);
    }
    public function show(Unit $unit)
    {
        $unit->load('estate', 'tenancies.user');
        return view('units.show', compact('unit'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'unit_number' => 'required|string',
            'unit_type' => 'required|string',
            'rent_amount' => 'required|numeric',
            'status' => 'required|in:vacant,occupied'
        ]);

        $unit = Unit::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unit created successfully',
                'unit' => $unit->load('estate')
            ]);
        }

        return back()->with('success', 'Unit created successfully');
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'unit_number' => 'required|string',
            'unit_type' => 'required|string',
            'rent_amount' => 'required|numeric',
            'status' => 'required|in:vacant,occupied',
        ]);

        $unit->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unit updated successfully',
                'unit' => $unit->load('estate')
            ]);
        }

        return back()->with('success', 'Unit updated successfully');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unit deleted successfully'
            ]);
        }

        return back()->with('success', 'Unit deleted successfully');
    }
}