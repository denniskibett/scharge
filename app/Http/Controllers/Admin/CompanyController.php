<?php
// app/Http/Controllers/Admin/CompanyController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\Role;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\SubscriptionInvoice;
use App\Modules\Expenses\Models\Expense;

class CompanyController extends Controller
{
    /**
     * Display the companies index page
     */
    public function index()
    {
        return view('admin.companies.index');
    }
    
    /**
     * Get companies data for AJAX requests
     */
    public function getCompaniesData(Request $request)
    {
        $companies = Company::withCount(['users', 'units'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $stats = [
            'totalCompanies' => Company::count(),
            'activeCompanies' => Company::where('is_active', true)->count(),
            'totalUsers' => User::count(),
            'avgUsersPerCompany' => Company::count() > 0 
                ? round(User::count() / Company::count(), 1) 
                : 0,
        ];
        
        $formattedCompanies = $companies->map(function($company) {
            return [
                'id' => $company->id,
                'name' => $company->name,
                'registration_number' => $company->registration_number,
                'tax_id' => $company->tax_id,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'location' => $company->location ?? null,
                'is_active' => (bool) $company->is_active,
                'subscription_status' => $company->subscription_status ?? 'pending',
                'subscription_ends_at' => $company->subscription_ends_at,
                'users_count' => $company->users_count ?? $company->users()->count(),
                'units_count' => $company->units_count ?? $company->units()->count(),
                'tenants_count' => $this->getTenantCountForCompany($company),
                'created_at' => $company->created_at ? $company->created_at->toISOString() : null,
                'updated_at' => $company->updated_at ? $company->updated_at->toISOString() : null,
            ];
        });
        
        return response()->json([
            'success' => true,
            'companies' => $formattedCompanies,
            'stats' => $stats,
        ]);
    }
    
    /**
     * Get tenant count for a company
     */
    private function getTenantCountForCompany($company)
    {
        return Tenancy::whereHas('unit', function($q) use ($company) {
            $q->whereHas('estate', function($qe) use ($company) {
                $qe->where('company_id', $company->id);
            });
        })->where('status', 'active')->distinct('tenant_id')->count('tenant_id');
    }
    
    /**
     * Display the company show page (HTML view) or return JSON data
     */
    public function show($id)
    {
        $company = Company::with(['users.role', 'estates'])->findOrFail($id);
        
        // If this is an AJAX request expecting JSON, return the company data
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'id' => $company->id,
                'name' => $company->name,
                'registration_number' => $company->registration_number,
                'tax_id' => $company->tax_id,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'location' => $company->location,
                'is_active' => (bool) $company->is_active,
                'subscription_status' => $company->subscription_status ?? 'pending',
                'max_units' => $company->max_units,
                'max_tenants' => $company->max_tenants,
                'max_users' => $company->max_users,
                'created_at' => $company->created_at ? $company->created_at->toISOString() : null,
                'updated_at' => $company->updated_at ? $company->updated_at->toISOString() : null,
            ]);
        }
        
        // For HTML requests, return the view
        // Get current subscription
        $subscription = CompanySubscription::where('company_id', $company->id)
            ->where('status', 'active')
            ->with('plan')
            ->first();
        
        // Get available roles for staff selection (exclude sysadmin and tenant)
        $availableRoles = Role::whereNotIn('name', ['sysadmin', 'tenant'])->get();
        
        // Get stats for the company
        $stats = $this->getCompanyStats($company);
        
        // Get estates data for the view
        $estatesData = $this->getEstatesForView($company);
        
        // Get tenancies data for the view (active only)
        $tenanciesData = $this->getTenanciesForView($company);
        
        // Get subscription invoices for the view
        $subscriptionInvoices = $this->getSubscriptionInvoicesForView($company);
        
        // Get total invoice amount
        $totalInvoiceAmount = $this->getTotalInvoiceAmount($company);
        
        // Get plan ID for the generate button
        $planId = $subscription?->plan_id ?? null;
        
