<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Maintenance;
use App\Models\SecurityLog;
use App\Models\WaterReading;
use App\Models\Company;
use App\Models\Estate;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Check if user is ready (email verified, active status, and has company if not sysadmin)
        if (!$this->isUserReady($user)) {
            return $this->pendingVerificationView($user);
        }
        
        // Route to role-specific dashboard
        $roleName = $user->role ? $user->role->name : 'guest';
        
        switch ($roleName) {
            case 'sysadmin':
                return $this->sysAdminDashboard();
            case 'super_admin':
            case 'admin':
                return $this->adminDashboard();
            case 'property_manager':
                return $this->propertyManagerDashboard();
            case 'accountant':
                return $this->accountantDashboard();
            case 'tenant':
                return $this->tenantDashboard();
            case 'meter_reader':
                return $this->meterReaderDashboard();
            case 'maintenance':
                return $this->maintenanceDashboard();
            case 'security':
                return $this->securityDashboard();
            case 'cleaning_staff':
                return $this->cleaningStaffDashboard();
            default:
                return $this->guestDashboard();
        }
    }
    
    private function isUserReady($user)
    {
        // Must have email verified
        if (is_null($user->email_verified_at)) {
            return false;
        }
        
        // Must be active (status = 0)
        if ($user->status == 1) {
            return false;
        }
        
        // Sysadmin doesn't need company assignment - they can access the dashboard
        if ($user->hasRole('sysadmin')) {
            return true;
        }
        
        // Other roles must have a company assigned
        return !is_null($user->company_id);
    }

    private function getUserNotReadyReason($user)
    {
        if (is_null($user->email_verified_at)) {
            return ['message' => 'Please verify your email address to access the dashboard. Check your inbox for the verification link.', 'status' => 'unverified'];
        }
        
        if ($user->status == 1) {
            return ['message' => 'Your account has been deactivated. Please contact your system administrator.', 'status' => 'inactive'];
        }
        
        // For sysadmin, even without company, they can proceed (handled in isUserReady)
        if ($user->hasRole('sysadmin')) {
            return ['message' => 'System Administrator access granted.', 'status' => 'sysadmin'];
        }
        
        if (!$user->hasRole('sysadmin') && is_null($user->company_id)) {
            return ['message' => 'Your account is not assigned to any company. Please contact your system administrator.', 'status' => 'no_company'];
        }
        
        return ['message' => 'Your account requires further configuration. Please contact support.', 'status' => 'unknown'];
    }
    
    
    /**
     * Show pending verification view with appropriate message
     */
    private function pendingVerificationView($user)
    {
        $reason = $this->getUserNotReadyReason($user);
        $status = $reason['status'];
        $message = $reason['message'];

        return view('partials.dashboard.pending', compact('user', 'status', 'message'));
    }
    
    /**
     * SYS ADMIN DASHBOARD
     */
    private function sysAdminDashboard()
    {
        $user = auth()->user();
        
        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('subscription_status', 'active')->count(),
            'pending_companies' => Company::where('subscription_status', 'pending')->count(),
            'total_users' => User::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'pending_verification_users' => User::whereNull('email_verified_at')->count(),
            'active_users' => User::where('status', 0)->count(),
            'inactive_users' => User::where('status', 1)->count(),
            'total_units' => Unit::count(),
            'total_tenants' => Tenant::count(),
            'total_revenue' => Payment::sum('amount'),
            'total_invoices' => Invoice::count(),
            'total_maintenance' => Maintenance::count(),
        ];
        
        $companies = Company::withCount(['users', 'units', 'tenantUsers'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Users pending email verification
        $pendingUsers = User::whereNull('email_verified_at')
            ->with('role')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_name' => $user->role->name ?? 'N/A',
                    'company_name' => $user->company->name ?? null,
                    'created_at_formatted' => $user->created_at->format('M d, Y'),
                ];
            });
        
        // Users pending company assignment (verified but no company)
        $pendingCompanyUsers = User::whereNotNull('email_verified_at')
            ->where('status', 0)
            ->whereNull('company_id')
            ->whereDoesntHave('role', function($q) {
                $q->where('name', 'sysadmin');
            })
            ->with('role')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_name' => $user->role->name ?? 'N/A',
                    'created_at_formatted' => $user->created_at->format('M d, Y'),
                ];
            });
        
        // Inactive users
        $inactiveUsers = User::where('status', 1)
            ->with('role', 'company')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_name' => $user->role->name ?? 'N/A',
                    'company_name' => $user->company->name ?? null,
                    'status' => $user->status,
                    'updated_at_formatted' => $user->updated_at->format('M d, Y'),
                ];
            });
        
        return view('partials.dashboard.sys-admin', compact(
            'user', 'stats', 'companies', 
            'pendingUsers', 'pendingCompanyUsers', 'inactiveUsers'
        ));
    }
    
    /**
     * ADMIN / SUPER ADMIN DASHBOARD
     */
    private function adminDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        // If company is null, redirect to pending view
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        // Get all invoices for the dashboard (filtered by company)
        $invoices = Invoice::with('tenancy.tenant.user', 'tenancy.unit', 'items', 'payments')
            ->whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get active tenancies for bulk invoice creation
        $activeTenancies = Tenancy::where('status', 'active')
            ->whereHas('unit', fn($q) => $q->where('company_id', $company->id))
            ->with('tenant.user', 'unit')
            ->get();
        
        // Get users for tenant selection
        $users = Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->with('user')
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => optional($tenant->user)->name ?? 'N/A',
                ];
            });
        
        // Get payment invoices for dropdown
        $paymentInvoices = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with('items', 'tenancy.tenant.user')
            ->get()
            ->map(function ($invoice) {
                $payerName = optional(optional($invoice->tenancy)->tenant->user)->name ?? 'N/A';
                
                $itemsLabel = $invoice->items->count()
                    ? $invoice->items
                        ->map(fn ($item) =>
                            ($item->item_type ?? 'Item') .
                            ($item->description ? ' (' . $item->description . ')' : '')
                        )
                        ->implode(', ')
                    : '-';
                
                return [
                    'id' => $invoice->id,
                    'label' => $payerName . ' - Invoice #' . ($invoice->invoice_number ?? $invoice->id) . ': ' . $itemsLabel,
                    'payer_name' => $payerName,
                ];
            });
        
        // Map invoices for the table
        $mappedInvoices = $invoices->map(function($invoice) {
            $lastPayment = $invoice->payments->last();
            
            return [
                'id' => $invoice->id,
                'tenant_name' => $invoice->tenancy->tenant->user->name ?? '-',
                'tenant_id' => $invoice->tenancy->tenant_id ?? null,
                'unit_number' => $invoice->tenancy->unit->unit_number ?? '-',
                'unit_id' => $invoice->tenancy->unit_id ?? null,
                'invoice_type' => $invoice->invoice_type,
                'billing_month' => $invoice->billing_month,
                'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'tenancy_id' => $invoice->tenancy_id,
                'created_at' => $invoice->created_at ? $invoice->created_at->getTimestamp() * 1000 : null,
                'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                'payer_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                'payment_method' => $lastPayment->payment_method ?? 'N/A',
                'payment_datetime' => $lastPayment->payment_datetime ?? null,
                'paid_amount' => (float) $invoice->payments->sum('amount'),
                'balance' => (float) ($invoice->total_amount - $invoice->payments->sum('amount')),
                'items' => $invoice->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'item_type' => $item->item_type,
                        'amount' => $item->amount,
                    ];
                }),
            ];
        });
        
        // Map active tenancies for bulk creation
        $mappedActiveTenancies = $activeTenancies->map(function($tenancy) {
            return [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->user->name ?? 'Unknown',
                'unit_number' => $tenancy->unit->unit_number ?? 'No Unit',
                'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                'move_in_date' => $tenancy->move_in_date,
                'move_out_date' => $tenancy->move_out_date,
            ];
        });
        
        // Get estates for bulk mode dropdown
        $estates = Estate::where('company_id', $company->id)->orderBy('name')->get();
        
        // Get all units for maintenance modal
        $units = Unit::where('company_id', $company->id)->with('estate')->get();
        
        // Stats for cards
        $stats = [
            'total_units' => Unit::where('company_id', $company->id)->count(),
            'occupied_units' => Unit::where('company_id', $company->id)->where('status', 'occupied')->count(),
            'vacant_units' => Unit::where('company_id', $company->id)->where('status', 'vacant')->count(),
            'total_tenants' => Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))->count(),
            'active_tenancies' => Tenancy::whereHas('unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'active')->count(),
            'total_revenue' => Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount'),
            'total_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->count(),
            'paid_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'paid')->count(),
            'unpaid_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'unpaid')->count(),
            'occupancy_rate' => $this->calculateOccupancyRate($company->id),
            'total_consumption' => Unit::where('company_id', $company->id)->sum(DB::raw('GREATEST(0, COALESCE(current_water_reading, 0) - COALESCE(previous_water_reading, 0))')),
            'outstanding_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->whereIn('status', ['unpaid', 'partial'])->sum('total_amount'),
            'collection_rate' => $this->calculateCollectionRate($company->id),
            'units_needing_reading' => Unit::where('company_id', $company->id)->where('status', 'occupied')
                ->where(fn($q) => $q->whereNull('last_reading_date')->orWhere('last_reading_date', '<=', Carbon::now()->subDays(30)))->count(),
        ];
        
        // Get tenant-specific data for cards
        $outstandingBalance = 0;
        $totalPaid = 0;
        $tenant = $user->tenant;
        if ($tenant && $tenant->activeTenancy) {
            $tenantInvoices = Invoice::where('tenancy_id', $tenant->activeTenancy->id)->get();
            $totalPaid = Payment::whereHas('invoice', function($q) use ($tenant) {
                $q->where('tenancy_id', $tenant->activeTenancy->id);
            })->sum('amount');
            $outstandingBalance = $tenantInvoices->sum('total_amount') - $totalPaid;
        }
        
        // Calculate totals for overview cards
        $totalDraft = $invoices->where('status', 'draft')->sum('total_amount');
        $totalUnpaid = $invoices->where('status', 'unpaid')->sum('total_amount');
        $totalPartial = $invoices->where('status', 'partial')->sum('total_amount');
        $totalPaidAll = $invoices->where('status', 'paid')->sum('total_amount');
        
        // Get tenancies needing move-in invoices
        $tenanciesNeedingMoveInInvoices = $activeTenancies->filter(function($tenancy) use ($invoices) {
            $hasMoveInInvoice = $invoices->where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'move_in')
                ->count() > 0;
            return !$hasMoveInInvoice && $tenancy->move_in_date;
        });
        
        // Recent activity data
        $recentInvoices = $invoices->take(5);
        $recentPayments = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with('invoice.tenancy.tenant.user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // Unit statistics
        $totalUnits = Unit::where('company_id', $company->id)->count();
        $occupiedUnits = Unit::where('company_id', $company->id)->where('status', 'occupied')->count();
        $availableUnits = Unit::where('company_id', $company->id)->where('status', 'available')->count();
        
        // Payment statistics for charts
        $monthlyRevenue = $this->getMonthlyRevenueForCompany($company->id);
        $paymentMethods = $this->getPaymentMethodStatsForCompany($company->id);
        
        // Get role-specific data
        $roleData = $this->getRoleSpecificData($user, $company);
        
        // Current unit for tenant
        $currentUnit = null;
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $currentUnit = $user->tenant->activeTenancy->unit->load('estate');
        }
        
        return view('dashboard', compact(
            'user', 'company', 'stats', 'mappedInvoices', 'mappedActiveTenancies',
            'activeTenancies', 'users', 'paymentInvoices', 'totalDraft', 'totalUnpaid',
            'totalPartial', 'totalPaidAll', 'tenanciesNeedingMoveInInvoices',
            'recentInvoices', 'recentPayments', 'totalUnits', 'occupiedUnits',
            'availableUnits', 'monthlyRevenue', 'paymentMethods', 'roleData',
            'outstandingBalance', 'totalPaid', 'units', 'currentUnit', 'estates'
        ));
    }


    private function getRoleSpecificData($user, $company = null)
{
    $roleName = $user->role ? $user->role->name : 'guest';
    
    switch ($roleName) {
        case 'sysadmin':
            return $this->getSysAdminData();
        case 'super_admin':
        case 'admin':
            return $this->getAdminData($company);
        case 'property_manager':
            return $this->getPropertyManagerData($company);
        case 'accountant':
            return $this->getAccountantData($company);
        case 'tenant':
            return $this->getTenantData($user);
        case 'meter_reader':
            return $this->getMeterReaderData($company);
        case 'cleaning_staff':
            return $this->getCleaningStaffData();
        case 'maintenance':
            return $this->getMaintenanceData($company);
        case 'security':
            return $this->getSecurityData($company);
        default:
            return ['type' => 'guest'];
    }
}
    
    /**
     * PROPERTY MANAGER DASHBOARD
     */
    private function propertyManagerDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        $stats = [
            'total_units' => Unit::where('company_id', $company->id)->count(),
            'occupied_units' => Unit::where('company_id', $company->id)->where('status', 'occupied')->count(),
            'vacant_units' => Unit::where('company_id', $company->id)->where('status', 'vacant')->count(),
            'occupancy_rate' => $this->calculateOccupancyRate($company->id),
            'units_needing_reading' => Unit::where('company_id', $company->id)->where('status', 'occupied')
                ->where(fn($q) => $q->whereNull('last_reading_date')->orWhere('last_reading_date', '<=', Carbon::now()->subDays(30)))->count(),
            'open_maintenance' => Maintenance::where('company_id', $company->id)
                ->whereIn('status', ['pending', 'open'])->count(),
        ];
        
        $roleData = $this->getRoleSpecificData($user, $company);
        
        // Required for the view
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * ACCOUNTANT DASHBOARD
     */
    private function accountantDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        $stats = [
            'total_revenue' => Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount'),
            'total_expenses' => Expense::where('company_id', $company->id)->sum('amount'),
            'net_income' => Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount') - Expense::where('company_id', $company->id)->sum('amount'),
            'total_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->count(),
            'paid_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'paid')->count(),
            'unpaid_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'unpaid')->count(),
            'outstanding_amount' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->whereIn('status', ['unpaid', 'partial'])->sum('total_amount'),
            'collection_rate' => $this->calculateCollectionRate($company->id),
        ];
        
        $roleData = $this->getRoleSpecificData($user, $company);
        
        $monthlyRevenue = $this->getMonthlyRevenueForCompany($company->id);
        $paymentMethods = $this->getPaymentMethodStatsForCompany($company->id);
        
        // Required for the view
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'monthlyRevenue', 'paymentMethods', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * TENANT DASHBOARD
     */
    private function tenantDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        $stats = $this->getCommonStats($user);
        $roleData = $this->getRoleSpecificData($user, $company);
        
        $outstandingBalance = $roleData['outstandingBalance'] ?? 0;
        $totalPaid = $roleData['totalPaid'] ?? 0;
        
        // Get units, estates, currentUnit from roleData
        $units = $roleData['units'] ?? [];
        $estates = $roleData['estates'] ?? [];
        $currentUnit = $roleData['currentUnit'] ?? null;
        
        // Required for the view
        $mappedActiveTenancies = collect();
        
        return view('dashboard', compact(
            'user', 'company', 'stats', 'roleData', 
            'outstandingBalance', 'totalPaid', 'mappedActiveTenancies',
            'units', 'estates', 'currentUnit'
        ));
    }
    
    /**
     * METER READER DASHBOARD
     */
    private function meterReaderDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        $stats = $this->getCommonStats($user);
        $roleData = $this->getRoleSpecificData($user, $company);
        
        // Required for the view
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * MAINTENANCE DASHBOARD
     */
    private function maintenanceDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        $stats = $this->getCommonStats($user);
        $roleData = $this->getRoleSpecificData($user, $company);
        
        // Required for the view
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * SECURITY DASHBOARD
     */
    private function securityDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        $stats = $this->getCommonStats($user);
        $roleData = $this->getRoleSpecificData($user, $company);
        
        // Required for the view
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * CLEANING STAFF DASHBOARD
     */
    private function cleaningStaffDashboard()
    {
        $user = auth()->user();
        $company = $user->company;
        
        if (!$company) {
            return $this->pendingVerificationView($user);
        }
        
        $stats = $this->getCommonStats($user);
        $roleData = $this->getRoleSpecificData($user, $company);
        
        // Required for the view
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * GUEST DASHBOARD
     */
    private function guestDashboard()
    {
        $user = auth()->user();
        return view('dashboard.guest', compact('user'));
    }
    
private function getTenantData($user)
{
    $tenant = $user->tenant;
    $company = $user->company;
    
    if (!$tenant || !$tenant->activeTenancy) {
        // Get units and estates for the company (for maintenance modal even without active tenancy)
        $units = [];
        $estates = [];
        $currentUnit = null;
        
        if ($company) {
            $units = Unit::where('company_id', $company->id)
                ->with('estate')
                ->get()
                ->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'unit_number' => $unit->unit_number,
                        'unit_type' => $unit->unit_type,
                        'estate_id' => $unit->estate_id,
                        'estate' => $unit->estate ? ['name' => $unit->estate->name] : null,
                    ];
                });
            
            $estates = Estate::where('company_id', $company->id)
                ->orderBy('name')
                ->get()
                ->map(function($estate) {
                    return [
                        'id' => $estate->id,
                        'name' => $estate->name,
                    ];
                });
        }
        
        return [
            'type' => 'tenant',
            'has_tenancy' => false,
            'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->user->name ?? 'N/A'] : null,
            'invoices' => [],
            'payments' => [],
            'waterInfo' => [
                'previous_reading' => 0,
                'current_reading' => 0,
                'consumption' => 0,
                'rate' => 50,
                'billing_type' => 'consumption',
                'last_reading_date' => null
            ],
            'outstandingBalance' => 0,
            'totalPaid' => 0,
            'accessLogs' => [],
            'maintenanceRequests' => [],
            'units' => $units,
            'estates' => $estates,
            'currentUnit' => $currentUnit
        ];
    }
    
    $activeTenancy = $tenant->activeTenancy;
    
    // Get invoices
    $invoices = Invoice::where('tenancy_id', $activeTenancy->id)
        ->with('items', 'payments')
        ->orderBy('billing_month', 'desc')
        ->get()
        ->map(function ($invoice) use ($activeTenancy) {
            $totalPaid = (float) $invoice->payments->sum('amount');
            return [
                'id' => $invoice->id,
                'tenant_name' => $activeTenancy->tenant->user->name ?? 'N/A',
                'unit_number' => $activeTenancy->unit->unit_number ?? 'N/A',
                'total_amount' => (float) $invoice->total_amount,
                'status' => $invoice->status,
                'billing_month' => $invoice->billing_month,
                'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                'tenancy_id' => $invoice->tenancy_id,
                'paid_amount' => $totalPaid,
                'balance' => (float) ($invoice->total_amount - $totalPaid),
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'item_type' => $item->item_type,
                        'amount' => (float) $item->amount,
                    ];
                })->toArray(),
            ];
        });
    
    // Get payments - FIX: Get payments for the tenant
    $payments = Payment::whereHas('invoice', function ($query) use ($activeTenancy) {
            $query->where('tenancy_id', $activeTenancy->id);
        })
        ->orderBy('payment_datetime', 'desc')
        ->get()
        ->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_datetime' => $payment->payment_datetime,
                'date' => $payment->payment_datetime ? Carbon::parse($payment->payment_datetime)->format('M d, Y') : '-',
                'transaction_id' => $payment->transaction_id,
                'invoice_id' => $payment->invoice_id,
                'payer_name' => $payment->payer_name ?? 'Tenant Payment',
            ];
        });
    
    $totalPaid = (float) $payments->sum('amount');
    $totalBilled = (float) collect($invoices)->sum('total_amount');
    $outstandingBalance = $totalBilled - $totalPaid;
    
    // Water reading info
    $unit = $activeTenancy->unit;
    $waterInfo = [
        'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
        'current_reading' => (float) ($unit->current_water_reading ?? 0),
        'consumption' => max(0, ($unit->current_water_reading ?? 0) - ($unit->previous_water_reading ?? 0)),
        'rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
        'billing_type' => $unit->water_billing_type ?? 'consumption',
        'last_reading_date' => $unit->last_reading_date,
    ];
    
    // Get access logs for tenant - FIX: Get security logs for the unit
    $accessLogs = SecurityLog::where('unit_id', $unit->id)
        ->latest('access_time')
        ->take(20)
        ->get()
        ->map(function ($log) {
            return [
                'id' => $log->id,
                'datetime' => $log->access_time,
                'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y H:i') : '-',
                'person_name' => $log->visitor_name_snapshot ?? $log->person_name ?? 'Unknown',
                'visitor_phone' => $log->visitor_phone_snapshot ?? null,
                'unit_number' => $log->unit->unit_number ?? 'N/A',
                'access_type' => $log->access_type,
                'access_type_label' => $log->access_type_label,
                'status' => $log->status,
                'verified_by' => $log->approved_by ?? $log->verified_by ?? 'System',
                'purpose' => $log->purpose,
                'notes' => $log->notes,
            ];
        });
    
    // Get maintenance requests for tenant
    $maintenanceRequests = Maintenance::where('tenant_id', $tenant->id)
        ->with('unit')
        ->latest()
        ->take(10)
        ->get()
        ->map(function ($request) {
            return [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'unit_number' => $request->unit->unit_number ?? 'N/A',
                'tenant_name' => $request->tenant->user->name ?? 'N/A',
                'title' => $request->name,
                'description' => $request->description,
                'priority' => $request->priority,
                'priority_label' => ucfirst($request->priority),
                'priority_color' => $this->getPriorityColor($request->priority),
                'status' => $request->status,
                'status_label' => ucfirst(str_replace('_', ' ', $request->status)),
                'status_color' => $this->getStatusColor($request->status),
                'created_at' => $request->created_at,
                'created_at_formatted' => $request->created_at ? $request->created_at->format('M d, Y') : '-',
            ];
        });
    
    // Get units and estates for the company (for maintenance modal)
    $units = Unit::where('company_id', $company->id)
        ->with('estate')
        ->get()
        ->map(function($unit) {
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'estate_id' => $unit->estate_id,
                'estate' => $unit->estate ? ['name' => $unit->estate->name] : null,
            ];
        });
    
    $estates = Estate::where('company_id', $company->id)
        ->orderBy('name')
        ->get()
        ->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
            ];
        });
    
    // Current unit for the tenant
    $currentUnit = $unit->load('estate');
    
    return [
        'type' => 'tenant',
        'has_tenancy' => true,
        'tenant' => [
            'id' => $tenant->id,
            'name' => $tenant->user->name ?? 'N/A',
        ],
        'activeTenancy' => [
            'id' => $activeTenancy->id,
            'unit_number' => $activeTenancy->unit->unit_number ?? 'N/A',
            'estate_name' => $activeTenancy->unit->estate->name ?? 'N/A',
        ],
        'invoices' => $invoices,
        'payments' => $payments,
        'totalPaid' => $totalPaid,
        'totalBilled' => $totalBilled,
        'outstandingBalance' => $outstandingBalance,
        'waterInfo' => $waterInfo,
        'accessLogs' => $accessLogs,
        'maintenanceRequests' => $maintenanceRequests,
        'units' => $units,
        'estates' => $estates,
        'currentUnit' => $currentUnit,
    ];
}

