<?php
// app/Http/Controllers/Admin/CompanyController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\Role;
use App\Models\Property;
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
     * Company ids this person may open here: null for a sysadmin (all); anyone else (account manager, sales) only the
     * companies they own (companies.owner_id), the same rule as App\Support\CompanyAccess.
     */
    private function ownedIds(): ?array
    {
        $user = auth()->user();
        if (! $user || $user->hasRole('sysadmin')) {
            return null;
        }

        return DB::table('companies')->where('owner_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Every /admin/companies/{company}/... address answers "not found" for a company that is not theirs. */
    public function callAction($method, $parameters)
    {
        $own = $this->ownedIds();
        $companyId = request()->route('company');
        if ($own !== null && $companyId !== null) {
            abort_unless(in_array((int) $companyId, $own, true), 404);
        }

        return $this->{$method}(...array_values($parameters));
    }

    /** Roles someone other than a sysadmin may hand out here: the company staff roles, never scharge staff roles. */
    private function mayGive(Role $role): bool
    {
        return auth()->user()?->hasRole('sysadmin') || array_key_exists($role->name, \App\Support\Access::COMPANY_ROLES);
    }

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
        $own = $this->ownedIds();
        $mine = fn () => Company::when($own !== null, fn ($q) => $q->whereIn('companies.id', $own ?: [0]));
        $companies = $mine()->withCount(['users', 'units'])
            ->orderBy('created_at', 'desc')
            ->get();

        $userCount = User::when($own !== null, fn ($q) => $q->whereIn('users.company_id', $own ?: [0]))->count();
        $stats = [
            'totalCompanies' => $mine()->count(),
            'activeCompanies' => $mine()->where('is_active', true)->count(),
            'totalUsers' => $userCount,
            'avgUsersPerCompany' => $mine()->count() > 0
                ? round($userCount / $mine()->count(), 1)
                : 0,
        ];
        
        $overdue = \App\Support\BillingLock::overdueCompanyIds(); // unpaid scharge bill past due (locked when the lock is on)
        $formattedCompanies = $companies->map(function($company) use ($overdue) {
            return [
                'id' => $company->id,
                'billing_overdue' => in_array((int) $company->id, $overdue, true),
                'billing_locked' => in_array((int) $company->id, $overdue, true) && \App\Support\BillingLock::enabled(),
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
        return Tenancy::whereIn('unit_id', $this->unitIdsQuery($company))->where('status', 'active')->distinct('tenant_id')->count('tenant_id');
    }
    
    /**
     * Display the company show page (HTML view) or return JSON data
     */
    public function show($id)
    {
        $company = Company::with(['users.roles', 'properties'])->findOrFail($id);
        
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
        
        // Get properties data for the view
        $propertiesData = $this->getPropertiesForView($company);
        
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

        // Subscriptions tab: only this company's own subscriptions (not the plan catalogue), and what each of its
        // properties costs under the active plan (units x price per unit, less the plan discount).
        $companySubscriptions = CompanySubscription::where('company_id', $company->id)->with('plan')
            ->orderByRaw("status = 'active' desc")->orderByDesc('starts_at')->get();
        $propertyBilling = \Illuminate\Support\Facades\DB::table('properties')->where('properties.company_id', $company->id)
            ->leftJoin('units', 'units.property_id', '=', 'properties.id')
            ->groupBy('properties.id', 'properties.name', 'properties.location')
            ->selectRaw("properties.id, properties.name, properties.location, COUNT(units.id) as units, SUM(units.status = 'occupied') as occupied")
            ->orderBy('properties.name')->get();
        // A host runs units in buildings it does not own: list those too, counting only its own units there.
        if (\Illuminate\Support\Facades\Schema::hasTable('unit_managements')) {
            $hosted = \Illuminate\Support\Facades\DB::table('unit_managements as m')->join('units', 'units.id', '=', 'm.unit_id')
                ->join('properties', 'properties.id', '=', 'units.property_id')
                ->where('m.company_id', $company->id)->where('m.status', 'active')
                ->where(fn ($q) => $q->whereNull('properties.company_id')->orWhere('properties.company_id', '!=', $company->id))
                ->groupBy('properties.id', 'properties.name', 'properties.location')
                ->selectRaw("properties.id, properties.name, properties.location, COUNT(DISTINCT units.id) as units,
                    COUNT(DISTINCT CASE WHEN units.status = 'occupied' THEN units.id END) as occupied, GROUP_CONCAT(DISTINCT units.unit_number) as hosted")
                ->orderBy('properties.name')->get();
            $propertyBilling = $propertyBilling->concat($hosted)->sortBy('name')->values();
        }

        return view('admin.companies.show', compact(
            'companySubscriptions',
            'propertyBilling',
            'company', 
            'subscription', 
            'availableRoles', 
            'stats',
            'propertiesData',
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
            ->whereHas('roles', function($q) {
                $q->where('name', '!=', 'tenant');
            })
            ->with('roles')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role' => $user->roles->first()?->name ?? 'unknown',
                    'role_name' => ucfirst(str_replace('_', ' ', $user->roles->first()?->name ?? 'unknown')),
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
            ->whereHas('roles', function($q) {
                $q->where('name', '!=', 'tenant');
            })
            ->count();
        
        // Properties count
        $units = $this->unitIdsQuery($company);
        $totalProperties = $this->propertiesOf($company)->count();
        
        // Units count
        $totalUnits = Unit::whereIn('id', $units)->count();
        
        // Active tenancies count (status = active)
        $totalTenants = Tenancy::whereIn('unit_id', $units)->where('status', 'active')->count();
        
        // Invoices count
        $totalInvoices = Invoice::whereHas('tenancy', fn ($q) => $q->whereIn('unit_id', $units))->count();
        
        // Expenses count
        $totalExpenses = Expense::whereHas('property', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->count();
        
        // Total revenue
        $totalRevenue = Payment::whereHas('invoice.tenancy', fn ($q) => $q->whereIn('unit_id', $units))->sum('amount');
        
        return [
            'totalStaff' => $totalStaff,
            'totalProperties' => $totalProperties,
            'totalUnits' => $totalUnits,
            'totalTenants' => $totalTenants,
            'totalInvoices' => $totalInvoices,
            'totalExpenses' => $totalExpenses,
            'totalRevenue' => (float) $totalRevenue,
        ];
    }
    
    /**
     * The units a company works with: units in buildings it owns, plus units it manages or hosts (BnB) in buildings
     * owned by others (unit_managements, active). One building exists once; hosts and managers link to it.
     */
    private function unitIdsQuery($company)
    {
        return fn ($q) => $q->select('units.id')->from('units')
            ->whereIn('units.property_id', fn ($p) => $p->select('id')->from('properties')->where('company_id', $company->id))
            ->orWhereIn('units.id', fn ($m) => $m->select('unit_id')->from('unit_managements')->where('company_id', $company->id)->where('status', 'active'));
    }

    /** Buildings the company owns or has managed/hosted units in, with how it is linked and its unit counts. */
    private function propertiesOf($company)
    {
        $managed = DB::table('unit_managements')->join('units', 'units.id', '=', 'unit_managements.unit_id')
            ->where('unit_managements.company_id', $company->id)->where('unit_managements.status', 'active')
            ->selectRaw('units.property_id, unit_managements.role, COUNT(DISTINCT units.id) as units')
            ->groupBy('units.property_id', 'unit_managements.role')->get()->groupBy('property_id');

        return Property::where('company_id', $company->id)->orWhereIn('id', $managed->keys())
            ->withCount('units')
            ->get()
            ->map(function ($property) use ($company, $managed) {
                $owns = (int) $property->company_id === (int) $company->id;
                $links = $managed->get($property->id, collect());
                $role = $owns ? 'Owner' : (collect(['bnb_host' => 'BnB host', 'manager' => 'Manager', 'owner' => 'Unit owner'])
                    ->only($links->pluck('role')->all())->values()->join(', ') ?: 'Manager');
                $unitIds = $owns ? null : DB::table('unit_managements')->where('company_id', $company->id)->where('status', 'active')
                    ->whereIn('unit_id', fn ($q) => $q->select('id')->from('units')->where('property_id', $property->id))->pluck('unit_id');
                $units = $owns ? $property->units_count : $unitIds->count();
                $occupied = $owns ? $property->units()->where('status', 'occupied')->count()
                    : Unit::whereIn('id', $unitIds)->where('status', 'occupied')->count();

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'location' => $property->location ?? '-',
                    'units_count' => $units,
                    'occupied_units' => $occupied,
                    'role' => $role,
                    'owned' => $owns,
                    'created_at' => $property->created_at ? $property->created_at->toISOString() : null,
                ];
            })->values();
    }

    /**
     * Get properties for view
     */
    private function getPropertiesForView($company)
    {
        return $this->propertiesOf($company);
    }
    
    /**
     * Get tenancies for view - ACTIVE ONLY
     */
    private function getTenanciesForView($company)
    {
        return Tenancy::whereIn('unit_id', $this->unitIdsQuery($company))
        ->with(['tenant.user', 'unit.property'])
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
                'property_name' => $tenancy->unit->property->name ?? 'N/A',
                'property_id' => $tenancy->unit->property_id ?? null,
                'move_in_date' => $tenancy->move_in_date ? $tenancy->move_in_date->toISOString() : null,
                'move_out_date' => $tenancy->move_out_date ? $tenancy->move_out_date->toISOString() : null,
                'duration' => $tenancy->move_in_date ? $tenancy->move_in_date->diffInMonths(now()) . ' months' : '-',
                'status' => $tenancy->status ?? 'active',
            ];
        });
    }
    
    /**
     * Get properties data (AJAX)
     */
    public function getProperties($id)
    {
        $company = Company::findOrFail($id);
        
        $properties = $this->propertiesOf($company);
        
        return response()->json([
            'success' => true,
            'properties' => $properties
        ]);
    }
    
    /**
     * Get tenancies data (AJAX) - ACTIVE ONLY
     */
    public function getTenancies($id)
    {
        $company = Company::findOrFail($id);
        
        $tenancies = Tenancy::whereIn('unit_id', $this->unitIdsQuery($company))
        ->with(['tenant.user', 'unit.property'])
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
                'property_name' => $tenancy->unit->property->name ?? 'N/A',
                'property_id' => $tenancy->unit->property_id ?? null,
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
            ->whereHas('roles', function($q) {
                $q->where('name', '!=', 'tenant');
            })
            ->with('roles')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '-',
                    'role' => $user->roles->first()?->name ?? 'unknown',
                    'role_name' => ucfirst(str_replace('_', ' ', $user->roles->first()?->name ?? 'unknown')),
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
    
    $expenses = Expense::whereHas('property', function($q) use ($company) {
        $q->where('company_id', $company->id);
    })
    ->with(['company', 'property.company', 'payee', 'category'])
    ->orderBy('expense_date', 'desc')
    ->get()
    ->map(function($expense) {
        return [
            'id' => $expense->id,
            'property_name' => $expense->property->name ?? 'N/A',
            'payee_name' => $expense->payee->name ?? 'N/A',
            'label' => $expense->label,
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
        
        $invoices = Invoice::whereHas('tenancy.unit.property', function($q) use ($company) {
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
            if (in_array($role->name, ['sysadmin', 'tenant']) || ! $this->mayGive($role)) {
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
            if ($role->name === 'sysadmin' || ! $this->mayGive($role)) {
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
            
            // Create the company
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
                // An account manager's new company goes into their own book (sysadmins assign an owner later).
                'owner_id' => auth()->user()?->hasRole('sysadmin') ? null : auth()->id(),
            ]);
            
            // Log company creation
            Log::info('Company created', ['company_id' => $company->id, 'name' => $company->name]);
            
            $adminCredentials = null;
            
            // Create admin user if admin_user data is provided
            if ($request->has('admin_user') && !empty($request->admin_user['first_name'])) {
                // Get or create admin role
                $adminRole = Role::where('name', 'admin')->first();
                if (!$adminRole) {
                    Log::warning('Admin role not found, creating it...');
                    $adminRole = Role::create([
                        'name' => 'admin',
                        'display_name' => 'Administrator',
                        'description' => 'System Administrator'
                    ]);
                }
                
                // Verify the role exists and has the correct ID
                Log::info('Admin role found', ['role_id' => $adminRole->id, 'role_name' => $adminRole->name]);
                
                // Check if email already exists
                $existingUser = User::where('email', $request->admin_user['email'])->first();
                if ($existingUser) {
                    throw new \Exception('User with email ' . $request->admin_user['email'] . ' already exists.');
                }
                
                // Create the admin user
                $user = User::create([
                    'first_name' => $request->admin_user['first_name'],
                    'last_name' => $request->admin_user['last_name'],
                    'name' => $request->admin_user['first_name'] . ' ' . $request->admin_user['last_name'],
                    'email' => $request->admin_user['email'],
                    'phone' => $request->admin_user['phone'] ?? null,
                    'role_id' => $adminRole->id, // This will be 2 if admin role exists with ID 2
                    'company_id' => $company->id,
                    'password' => Hash::make($request->admin_user['password']),
                    'email_verified_at' => now(),
                    'status' => 0, // Active status (0 = active, 1 = inactive)
                ]);
                
                // Log user creation
                Log::info('Admin user created', [
                    'user_id' => $user->id, 
                    'email' => $user->email,
                    'company_id' => $user->company_id,
                    'role_id' => $user->roles()->pluck('name') 
                ]);
                
                // Verify the user was created with the correct company_id
                $verifiedUser = User::find($user->id);
                Log::info('Verified user data', [
                    'user_id' => $verifiedUser->id,
                    'company_id' => $verifiedUser->company_id,
                    'role_id' => $verifiedUser->role_id,
                    'company_id_matches' => ($verifiedUser->company_id == $company->id)
                ]);
                
                if ($verifiedUser->company_id != $company->id) {
                    throw new \Exception('Failed to assign company to user. Expected company_id ' . $company->id . ' but got ' . $verifiedUser->company_id);
                }
                
                $adminCredentials = [
                    'email' => $request->admin_user['email'],
                    'password' => $request->admin_user['password']
                ];
            } else {
                Log::info('No admin user data provided, skipping user creation');
            }
            
            DB::commit();
            
            // Reload company with counts
            $company->loadCount('users');
            
            return response()->json([
                'success' => true,
                'message' => 'Company created successfully!' . ($adminCredentials ? ' Admin user account has been created.' : ''),
                'company' => $company,
                'credentials' => $adminCredentials,
                'debug' => [
                    'company_id' => $company->id,
                    'user_created' => $adminCredentials ? true : false,
                    'role_id_used' => isset($adminRole) ? $adminRole->id : null,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
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
            
            if ($company->properties()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with existing properties. Delete properties first.',
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