        // Get users for the staff table (non-tenant users with company_id)
        $usersData = $this->getUsersForView($company);
        
        return view('admin.companies.show', compact(
            'company', 
            'subscription', 
            'availableRoles', 
            'stats',
            'estatesData',
            'tenanciesData',
            'subscriptionInvoices',
            'totalInvoiceAmount',
            'planId',
            'usersData'
        ));
    }
    
    /**
     * Get users for view (staff only - non-tenant users with company_id)
     */
    private function getUsersForView($company)
    {
        return User::where('company_id', $company->id)
            ->whereHas('role', function($q) {
                $q->where('name', '!=', 'tenant');
            })
            ->with('role')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role' => $user->role->name ?? 'unknown',
                    'role_name' => ucfirst(str_replace('_', ' ', $user->role->name ?? 'unknown')),
                    'company_id' => $user->company_id,
                    'company_name' => $user->company->name ?? '-',
                    'is_active' => $user->status === 0,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                ];
            });
    }
    
    /**
     * Get total invoice amount for subscription invoices
     */
    private function getTotalInvoiceAmount($company)
    {
        return SubscriptionInvoice::whereHas('subscription', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->sum('amount') ?? 0;
    }
    
    /**
     * Get subscription invoices for view
     */
    private function getSubscriptionInvoicesForView($company)
    {
        return SubscriptionInvoice::whereHas('subscription', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with(['subscription.plan'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? '#'.$invoice->id,
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status ?? 'pending',
                'due_date' => $invoice->due_date ? $invoice->due_date->toISOString() : null,
                'created_at' => $invoice->created_at ? $invoice->created_at->toISOString() : null,
                'company_name' => $invoice->subscription?->company?->name ?? 'N/A',
                'plan_name' => $invoice->subscription?->plan?->name ?? 'N/A',
            ];
        });
    }
    
    /**
     * Get company statistics
     */
    private function getCompanyStats($company)
    {
        // Staff count (users with company_id that are NOT tenant role)
        $totalStaff = User::where('company_id', $company->id)
            ->whereHas('role', function($q) {
                $q->where('name', '!=', 'tenant');
            })
            ->count();
        
        // Estates count
        $totalEstates = Estate::where('company_id', $company->id)->count();
        
        // Units count
        $totalUnits = Unit::whereHas('estate', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })->count();
        
        // Active tenancies count (status = active)
        $totalTenants = Tenancy::whereHas('unit.estate', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })->where('status', 'active')->count();
        
        // Invoices count
        $totalInvoices = Invoice::whereHas('tenancy.unit.estate', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })->count();
        
        // Expenses count
        $totalExpenses = Expense::whereHas('estate', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->count();
        
        // Total revenue
        $totalRevenue = Payment::whereHas('invoice.tenancy.unit.estate', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })->sum('amount');
        
        return [
            'totalStaff' => $totalStaff,
            'totalEstates' => $totalEstates,
            'totalUnits' => $totalUnits,
            'totalTenants' => $totalTenants,
            'totalInvoices' => $totalInvoices,
            'totalExpenses' => $totalExpenses,
            'totalRevenue' => (float) $totalRevenue,
        ];
    }
    
    /**
     * Get estates for view
     */
    private function getEstatesForView($company)
    {
        return Estate::where('company_id', $company->id)
            ->withCount('units')
            ->get()
            ->map(function($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'location' => $estate->location ?? '-',
                    'units_count' => $estate->units_count,
                    'occupied_units' => $estate->units()->where('status', 'occupied')->count(),
                    'created_at' => $estate->created_at ? $estate->created_at->toISOString() : null,
                ];
            });
    }
    
    /**
     * Get tenancies for view - ACTIVE ONLY
     */
    private function getTenanciesForView($company)
    {
        return Tenancy::whereHas('unit.estate', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with(['tenant.user', 'unit.estate'])
        ->where('status', 'active')  // ONLY ACTIVE TENANCIES
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($tenancy) {
            return [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->user->name ?? 'N/A',
                'tenant_phone' => $tenancy->tenant->user->phone ?? '-',
                'unit_number' => $tenancy->unit->unit_number ?? 'N/A',
                'unit_type' => $tenancy->unit->unit_type ?? '-',
                'estate_name' => $tenancy->unit->estate->name ?? 'N/A',
                'estate_id' => $tenancy->unit->estate_id ?? null,
                'move_in_date' => $tenancy->move_in_date ? $tenancy->move_in_date->toISOString() : null,
                'move_out_date' => $tenancy->move_out_date ? $tenancy->move_out_date->toISOString() : null,
                'duration' => $tenancy->move_in_date ? $tenancy->move_in_date->diffInMonths(now()) . ' months' : '-',
                'status' => $tenancy->status ?? 'active',
            ];
        });
    }
    
    /**
     * Get estates data (AJAX)
     */
    public function getEstates($id)
    {
        $company = Company::findOrFail($id);
        
        $estates = Estate::where('company_id', $company->id)
            ->withCount('units')
            ->get()
            ->map(function($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'location' => $estate->location ?? '-',
                    'units_count' => $estate->units_count,
                    'occupied_units' => $estate->units()->where('status', 'occupied')->count(),
                    'created_at' => $estate->created_at ? $estate->created_at->toISOString() : null,
                ];
            });
        
        return response()->json([
            'success' => true,
            'estates' => $estates
        ]);
    }
    
    /**
     * Get tenancies data (AJAX) - ACTIVE ONLY
     */
    public function getTenancies($id)
    {
        $company = Company::findOrFail($id);
        
        $tenancies = Tenancy::whereHas('unit.estate', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with(['tenant.user', 'unit.estate'])
        ->where('status', 'active')  // ONLY ACTIVE TENANCIES
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($tenancy) {
            return [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->user->name ?? 'N/A',
                'tenant_phone' => $tenancy->tenant->user->phone ?? '-',
                'unit_number' => $tenancy->unit->unit_number ?? 'N/A',
                'unit_type' => $tenancy->unit->unit_type ?? '-',
                'estate_name' => $tenancy->unit->estate->name ?? 'N/A',
                'estate_id' => $tenancy->unit->estate_id ?? null,
                'move_in_date' => $tenancy->move_in_date ? $tenancy->move_in_date->toISOString() : null,
                'move_out_date' => $tenancy->move_out_date ? $tenancy->move_out_date->toISOString() : null,
                'duration' => $tenancy->move_in_date ? $tenancy->move_in_date->diffInMonths(now()) . ' months' : '-',
                'status' => $tenancy->status ?? 'active',
            ];
        });
        
        return response()->json([
            'success' => true,
            'tenancies' => $tenancies
        ]);
    }
    
    /**
     * Get users data (AJAX) - staff only (non-tenant users with company_id)
     */
    public function getCompanyUsers($id)
    {
        $company = Company::findOrFail($id);
        
        $users = User::where('company_id', $company->id)
            ->whereHas('role', function($q) {
                $q->where('name', '!=', 'tenant');
            })
            ->with('role')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role' => $user->role->name ?? 'unknown',
                    'role_name' => ucfirst(str_replace('_', ' ', $user->role->name ?? 'unknown')),
                    'company_id' => $user->company_id,
                    'company_name' => $user->company->name ?? '-',
                    'is_active' => $user->status === 0,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                ];
            });
        
        return response()->json([
            'success' => true,
            'users' => $users,
            'total' => $users->count(),
        ]);
    }
    
    /**
     * Get company staff (non-tenant users only) - alias for getCompanyUsers
     */
    public function getCompanyStaff($id)
    {
        return $this->getCompanyUsers($id);
    }
    
    /**
     * Get subscription invoices for the company (AJAX)
     */
    public function getCompanySubscriptionInvoices($id)
    {
        $company = Company::findOrFail($id);
        
        $invoices = SubscriptionInvoice::whereHas('subscription', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with(['subscription.plan'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? '#'.$invoice->id,
                'company_name' => $invoice->subscription?->company?->name ?? 'N/A',
                'plan_name' => $invoice->subscription?->plan?->name ?? 'N/A',
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status ?? 'pending',
                'due_date' => $invoice->due_date ? $invoice->due_date->toISOString() : null,
                'created_at' => $invoice->created_at ? $invoice->created_at->toISOString() : null,
            ];
        });
        
        $totalAmount = $invoices->sum('amount');
        
        return response()->json([
            'success' => true,
            'invoices' => $invoices,
            'total_amount' => (float) $totalAmount,
        ]);
    }
    
