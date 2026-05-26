<?php
// app/Http/Controllers/Admin/CompanyController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function index()
    {
        return view('admin.companies.index');
    }

    public function show(Request $request, Company $company)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'id' => $company->id,
                'name' => $company->name,
                'registration_number' => $company->registration_number,
                'tax_id' => $company->tax_id,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'is_active' => $company->is_active,
                'users_count' => $company->users()->count(),
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
            ]);
        }
        
        $users = $company->users()->with('role')->latest()->paginate(20);
        $availableRoles = Role::where('name', '!=', 'super_admin')->get();
        $availableUserSlots = $company->getAvailableUserSlots();
        $subscription = $company->currentSubscription;
        
        return view('admin.companies.show', compact('company', 'users', 'availableRoles', 'availableUserSlots', 'subscription'));
    }

    public function getCompaniesData()
    {
        $companies = Company::withCount('users')->orderBy('name')->get();
        
        $totalCompanies = $companies->count();
        $activeCompanies = $companies->where('is_active', true)->count();
        $totalUsers = $companies->sum('users_count');
        $avgUsersPerCompany = $totalCompanies > 0 ? round($totalUsers / $totalCompanies, 1) : 0;
        
        return response()->json([
            'success' => true,
            'companies' => $companies,
            'stats' => [
                'totalCompanies' => $totalCompanies,
                'activeCompanies' => $activeCompanies,
                'totalUsers' => $totalUsers,
                'avgUsersPerCompany' => $avgUsersPerCompany,
            ]
        ]);
    }

    // ========== COMPANY USERS MANAGEMENT (for the modal) ==========
    
    /**
     * Get users for a specific company (for the modal)
     */

    public function getCompanyUsers(Company $company)
    {
        // Get all users belonging to this company (excluding sysadmin)
        $users = $company->users()
            ->whereDoesntHave('role', function($q) {
                $q->where('name', 'sysadmin');
            })
            ->with('role')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role_name' => $user->role?->display_name ?? $user->role?->name ?? 'No Role',
                    'role_badge' => $this->getRoleBadge($user->role?->name),
                    'created_at_formatted' => $user->created_at ? $user->created_at->format('M d, Y') : '-',
                ];
            });
        
        // Get available roles (excluding sysadmin and tenant)
        $availableRoles = Role::whereNotIn('name', ['sysadmin', 'tenant'])
            ->orderBy('name')
            ->get()
            ->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? ucfirst(str_replace('_', ' ', $role->name)),
                ];
            });
        
        // Calculate available user slots based on subscription
        $availableUserSlots = $company->getAvailableUserSlots();
        
        return response()->json([
            'success' => true,
            'users' => $users,
            'availableRoles' => $availableRoles,
            'availableUserSlots' => $availableUserSlots,
            'currentUserCount' => $users->count(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:companies,name',
                'registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:companies,email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
                'admin_user.first_name' => 'required_with:admin_user|string|max:255',
                'admin_user.last_name' => 'required_with:admin_user|string|max:255',
                'admin_user.email' => 'required_with:admin_user|email|unique:users,email',
                'admin_user.phone' => 'nullable|string|max:20',
                'admin_user.password' => 'required_with:admin_user|string|min:8',
            ]);

            $company = Company::create([
                'name' => $validated['name'],
                'registration_number' => $validated['registration_number'] ?? null,
                'tax_id' => $validated['tax_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $adminCredentials = null;
            if ($request->has('admin_user') && !empty($request->admin_user['first_name'])) {
                $adminRole = Role::where('name', 'admin')->first();
                if (!$adminRole) {
                    $adminRole = Role::create([
                        'name' => 'admin',
                        'display_name' => 'Administrator',
                        'description' => 'System Administrator'
                    ]);
                }

                $user = User::create([
                    'first_name' => $request->admin_user['first_name'],
                    'last_name' => $request->admin_user['last_name'],
                    'name' => $request->admin_user['first_name'] . ' ' . $request->admin_user['last_name'],
                    'email' => $request->admin_user['email'],
                    'phone' => $request->admin_user['phone'] ?? null,
                    'role_id' => $adminRole->id,
                    'company_id' => $company->id,
                    'password' => Hash::make($request->admin_user['password']),
                    'email_verified_at' => now(),
                ]);

                $adminCredentials = [
                    'email' => $request->admin_user['email'],
                    'password' => $request->admin_user['password']
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully!',
                'company' => $company->loadCount('users'),
                'credentials' => $adminCredentials
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Company $company)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:companies,name,' . $company->id,
                'registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:companies,email,' . $company->id,
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            $company->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully!',
                'company' => $company->loadCount('users')
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Company $company)
    {
        try {
            if ($company->users()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with associated users. Please remove or reassign users first.'
                ], 422);
            }
            
            $company->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== ADD USER TO COMPANY ==========
    
    public function addUser(Request $request, Company $company)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'role_id' => 'required|exists:roles,id',
                'password' => 'required|string|min:8',
            ]);

            // Check user limit
            $availableSlots = $company->getAvailableUserSlots();
            if ($availableSlots <= 0 && $company->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company has reached maximum user limit. Please upgrade subscription.'
                ], 422);
            }

            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'role_id' => $validated['role_id'],
                'company_id' => $company->id,
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "User {$user->full_name} added successfully!",
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role_name' => $user->role?->display_name ?? $user->role?->name ?? 'No Role',
                    'role_badge' => $this->getRoleBadge($user->role?->name),
                    'created_at_formatted' => $user->created_at->format('M d, Y'),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add user: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== REMOVE USER FROM COMPANY ==========
    
    public function removeUser(Company $company, User $user)
    {
        try {
            if ($user->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not belong to this company.'
                ], 422);
            }

            $userName = $user->full_name;
            $user->update(['company_id' => null]);
            
            return response()->json([
                'success' => true,
                'message' => "User {$userName} removed from company."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateUserRole(Request $request, Company $company, User $user)
    {
        try {
            $validated = $request->validate([
                'role_id' => 'required|exists:roles,id',
            ]);

            if ($user->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not belong to this company.'
                ], 422);
            }

            $user->update(['role_id' => $validated['role_id']]);

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully!',
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'role_name' => $user->role?->display_name ?? $user->role?->name ?? 'No Role',
                    'role_badge' => $this->getRoleBadge($user->role?->name),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== ESTATE MANAGEMENT ==========
    
    public function getCompanyEstates(Company $company)
    {
        $estates = $company->estates()->withCount('units')->get();
        $totalUnits = $company->units()->count();
        
        return response()->json([
            'estates' => $estates->map(function($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'location' => $estate->location,
                    'total_units' => $estate->units_count,
                    'occupied_units' => $estate->units()->where('status', 'occupied')->count(),
                ];
            }),
            'total' => $estates->count(),
            'total_units' => $totalUnits,
        ]);
    }

    // ========== SUBSCRIPTION MANAGEMENT ==========
    
    public function getCompanySubscriptions(Company $company)
    {
        $subscriptions = $company->subscriptions()->with('plan')->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'subscriptions' => $subscriptions->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'plan_name' => $sub->plan->name ?? 'Unknown',
                    'billing_cycle' => $sub->billing_cycle,
                    'starts_at' => $sub->starts_at,
                    'ends_at' => $sub->ends_at,
                    'status' => $sub->status,
                    'amount' => $sub->billing_cycle === 'monthly' ? $sub->plan->price_monthly : $sub->plan->price_yearly,
                ];
            }),
        ]);
    }

    // ========== INVOICE MANAGEMENT ==========
    
    public function getCompanyInvoices(Company $company)
    {
        $invoices = \App\Models\Invoice::whereHas('tenancy.tenant.user', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })->with('tenancy.tenant.user', 'tenancy.unit')->orderBy('created_at', 'desc')->get();
        
        $totalRevenue = $invoices->where('status', 'paid')->sum('total_amount');
        
        return response()->json([
            'invoices' => $invoices->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'created_at' => $invoice->created_at,
                ];
            }),
            'total_revenue' => $totalRevenue,
        ]);
    }

    // ========== PAYMENT MANAGEMENT ==========
    
    public function getCompanyPayments(Company $company)
    {
        $payments = \App\Models\Payment::whereHas('invoice.tenancy.tenant.user', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })->with('invoice.tenancy.tenant.user')->orderBy('payment_datetime', 'desc')->get();
        
        return response()->json([
            'payments' => $payments->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'tenant_name' => $payment->invoice->tenancy->tenant->user->name ?? 'N/A',
                    'invoice_id' => $payment->invoice_id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_datetime' => $payment->payment_datetime,
                ];
            }),
        ]);
    }

    // ========== STAFF MANAGEMENT (Backward compatibility) ==========
    
    public function getCompanyStaff(Company $company)
    {
        return $this->getCompanyUsers($company);
    }

    public function addStaff(Request $request, Company $company)
    {
        return $this->addUser($request, $company);
    }

    public function removeStaff(Company $company, User $user)
    {
        return $this->removeUser($company, $user);
    }

    // ========== HELPER METHODS ==========
    
    private function getRoleBadge($roleName)
    {
        $badges = [
            'sysadmin' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            'super_admin' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'admin' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'property_manager' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'accountant' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'meter_reader' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            'cleaning_staff' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'maintenance' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'security' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
            'tenant' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
        ];
        
        return $badges[$roleName] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
    }
}