// Add these helper methods for colors
private function getPriorityColor($priority)
{
    $colors = [
        'emergency' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'low' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
    ];
    return $colors[$priority] ?? 'bg-gray-100 text-gray-800';
}

private function getStatusColor($status)
{
    $colors = [
        'resolved' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'pending_parts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        'open' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}
    
    private function getCommonStats($user = null)
    {
        $user = $user ?? auth()->user();
        $company = $user->company;
        
        // If no company and not sysadmin, return empty stats
        if (!$company && !$user->hasRole('sysadmin')) {
            return [];
        }
        
        $stats = [
            'total_units' => $company ? Unit::where('company_id', $company->id)->count() : Unit::count(),
            'occupied_units' => $company ? Unit::where('company_id', $company->id)->where('status', 'occupied')->count() : Unit::where('status', 'occupied')->count(),
            'vacant_units' => $company ? Unit::where('company_id', $company->id)->where('status', 'vacant')->count() : Unit::where('status', 'vacant')->count(),
            'total_tenants' => $company ? Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))->count() : Tenant::count(),
            'active_tenancies' => $company ? Tenancy::whereHas('unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'active')->count() : Tenancy::where('status', 'active')->count(),
            'total_invoices' => $company ? Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->count() : Invoice::count(),
            'paid_invoices' => $company ? Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'paid')->count() : Invoice::where('status', 'paid')->count(),
            'unpaid_invoices' => $company ? Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'unpaid')->count() : Invoice::where('status', 'unpaid')->count(),
            'partial_invoices' => $company ? Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'partial')->count() : Invoice::where('status', 'partial')->count(),
            'total_revenue' => $company ? Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount') : Payment::sum('amount'),
            'total_expenses' => $company ? Expense::where('company_id', $company->id)->sum('amount') : Expense::sum('amount'),
            'net_income' => ($company ? Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount') : Payment::sum('amount')) - ($company ? Expense::where('company_id', $company->id)->sum('amount') : Expense::sum('amount')),
            'occupancy_rate' => $this->calculateOccupancyRate($company ? $company->id : null),
            'total_consumption' => $company ? Unit::where('company_id', $company->id)->sum(DB::raw('GREATEST(0, COALESCE(current_water_reading, 0) - COALESCE(previous_water_reading, 0))')) : max(0, Unit::sum('current_water_reading') - Unit::sum('previous_water_reading')),
            'monthly_consumption' => $company ? Unit::where('company_id', $company->id)->whereMonth('last_reading_date', Carbon::now()->month)->sum('current_water_reading') : Unit::whereMonth('last_reading_date', Carbon::now()->month)->sum('current_water_reading'),
            'outstanding_invoices' => $company ? Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->whereIn('status', ['unpaid', 'partial'])->sum('total_amount') : Invoice::whereIn('status', ['unpaid', 'partial'])->sum('total_amount'),
            'collection_rate' => $this->calculateCollectionRate($company ? $company->id : null),
            'units_needing_reading' => $company ? Unit::where('company_id', $company->id)->where('status', 'occupied')
                ->where(fn($q) => $q->whereNull('last_reading_date')->orWhere('last_reading_date', '<=', Carbon::now()->subDays(30)))->count() : Unit::where('status', 'occupied')
                ->where(fn($q) => $q->whereNull('last_reading_date')->orWhere('last_reading_date', '<=', Carbon::now()->subDays(30)))->count(),
            'total_occupied_units' => $company ? Unit::where('company_id', $company->id)->where('status', 'occupied')->count() : Unit::where('status', 'occupied')->count(),
            'total_consumption_count' => WaterReading::count(),
            'today_readings' => WaterReading::whereDate('reading_date', Carbon::today())->count(),
            'month_readings' => WaterReading::whereMonth('reading_date', Carbon::now()->month)->count(),
            'my_readings' => $user->hasRole('meter_reader') ? WaterReading::where('recorded_by', $user->id)->count() : 0,
        ];
        
        // Add tenant water readings
        if ($user && $user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $unit = $user->tenant->activeTenancy->unit;
            $stats['tenant_previous_reading'] = (float) ($unit->previous_water_reading ?? 0);
            $stats['tenant_current_reading'] = (float) ($unit->current_water_reading ?? 0);
            $stats['tenant_consumption'] = max(0, $stats['tenant_current_reading'] - $stats['tenant_previous_reading']);
            $stats['tenant_unit_number'] = $unit->unit_number ?? 'N/A';
            $stats['tenant_water_billing_type'] = $unit->water_billing_type ?? 'consumption';
        } else {
            $stats['tenant_previous_reading'] = 0;
            $stats['tenant_current_reading'] = 0;
            $stats['tenant_consumption'] = 0;
            $stats['tenant_unit_number'] = 'N/A';
            $stats['tenant_water_billing_type'] = 'consumption';
        }
        
        return $stats;
    }
    
    private function getSysAdminData()
    {
        return [
            'type' => 'sysadmin',
        ];
    }
    
    private function getAdminData($company)
    {
        // Recent invoices (company scoped)
        $recentInvoices = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['tenancy.tenant.user', 'tenancy.unit', 'items', 'payments'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($invoice) {
                $lastPayment = $invoice->payments->last();
                return [
                    'id' => $invoice->id,
                    'unit_id' => $invoice->tenancy->unit->id ?? null,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'payer_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'payment_method' => $lastPayment->payment_method ?? 'N/A',
                    'payment_datetime' => $lastPayment->payment_datetime ?? null,
                    'total_amount' => (float) $invoice->total_amount,
                    'status' => $invoice->status,
                    'billing_month' => $invoice->billing_month,
                    'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                    'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                    'tenancy_id' => $invoice->tenancy_id,
                    'paid_amount' => (float) $invoice->payments->sum('amount'),
                    'balance' => (float) ($invoice->total_amount - $invoice->payments->sum('amount')),
                    'items' => $invoice->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                        ];
                    })->toArray(),
                ];
            });
        
        // Recent payments (company scoped)
        $recentPayments = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'invoice_id' => $payment->invoice_id ?? null,
                    'tenant_name' => $payment->invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $payment->invoice->tenancy->unit->unit_number ?? 'N/A',
                    'unit_id' => $payment->invoice->tenancy->unit->id ?? null,
                    'payer_name' => $payment->payer_name ?? ($payment->invoice->tenancy->tenant->user->name ?? 'N/A'),
                    'payment_method' => $payment->payment_method,
                    'payment_datetime' => $payment->payment_datetime,   
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'date' => Carbon::parse($payment->payment_datetime)->format('M d, Y'),
                    'transaction_id' => $payment->transaction_id,
                ];
            });
        
        // Water readings (company scoped - from units table)
        $recentReadings = Unit::where('company_id', $company->id)
            ->whereNotNull('current_water_reading')
            ->where('current_water_reading', '>', 0)
            ->with('estate')
            ->orderBy('last_reading_date', 'desc')
            ->get()
            ->map(function ($unit) {
                $consumption = max(0, ($unit->current_water_reading ?? 0) - ($unit->previous_water_reading ?? 0));
                $billingType = $unit->water_billing_type ?? 'consumption';
                $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                $charge = $billingType === 'flat' ? ($unit->water_charge ?? 0) : ($consumption * $rate);
                
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number ?? 'N/A',
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'current_reading' => (float) $unit->current_water_reading,
                    'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
                    'consumption' => $consumption,
                    'charge' => $charge,
                    'reading_date' => $unit->last_reading_date,
                    'water_billing_type' => $billingType,
                    'needs_reading' => !$unit->last_reading_date || $unit->last_reading_date->diffInDays(now()) > 30
                ];
            });
        
        return [
            'type' => 'admin',
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
            'recentReadings' => $recentReadings,
        ];
    }
    
    private function getAccountantData($company)
    {
        $overdueInvoices = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->where('status', 'unpaid')
            ->where('billing_month', '<=', Carbon::now()->subMonth()->format('Y-m-01'))
            ->with(['tenancy.tenant.user', 'tenancy.unit', 'items', 'payments'])
            ->take(10)
            ->get()
            ->map(function ($invoice) {
                $lastPayment = $invoice->payments->last();
                return [
                    'id' => $invoice->id,
                    'invoice_id' => $invoice->id,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'unit_id' => $invoice->tenancy->unit->id ?? null,
                    'payer_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'payment_method' => $lastPayment->payment_method ?? 'N/A',
                    'payment_datetime' => $lastPayment->payment_datetime ?? null,
                    'total_amount' => (float) $invoice->total_amount,
                    'status' => $invoice->status,
                    'billing_month' => $invoice->billing_month,
                    'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                    'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                    'tenancy_id' => $invoice->tenancy_id,
                    'days_overdue' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->diffInMonths(now()) : 0,
                    'paid_amount' => (float) $invoice->payments->sum('amount'),
                    'balance' => (float) ($invoice->total_amount - $invoice->payments->sum('amount')),
                    'items' => $invoice->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                        ];
                    })->toArray(),
                ];
            });
        
        $recentTransactions = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
            ->latest()
            ->take(15)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'invoice_id' => $payment->invoice_id ?? null,
                    'tenant_name' => $payment->invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $payment->invoice->tenancy->unit->unit_number ?? 'N/A',
                    'unit_id' => $payment->invoice->tenancy->unit->id ?? null,
                    'payer_name' => $payment->payer_name ?? ($payment->invoice->tenancy->tenant->user->name ?? 'N/A'),
                    'payment_method' => $payment->payment_method,
                    'payment_datetime' => $payment->payment_datetime,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'date' => Carbon::parse($payment->payment_datetime)->format('M d, Y'),
                ];
            });
        
        return [
            'type' => 'accountant',
            'overdueInvoices' => $overdueInvoices,
            'recentTransactions' => $recentTransactions,
        ];
    }
    

    
    private function getPropertyManagerData($company)
    {
        // Long term vacant units (company scoped)
        $longTermVacant = Unit::where('company_id', $company->id)
            ->where('status', 'vacant')
            ->where('updated_at', '<=', Carbon::now()->subDays(30))
            ->with('estate')
            ->take(10)
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'estate_id' => $unit->estate_id,
                    'rent_amount' => $unit->rent_amount,
                    'vacant_days' => Carbon::parse($unit->updated_at)->diffInDays(now()),
                    'status' => $unit->status,
                    'unit_type' => $unit->unit_type,
                ];
            });
        
        // Recent water readings (company scoped - from units table)
        $recentReadings = Unit::where('company_id', $company->id)
            ->whereNotNull('current_water_reading')
            ->where('current_water_reading', '>', 0)
            ->with('estate')
            ->orderBy('last_reading_date', 'desc')
            ->get()
            ->map(function ($unit) {
                $consumption = max(0, ($unit->current_water_reading ?? 0) - ($unit->previous_water_reading ?? 0));
                $billingType = $unit->water_billing_type ?? 'consumption';
                $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                $charge = $billingType === 'flat' ? ($unit->water_charge ?? 0) : ($consumption * $rate);
                
                return [
                    'id' => $unit->id,
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number ?? 'N/A',
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'estate_id' => $unit->estate_id,
                    'current_reading' => (float) $unit->current_water_reading,
                    'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
                    'consumption' => $consumption,
                    'charge' => $charge,
                    'reading_date' => $unit->last_reading_date,
                    'last_reading_date' => $unit->last_reading_date,
                    'water_billing_type' => $billingType,
                    'water_charge' => (float) ($unit->water_charge ?? 0),
                    'custom_water_rate' => (float) ($unit->custom_water_rate ?? 0),
                    'rate' => (float) $rate,
                    'needs_reading' => !$unit->last_reading_date || $unit->last_reading_date->diffInDays(now()) > 30,
                    'status' => $unit->status,
                    'unit_type' => $unit->unit_type,
                ];
            });
        
        // Get maintenance requests (company scoped)
        $maintenanceRequests = Maintenance::where('company_id', $company->id)
            ->orWhereHas('unit', function($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->with(['unit', 'tenant.user'])
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'unit_number' => $request->unit->unit_number ?? 'N/A',
                    'unit_id' => $request->unit_id,
                    'tenant_name' => $request->tenant->user->name ?? 'N/A',
                    'tenant_id' => $request->tenant_id,
                    'title' => $request->title,
                    'description' => $request->description,
                    'priority' => $request->priority,
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                    'created_at_formatted' => $request->created_at ? $request->created_at->format('M d, Y H:i') : '-',
                    'updated_at' => $request->updated_at,
                ];
            });
        
        return [
            'type' => 'property_manager',
            'longTermVacant' => $longTermVacant,
            'recentReadings' => $recentReadings,
            'maintenanceRequests' => $maintenanceRequests,
        ];
    }
    
