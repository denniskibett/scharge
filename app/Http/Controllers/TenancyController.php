<?php

namespace App\Http\Controllers;

use App\Models\Tenancy; // Renamed from Tenant
use App\Models\User;
use App\Models\Unit;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TenancyController extends Controller // Renamed from TenantController
{
    public function index()
    {
        $tenancies = Tenancy::with('tenant.user', 'unit.estate')->get();
        
        // Format data for Alpine.js
        $tenanciesData = $tenancies->map(function ($tenancy) {
            return [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->user->name ?? null, 
                'tenant_email' => $tenancy->tenant->user->email ?? null, 
                'tenant_phone' => $tenancy->tenant->user->phone ?? null, 
                'user_id' => $tenancy->tenant->user_id ?? null,
                'tenant_id' => $tenancy->tenant_id,
                'unit_number' => $tenancy->unit->unit_number ?? null,
                'unit_id' => $tenancy->unit_id,
                'estate_name' => $tenancy->unit->estate->name ?? null,
                'move_in_date' => $tenancy->move_in_date,
                'move_out_date' => $tenancy->move_out_date,
                'status' => $tenancy->status,
                'move_in_date_formatted' => $tenancy->move_in_date 
                    ? \Carbon\Carbon::parse($tenancy->move_in_date)->format('M d, Y') 
                    : null,
                'move_out_date_formatted' => $tenancy->move_out_date 
                    ? \Carbon\Carbon::parse($tenancy->move_out_date)->format('M d, Y') 
                    : null,
            ];
        });
        
        // Get ALL tenants from tenants table
        $allTenants = Tenant::with('user')->get();
        
        // Get tenant IDs that exist in tenancies table
        $tenantIdsInTenancies = Tenancy::pluck('tenant_id')->toArray();
        
        // Filter tenants: show all tenants.id that are NOT in tenancies.tenant_id
        // This includes tenants who have never had a tenancy
        $availableTenants = $allTenants->filter(function ($tenant) use ($tenantIdsInTenancies) {
            return !in_array($tenant->id, $tenantIdsInTenancies);
        })->values();
        
        // Format available tenants for dropdown
        $availableUsersFormatted = $availableTenants->map(function ($tenant) {
            return [
                'id' => $tenant->user->id ?? null,
                'name' => $tenant->user->name ?? 'Unknown',
                'email' => $tenant->user->email ?? null,
                'phone' => $tenant->user->phone ?? null,
                'tenant_id' => $tenant->id,
                'label' => ($tenant->user->name ?? 'Unknown') . ' (' . ($tenant->user->phone ?? 'No Phone') . ')',
            ];
        });
        
        // Also include all users for the "Create New Tenant" option
        // Get all users with guest role
        $allUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'guest');
            })
            ->select('id', 'name', 'email', 'phone')
            ->get();
        
        // Format all users for reference
        $allUsersFormatted = $allUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'label' => $user->name . ' (' . $user->phone . ')',
            ];
        });

        // Pass ALL units (for edit modal)
        $allUnits = Unit::with('estate')
            ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount', 'status')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? null,
                    'unit_type' => $unit->unit_type,
                    'rent_amount' => $unit->rent_amount,
                    'status' => $unit->status,
                    'label' => $unit->unit_number . ' - ' . ($unit->estate->name ?? 'No Estate') . ', (' . $unit->unit_type . ')',
                ];
            });

        // Get vacant units for create modal
        $units = Unit::with('estate')
            ->where('status', 'vacant')
            ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? null,
                    'unit_type' => $unit->unit_type,
                    'rent_amount' => $unit->rent_amount,
                    'label' => $unit->unit_number . ' - ' . ($unit->estate->name ?? 'No Estate') . ', (' . $unit->unit_type . ')',
                ];
            });
        
        // Also get vacant units from allUnits for consistency
        $vacantUnits = $allUnits->filter(function ($unit) {
            return $unit['status'] === 'vacant';
        })->values();

        return view('tenancies.index', [
            'tenanciesData' => $tenanciesData,
            'availableUsers' => $availableUsersFormatted,  // Pass the formatted array
            'allUsersFormatted' => $allUsersFormatted,
            'allUnits' => $allUnits,
            'units' => $units,
            'vacantUnits' => $vacantUnits
        ]);
    }
    
