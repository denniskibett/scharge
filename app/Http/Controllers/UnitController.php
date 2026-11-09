<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnitController extends Controller
{
    public function index()
    {
        // Get the current authenticated user's company ID
        $companyId = Auth::user()->company_id;
        
        // Scope units by company_id
        $units = Unit::with(['property', 'tenancies.tenant.user'])
                    ->where('company_id', $companyId)
                    ->get();
        
        // Also scope properties by company_id
        $properties = Property::where('company_id', $companyId)->get();
        
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
                'property_id' => $unit->property_id,
                'property_name' => $unit->property->name ?? null,
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
        
        return view('units.index', [
            'units' => $units,
            'unitsData' => $unitsData,
            'properties' => $properties,
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
        // Ensure the unit belongs to the current user's company
        $this->authorizeCompanyAccess($unit);
        
        $unit->load(['property', 'tenancies.tenant.user']);
        
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
            'property_id' => 'required|exists:properties,id',
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

        // Add company_id from authenticated user
        $validated['company_id'] = Auth::user()->company_id;

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
                'unit' => $unit->load('property')
            ]);
        }

        return redirect()->route('units.index')->with('success', 'Unit created successfully');
    }

    public function edit(Unit $unit)
    {
        // Ensure the unit belongs to the current user's company
        $this->authorizeCompanyAccess($unit);
        
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
                    'property_name' => $unit->property->name,
                    'property_id' => $unit->property_id,
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
        
        $properties = Property::where('company_id', Auth::user()->company_id)->get();
        return view('units.edit', compact('unit', 'properties'));
    }

    public function update(Request $request, Unit $unit)
    {
        // Ensure the unit belongs to the current user's company
        $this->authorizeCompanyAccess($unit);
        
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

        $unit->update($this->restrictHostUnitFields($unit, $validated));

        if ($request->wantsJson()) {
            // Reload the unit with property to return complete data
            $unit->load('property');
            
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
                    'property_name' => $unit->property->name,
                    'property_id' => $unit->property_id,
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
        // A host-managed unit is never deleted by the building's company (only scharge support can move it).
        if (\Illuminate\Support\Facades\Schema::hasTable('unit_managements') && ($host = \App\Models\UnitManagement::hostOf($unit->id))
            && (int) $host->company_id !== (int) Auth::user()->company_id && ! Auth::user()->hasRole('sysadmin')) {
            abort(403, 'This unit is managed by its BnB host and cannot be deleted here.');
        }
        // Ensure the unit belongs to the current user's company
        $this->authorizeCompanyAccess($unit);
        
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
    
    // Optional: Bulk update utility charges for all units in an property
    public function bulkUpdateUtilities(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'water_charge' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'garbage_charge' => 'nullable|numeric|min:0',
            'security_charge' => 'nullable|numeric|min:0',
        ]);
        
        // Ensure the property belongs to the current company
        $property = Property::where('id', $validated['property_id'])
                       ->where('company_id', Auth::user()->company_id)
                       ->firstOrFail();
        
        $query = Unit::where('property_id', $validated['property_id'])
                    ->where('company_id', Auth::user()->company_id);
        
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
    
    // Optional: Get unit charges summary for an property
    public function getPropertyChargesSummary(Property $property)
    {
        // Ensure the property belongs to the current company
        if ($property->company_id !== Auth::user()->company_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $summary = [
            'total_units' => $property->units()->count(),
            'total_rent' => $property->units()->sum('rent_amount'),
            'total_water' => $property->units()->sum('water_charge'),
            'total_service' => $property->units()->sum('service_charge'),
            'total_garbage' => $property->units()->sum('garbage_charge'),
            'total_security' => $property->units()->sum('security_charge'),
            'total_monthly' => $property->units()->sum('total_monthly_charges'),
        ];
        
        return response()->json($summary);
    }
    
    // New: Get units filtered by property category
    public function getByCategory($category)
    {
        $units = Unit::with(['property', 'tenancies.tenant.user'])
                    ->where('property_category', $category)
                    ->where('company_id', Auth::user()->company_id)
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
        $units = Unit::with(['property', 'tenancies.tenant.user'])
                    ->where('stay_type', $stayType)
                    ->where('company_id', Auth::user()->company_id)
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
        $units = Unit::with(['property', 'tenancies.tenant.user'])
                    ->where('stay_type', 'bnb')
                    ->where('is_active', true)
                    ->where('company_id', Auth::user()->company_id)
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
                            'property_name' => $unit->property->name,
                            'property_location' => $unit->property->location ?? null,
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
        // Ensure the unit belongs to the current user's company
        $this->authorizeCompanyAccess($unit);
        
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
        // Ensure the unit belongs to the current user's company
        $this->authorizeCompanyAccess($unit);
        
        $unit->load('property');
        
        return response()->json([
            'success' => true,
            'unit' => $unit,
            'water_rate' => $unit->property->water_rate ?? 50,
        ]);
    }

    /**
     * Helper method to check if a unit belongs to the current user's company
     * 
     * @param Unit $unit
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function authorizeCompanyAccess(Unit $unit)
    {
        $companyId = Auth::user()->company_id;
        if ((int) $unit->company_id === (int) $companyId || Auth::user()->hasRole('sysadmin')) {
            return;
        }
        // A BnB host manages their own unit even when the building's company holds its books.
        if (\Illuminate\Support\Facades\Schema::hasTable('unit_managements')
            && \App\Models\UnitManagement::where('unit_id', $unit->id)->where('company_id', $companyId)->where('role', 'bnb_host')->where('status', 'active')->exists()) {
            return;
        }
        abort(403, 'You do not have permission to access this unit.');
    }

    /** Which fields of a host-managed unit the building's management company may change (billing only). */
    private function restrictHostUnitFields(Unit $unit, array $validated): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('unit_managements')) {
            return $validated;
        }
        $host = \App\Models\UnitManagement::hostOf($unit->id);
        if (! $host) {
            return $validated;
        }
        $billing = ['water_charge', 'service_charge', 'garbage_charge', 'security_charge', 'custom_water_rate', 'water_billing_type'];
        $hostOnly = ['unit_type', 'rent_amount', 'status', 'stay_type', 'bnb_nightly_rate', 'bnb_cleaning_fee', 'min_stay_days', 'max_stay_days', 'furnishing_status'];

        // The host edits their listing; the building's company edits only what it bills.
        return (int) $host->company_id === (int) Auth::user()->company_id
            ? array_diff_key($validated, array_flip($billing))
            : array_intersect_key($validated, array_flip($billing));
    }
}