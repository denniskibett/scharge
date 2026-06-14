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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;

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
        $companies = Company::withCount(['users', 'units', 'tenantUsers'])
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
        
        // Format companies data for the frontend
        $formattedCompanies = $companies->map(function($company) {
            return [
                'id' => $company->id,
                'name' => $company->name,
                'registration_number' => $company->registration_number,
                'tax_id' => $company->tax_id,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'location' => $company->location,
                'is_active' => (bool) $company->is_active,
                'subscription_status' => $company->subscription_status,
                'subscription_ends_at' => $company->subscription_ends_at,
                'users_count' => $company->users_count ?? $company->users()->count(),
                'units_count' => $company->units_count ?? $company->units()->count(),
                'tenants_count' => $company->tenantUsers()->count(),
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
     * Display the company show page (HTML view)
     */
    public function show($id)
    {
        $company = Company::with(['users.role', 'estates'])->findOrFail($id);
        
        // Get current subscription
        $subscription = CompanySubscription::where('company_id', $company->id)
            ->where('status', 'active')
            ->with('plan')
            ->first();
        
        // Get available roles for staff selection (exclude sysadmin and tenant)
        $availableRoles = Role::whereNotIn('name', ['sysadmin', 'tenant'])->get();
        
        return view('admin.companies.show', compact('company', 'subscription', 'availableRoles'));
    }
    
    /**
     * Get company estates with their units and tenants (for AJAX)
     */
    public function getCompanyEstatesWithTenants($id)
    {
        $company = Company::findOrFail($id);
        
        $estates = Estate::where('company_id', $company->id)
            ->with(['units' => function($q) {
                $q->with(['currentTenant.user']);
            }])
            ->get()
            ->map(function($estate) {
                $totalUnits = $estate->units->count();
                $occupiedUnits = $estate->units->where('status', 'occupied')->count();
                $totalMonthlyRent = $estate->units->sum('rent_amount');
                
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'location' => $estate->location,
                    'total_units' => $totalUnits,
                    'occupied_units' => $occupiedUnits,
                    'occupancy_rate' => $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0,
                    'total_monthly_rent' => $totalMonthlyRent,
                    'units' => $estate->units->map(function($unit) {
                        $tenant = $unit->currentTenant;
                        return [
                            'id' => $unit->id,
                            'unit_number' => $unit->unit_number,
                            'unit_type' => $unit->unit_type,
                            'rent_amount' => $unit->rent_amount,
                            'status' => $unit->status,
                            'current_tenant' => $tenant ? [
                                'name' => $tenant->user->name ?? 'N/A',
                                'email' => $tenant->user->email ?? 'N/A',
                                'phone' => $tenant->user->phone ?? 'N/A',
                            ] : null,
                        ];
                    }),
                ];
            });
        
        $totalUnits = $estates->sum('total_units');
        $totalTenants = $estates->sum(function($estate) {
            return $estate->units->filter(function($unit) {
                return !is_null($unit['current_tenant']);
            })->count();
        });
        
        return response()->json([
            'success' => true,
            'estates' => $estates,
            'total_units' => $totalUnits,
            'total_tenants' => $totalTenants,
        ]);
    }
    
    /**
     * Get company staff (non-tenant users)
     */
    public function getCompanyStaff($id)
    {
        $company = Company::findOrFail($id);
        
        $staff = $company->users()
            ->with('role')
            ->whereHas('role', function($q) {
                $q->whereNotIn('name', ['tenant']);
            })
            ->get()
            ->map(function($user) {
                $roleBadge = [
                    'super_admin' => 'bg-purple-100 text-purple-800',
                    'admin' => 'bg-blue-100 text-blue-800',
                    'property_manager' => 'bg-green-100 text-green-800',
                    'accountant' => 'bg-yellow-100 text-yellow-800',
                    'meter_reader' => 'bg-indigo-100 text-indigo-800',
                    'maintenance' => 'bg-orange-100 text-orange-800',
                    'security' => 'bg-red-100 text-red-800',
                    'cleaning_staff' => 'bg-teal-100 text-teal-800',
                ];
                
                $roleName = $user->role->name ?? 'unknown';
                
                return [
                    'id' => $user->id,
                    'full_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role_name' => ucfirst(str_replace('_', ' ', $roleName)),
                    'role_badge' => $roleBadge[$roleName] ?? 'bg-gray-100 text-gray-800',
                    'created_at_formatted' => $user->created_at ? $user->created_at->format('M d, Y') : '-',
                ];
            });
        
        return response()->json([
            'success' => true,
            'staff' => $staff,
            'total' => $staff->count(),
        ]);
    }
    
    /**
     * Get company subscriptions history
     */
    public function getCompanySubscriptions($id)
    {
        $company = Company::findOrFail($id);
        
        $subscriptions = CompanySubscription::where('company_id', $company->id)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($subscription) {
                $features = $subscription->plan ? ($subscription->plan->features_json ?? []) : [];
                $pricePerUnit = $features['price_per_unit'] ?? 0;
                $unitCount = Unit::where('company_id', $subscription->company_id)->count();
                
                $amount = 0;
                if ($features['pricing_type'] ?? 'fixed' === 'per_unit') {
                    $amount = $pricePerUnit * $unitCount;
                    if ($subscription->billing_cycle === 'yearly') {
                        $amount = $amount * 12 * 0.9;
                    }
                } else {
                    $amount = $subscription->billing_cycle === 'monthly' 
                        ? ($subscription->plan->price_monthly ?? 0)
                        : ($subscription->plan->price_yearly ?? 0);
                }
                
                return [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->plan->name ?? 'N/A',
                    'billing_cycle' => $subscription->billing_cycle,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                    'status' => $subscription->status,
                    'amount' => $amount,
                ];
            });
        
        return response()->json([
            'success' => true,
            'subscriptions' => $subscriptions,
        ]);
    }
    
    /**
     * Get company invoices
     */
    public function getCompanyInvoices($id)
    {
        $company = Company::findOrFail($id);
        
        $invoices = Invoice::whereHas('tenancy.unit', function($q) use ($company) {
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
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'created_at' => $invoice->created_at,
                ];
            });
        
        $totalRevenue = $invoices->sum('total_amount');
        
        return response()->json([
            'success' => true,
            'invoices' => $invoices,
            'total_revenue' => $totalRevenue,
        ]);
    }
    
    /**
     * Get company payments
     */
    public function getCompanyPayments($id)
    {
        $company = Company::findOrFail($id);
        
        $payments = Payment::whereHas('invoice.tenancy.unit', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'tenant_name' => $payment->invoice->tenancy->tenant->user->name ?? 'N/A',
                    'invoice_id' => $payment->invoice_id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_datetime' => $payment->created_at,
                ];
            });
        
        return response()->json([
            'success' => true,
            'payments' => $payments,
        ]);
    }
    
    /**
     * Store a new company
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
            'subscription_status' => 'nullable|string|in:pending,active,expired,cancelled',
            'max_units' => 'nullable|integer',
            'max_tenants' => 'nullable|integer',
            'max_users' => 'nullable|integer',
        ]);
        
        $company = Company::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Company created successfully',
            'company' => $company,
        ]);
    }
    
    /**
     * Update a company
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
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
            'company' => $company,
        ]);
    }
    
    /**
     * Delete a company
     */
    public function destroy($id)
    {
        $company = Company::findOrFail($id);
        
        // Check if company has users before deleting
        if ($company->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete company with existing users. Transfer or delete users first.',
            ], 422);
        }
        
        $company->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully',
        ]);
    }
    
    /**
     * Add a user to a company
     */
    public function addUser(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'password' => 'required|string|min:8',
        ]);
        
        $user = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role_id' => $validated['role_id'],
            'company_id' => $company->id,
            'password' => bcrypt($validated['password']),
            'status' => 0, // Active
            'email_verified_at' => now(), // Auto-verify for staff
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Staff member added successfully',
            'user' => $user,
        ]);
    }
    
    /**
     * Remove a user from a company
     */
    public function removeUser($companyId, $userId)
    {
        $company = Company::findOrFail($companyId);
        $user = User::findOrFail($userId);
        
        if ($user->company_id != $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to this company',
            ], 422);
        }
        
        $user->company_id = null;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Staff member removed from company successfully',
        ]);
    }
}