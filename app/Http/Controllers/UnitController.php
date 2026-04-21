<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Estate;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::with(['estate', 'tenancies.tenant.user'])->get();
        $estates = Estate::all();
        
        // Calculate stats with total charges
        $occupiedCount = $units->where('status', 'occupied')->count();
        $vacantCount = $units->where('status', 'vacant')->count();
        $totalUnits = $units->count();
        $monthlyRentPotential = $units->sum('rent_amount');
        $monthlyWaterPotential = $units->sum('water_charge');
        $monthlyServicePotential = $units->sum('service_charge');
        $monthlyGarbagePotential = $units->sum('garbage_charge');
        $monthlySecurityPotential = $units->sum('security_charge');
        $monthlyTotalChargesPotential = $units->sum(function($unit) {
            return $unit->total_monthly_charges;
        });
        
        // Prepare units data for Alpine.js - INCLUDING NEW CLASSIFICATION FIELDS
        $unitsData = $units->map(function($unit) {
            $activeTenant = $unit->tenancies->where('status', 'active')->first();
            
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'rent_amount' => (float) $unit->rent_amount,
                'water_charge' => (float) ($unit->water_charge ?? 0),
                'service_charge' => (float) ($unit->service_charge ?? 0),
                'garbage_charge' => (float) ($unit->garbage_charge ?? 0),
                'security_charge' => (float) ($unit->security_charge ?? 0),
                'total_monthly_charges' => (float) ($unit->total_monthly_charges ?? 0),
                'status' => $unit->status,
                'estate_id' => $unit->estate_id,
                'estate_name' => $unit->estate->name ?? null,
                // New classification fields
                'ownership_type' => $unit->ownership_type ?? 'tenant',
                'furnishing_status' => $unit->furnishing_status ?? 'unfurnished',
                'stay_type' => $unit->stay_type ?? 'long_stay',
                'property_category' => $unit->property_category ?? 'residential',
                'is_active' => $unit->is_active ?? true,
                'min_stay_days' => $unit->min_stay_days,
                'max_stay_days' => $unit->max_stay_days,
                'bnb_cleaning_fee' => (float) ($unit->bnb_cleaning_fee ?? 0),
                'bnb_nightly_rate' => (float) ($unit->bnb_nightly_rate ?? 0),
                'security_deposit_amount' => (float) ($unit->security_deposit_amount ?? 0),
                'commission_rate' => (float) ($unit->commission_rate ?? 0),
                'active_tenancy' => $activeTenant ? [
                    'tenant_id' => $activeTenant->id,
                    'tenant' => [
                        'id' => $activeTenant->tenant->id ?? null,
                        'name' => $activeTenant->tenant->user->name ?? null,
                        'phone' => $activeTenant->tenant->user->phone ?? '',
                    ]
                ] : null,
                'balance' => 0.00,
                'tenancies_count' => $unit->tenancies->count(),
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at
            ];
        });
        
        // Debug: Log the first unit to see if new fields are included
        \Log::info('First unit data with classifications:', ['unit' => $unitsData->first()]);
        
        return view('units.index', [
            'units' => $units,
            'unitsData' => $unitsData,
            'estates' => $estates,
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'totalUnits' => $totalUnits,
            'monthlyRentPotential' => $monthlyRentPotential,
            'monthlyWaterPotential' => $monthlyWaterPotential,
            'monthlyServicePotential' => $monthlyServicePotential,
            'monthlyGarbagePotential' => $monthlyGarbagePotential,
            'monthlySecurityPotential' => $monthlySecurityPotential,
            'monthlyTotalChargesPotential' => $monthlyTotalChargesPotential
        ]);
    }

    public function show(Unit $unit)
    {
        $unit->load(['estate', 'tenancies.tenant.user']);
        
        // Calculate total charges
        $unit->total_charges = $unit->rent_amount + 
                               $unit->water_charge + 
                               $unit->service_charge + 
                               $unit->garbage_charge + 
                               $unit->security_charge;
        
        return view('units.show', compact('unit'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Existing fields
            'estate_id' => 'required|exists:estates,id',
            'unit_number' => 'required|string|max:255',
            'unit_type' => 'required|string|max:255',
            'rent_amount' => 'required|numeric|min:0',
            'water_charge' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'garbage_charge' => 'nullable|numeric|min:0',
            'security_charge' => 'nullable|numeric|min:0',
            'status' => 'required|in:vacant,occupied',
            
            // New classification fields (all optional with defaults)
            'ownership_type' => 'nullable|in:homeowner,tenant,company',
            'furnishing_status' => 'nullable|in:furnished,unfurnished,semi_furnished',
            'stay_type' => 'nullable|in:long_stay,short_stay,bnb,mixed',
            'property_category' => 'nullable|in:residential,commercial,showhouse,office,retail,industrial',
            'is_active' => 'nullable|boolean',
            'min_stay_days' => 'nullable|integer|min:1',
            'max_stay_days' => 'nullable|integer|min:1',
            'bnb_cleaning_fee' => 'nullable|numeric|min:0',
            'bnb_nightly_rate' => 'nullable|numeric|min:0',
            'security_deposit_amount' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Set default values for utilities if not provided
        $validated['water_charge'] = $validated['water_charge'] ?? 0;
        $validated['service_charge'] = $validated['service_charge'] ?? 0;
        $validated['garbage_charge'] = $validated['garbage_charge'] ?? 0;
        $validated['security_charge'] = $validated['security_charge'] ?? 0;
        
        // Set default values for new classification fields
        $validated['ownership_type'] = $validated['ownership_type'] ?? 'tenant';
        $validated['furnishing_status'] = $validated['furnishing_status'] ?? 'unfurnished';
        $validated['stay_type'] = $validated['stay_type'] ?? 'long_stay';
        $validated['property_category'] = $validated['property_category'] ?? 'residential';
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['bnb_cleaning_fee'] = $validated['bnb_cleaning_fee'] ?? 0;
        $validated['bnb_nightly_rate'] = $validated['bnb_nightly_rate'] ?? null;
        $validated['security_deposit_amount'] = $validated['security_deposit_amount'] ?? null;
        $validated['commission_rate'] = $validated['commission_rate'] ?? null;

        $unit = Unit::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unit created successfully',
                'unit' => $unit->load('estate')
            ]);
        }

        return redirect()->route('units.index')->with('success', 'Unit created successfully');
    }

    public function edit(Unit $unit)
    {
        // Return JSON for the edit modal if requested - INCLUDING NEW FIELDS
        if (request()->wantsJson()) {
            return response()->json([
                'unit' => [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'unit_type' => $unit->unit_type,
                    'rent_amount' => (float) $unit->rent_amount,
                    'water_charge' => (float) ($unit->water_charge ?? 0),
                    'service_charge' => (float) ($unit->service_charge ?? 0),
                    'garbage_charge' => (float) ($unit->garbage_charge ?? 0),
                    'security_charge' => (float) ($unit->security_charge ?? 0),
                    'total_monthly_charges' => (float) ($unit->total_monthly_charges ?? 0),
                    'status' => $unit->status,
                    'estate_name' => $unit->estate->name,
                    'estate_id' => $unit->estate_id,
                    // New classification fields
                    'ownership_type' => $unit->ownership_type ?? 'tenant',
                    'furnishing_status' => $unit->furnishing_status ?? 'unfurnished',
                    'stay_type' => $unit->stay_type ?? 'long_stay',
                    'property_category' => $unit->property_category ?? 'residential',
                    'is_active' => $unit->is_active ?? true,
                    'min_stay_days' => $unit->min_stay_days,
                    'max_stay_days' => $unit->max_stay_days,
                    'bnb_cleaning_fee' => (float) ($unit->bnb_cleaning_fee ?? 0),
                    'bnb_nightly_rate' => (float) ($unit->bnb_nightly_rate ?? 0),
                    'security_deposit_amount' => (float) ($unit->security_deposit_amount ?? 0),
                    'commission_rate' => (float) ($unit->commission_rate ?? 0),
                ]
            ]);
        }
        
        $estates = Estate::all();
        return view('units.edit', compact('unit', 'estates'));
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            // Existing fields
            'unit_number' => 'required|string|max:255',
            'unit_type' => 'required|string|max:255',
            'rent_amount' => 'required|numeric|min:0',
            'water_charge' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'garbage_charge' => 'nullable|numeric|min:0',
            'security_charge' => 'nullable|numeric|min:0',
            'status' => 'required|in:vacant,occupied',
            
            // New classification fields
            'ownership_type' => 'nullable|in:homeowner,tenant,company',
            'furnishing_status' => 'nullable|in:furnished,unfurnished,semi_furnished',
            'stay_type' => 'nullable|in:long_stay,short_stay,bnb,mixed',
            'property_category' => 'nullable|in:residential,commercial,showhouse,office,retail,industrial',
            'is_active' => 'nullable|boolean',
            'min_stay_days' => 'nullable|integer|min:1',
            'max_stay_days' => 'nullable|integer|min:1',
            'bnb_cleaning_fee' => 'nullable|numeric|min:0',
            'bnb_nightly_rate' => 'nullable|numeric|min:0',
            'security_deposit_amount' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Set default values for utilities if not provided
        $validated['water_charge'] = $validated['water_charge'] ?? 0;
        $validated['service_charge'] = $validated['service_charge'] ?? 0;
        $validated['garbage_charge'] = $validated['garbage_charge'] ?? 0;
        $validated['security_charge'] = $validated['security_charge'] ?? 0;
        
        // Handle nullable fields
        $validated['bnb_nightly_rate'] = $validated['bnb_nightly_rate'] ?? null;
        $validated['security_deposit_amount'] = $validated['security_deposit_amount'] ?? null;
        $validated['commission_rate'] = $validated['commission_rate'] ?? null;

        $unit->update($validated);

        if ($request->wantsJson()) {
            // Reload the unit with estate to return complete data
            $unit->load('estate');
            
            return response()->json([
                'success' => true,
                'message' => 'Unit updated successfully',
                'unit' => [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'unit_type' => $unit->unit_type,
                    'rent_amount' => (float) $unit->rent_amount,
                    'water_charge' => (float) $unit->water_charge,
                    'service_charge' => (float) $unit->service_charge,
                    'garbage_charge' => (float) $unit->garbage_charge,
                    'security_charge' => (float) $unit->security_charge,
                    'total_monthly_charges' => (float) $unit->total_monthly_charges,
                    'status' => $unit->status,
                    'estate_name' => $unit->estate->name,
                    'estate_id' => $unit->estate_id,
                    // New classification fields
                    'ownership_type' => $unit->ownership_type,
                    'furnishing_status' => $unit->furnishing_status,
                    'stay_type' => $unit->stay_type,
                    'property_category' => $unit->property_category,
                    'is_active' => $unit->is_active,
                    'min_stay_days' => $unit->min_stay_days,
                    'max_stay_days' => $unit->max_stay_days,
                    'bnb_cleaning_fee' => (float) ($unit->bnb_cleaning_fee ?? 0),
                    'bnb_nightly_rate' => (float) ($unit->bnb_nightly_rate ?? 0),
                    'security_deposit_amount' => (float) ($unit->security_deposit_amount ?? 0),
                    'commission_rate' => (float) ($unit->commission_rate ?? 0),
                ]
            ]);
        }

        return redirect()->route('units.index')->with('success', 'Unit updated successfully');
    }

    public function destroy(Unit $unit)
    {
        // Check if unit has active tenancies
        if ($unit->tenancies()->where('status', 'active')->exists()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete unit with active tenancy'
                ], 422);
            }
            
            return redirect()->route('units.index')->with('error', 'Cannot delete unit with active tenancy');
        }
        
        $unit->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unit deleted successfully'
            ]);
        }

        return redirect()->route('units.index')->with('success', 'Unit deleted successfully');
    }
    
    // Optional: Bulk update utility charges for all units in an estate
    public function bulkUpdateUtilities(Request $request)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'water_charge' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'garbage_charge' => 'nullable|numeric|min:0',
            'security_charge' => 'nullable|numeric|min:0',
        ]);
        
        $query = Unit::where('estate_id', $validated['estate_id']);
        
        $updateData = [];
        if (isset($validated['water_charge'])) $updateData['water_charge'] = $validated['water_charge'];
        if (isset($validated['service_charge'])) $updateData['service_charge'] = $validated['service_charge'];
        if (isset($validated['garbage_charge'])) $updateData['garbage_charge'] = $validated['garbage_charge'];
        if (isset($validated['security_charge'])) $updateData['security_charge'] = $validated['security_charge'];
        
        $updated = $query->update($updateData);
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Updated $updated units successfully",
                'updated_count' => $updated
            ]);
        }
        
        return redirect()->route('units.index')->with('success', "Updated $updated units successfully");
    }
    
    // Optional: Get unit charges summary for an estate
    public function getEstateChargesSummary(Estate $estate)
    {
        $summary = [
            'total_units' => $estate->units()->count(),
            'total_rent' => $estate->units()->sum('rent_amount'),
            'total_water' => $estate->units()->sum('water_charge'),
            'total_service' => $estate->units()->sum('service_charge'),
            'total_garbage' => $estate->units()->sum('garbage_charge'),
            'total_security' => $estate->units()->sum('security_charge'),
            'total_monthly' => $estate->units()->sum('total_monthly_charges'),
        ];
        
        return response()->json($summary);
    }
    
    // New: Get units filtered by property category
    public function getByCategory($category)
    {
        $units = Unit::with(['estate', 'tenancies.tenant.user'])
                    ->where('property_category', $category)
                    ->get();
        
        return response()->json([
            'success' => true,
            'category' => $category,
            'count' => $units->count(),
            'units' => $units
        ]);
    }
    
    // New: Get units filtered by stay type
    public function getByStayType($stayType)
    {
        $units = Unit::with(['estate', 'tenancies.tenant.user'])
                    ->where('stay_type', $stayType)
                    ->get();
        
        return response()->json([
            'success' => true,
            'stay_type' => $stayType,
            'count' => $units->count(),
            'units' => $units
        ]);
    }
    
    // New: Get BNB units with availability (for frontend calendar)
    public function getBnbUnits()
    {
        $units = Unit::with(['estate', 'tenancies.tenant.user'])
                    ->where('stay_type', 'bnb')
                    ->where('is_active', true)
                    ->get()
                    ->map(function($unit) {
                        return [
                            'id' => $unit->id,
                            'unit_number' => $unit->unit_number,
                            'unit_type' => $unit->unit_type,
                            'nightly_rate' => $unit->bnb_nightly_rate ?? ($unit->rent_amount / 30),
                            'cleaning_fee' => $unit->bnb_cleaning_fee ?? 0,
                            'security_deposit' => $unit->security_deposit_amount ?? 0,
                            'min_stay_days' => $unit->min_stay_days ?? 1,
                            'max_stay_days' => $unit->max_stay_days,
                            'furnishing_status' => $unit->furnishing_status,
                            'estate_name' => $unit->estate->name,
                            'estate_location' => $unit->estate->location ?? null,
                        ];
                    });
        
        return response()->json([
            'success' => true,
            'count' => $units->count(),
            'units' => $units
        ]);
    }

    public function updateWaterReading(Request $request, Unit $unit)
    {
        $request->validate([
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
        ]);
        
        $unit->submitWaterReading($request->current_reading, $request->reading_date);
        
        return response()->json([
            'success' => true,
            'message' => 'Water reading updated successfully',
            'unit' => $unit
        ]);
    }

    public function getMeterReadingData(Unit $unit)
    {
        $unit->load('estate');
        
        return response()->json([
            'success' => true,
            'unit' => $unit,
            'water_rate' => $unit->estate->water_rate ?? 50,
        ]);
    }
}