private function getMeterReaderData($company)
{
    if (!$company) {
        return [
            'type' => 'meter_reader',
            'pendingCount' => 0,
            'unitsNeedingReading' => collect(),
            'currentMonthCount' => 0,
            'currentMonthReadings' => collect(),
            'unitsWithHistoryCount' => 0,
            'historyReadings' => collect(),
            'firstReadingDate' => 'N/A',
            'lastReadingDate' => 'N/A',
            'totalConsumption' => 0,
            'allWaterReadings' => 0,
            'todayReadings' => 0,
            'thisMonthReadings' => 0,
            'units' => collect(),
            'estates' => collect(),
            'readingHistory' => collect(),
            'unitsWithHistory' => collect(),
        ];
    }
    
    // Get all units for the company
    $allUnits = Unit::where('company_id', $company->id)
        ->with('estate')
        ->get();
    
    // Get current month's start and end
    $currentMonthStart = Carbon::now()->startOfMonth();
    $currentMonthEnd = Carbon::now()->endOfMonth();
    
    // Get all water readings for this company, ordered by date ASC for proper previous calculation
    $allReadings = WaterReading::whereHas('unit', fn($q) => $q->where('company_id', $company->id))
        ->with(['unit.estate', 'recordedBy'])
        ->orderBy('reading_date', 'asc')
        ->get();
    
    // ========== 1. CURRENT MONTH READINGS (Units WITH readings this month) ==========
    $currentMonthReadingsCollection = $allReadings->filter(function($reading) use ($currentMonthStart, $currentMonthEnd) {
        return $reading->reading_date->between($currentMonthStart, $currentMonthEnd);
    });
    
    $currentMonthReadings = $currentMonthReadingsCollection->map(function ($reading) {
        $unit = $reading->unit;
        if (!$unit) return null;
        
        $consumption = $reading->consumption ?: max(0, ($reading->current_reading ?? 0) - ($reading->previous_reading ?? 0));
        
        return [
            'id' => $reading->id,
            'unit_id' => $unit->id,
            'unit_number' => $unit->unit_number ?? 'N/A',
            'estate_name' => $unit->estate->name ?? 'N/A',
            'estate_id' => $unit->estate_id,
            'previous_reading' => (float) ($reading->previous_reading ?? 0),
            'current_reading' => (float) ($reading->current_reading ?? 0),
            'consumption' => (float) max(0, $consumption),
            'charge' => (float) ($reading->charge ?? 0),
            'reading_date' => $reading->reading_date->format('Y-m-d'),
            'last_reading_date' => $reading->reading_date->format('Y-m-d'),
            'needs_reading' => false,
            'recorded_by_name' => optional($reading->recordedBy)->name ?? 'System',
        ];
    })->filter()->values();
    
    // Sort current month readings by consumption (highest to lowest)
    $currentMonthReadings = $currentMonthReadings->sortByDesc('consumption')->values();
    
    // Get total consumption for current month
    $totalConsumption = $currentMonthReadingsCollection->sum('consumption');
    
    // ========== 2. PENDING READINGS (Units with NO reading this month) ==========
    $unitIdsWithCurrentMonthReading = $currentMonthReadingsCollection->pluck('unit_id')->toArray();
    
    $pendingReadings = $allUnits
        ->filter(function($unit) use ($unitIdsWithCurrentMonthReading) {
            // Only include occupied units that don't have a reading this month
            return $unit->status === 'occupied' && !in_array($unit->id, $unitIdsWithCurrentMonthReading);
        })
        ->map(function($unit) {
            // Get the latest reading for this unit (to show previous reading)
            $lastReading = WaterReading::where('unit_id', $unit->id)
                ->orderBy('reading_date', 'desc')
                ->first();
            
            return [
                'id' => null,
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number ?? 'N/A',
                'estate_name' => $unit->estate->name ?? 'N/A',
                'estate_id' => $unit->estate_id,
                'previous_reading' => (float) ($lastReading ? $lastReading->current_reading : ($unit->previous_water_reading ?? 0)),
                'current_reading' => null,
                'consumption' => null,
                'charge' => null,
                'reading_date' => null,
                'last_reading_date' => $lastReading ? $lastReading->reading_date->format('Y-m-d') : null,
                'needs_reading' => true,
                'status' => $unit->status,
                'unit_type' => $unit->unit_type,
            ];
        })
        ->values();
    
    // ========== 3. READING HISTORY (Individual readings with proper previous/current) ==========
    // Group readings by unit to calculate sequential previous readings
    $readingsByUnit = [];
    
    foreach ($allReadings as $reading) {
        $unitId = $reading->unit_id;
        if (!isset($readingsByUnit[$unitId])) {
            $readingsByUnit[$unitId] = [];
        }
        $readingsByUnit[$unitId][] = $reading;
    }
    
    // Process each unit's readings to ensure correct previous/current relationship
    $historyReadingsList = [];
    
    foreach ($readingsByUnit as $unitId => $unitReadings) {
        // Sort readings by date (oldest to newest)
        $sortedReadings = collect($unitReadings)->sortBy('reading_date')->values();
        $unit = $allUnits->firstWhere('id', $unitId);
        
        if (!$unit) continue;
        
        $previousValue = (float) ($unit->previous_water_reading ?? 0);
        
        foreach ($sortedReadings as $index => $reading) {
            $currentValue = (float) $reading->current_reading;
            $consumption = $reading->consumption ?: max(0, $currentValue - $previousValue);
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            
            if ($unit->water_billing_type === 'flat') {
                $charge = $unit->water_charge ?? 0;
            } else {
                $charge = $consumption * $rate;
            }
            
            $historyReadingsList[] = [
                'id' => $reading->id,
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number ?? 'N/A',
                'estate_name' => $unit->estate->name ?? 'N/A',
                'estate_id' => $unit->estate_id,
                'previous_reading' => (float) $previousValue,
                'current_reading' => $currentValue,
                'consumption' => (float) max(0, $consumption),
                'charge' => (float) max(0, $charge),
                'reading_date' => $reading->reading_date->format('Y-m-d'),
                'last_reading_date' => $reading->reading_date->format('Y-m-d'),
                'needs_reading' => false,
                'recorded_by_name' => optional($reading->recordedBy)->name ?? 'System',
                'status' => $unit->status,
                'unit_type' => $unit->unit_type,
            ];
            
            // Update previous value for next iteration
            $previousValue = $currentValue;
        }
    }
    
    // Sort history readings by date (newest first) for display
    $historyReadings = collect($historyReadingsList)->sortByDesc('reading_date')->values();
    
    // Get date range for history tab header
    $firstReading = $allReadings->sortBy('reading_date')->first();
    $lastReading = $allReadings->sortByDesc('reading_date')->first();
    
    $firstReadingDate = $firstReading ? $firstReading->reading_date->format('M Y') : 'N/A';
    $lastReadingDate = $lastReading ? $lastReading->reading_date->format('M Y') : 'N/A';
    
    // Get unique units with history count
    $unitsWithHistoryCount = collect($historyReadingsList)->pluck('unit_id')->unique()->count();
    
    // ========== 4. Units for modal dropdown ==========
    $units = Unit::where('company_id', $company->id)
        ->with('estate')
        ->where('is_active', true)
        ->get()
        ->map(function($unit) {
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_name' => $unit->estate->name ?? 'N/A',
                'estate_id' => $unit->estate_id,
                'water_billing_type' => $unit->water_billing_type ?? 'consumption',
                'water_charge' => (float) ($unit->water_charge ?? 0),
                'custom_water_rate' => (float) ($unit->custom_water_rate ?? 0),
                'current_water_reading' => (float) ($unit->current_water_reading ?? 0),
                'previous_water_reading' => (float) ($unit->previous_water_reading ?? 0),
                'last_reading_date' => $unit->last_reading_date,
            ];
        });
    
    // ========== 5. Estates for filtering ==========
    $estates = Estate::where('company_id', $company->id)
        ->orderBy('name')
        ->get()
        ->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
            ];
        });
    
    // ========== 6. Reading history list (for backward compatibility) ==========
    $readingHistory = $historyReadings->map(function ($reading) {
        return [
            'id' => $reading['id'],
            'unit_id' => $reading['unit_id'],
            'unit_number' => $reading['unit_number'],
            'estate_name' => $reading['estate_name'],
            'estate_id' => $reading['estate_id'],
            'previous_reading' => $reading['previous_reading'],
            'current_reading' => $reading['current_reading'],
            'consumption' => $reading['consumption'],
            'reading_date' => $reading['reading_date'],
            'recorded_by_name' => $reading['recorded_by_name'],
        ];
    });
    
    // Units with history summary (for backward compatibility)
    $unitsWithHistorySummary = [];
    foreach ($readingsByUnit as $unitId => $unitReadings) {
        $unit = $allUnits->firstWhere('id', $unitId);
        if (!$unit) continue;
        
        $totalConsumptionForUnit = 0;
        $prevValue = (float) ($unit->previous_water_reading ?? 0);
        $sorted = collect($unitReadings)->sortBy('reading_date')->values();
        
        foreach ($sorted as $reading) {
            $currentValue = (float) $reading->current_reading;
            $totalConsumptionForUnit += max(0, $currentValue - $prevValue);
            $prevValue = $currentValue;
        }
        
        $unitsWithHistorySummary[] = [
            'id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'unit_type' => $unit->unit_type,
            'estate_name' => $unit->estate->name ?? 'N/A',
            'estate_id' => $unit->estate_id,
            'status' => $unit->status,
            'rent_amount' => $unit->rent_amount,
            'total_readings' => count($unitReadings),
            'last_reading_date' => $sorted->last()->reading_date->format('Y-m-d'),
            'last_reading_value' => (float) $sorted->last()->current_reading,
            'total_consumption' => $totalConsumptionForUnit,
        ];
    }
    
    $unitsWithHistory = collect($unitsWithHistorySummary)->sortBy('unit_number')->values();
    
    // ========== 7. Return all data ==========
    return [
        'type' => 'meter_reader',
        
        // For Pending Tab (units needing reading)
        'pendingCount' => $pendingReadings->count(),
        'unitsNeedingReading' => $pendingReadings,
        'pendingReadings' => $pendingReadings,
        
        // For Current Month Tab
        'currentMonthCount' => $currentMonthReadings->count(),
        'currentMonthReadings' => $currentMonthReadings,
        
        // For History Tab - Each individual reading with proper previous/current
        'unitsWithHistoryCount' => $unitsWithHistoryCount,
        'historyReadings' => $historyReadings,
        'firstReadingDate' => $firstReadingDate,
        'lastReadingDate' => $lastReadingDate,
        
        // Stats cards
        'totalConsumption' => $totalConsumption,
        'allWaterReadings' => $allReadings->count(),
        'todayReadings' => $allReadings->filter(fn($r) => $r->reading_date->isToday())->count(),
        'thisMonthReadings' => $currentMonthReadings->count(),
        
        // Supporting data
        'units' => $units,
        'estates' => $estates,
        
        // Backward compatibility
        'readingHistory' => $readingHistory,
        'unitsWithHistory' => $unitsWithHistory,
    ];
}
    
    private function getCleaningStaffData()
    {
        $cleaningTasks = [
            [
                'id' => 1,
                'unit_number' => 'A101',
                'estate_name' => 'Sunset Estate',
                'type' => 'regular',
                'description' => 'Weekly cleaning of common areas',
                'priority' => 'medium',
                'status' => 'pending',
                'assigned_to' => 'John Doe',
                'due_date' => now()->addDays(2),
            ],
            [
                'id' => 2,
                'unit_number' => 'B205',
                'estate_name' => 'Sunset Estate',
                'type' => 'deep',
                'description' => 'Deep cleaning for vacated unit',
                'priority' => 'high',
                'status' => 'in_progress',
                'assigned_to' => 'Jane Smith',
                'due_date' => now()->addDays(1),
            ],
        ];
        
        return [
            'type' => 'cleaning_staff',
            'cleaningTasks' => $cleaningTasks,
        ];
    }
    