public function getCompanyExpenses($id)
{
    $company = Company::findOrFail($id);
    
    $expenses = Expense::whereHas('estate', function($q) use ($company) {
        $q->where('company_id', $company->id);
    })
    ->with(['estate', 'payee', 'category'])
    ->orderBy('expense_date', 'desc')
    ->get()
    ->map(function($expense) {
        return [
            'id' => $expense->id,
            'estate_name' => $expense->estate->name ?? 'N/A',
            'payee_name' => $expense->payee->name ?? 'N/A',
            'category_name' => $expense->category->name ?? 'Uncategorized',
            'amount' => (float) $expense->amount,
            'description' => $expense->description ?? '-',
            'expense_date' => $expense->expense_date ? $expense->expense_date->toISOString() : null,
            'status' => $expense->status ?? 'pending',
            'created_at' => $expense->created_at ? $expense->created_at->toISOString() : null,
        ];
    });
    
    $totalExpenses = $expenses->sum('amount');
    
    return response()->json([
        'success' => true,
        'expenses' => $expenses,
        'total_expenses' => (float) $totalExpenses,
    ]);
}
    
    /**
     * Get company subscriptions (active only)
     */
    public function getCompanySubscriptions($id)
    {
        $company = Company::findOrFail($id);
        
        $subscriptions = CompanySubscription::where('company_id', $company->id)
            ->where('status', 'active')  // ACTIVE ONLY
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($subscription) {
                $amount = 0;
                if ($subscription->plan) {
                    if ($subscription->billing_cycle === 'monthly') {
                        $amount = (float) ($subscription->plan->price_monthly ?? 0);
                    } else {
                        $amount = (float) ($subscription->plan->price_yearly ?? 0);
                    }
                }
                
                return [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->plan->name ?? 'N/A',
                    'billing_cycle' => $subscription->billing_cycle ?? 'monthly',
                    'starts_at' => $subscription->starts_at ? $subscription->starts_at->toISOString() : null,
                    'ends_at' => $subscription->ends_at ? $subscription->ends_at->toISOString() : null,
                    'status' => $subscription->status ?? 'inactive',
                    'amount' => $amount,
                ];
            });
        
        return response()->json([
            'success' => true,
            'subscriptions' => $subscriptions,
        ]);
    }
    
    /**
     * Get company invoices (tenant invoices)
     */
    public function getCompanyInvoices($id)
    {
        $company = Company::findOrFail($id);
        
        $invoices = Invoice::whereHas('tenancy.unit.estate', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->with(['tenancy.tenant.user', 'tenancy.unit'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'total_amount' => (float) $invoice->total_amount,
                    'status' => $invoice->status ?? 'draft',
                    'created_at' => $invoice->created_at ? $invoice->created_at->toISOString() : null,
                ];
            });
        
        $totalRevenue = $invoices->where('status', 'paid')->sum('total_amount');
        
        return response()->json([
            'success' => true,
            'invoices' => $invoices,
            'total_revenue' => (float) $totalRevenue,
        ]);
    }
    
    /**
     * Add a user to a company (staff)
     */
    public function addUser(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);
            
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:255',
                'role_id' => 'required|exists:roles,id',
                'password' => 'required|string|min:8',
            ]);
            
            $role = Role::find($validated['role_id']);
            if (in_array($role->name, ['sysadmin', 'tenant'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign ' . $role->name . ' role to company staff.',
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
                'status' => 0,
                'email_verified_at' => now(),
            ]);
            
            $roleBadges = [
                'super_admin' => 'bg-purple-100 text-purple-800',
                'admin' => 'bg-red-100 text-red-800',
                'property_manager' => 'bg-blue-100 text-blue-800',
                'accountant' => 'bg-green-100 text-green-800',
                'meter_reader' => 'bg-indigo-100 text-indigo-800',
                'maintenance' => 'bg-orange-100 text-orange-800',
                'security' => 'bg-red-100 text-red-800',
                'cleaning_staff' => 'bg-teal-100 text-teal-800',
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Staff member added successfully',
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role_name' => ucfirst(str_replace('_', ' ', $role->name)),
                    'role_badge' => $roleBadges[$role->name] ?? 'bg-gray-100 text-gray-800',
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
            Log::error('Failed to add user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add staff member: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove a user from a company
     */
    public function removeUser($companyId, $userId)
    {
        try {
            $company = Company::findOrFail($companyId);
            $user = User::findOrFail($userId);
            
            if ($user->company_id != $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not belong to this company',
                ], 422);
            }
            
            $userName = $user->name;
            $user->company_id = null;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => "Staff member {$userName} removed from company successfully",
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to remove user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove staff member: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update user role
     */
    public function updateUserRole(Request $request, $companyId, $userId)
    {
        try {
            $company = Company::findOrFail($companyId);
            $user = User::findOrFail($userId);
            
            $validated = $request->validate([
                'role_id' => 'required|exists:roles,id',
            ]);
            
            if ($user->company_id !== $company->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not belong to this company.'
                ], 422);
            }
            
            $role = Role::find($validated['role_id']);
            if ($role->name === 'sysadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign sysadmin role to company staff.'
                ], 422);
            }
            
            $user->update(['role_id' => $validated['role_id']]);
            
            $roleBadges = [
                'super_admin' => 'bg-purple-100 text-purple-800',
                'admin' => 'bg-red-100 text-red-800',
                'property_manager' => 'bg-blue-100 text-blue-800',
                'accountant' => 'bg-green-100 text-green-800',
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully!',
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->name,
                    'role_name' => ucfirst(str_replace('_', ' ', $role->name)),
                    'role_badge' => $roleBadges[$role->name] ?? 'bg-gray-100 text-gray-800',
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update user role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store a new company
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:companies,name',
                'registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:companies,email',
                'phone' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
                'subscription_status' => 'nullable|string|in:pending,active,expired,cancelled',
                'max_units' => 'nullable|integer',
                'max_tenants' => 'nullable|integer',
                'max_users' => 'nullable|integer',
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
                'subscription_status' => $validated['subscription_status'] ?? 'pending',
                'max_units' => $validated['max_units'] ?? null,
                'max_tenants' => $validated['max_tenants'] ?? null,
                'max_users' => $validated['max_users'] ?? null,
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
                    'status' => 0,
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
    
    /**
     * Update a company
     */
    public function update(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:companies,name,' . $id,
                'registration_number' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:companies,email,' . $id,
                'phone' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
                'subscription_status' => 'nullable|string|in:pending,active,expired,cancelled',
                'max_units' => 'nullable|integer',
                'max_tenants' => 'nullable|integer',
                'max_users' => 'nullable|integer',
            ]);
            
            $company->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'company' => $company->loadCount('users'),
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Company update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a company
     */
    public function destroy($id)
    {
        try {
            $company = Company::findOrFail($id);
            
            if ($company->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with existing users. Transfer or delete users first.',
                ], 422);
            }
            
            if ($company->estates()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with existing estates. Delete estates first.',
                ], 422);
            }
            
            $company->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Company deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get role badge CSS class
     */
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