// In TenancyController.php
public function show(Tenancy $tenancy)
{
    $tenancy->load([
        'tenant.user', 
        'unit.estate', 
        'payments.invoice',
        'invoices.items',
        'invoices.payments'
    ]);
    
    // Get unit tenancy history
    $unitTenancyHistory = Tenancy::where('unit_id', $tenancy->unit_id)
        ->with(['tenant.user'])
        ->orderBy('move_in_date', 'desc')
        ->get();
    
    // Calculate duration
    $moveIn = $tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date) : null;
    $moveOut = $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date) : null;
    $duration = $moveIn ? $moveIn->diffForHumans($moveOut ?? now(), true) : 'N/A';
    
    return view('tenancies.show', compact(
        'tenancy', 
        'unitTenancyHistory', 
        'duration'
    ));
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required_if:new_tenant_name,null|nullable|exists:users,id',
            'new_tenant_name' => 'required_if:user_id,new|nullable|string|max:255',
            'new_tenant_phone' => 'required_if:user_id,new|nullable|string|max:255',
            'unit_id' => 'required|exists:units,id',
            'move_in_date' => 'required|date',
            'tenant_id' => 'nullable|exists:tenants,id', // Add this validation
        ]);

        DB::beginTransaction();
        
        try {
            // Handle existing tenant
            if ($request->user_id !== 'new' && $request->user_id) {
                $tenant = Tenant::where('user_id', $request->user_id)->first();
                
                if (!$tenant) {
                    // Create tenant record if it doesn't exist
                    $tenant = Tenant::create([
                        'user_id' => $request->user_id,
                        'id_number' => null,
                        'emergency_contact' => null,
                        'notes' => null,
                    ]);
                }
                
                $tenantId = $tenant->id;
            } 
            // Handle new tenant creation
            else if ($request->user_id === 'new') {
                // First create the user
                $user = User::create([
                    'name' => $request->new_tenant_name,
                    'phone' => $request->new_tenant_phone,
                    'email' => Str::slug($request->new_tenant_name) . '@example.com', // Generate email
                    'password' => Hash::make('password'), // Default password
                ]);
                
                // Assign guest role
                $user->assignRole('guest');
                
                // Then create the tenant record
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'id_number' => null,
                    'emergency_contact' => null,
                    'notes' => null,
                ]);
                
                $tenantId = $tenant->id;
            } else {
                throw new \Exception('Invalid tenant selection');
            }

            // Create the tenancy
            $tenancy = Tenancy::create([
                'tenant_id' => $tenantId, // Make sure this is set
                'unit_id' => $request->unit_id,
                'move_in_date' => $request->move_in_date,
                'status' => 'active',
            ]);

            // Update unit status to occupied
            $unit = Unit::find($request->unit_id);
            if ($unit) {
                $unit->update(['status' => 'occupied']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Tenancy created successfully!',
                'tenancy' => $tenancy
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create tenancy: ' . $e->getMessage(),
                'errors' => []
            ], 500);
        }
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'tenancies' => 'required|array|min:1',
            'tenancies.*.unit_id' => 'required|exists:units,id',
            'tenancies.*.move_in_date' => 'required|date',
            'tenancies.*.tenant_name' => 'required|string|max:255',
            'tenancies.*.tenant_email' => 'required|email|unique:users,email',
            'tenancies.*.tenant_phone' => 'nullable|string|max:20',
        ]);

        $created = [];
        $errors = [];

        foreach ($validated['tenancies'] as $index => $data) {
            try {
                // Create new user
                $user = User::create([
                    'name' => $data['tenant_name'],
                    'email' => $data['tenant_email'],
                    'phone' => $data['tenant_phone'] ?? null,
                    'password' => Hash::make('00000000'),
                ]);

                // Assign guest role
                $user->assignRole('guest');

                // Create tenancy
                $tenancy = Tenancy::create([
                    'user_id' => $user->id,
                    'unit_id' => $data['unit_id'],
                    'move_in_date' => $data['move_in_date'],
                    'status' => 'active',
                ]);

                // Update unit status
                Unit::find($data['unit_id'])->update(['status' => 'occupied']);

                $created[] = [
                    'index' => $index,
                    'tenancy' => $user->name,
                    'unit' => $tenancy->unit->unit_number,
                    'tenancy_id' => $tenancy->id,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'tenancy' => $data['tenant_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' tenancies created successfully',
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    public function update(Request $request, Tenancy $tenancy)
    {
        $validated = $request->validate([
            'move_out_date' => 'nullable|date',
            'status' => 'required|in:active,ended',
        ]);

        $tenancy->update($validated);

        // If ended, set unit vacant
        if ($validated['status'] === 'ended') {
            $tenancy->unit->update(['status' => 'vacant']);
        }

        return response()->json(['success' => true, 'message' => 'Tenancy updated successfully']);
    }

    public function destroy(Tenancy $tenancy)
    {
        // Set unit to vacant before deleting
        $tenancy->unit->update(['status' => 'vacant']);
        $tenancy->delete();

        return response()->json(['success' => true, 'message' => 'Tenancy deleted successfully']);
    }

    // API endpoint to get users for select
    public function getUsers(Request $request)
    {
        if ($request->ajax()) {
            $users = User::role('guest') // Only show guest users
                ->select('id', 'name', 'email', 'phone')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'label' => "{$user->name} ({$user->email})"
                    ];
                });
            
            return response()->json($users);
        }
    }

    // API endpoint to get units for select
    public function getUnits(Request $request)
    {
        if ($request->ajax()) {
            $units = Unit::with('estate')
                ->where('status', 'vacant') // Only show vacant units
                ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount')
                ->get()
                ->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'estate_name' => $unit->estate->name ?? null,
                        'unit_type' => $unit->unit_type,
                        'rent_amount' => $unit->rent_amount,
                        'label' => $unit->unit_number . ' - ' . ($unit->estate->name ?? 'No Estate') . ', (' . $unit->unit_type . ')',

                    ];
                });
            
            return response()->json($units);
        }
    }

    // Search existing users by email or name
    public function searchUsers(Request $request)
    {
        if ($request->ajax()) {
            $search = $request->get('search', '');
            
            $users = User::role('guest')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                })
                ->select('id', 'name', 'email', 'phone')
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'label' => "{$user->name} ({$user->email})"
                    ];
                });
            
            return response()->json($users);
        }
    }

    // Check if email already exists
    public function checkEmail(Request $request)
    {
        if ($request->ajax()) {
            $email = $request->get('email');
            
            $exists = User::where('email', $email)->exists();
            
            return response()->json([
                'exists' => $exists,
                'message' => $exists ? 'Email already exists' : 'Email available'
            ]);
        }
    }
}