private function getMaintenanceData($company)
{
    // Get estates for the company
    $estates = Estate::where('company_id', $company->id)
        ->orderBy('name')
        ->get()
        ->map(function($estate) {
            return [
                'id' => $estate->id,
                'name' => $estate->name,
            ];
        });
    
    // Get all units for the company (with estate info)
    $units = Unit::where('company_id', $company->id)
        ->with('estate')
        ->get()
        ->map(function($unit) {
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'estate_id' => $unit->estate_id,
                'estate' => $unit->estate ? [
                    'id' => $unit->estate->id,
                    'name' => $unit->estate->name,
                ] : null,
            ];
        });
    
    // Get all maintenance requests for this company
    $allRequests = Maintenance::where('company_id', $company->id)
        ->orWhereHas('unit', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with(['unit', 'tenant.user', 'assignedStaff'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($request) {
            return [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'unit_id' => $request->unit_id,
                'unit_number' => $request->unit->unit_number ?? 'N/A',
                'tenant_id' => $request->tenant_id,
                'tenant_name' => optional($request->tenant)->user->name ?? 'N/A',
                'assigned_to' => $request->assigned_to,
                'assigned_to_name' => optional($request->assignedStaff)->name ?? 'Unassigned',
                'name' => $request->name,
                'title' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'category_label' => ucfirst(str_replace('_', ' ', $request->category)),
                'priority' => $request->priority,
                'priority_label' => ucfirst($request->priority),
                'status' => $request->status,
                'status_label' => ucfirst(str_replace('_', ' ', $request->status)),
                'duration' => $request->duration,
                'admin_notes' => $request->admin_notes,
                'resolution_notes' => $request->resolution_notes,
                'scheduled_date' => $request->scheduled_date,
                'scheduled_date_formatted' => $request->scheduled_date ? Carbon::parse($request->scheduled_date)->format('M d, Y') : null,
                'completed_date' => $request->completed_date,
                'completed_date_formatted' => $request->completed_date ? Carbon::parse($request->completed_date)->format('M d, Y') : null,
                'cost' => (float) ($request->cost ?? 0),
                'created_at' => $request->created_at,
                'created_at_formatted' => $request->created_at ? $request->created_at->format('M d, Y H:i') : '-',
                'updated_at' => $request->updated_at,
                'images' => $request->images,
            ];
        });

    // Separate by status
    $openRequests = $allRequests->filter(function($request) {
        return in_array($request['status'], ['open', 'pending']);
    })->values();

    $inProgressRequests = $allRequests->filter(function($request) {
        return $request['status'] === 'in_progress' || $request['status'] === 'pending_parts';
    })->values();

    $completedRequests = $allRequests->filter(function($request) {
        return in_array($request['status'], ['completed', 'resolved', 'cancelled']);
    })->values();
    
    // Get current unit for tenant (if tenant is viewing)
    $user = auth()->user();
    $currentUnit = null;
    if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
        $currentUnit = $user->tenant->activeTenancy->unit->load('estate');
    }

    return [
        'type' => 'maintenance',
        'openRequests' => $openRequests,
        'inProgressRequests' => $inProgressRequests,
        'completedRequests' => $completedRequests,
        'allRequests' => $allRequests,
        'estates' => $estates,
        'units' => $units,
        'currentUnit' => $currentUnit,
    ];
}
    
