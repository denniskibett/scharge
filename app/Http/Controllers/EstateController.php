<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\Tenancy;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class EstateController extends Controller
{
    public function index()
    {
        // Get the current authenticated user's company ID
        $companyId = Auth::user()->company_id;
        
        // Scope estates by company_id
        $estates = Estate::where('company_id', $companyId)
                        ->withCount('units')
                        ->get();
        
        // Prepare estates data for Alpine.js including utility fields
        $estatesData = $estates->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
                'location' => $estate->location,
                'units_count' => $estate->units_count,
                'water_rate' => (float) ($estate->water_rate ?? 0),
                'service_charge' => (float) ($estate->service_charge ?? 0),
                'garbage_charge' => (float) ($estate->garbage_charge ?? 0),
                'security_charge' => (float) ($estate->security_charge ?? 0)
            ];
        });
        
        return view('estates.index', [
            'estates' => $estates,
            'estatesData' => $estatesData
        ]);
    }

    public function show(Estate $estate)
    {
        // Ensure the estate belongs to the current user's company
        $this->authorizeCompanyAccess($estate);
        
        $estate->load(['units' => function($query) {
            $query->with(['tenancies' => function($q) {
                $q->with('tenant')->where('status', 'active')->latest();
            }]);
        }]);
        
        // Calculate stats
        $occupiedCount = $estate->units->where('status', 'occupied')->count();
        $vacantCount = $estate->units->where('status', 'vacant')->count();
        $monthlyRentPotential = $estate->units->sum('rent_amount');
        
        // Prepare units data for Alpine.js
        $unitsData = $estate->units->map(function($unit) {
            // Get the active tenancy (where status is active)
            $activeTenancy = $unit->tenancies->where('status', 'active')->first();
            
            return [
                'id' => $unit->id,
                'estate_id' => $unit->estate_id,
                'estate_name' => $unit->estate->name ?? '',
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
        $monthlyTotalChargesPotential = $estate->units->sum(function($unit) {
            return ($unit->rent_amount ?? 0) + 
                   ($unit->water_charge ?? 0) + 
                   ($unit->service_charge ?? 0) + 
                   ($unit->garbage_charge ?? 0) + 
                   ($unit->security_charge ?? 0);
        });
        
        return view('estates.show', [
            'estate' => $estate,
            'unitsData' => $unitsData,
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'monthlyRentPotential' => $monthlyRentPotential,
            'monthlyTotalChargesPotential' => $monthlyTotalChargesPotential,
            'totalUnits' => $estate->units->count()
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

            // Add company_id from authenticated user
            $validated['company_id'] = Auth::user()->company_id;

            $estate = Estate::create($validated);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Estate created successfully!',
                    'estate' => $estate->loadCount('units'),
                    'redirect' => route('estates.index')
                ]);
            }

            return redirect()->route('estates.index')->with('success', 'Estate created successfully!');

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
            \Log::error('Failed to create estate: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create estate: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to create estate: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Estate $estate)
    {
        try {
            // Ensure the estate belongs to the current user's company
            $this->authorizeCompanyAccess($estate);
            
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

            $estate->update($validated);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Estate updated successfully!',
                    'estate' => $estate->loadCount('units'),
                    'redirect' => route('estates.index')
                ]);
            }

            return redirect()->route('estates.index')->with('success', 'Estate updated successfully!');

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
            \Log::error('Failed to update estate: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update estate: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to update estate: ' . $e->getMessage());
        }
    }

    public function destroy(Estate $estate)
    {
        try {
            // Ensure the estate belongs to the current user's company
            $this->authorizeCompanyAccess($estate);
            
            // Check if estate has units
            if ($estate->units()->count() > 0) {
                if (request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete estate with existing units. Please delete all units first.'
                    ], 422);
                }
                return back()->with('error', 'Cannot delete estate with existing units. Please delete all units first.');
            }

            $estateName = $estate->name;
            $estate->delete();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Estate '{$estateName}' deleted successfully!",
                    'redirect' => route('estates.index')
                ]);
            }

            return redirect()->route('estates.index')->with('success', "Estate '{$estateName}' deleted successfully!");

        } catch (\Exception $e) {
            \Log::error('Failed to delete estate: ' . $e->getMessage());
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete estate: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Failed to delete estate: ' . $e->getMessage());
        }
    }

    // Bulk update utility charges for all units in an estate
    public function bulkUpdateUtilities(Request $request, Estate $estate)
    {
        try {
            // Ensure the estate belongs to the current user's company
            $this->authorizeCompanyAccess($estate);
            
            $validated = $request->validate([
                'water_rate' => 'nullable|numeric|min:0',
                'service_charge' => 'nullable|numeric|min:0',
                'garbage_charge' => 'nullable|numeric|min:0',
                'security_charge' => 'nullable|numeric|min:0',
                'apply_to_units' => 'boolean'
            ]);

            // Update estate rates
            $updateData = [];
            if (isset($validated['water_rate'])) $updateData['water_rate'] = $validated['water_rate'];
            if (isset($validated['service_charge'])) $updateData['service_charge'] = $validated['service_charge'];
            if (isset($validated['garbage_charge'])) $updateData['garbage_charge'] = $validated['garbage_charge'];
            if (isset($validated['security_charge'])) $updateData['security_charge'] = $validated['security_charge'];
            
            $estate->update($updateData);
            
            $updatedUnits = 0;
            
            // Optionally apply to all units in the estate
            if (isset($validated['apply_to_units']) && $validated['apply_to_units']) {
                $unitUpdateData = [];
                if (isset($validated['water_rate'])) $unitUpdateData['water_charge'] = $validated['water_rate'];
                if (isset($validated['service_charge'])) $unitUpdateData['service_charge'] = $validated['service_charge'];
                if (isset($validated['garbage_charge'])) $unitUpdateData['garbage_charge'] = $validated['garbage_charge'];
                if (isset($validated['security_charge'])) $unitUpdateData['security_charge'] = $validated['security_charge'];
                
                $updatedUnits = $estate->units()->update($unitUpdateData);
            }

            if ($request->wantsJson()) {
                $message = "Utility rates updated successfully!";
                if ($updatedUnits > 0) {
                    $message .= " Applied to {$updatedUnits} unit(s).";
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'estate' => $estate->fresh(),
                    'updated_units' => $updatedUnits
                ]);
            }

            return redirect()->route('estates.show', $estate)->with('success', 'Utility rates updated successfully!');

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

    // Get estate financial summary
    public function getFinancialSummary(Estate $estate)
    {
        try {
            // Ensure the estate belongs to the current user's company
            $this->authorizeCompanyAccess($estate);
            
            $summary = [
                'total_units' => $estate->units()->count(),
                'occupied_units' => $estate->units()->where('status', 'occupied')->count(),
                'vacant_units' => $estate->units()->where('status', 'vacant')->count(),
                'total_rent' => (float) $estate->units()->sum('rent_amount'),
                'total_water' => (float) $estate->units()->sum('water_charge'),
                'total_service' => (float) $estate->units()->sum('service_charge'),
                'total_garbage' => (float) $estate->units()->sum('garbage_charge'),
                'total_security' => (float) $estate->units()->sum('security_charge'),
                'total_monthly' => (float) $estate->units()->get()->sum(function($unit) {
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
     * Helper method to check if an estate belongs to the current user's company
     * 
     * @param Estate $estate
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function authorizeCompanyAccess(Estate $estate)
    {
        if ($estate->company_id !== Auth::user()->company_id) {
            abort(403, 'You do not have permission to access this estate.');
        }
    }
}