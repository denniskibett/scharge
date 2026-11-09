<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Tenancy;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    public function index()
    {
        // Get the current authenticated user's company ID
        $companyId = Auth::user()->company_id;
        
        // Scope properties by company_id
        $properties = Property::where('company_id', $companyId)
                        ->withCount('units')
                        ->get();
        
        // Prepare properties data for Alpine.js including utility fields
        $propertiesData = $properties->map(function($property) {
            return [
                'id' => $property->id,
                'name' => $property->name,
                'location' => $property->location,
                'units_count' => $property->units_count,
                'water_rate' => (float) ($property->water_rate ?? 0),
                'service_charge' => (float) ($property->service_charge ?? 0),
                'garbage_charge' => (float) ($property->garbage_charge ?? 0),
                'security_charge' => (float) ($property->security_charge ?? 0)
            ];
        });
        
        return view('properties.index', [
            'properties' => $properties,
            'propertiesData' => $propertiesData
        ]);
    }

    public function show(Property $property)
    {
        // Ensure the property belongs to the current user's company
        $this->authorizeCompanyAccess($property);
        
        $property->load(['units' => function($query) {
            $query->with(['tenancies' => function($q) {
                $q->with('tenant')->where('status', 'active')->latest();
            }]);
        }]);
        
        // Calculate stats
        $occupiedCount = $property->units->where('status', 'occupied')->count();
        $vacantCount = $property->units->where('status', 'vacant')->count();
        $monthlyRentPotential = $property->units->sum('rent_amount');
        
        // Prepare units data for Alpine.js
        $unitsData = $property->units->map(function($unit) {
            // Get the active tenancy (where status is active)
            $activeTenancy = $unit->tenancies->where('status', 'active')->first();
            
            return [
                'id' => $unit->id,
                'property_id' => $unit->property_id,
                'property_name' => $unit->property->name ?? '',
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'rent_amount' => (float) $unit->rent_amount,
                'water_charge' => (float) ($unit->water_charge ?? 0),
                'service_charge' => (float) ($unit->service_charge ?? 0),
                'garbage_charge' => (float) ($unit->garbage_charge ?? 0),
                'security_charge' => (float) ($unit->security_charge ?? 0),
                'total_monthly_charges' => (float) (($unit->rent_amount ?? 0) + 
                    ($unit->water_charge ?? 0) + ($unit->service_charge ?? 0) + 
                    ($unit->garbage_charge ?? 0) + ($unit->security_charge ?? 0)),
                'status' => $unit->status,
                'active_tenancy' => $activeTenancy && $activeTenancy->tenant ? [
                    'tenant_id' => $activeTenancy->tenant_id,
                    'tenant' => [
                        'id' => $activeTenancy->tenant->id,
                        'name' => $activeTenancy->tenant->name,
                        'phone' => $activeTenancy->tenant->phone ?? '',
                        'phone2' => $activeTenancy->tenant->phone2 ?? '',
                        'email' => $activeTenancy->tenant->email ?? ''
                    ]
                ] : null,
                'balance' => 0.00 // You can calculate this from your payment system
            ];
        });
        
        // Calculate total monthly charges potential including utilities
        $monthlyTotalChargesPotential = $property->units->sum(function($unit) {
            return ($unit->rent_amount ?? 0) + 
                   ($unit->water_charge ?? 0) + 
                   ($unit->service_charge ?? 0) + 
                   ($unit->garbage_charge ?? 0) + 
                   ($unit->security_charge ?? 0);
        });
        
        return view('properties.show', [
            'property' => $property,
            'unitsData' => $unitsData,
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'monthlyRentPotential' => $monthlyRentPotential,
            'monthlyTotalChargesPotential' => $monthlyTotalChargesPotential,
            'totalUnits' => $property->units->count()
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'water_rate' => 'nullable|numeric|min:0',
                'service_charge' => 'nullable|numeric|min:0',
                'garbage_charge' => 'nullable|numeric|min:0',
                'security_charge' => 'nullable|numeric|min:0'
            ]);

            // Set default values for utilities if not provided
            $validated['water_rate'] = $validated['water_rate'] ?? 0;
            $validated['service_charge'] = $validated['service_charge'] ?? 0;
            $validated['garbage_charge'] = $validated['garbage_charge'] ?? 0;
            $validated['security_charge'] = $validated['security_charge'] ?? 0;

            // One record per building: if this looks like a property already in scharge (e.g. a BnB
            // host registered it first), offer to claim it instead of creating a duplicate.
            if (! $request->boolean('confirm_new') && \Illuminate\Support\Facades\Schema::hasTable('property_claims')) {
                $matches = app(\App\Services\Properties\PropertyRegistry::class)->matches($validated['name'], $validated['location'] ?? null)
                    ->reject(fn ($m) => (int) $m['property']->company_id === (int) Auth::user()->company_id);
                if ($matches->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'duplicate' => true,
                        'message' => 'This looks like a building already registered on scharge. Claim it instead of creating a duplicate, or confirm it is a different building.',
                        'matches' => $matches->take(5)->map(fn ($m) => \App\Services\Properties\PropertyRegistry::present($m['property'], $m['score']))->values(),
                    ], 409);
                }
            }

            // Add company_id from authenticated user
            $validated['company_id'] = Auth::user()->company_id;

            $property = Property::create($validated);
            \App\Services\Properties\PropertyRegistry::index($property);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property created successfully!',
                    'property' => $property->loadCount('units'),
                    'redirect' => route('properties.index')
                ]);
            }

            return redirect()->route('properties.index')->with('success', 'Property created successfully!');

        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to create property: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create property: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to create property: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Property $property)
    {
        try {
            // Ensure the property belongs to the current user's company
            $this->authorizeCompanyAccess($property);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'water_rate' => 'nullable|numeric|min:0',
                'service_charge' => 'nullable|numeric|min:0',
                'garbage_charge' => 'nullable|numeric|min:0',
                'security_charge' => 'nullable|numeric|min:0'
            ]);

            // Set default values for utilities if not provided
            $validated['water_rate'] = $validated['water_rate'] ?? 0;
            $validated['service_charge'] = $validated['service_charge'] ?? 0;
            $validated['garbage_charge'] = $validated['garbage_charge'] ?? 0;
            $validated['security_charge'] = $validated['security_charge'] ?? 0;

            $property->update($validated);
            \App\Services\Properties\PropertyRegistry::index($property);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property updated successfully!',
                    'property' => $property->loadCount('units'),
                    'redirect' => route('properties.index')
                ]);
            }

            return redirect()->route('properties.index')->with('success', 'Property updated successfully!');

        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update property: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update property: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to update property: ' . $e->getMessage());
        }
    }

    public function destroy(Property $property)
    {
        try {
            // Ensure the property belongs to the current user's company
            $this->authorizeCompanyAccess($property);
            
            // Check if property has units
            if ($property->units()->count() > 0) {
                if (request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete property with existing units. Please delete all units first.'
                    ], 422);
                }
                return back()->with('error', 'Cannot delete property with existing units. Please delete all units first.');
            }

            $propertyName = $property->name;
            $property->delete();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Property '{$propertyName}' deleted successfully!",
                    'redirect' => route('properties.index')
                ]);
            }

            return redirect()->route('properties.index')->with('success', "Property '{$propertyName}' deleted successfully!");

        } catch (\Exception $e) {
            \Log::error('Failed to delete property: ' . $e->getMessage());
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete property: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to delete property: ' . $e->getMessage());
        }
    }

    // Bulk update utility charges for all units in an property
    public function bulkUpdateUtilities(Request $request, Property $property)
    {
        try {
            // Ensure the property belongs to the current user's company
            $this->authorizeCompanyAccess($property);
            
            $validated = $request->validate([
                'water_rate' => 'nullable|numeric|min:0',
                'service_charge' => 'nullable|numeric|min:0',
                'garbage_charge' => 'nullable|numeric|min:0',
                'security_charge' => 'nullable|numeric|min:0',
                'apply_to_units' => 'boolean'
            ]);

            // Update property rates
            $updateData = [];
            if (isset($validated['water_rate'])) $updateData['water_rate'] = $validated['water_rate'];
            if (isset($validated['service_charge'])) $updateData['service_charge'] = $validated['service_charge'];
            if (isset($validated['garbage_charge'])) $updateData['garbage_charge'] = $validated['garbage_charge'];
            if (isset($validated['security_charge'])) $updateData['security_charge'] = $validated['security_charge'];
            
            $property->update($updateData);
            
            $updatedUnits = 0;
            
            // Optionally apply to all units in the property
            if (isset($validated['apply_to_units']) && $validated['apply_to_units']) {
                $unitUpdateData = [];
                if (isset($validated['water_rate'])) $unitUpdateData['water_charge'] = $validated['water_rate'];
                if (isset($validated['service_charge'])) $unitUpdateData['service_charge'] = $validated['service_charge'];
                if (isset($validated['garbage_charge'])) $unitUpdateData['garbage_charge'] = $validated['garbage_charge'];
                if (isset($validated['security_charge'])) $unitUpdateData['security_charge'] = $validated['security_charge'];
                
                $updatedUnits = $property->units()->update($unitUpdateData);
            }

            if ($request->wantsJson()) {
                $message = "Utility rates updated successfully!";
                if ($updatedUnits > 0) {
                    $message .= " Applied to {$updatedUnits} unit(s).";
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'property' => $property->fresh(),
                    'updated_units' => $updatedUnits
                ]);
            }

            return redirect()->route('properties.show', $property)->with('success', 'Utility rates updated successfully!');

        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Failed to update utility rates: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update utility rates: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to update utility rates: ' . $e->getMessage());
        }
    }

    // Get property financial summary
    public function getFinancialSummary(Property $property)
    {
        try {
            // Ensure the property belongs to the current user's company
            $this->authorizeCompanyAccess($property);
            
            $summary = [
                'total_units' => $property->units()->count(),
                'occupied_units' => $property->units()->where('status', 'occupied')->count(),
                'vacant_units' => $property->units()->where('status', 'vacant')->count(),
                'total_rent' => (float) $property->units()->sum('rent_amount'),
                'total_water' => (float) $property->units()->sum('water_charge'),
                'total_service' => (float) $property->units()->sum('service_charge'),
                'total_garbage' => (float) $property->units()->sum('garbage_charge'),
                'total_security' => (float) $property->units()->sum('security_charge'),
                'total_monthly' => (float) $property->units()->get()->sum(function($unit) {
                    return ($unit->rent_amount ?? 0) + 
                           ($unit->water_charge ?? 0) + 
                           ($unit->service_charge ?? 0) + 
                           ($unit->garbage_charge ?? 0) + 
                           ($unit->security_charge ?? 0);
                })
            ];
            
            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to get financial summary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get financial summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to check if an property belongs to the current user's company
     * 
     * @param Property $property
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function authorizeCompanyAccess(Property $property)
    {
        // Allow sysadmin to access any property
        if (Auth::user()->hasRole('sysadmin')) {
            return;
        }
        
        if ($property->company_id !== Auth::user()->company_id) {
            abort(403, 'You do not have permission to access this property.');
        }
    }
}