private function getSecurityData($company)
{
    // Get security logs with proper company filtering through unit relationship
    $accessLogs = SecurityLog::whereHas('unit', function($q) use ($company) {
            $q->where('company_id', $company->id);
        })
        ->with(['unit', 'visitor'])
        ->latest('access_time')
        ->get()
        ->map(function ($log) {
            return [
                'id' => $log->id,
                'datetime' => $log->access_time,
                'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y H:i') : '-',
                'unit_id' => $log->unit_id,
                'unit_number' => $log->unit->unit_number ?? 'N/A',
                'person_name' => $log->visitor_name_snapshot ?? ($log->visitor->full_name ?? $log->person_name ?? 'Unknown'),
                'visitor_phone' => $log->visitor_phone_snapshot ?? ($log->visitor->phone ?? null),
                'access_type' => $log->access_type,
                'access_type_label' => $log->access_type_label,
                'status' => $log->status,
                'verified_by' => $log->approved_by ?? $log->verified_by ?? 'System',
                'notes' => $log->notes,
                'purpose' => $log->purpose,
                'created_at' => $log->created_at,
            ];
        });
    
    $pendingLogs = $accessLogs->where('status', 'pending')->values();
    $todayLogs = $accessLogs->filter(function($log) {
        return Carbon::parse($log['datetime'])->isToday();
    })->values();
    
    // Get units for the modal dropdown
    $units = Unit::where('company_id', $company->id)
        ->with('estate')
        ->get()
        ->map(function($unit) {
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_name' => $unit->estate->name ?? 'N/A',
            ];
        });
    
    return [
        'type' => 'security',
        'accessLogs' => $accessLogs,
        'pendingLogs' => $pendingLogs,
        'todayLogs' => $todayLogs,
        'units' => $units,  // Add this for the modals
    ];
}
    
    // Helper methods
    private function calculateOccupancyRate($companyId = null)
    {
        if ($companyId) {
            $totalUnits = Unit::where('company_id', $companyId)->count();
            $occupiedUnits = Unit::where('company_id', $companyId)->where('status', 'occupied')->count();
        } else {
            $totalUnits = Unit::count();
            $occupiedUnits = Unit::where('status', 'occupied')->count();
        }
        return $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 2) : 0;
    }
    
    private function calculateCollectionRate($companyId = null)
    {
        if ($companyId) {
            $totalBilled = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $companyId))->sum('total_amount');
            $totalCollected = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))->sum('amount');
        } else {
            $totalBilled = Invoice::sum('total_amount');
            $totalCollected = Payment::sum('amount');
        }
        return $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 2) : 0;
    }
    
    private function getMonthlyRevenueForCompany($companyId)
    {
        $revenue = Payment::select(
                DB::raw('DATE_FORMAT(payment_datetime, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->whereYear('payment_datetime', '>=', Carbon::now()->subYear()->year)
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->pluck('total', 'month')
            ->toArray();
        
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $result[$month] = $revenue[$month] ?? 0;
        }
        return $result;
    }
    
    private function getPaymentMethodStatsForCompany($companyId)
    {
        return Payment::select('payment_method', DB::raw('SUM(amount) as total'))
            ->whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->groupBy('payment_method')
            ->get()
            ->pluck('total', 'payment_method')
            ->toArray();
    }
}