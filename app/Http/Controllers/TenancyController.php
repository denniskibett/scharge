<?php

namespace App\Http\Controllers;

use App\Modules\Properties\Models\Tenancy;
use App\Models\Payment;
use App\Models\User;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TenancyController extends Controller
{
    public function index()
    {
        $tenancies = Tenancy::with(['tenant.user', 'unit.estate'])->get();
        
        // Format data for Alpine.js - Include utility fields from unit
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
                'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                'water_charge' => $tenancy->unit->water_charge ?? 0,
                'service_charge' => $tenancy->unit->service_charge ?? 0,
                'garbage_charge' => $tenancy->unit->garbage_charge ?? 0,
                'security_charge' => $tenancy->unit->security_charge ?? 0,
                'total_monthly_payment' => ($tenancy->unit->rent_amount ?? 0) + 
                                          ($tenancy->unit->water_charge ?? 0) + 
                                          ($tenancy->unit->service_charge ?? 0) + 
                                          ($tenancy->unit->garbage_charge ?? 0) + 
                                          ($tenancy->unit->security_charge ?? 0),
                'move_in_date' => $tenancy->move_in_date,
                'move_out_date' => $tenancy->move_out_date,
                'status' => $tenancy->status,
                'notes' => $tenancy->notes,
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
        
        // Get tenant IDs that have ACTIVE tenancies (not all tenancies)
        $tenantIdsWithActiveTenancies = Tenancy::where('status', 'active')
            ->pluck('tenant_id')
            ->toArray();
        
        // Filter tenants: show tenants that do NOT have an ACTIVE tenancy
        // This includes:
        // 1. Tenants who have never had a tenancy
        // 2. Tenants whose tenancies have ended (status = 'ended')
        $availableTenants = $allTenants->filter(function ($tenant) use ($tenantIdsWithActiveTenancies) {
            return !in_array($tenant->id, $tenantIdsWithActiveTenancies);
        })->values();
        
        // Format available tenants for dropdown
        $availableUsersFormatted = $availableTenants->map(function ($tenant) {
            // Check if this tenant has any ended tenancies
            $hasEndedTenancy = Tenancy::where('tenant_id', $tenant->id)
                ->where('status', 'ended')
                ->exists();
                
            return [
                'id' => $tenant->user->id ?? null,
                'name' => $tenant->user->name ?? 'Unknown',
                'email' => $tenant->user->email ?? null,
                'phone' => $tenant->user->phone ?? null,
                'tenant_id' => $tenant->id,
                'has_ended_tenancy' => $hasEndedTenancy,
                'label' => ($tenant->user->name ?? 'Unknown') . ' (' . ($tenant->user->phone ?? 'No Phone') . ')' . 
                          ($hasEndedTenancy ? ' (Previous Tenant)' : ''),
            ];
        });
        
        // Also include all users for the "Create New Tenant" option
        // Get all users with guest role
        $allUsers = User::whereHas('role', function ($query) {
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

        // Pass ALL units (for edit modal) - Include utility fields
        $allUnits = Unit::with('estate')
            ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount', 
                    'water_charge', 'service_charge', 'garbage_charge', 'security_charge', 'status')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? null,
                    'unit_type' => $unit->unit_type,
                    'rent_amount' => $unit->rent_amount,
                    'water_charge' => $unit->water_charge ?? 0,
                    'service_charge' => $unit->service_charge ?? 0,
                    'garbage_charge' => $unit->garbage_charge ?? 0,
                    'security_charge' => $unit->security_charge ?? 0,
                    'total_monthly_payment' => ($unit->rent_amount ?? 0) + 
                                               ($unit->water_charge ?? 0) + 
                                               ($unit->service_charge ?? 0) + 
                                               ($unit->garbage_charge ?? 0) + 
                                               ($unit->security_charge ?? 0),
                    'status' => $unit->status,
                    'label' => $unit->unit_number . ' - ' . ($unit->estate->name ?? 'No Estate') . ', (' . $unit->unit_type . ')',
                ];
            });

        // Get vacant units for create modal - Include utility fields
        $units = Unit::with('estate')
            ->where('status', 'vacant')
            ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount',
                    'water_charge', 'service_charge', 'garbage_charge', 'security_charge')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? null,
                    'unit_type' => $unit->unit_type,
                    'rent_amount' => $unit->rent_amount,
                    'water_charge' => $unit->water_charge ?? 0,
                    'service_charge' => $unit->service_charge ?? 0,
                    'garbage_charge' => $unit->garbage_charge ?? 0,
                    'security_charge' => $unit->security_charge ?? 0,
                    'total_monthly_payment' => ($unit->rent_amount ?? 0) + 
                                               ($unit->water_charge ?? 0) + 
                                               ($unit->service_charge ?? 0) + 
                                               ($unit->garbage_charge ?? 0) + 
                                               ($unit->security_charge ?? 0),
                    'label' => $unit->unit_number . ' - ' . ($unit->estate->name ?? 'No Estate') . ', (' . $unit->unit_type . ')',
                ];
            });
        
        // Also get vacant units from allUnits for consistency
        $vacantUnits = $allUnits->filter(function ($unit) {
            return $unit['status'] === 'vacant';
        })->values();

        return view('tenancies.index', [
            'tenanciesData' => $tenanciesData,
            'availableUsers' => $availableUsersFormatted,
            'allUsersFormatted' => $allUsersFormatted,
            'allUnits' => $allUnits,
            'units' => $units,
            'vacantUnits' => $vacantUnits
        ]);
    }
    
    public function show(Tenancy $tenancy)
    {
        // Load only essential relationships with counts
        $tenancy->loadCount(['invoices']);
        
        // Load basic tenant and unit info (select only needed columns)
        $tenancy->load([
            'tenant' => function ($query) {
                $query->select('id', 'user_id', 'notes')
                    ->with(['user' => function ($q) {
                        $q->select('id', 'name', 'email', 'phone');
                    }]);
            },
            'unit' => function ($query) {
                $query->select('id', 'estate_id', 'unit_number', 'unit_type', 'rent_amount', 'status',
                            'water_charge', 'service_charge', 'garbage_charge', 'security_charge')
                    ->with(['estate' => function ($q) {
                        $q->select('id', 'name', 'location');
                    }]);
            },
            'payments' => function ($query) {
                $query->latest()->limit(50)->with('invoice');
            }
        ]);
        
        // Get unit tenancy history - limit to last 10 and only load essential data
        $unitTenancyHistory = Tenancy::where('unit_id', $tenancy->unit_id)
            ->with([
                'tenant' => function ($query) {
                    $query->select('id', 'user_id')
                        ->with(['user' => function ($q) {
                            $q->select('id', 'name');
                        }]);
                }
            ])
            ->select('id', 'tenant_id', 'move_in_date', 'move_out_date', 'status')
            ->orderBy('move_in_date', 'desc')
            ->limit(10)
            ->get();
        
        // Calculate duration without loading extra data
        $moveIn = $tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date) : null;
        $moveOut = $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date) : null;
        $duration = $moveIn ? $moveIn->diffForHumans($moveOut ?? now(), true) : 'N/A';
        
        // Calculate financial summary without loading all invoices
        $totalInvoiced = $tenancy->invoices()->sum('total_amount');
        
        // FIXED: Get payments through invoices instead of directly by tenancy_id
        $totalPaid = Payment::whereHas('invoice', function($query) use ($tenancy) {
            $query->where('tenancy_id', $tenancy->id);
        })->sum('amount');
        
        $balance = $totalInvoiced - $totalPaid;
        
        // Calculate total monthly payment including utilities
        $totalMonthlyPayment = ($tenancy->unit->rent_amount ?? 0) + 
                            ($tenancy->unit->water_charge ?? 0) + 
                            ($tenancy->unit->service_charge ?? 0) + 
                            ($tenancy->unit->garbage_charge ?? 0) + 
                            ($tenancy->unit->security_charge ?? 0);
        
        return view('tenancies.show', compact(
            'tenancy', 
            'unitTenancyHistory', 
            'duration',
            'totalInvoiced',
            'totalPaid',
            'balance',
            'totalMonthlyPayment'
        ));
    }

    public function edit(Tenancy $tenancy)
    {
        $tenancy->load(['tenant.user', 'unit.estate']);
        
        if (request()->wantsJson()) {
            return response()->json([
                'tenancy' => [
                    'id' => $tenancy->id,
                    'tenant_id' => $tenancy->tenant_id,
                    'tenant_name' => $tenancy->tenant->user->name ?? null,
                    'tenant_phone' => $tenancy->tenant->user->phone ?? null,
                    'unit_id' => $tenancy->unit_id,
                    'unit_number' => $tenancy->unit->unit_number ?? null,
                    'estate_name' => $tenancy->unit->estate->name ?? null,
                    'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                    'water_charge' => $tenancy->unit->water_charge ?? 0,
                    'service_charge' => $tenancy->unit->service_charge ?? 0,
                    'garbage_charge' => $tenancy->unit->garbage_charge ?? 0,
                    'security_charge' => $tenancy->unit->security_charge ?? 0,
                    'total_monthly_payment' => ($tenancy->unit->rent_amount ?? 0) + 
                                               ($tenancy->unit->water_charge ?? 0) + 
                                               ($tenancy->unit->service_charge ?? 0) + 
                                               ($tenancy->unit->garbage_charge ?? 0) + 
                                               ($tenancy->unit->security_charge ?? 0),
                    'move_in_date' => $tenancy->move_in_date,
                    'move_out_date' => $tenancy->move_out_date,
                    'status' => $tenancy->status,
                    'notes' => $tenancy->notes                
                ]
            ]);
        }
        
        $units = Unit::with('estate')->select('id', 'unit_number', 'estate_id', 'unit_type', 
                                              'rent_amount', 'water_charge', 'service_charge', 
                                              'garbage_charge', 'security_charge', 'status')->get();
        return view('tenancies.edit', compact('tenancy', 'units'));
    }

    public function create(Request $request)
    {
        $unitId = $request->get('unit_id');

        $unit = null;

        if ($unitId) {
            $unit = Unit::with('estate')->findOrFail($unitId);
        }

        // get available tenants
        $tenants = Tenant::with('user')->get();

        return view('tenancies.create', compact('unit', 'tenants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'new_tenant_name' => 'nullable|string|max:255',
            'new_tenant_phone' => 'nullable|string|max:255',
            'new_tenant_email' => 'nullable|email|unique:users,email',
            'unit_id' => 'required|exists:units,id',
            'move_in_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            $tenantId = null;
            
            // Handle existing tenant
            if (isset($validated['tenant_id']) && $validated['tenant_id']) {
                $tenant = Tenant::find($validated['tenant_id']);
                
                if (!$tenant) {
                    throw new \Exception('Selected tenant not found');
                }
                
                // Check if tenant already has an active tenancy
                $activeTenancy = Tenancy::where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->first();
                    
                if ($activeTenancy) {
                    throw new \Exception('This tenant already has an active tenancy');
                }
                
                $tenantId = $tenant->id;
            } 
            // Handle new tenant creation
            else if (isset($validated['new_tenant_name']) && $validated['new_tenant_name']) {
                // Validate new tenant fields
                if (!isset($validated['new_tenant_phone']) || !$validated['new_tenant_phone']) {
                    throw new \Exception('Phone number is required for new tenant');
                }
                
                // Generate a unique email if not provided
                $email = $validated['new_tenant_email'] ?? $this->generateTenantEmail($validated['new_tenant_name']);
                
                // Check if email already exists
                if (User::where('email', $email)->exists()) {
                    // Generate a unique email
                    $baseEmail = strtolower(str_replace(' ', '.', $validated['new_tenant_name']));
                    $counter = 1;
                    while (User::where('email', $email)->exists()) {
                        $email = $baseEmail . $counter . '@tenant.com';
                        $counter++;
                    }
                }
                
                // Create the user
                $user = User::create([
                    'name' => $validated['new_tenant_name'],
                    'email' => $email,
                    'phone' => $validated['new_tenant_phone'],
                    'password' => Hash::make('00000000'), // Default password
                ]);
                
                // Assign guest role
                $guestRole = Role::where('name', 'guest')->first();
                if ($guestRole) {
                    $user->roles()->attach($guestRole->id);
                }
                
                // Create the tenant record
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'notes' => $validated['notes'] ?? null,
                ]);
                
                $tenantId = $tenant->id;
            } else {
                throw new \Exception('Please select a tenant or provide new tenant details');
            }

            // Check if unit is vacant
            $unit = Unit::find($validated['unit_id']);
            if (!$unit) {
                throw new \Exception('Unit not found');
            }
            
            if ($unit->status !== 'vacant') {
                throw new \Exception('Selected unit is not vacant');
            }

            // Create the tenancy
            $tenancy = Tenancy::create([
                'tenant_id' => $tenantId,
                'unit_id' => $validated['unit_id'],
                'move_in_date' => $validated['move_in_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
            ]);

            // Update unit status to occupied
            $unit->update(['status' => 'occupied']);

            DB::commit();

            // Load unit with utility charges for response
            $tenancy->load(['tenant.user', 'unit']);

            return response()->json([
                'success' => true,
                'message' => 'Tenancy created successfully!',
                'tenancy' => [
                    'id' => $tenancy->id,
                    'tenant_name' => $tenancy->tenant->user->name ?? null,
                    'unit_number' => $tenancy->unit->unit_number ?? null,
                    'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                    'water_charge' => $tenancy->unit->water_charge ?? 0,
                    'service_charge' => $tenancy->unit->service_charge ?? 0,
                    'garbage_charge' => $tenancy->unit->garbage_charge ?? 0,
                    'security_charge' => $tenancy->unit->security_charge ?? 0,
                    'total_monthly_payment' => ($tenancy->unit->rent_amount ?? 0) + 
                                               ($tenancy->unit->water_charge ?? 0) + 
                                               ($tenancy->unit->service_charge ?? 0) + 
                                               ($tenancy->unit->garbage_charge ?? 0) + 
                                               ($tenancy->unit->security_charge ?? 0),
                    'move_in_date' => $tenancy->move_in_date,
                    'status' => $tenancy->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create tenancy: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenancy: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a unique email for new tenant
     */
    private function generateTenantEmail($name)
    {
        $baseEmail = strtolower(str_replace(' ', '.', $name)) . '@tenant.com';
        $email = $baseEmail;
        $counter = 1;
        
        while (User::where('email', $email)->exists()) {
            $email = strtolower(str_replace(' ', '.', $name)) . $counter . '@tenant.com';
            $counter++;
        }
        
        return $email;
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
            'tenancies.*.notes' => 'nullable|string',
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
                $guestRole = Role::where('name', 'guest')->first();
                if ($guestRole) {
                    $user->roles()->attach($guestRole->id);
                }

                // Create tenant record
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Create tenancy
                $tenancy = Tenancy::create([
                    'tenant_id' => $tenant->id,
                    'unit_id' => $data['unit_id'],
                    'move_in_date' => $data['move_in_date'],
                    'notes' => $data['notes'] ?? null,
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
            'unit_id' => 'required|exists:units,id',
            'move_in_date' => 'required|date',
            'move_out_date' => 'nullable|date',
            'status' => 'required|in:active,ended',
            'notes' => 'nullable|string',
        ]);

        // Store old unit_id before update
        $oldUnitId = $tenancy->unit_id;
        
        // Update the tenancy
        $tenancy->update($validated);

        // Handle unit status changes
        if ($tenancy->status === 'ended') {
            // If tenancy ended, set the unit to vacant
            Unit::where('id', $tenancy->unit_id)->update(['status' => 'vacant']);
            
            // Also update old unit if it was changed
            if ($oldUnitId && $oldUnitId != $tenancy->unit_id) {
                Unit::where('id', $oldUnitId)->update(['status' => 'vacant']);
            }
        } else if ($tenancy->status === 'active') {
            // If tenancy is active, ensure the unit is occupied
            Unit::where('id', $tenancy->unit_id)->update(['status' => 'occupied']);
            
            // If unit was changed, set old unit to vacant
            if ($oldUnitId && $oldUnitId != $tenancy->unit_id) {
                Unit::where('id', $oldUnitId)->update(['status' => 'vacant']);
            }
        }
        
        // Load updated unit with utility charges for response
        $tenancy->load('unit');

        return response()->json([
            'success' => true, 
            'message' => 'Tenancy updated successfully',
            'tenancy' => [
                'id' => $tenancy->id,
                'unit_id' => $tenancy->unit_id,
                'unit_number' => $tenancy->unit->unit_number ?? null,
                'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                'water_charge' => $tenancy->unit->water_charge ?? 0,
                'service_charge' => $tenancy->unit->service_charge ?? 0,
                'garbage_charge' => $tenancy->unit->garbage_charge ?? 0,
                'security_charge' => $tenancy->unit->security_charge ?? 0,
                'total_monthly_payment' => ($tenancy->unit->rent_amount ?? 0) + 
                                           ($tenancy->unit->water_charge ?? 0) + 
                                           ($tenancy->unit->service_charge ?? 0) + 
                                           ($tenancy->unit->garbage_charge ?? 0) + 
                                           ($tenancy->unit->security_charge ?? 0),
                'move_in_date' => $tenancy->move_in_date,
                'move_out_date' => $tenancy->move_out_date,
                'status' => $tenancy->status,
                'notes' => $tenancy->notes
            ]
        ]);
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
            $users = User::role('guest')
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

    // API endpoint to get units for select - Include utility fields
    public function getUnits(Request $request)
    {
        if ($request->ajax()) {
            $units = Unit::with('estate')
                ->where('status', 'vacant')
                ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount',
                        'water_charge', 'service_charge', 'garbage_charge', 'security_charge')
                ->get()
                ->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'estate_name' => $unit->estate->name ?? null,
                        'unit_type' => $unit->unit_type,
                        'rent_amount' => $unit->rent_amount,
                        'water_charge' => $unit->water_charge ?? 0,
                        'service_charge' => $unit->service_charge ?? 0,
                        'garbage_charge' => $unit->garbage_charge ?? 0,
                        'security_charge' => $unit->security_charge ?? 0,
                        'total_monthly_payment' => ($unit->rent_amount ?? 0) + 
                                                   ($unit->water_charge ?? 0) + 
                                                   ($unit->service_charge ?? 0) + 
                                                   ($unit->garbage_charge ?? 0) + 
                                                   ($unit->security_charge ?? 0),
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
    
    // Get utility charges for a specific unit (for invoice generation)
    public function getUnitCharges(Unit $unit)
    {
        return response()->json([
            'rent_amount' => $unit->rent_amount,
            'water_charge' => $unit->water_charge ?? 0,
            'service_charge' => $unit->service_charge ?? 0,
            'garbage_charge' => $unit->garbage_charge ?? 0,
            'security_charge' => $unit->security_charge ?? 0,
            'total_monthly_payment' => ($unit->rent_amount ?? 0) + 
                                       ($unit->water_charge ?? 0) + 
                                       ($unit->service_charge ?? 0) + 
                                       ($unit->garbage_charge ?? 0) + 
                                       ($unit->security_charge ?? 0)
        ]);
    }
}