<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\Invoice;
use App\Modules\Payments\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Maintenance;
use App\Models\SecurityLog;
use App\Models\WaterReading;
use App\Models\Company;
use App\Models\Estate;
use App\Helpers\SystemHelper;
use App\Models\Transaction;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        if (!$this->isUserReady($user)) {
            return $this->pendingVerificationView($user);
        }
        
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
        if (is_null($user->email_verified_at)) {
            return false;
        }
        
        if ($user->status == 1) {
            return false;
        }
        
        if ($user->hasRole('sysadmin')) {
            return true;
        }
        
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
        
        if ($user->hasRole('sysadmin')) {
            return ['message' => 'System Administrator access granted.', 'status' => 'sysadmin'];
        }
        
        if (!$user->hasRole('sysadmin') && is_null($user->company_id)) {
            return ['message' => 'Your account is not assigned to any company. Please contact your system administrator.', 'status' => 'no_company'];
        }
        
        return ['message' => 'Your account requires further configuration. Please contact support.', 'status' => 'unknown'];
    }
    
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
    $subscriptionStats = $this->getSubscriptionStats();
    
    $stats = [
        'total_companies' => Company::count(),
        'active_companies' => Company::where('subscription_status', 'active')->orWhere('is_active', true)->count(),
        'pending_companies' => Company::where('subscription_status', 'pending')->count(),
        'total_users' => User::count(),
        'verified_users' => User::whereNotNull('email_verified_at')->count(),
        'pending_verification_users' => User::whereNull('email_verified_at')->count(),
        'total_units' => Unit::count(),
        'total_tenants' => Tenant::count(),
        'total_revenue' => Payment::sum('amount'),
        'monthly_recurring_revenue' => $this->calculateMRR(),
        'subscription_stats' => $subscriptionStats,
    ];
    
    // ================================================================
    // FIX: Calculate max revenue safely in controller
    // ================================================================
    $maxRevenue = 0;
    if (!empty($subscriptionStats['revenue_by_plan']) && count($subscriptionStats['revenue_by_plan']) > 0) {
        $revenues = array_column($subscriptionStats['revenue_by_plan'], 'revenue');
        $maxRevenue = !empty($revenues) ? max($revenues) : 1;
    } else {
        $maxRevenue = 1; // Fallback to avoid division by zero
    }
    
    $pendingUsers = User::whereNull('email_verified_at')
        ->with('role', 'company')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_name' => $user->role->name ?? 'N/A',
                'company_name' => $user->company->name ?? null,
                'created_at_formatted' => $user->created_at ? $user->created_at->format('M d, Y') : '-',
            ];
        });
    
    $companies = Company::with(['currentSubscription.plan'])
        ->select('id', 'name', 'email', 'phone', 'is_active', 'subscription_status', 'created_at')
        ->withCount(['users', 'units'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($company) {
            $subscription = $company->currentSubscription;
            $plan = $subscription ? $subscription->plan : null;
            $features = $plan ? ($plan->features_json ?? []) : [];
            
            $unitCount = Unit::where('company_id', $company->id)
                ->whereIn('status', ['occupied', 'available'])
                ->count();
            
            return [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'is_active' => (bool) $company->is_active,
                'subscription_status' => $company->subscription_status,
                'subscription_plan' => $plan ? $plan->name : 'No Plan',
                'subscription_plan_slug' => $plan ? $plan->slug : null,
                'billing_cycle' => $subscription ? $subscription->billing_cycle : null,
                'subscription_ends_at' => $subscription && $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'price_per_unit' => $features['price_per_unit'] ?? null,
                'users_count' => $company->users_count ?? 0,
                'units_count' => $company->units_count ?? 0,
                'created_at' => $company->created_at ? $company->created_at->format('Y-m-d') : null,
            ];
        });
    
    $subscriptionPlans = SubscriptionPlan::withCount('subscriptions')
        ->orderBy('price_per_unit')
        ->get()
        ->map(function($plan) {
            $features = $plan->features_json ?? [];
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly' => (float) $plan->price_yearly,
                'trial_days' => $plan->trial_days,
                'is_active' => (bool) $plan->is_active,
                'features' => $features['features_list'] ?? [],
                'subscribers_count' => $plan->subscriptions_count,
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'price_per_unit' => $features['price_per_unit'] ?? null,
                'limits' => [
                    'max_properties' => $features['max_properties'] ?? 0,
                    'max_units' => $features['max_units'] ?? 0,
                    'max_users' => $features['max_users'] ?? 0,
                    'max_tenants' => $features['max_tenants'] ?? 0,
                    'storage_gb' => $features['storage_gb'] ?? 0,
                ]
            ];
        });
    
    $activeSubscriptions = CompanySubscription::where('status', 'active')
        ->where(function($q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
        })
        ->with(['company', 'plan'])
        ->get()
        ->map(function($subscription) {
            $company = $subscription->company;
            $plan = $subscription->plan;
            $features = $plan ? ($plan->features_json ?? []) : [];
            
            $unitCount = $company ? Unit::where('company_id', $company->id)
                ->whereIn('status', ['occupied', 'available'])
                ->count() : 0;
            
            $monthlyPrice = $plan ? $plan->calculateMonthlyPrice($unitCount) : 0;
            $yearlyPrice = $plan ? $plan->calculateYearlyPrice($unitCount) : 0;
            
            return [
                'id' => $subscription->id,
                'company_id' => $company ? $company->id : null,
                'company_name' => $company ? $company->name : 'N/A',
                'plan_id' => $plan ? $plan->id : null,
                'plan_name' => $plan ? $plan->name : 'N/A',
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'yearly_price' => $yearlyPrice,
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'price_per_unit' => $features['price_per_unit'] ?? null,
                'starts_at' => $subscription->starts_at ? $subscription->starts_at->format('Y-m-d') : null,
                'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
                'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : null,
                'auto_renew' => (bool) $subscription->auto_renew,
                'created_at' => $subscription->created_at ? $subscription->created_at->format('Y-m-d') : null,
            ];
        });
    
    $expiringSubscriptions = CompanySubscription::where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '>', now())
        ->where('ends_at', '<=', now()->addDays(30))
        ->with(['company', 'plan'])
        ->get()
        ->map(function($subscription) {
            return [
                'id' => $subscription->id,
                'company_name' => $subscription->company ? $subscription->company->name : 'N/A',
                'plan_name' => $subscription->plan ? $subscription->plan->name : 'N/A',
                'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
                'days_remaining' => $subscription->ends_at ? now()->diffInDays($subscription->ends_at, false) : 0,
            ];
        });
    
    $revenueByPlan = $this->getRevenueByPlan();
    
    $systemSettings = [
        'default_water_rate' => SystemHelper::get('settings.water.default_rate', 50),
        'invoice_due_days' => SystemHelper::get('settings.invoice.due_days', 30),
        'late_fee_percentage' => SystemHelper::get('settings.invoice.late_fee_percentage', 5),
        'maintenance_sla_days' => SystemHelper::get('settings.maintenance.sla_days', 3),
    ];
    
    return view('partials.dashboard.sys-admin', compact(
        'user', 'stats', 'companies', 'pendingUsers',
        'systemSettings', 'subscriptionPlans', 'activeSubscriptions',
        'expiringSubscriptions', 'revenueByPlan', 'subscriptionStats',
        'maxRevenue' // Pass the calculated max revenue to the view
    ));
}

    private function getSubscriptionStats()
    {
        $totalSubscriptions = CompanySubscription::count();
        $activeSubscriptions = CompanySubscription::where('status', 'active')
            ->where(function($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->count();
        
        $trialSubscriptions = CompanySubscription::where('status', 'trial')
            ->where(function($q) {
                $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now());
            })
            ->count();
        
        $expiredSubscriptions = CompanySubscription::where(function($q) {
            $q->where('status', 'expired')
              ->orWhere(function($sq) {
                  $sq->where('status', 'active')
                     ->whereNotNull('ends_at')
                     ->where('ends_at', '<', now());
              });
        })->count();
        
        $cancelledSubscriptions = CompanySubscription::where('status', 'cancelled')->count();
        $monthlySubscriptions = CompanySubscription::where('billing_cycle', 'monthly')->where('status', 'active')->count();
        $yearlySubscriptions = CompanySubscription::where('billing_cycle', 'yearly')->where('status', 'active')->count();
        
        $plansDistribution = SubscriptionPlan::withCount(['subscriptions' => function($q) {
            $q->where('status', 'active');
        }])->get()->map(function($plan) {
            $features = $plan->features_json ?? [];
            $pricingType = $features['pricing_type'] ?? 'fixed';
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            $maxUnits = $features['max_units'] ?? 0;
            
            $subscribers = $plan->subscriptions()->where('status', 'active')->get();
            $totalUnits = 0;
            $totalRevenue = 0;
            
            foreach ($subscribers as $subscription) {
                $company = $subscription->company;
                if ($company) {
                    $unitCount = Unit::where('company_id', $company->id)
                        ->whereIn('status', ['occupied', 'available'])
                        ->count();
                    $totalUnits += $unitCount;
                    $totalRevenue += $pricingType === 'per_unit' ? $pricePerUnit * $unitCount : $plan->price_monthly;
                }
            }
            
            $avgUnits = $subscribers->count() > 0 ? round($totalUnits / $subscribers->count()) : 0;
            $avgRevenue = $subscribers->count() > 0 ? round($totalRevenue / $subscribers->count()) : 0;
            
            return [
                'name' => $plan->name,
                'count' => $plan->subscriptions_count,
                'pricing_type' => $pricingType,
                'price_per_unit' => $pricePerUnit,
                'max_units' => $maxUnits === 0 ? 'Unlimited' : number_format($maxUnits),
                'avg_units' => $avgUnits,
                'avg_revenue' => $avgRevenue,
                'monthly_price' => (float) $plan->price_monthly,
                'yearly_price' => (float) $plan->price_yearly,
            ];
        });
        
        $mrr = $this->calculateMRR();
        $revenueByPlan = $this->getRevenueByPlan();
        
        return [
            'total' => $totalSubscriptions,
            'active' => $activeSubscriptions,
            'trial' => $trialSubscriptions,
            'expired' => $expiredSubscriptions,
            'cancelled' => $cancelledSubscriptions,
            'monthly_cycle' => $monthlySubscriptions,
            'yearly_cycle' => $yearlySubscriptions,
            'plans_distribution' => $plansDistribution,
            'total_mrr' => $mrr,
            'revenue_by_plan' => $revenueByPlan,
        ];
    }

    private function calculateMRR()
    {
        // Get all active subscriptions
        $activeSubscriptions = CompanySubscription::where('status', 'active')
            ->where(function($q) {
                $q->whereNull('ends_at')
                ->orWhere('ends_at', '>', now());
            })
            ->with(['company', 'plan'])
            ->get();
        
        \Log::info('MRR Calculation - Active Subscriptions Count: ' . $activeSubscriptions->count());
        
        $mrr = 0;
        $debugData = [];
        
        foreach ($activeSubscriptions as $subscription) {
            $company = $subscription->company;
            $plan = $subscription->plan;
            
            if (!$company || !$plan) {
                \Log::warning('MRR: Skipping subscription - missing company or plan', [
                    'subscription_id' => $subscription->id,
                    'has_company' => (bool) $company,
                    'has_plan' => (bool) $plan,
                ]);
                continue;
            }
            
            $totalUnits = Unit::where('company_id', $company->id)
                ->whereIn('status', ['occupied', 'available'])
                ->count();
            
            $pricePerUnit = (float) $plan->price_per_unit;
            $monthlyPrice = $pricePerUnit * $totalUnits;
            
            $subscriptionMrr = $monthlyPrice;
            if ($subscription->billing_cycle === 'yearly') {
                $subscriptionMrr = ($monthlyPrice * 12 * 0.9) / 12;
            }
            
            $mrr += $subscriptionMrr;
            
            $debugData[] = [
                'company' => $company->name,
                'plan' => $plan->name,
                'price_per_unit' => $pricePerUnit,
                'billing_cycle' => $subscription->billing_cycle,
                'mrr_contribution' => $subscriptionMrr,
            ];
        }
        
        \Log::info('MRR Calculation Debug:', [
            'total_mrr' => $mrr,
            'subscriptions_count' => $activeSubscriptions->count(),
            'details' => $debugData,
        ]);
        
        return $mrr;
    }

    private function getRevenueByPlan()
    {
        $activeSubscriptions = CompanySubscription::where('status', 'active')
            ->where(function($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->with(['company', 'plan'])
            ->get();
        
        $revenueByPlan = [];
        
        foreach ($activeSubscriptions as $subscription) {
            $company = $subscription->company;
            $plan = $subscription->plan;
            
            if (!$company || !$plan) continue;
            
            $planName = $plan->name;
            $unitCount = Unit::where('company_id', $company->id)
                ->whereIn('status', ['occupied', 'available'])
                ->count();
            
            $features = $plan->features_json ?? [];
            $pricingType = $features['pricing_type'] ?? 'fixed';
            
            if ($pricingType === 'per_unit') {
                $pricePerUnit = $features['price_per_unit'] ?? 0;
                $monthlyPrice = $pricePerUnit * $unitCount;
            } else {
                $monthlyPrice = (float) $plan->price_monthly;
            }
            
            if (!isset($revenueByPlan[$planName])) {
                $revenueByPlan[$planName] = [
                    'plan_name' => $planName,
                    'count' => 0,
                    'revenue' => 0,
                    'total_units' => 0,
                    'avg_units' => 0
                ];
            }
            
            $revenueByPlan[$planName]['count']++;
            $revenueByPlan[$planName]['revenue'] += $monthlyPrice;
            $revenueByPlan[$planName]['total_units'] += $unitCount;
        }
        
        foreach ($revenueByPlan as &$plan) {
            $plan['avg_units'] = $plan['count'] > 0 ? round($plan['total_units'] / $plan['count']) : 0;
        }
        
        return array_values($revenueByPlan);
    }
    
/**
 * ADMIN DASHBOARD
 */
private function adminDashboard()
{
    $user = auth()->user();
    $company = $user->company;
    
    if (!$company) {
        return $this->pendingVerificationView($user);
    }
    
    $invoices = Invoice::with('tenancy.tenant.user', 'tenancy.unit', 'items', 'payments')
        ->whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
        ->orderBy('created_at', 'desc')
        ->get();
    
    $activeTenancies = Tenancy::where('status', 'active')
        ->whereHas('unit', fn($q) => $q->where('company_id', $company->id))
        ->with('tenant.user', 'unit')
        ->get();
    
    $users = Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))
        ->with('user')
        ->get()
        ->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });
    
    $paymentInvoices = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
        ->with('items', 'tenancy.tenant.user')
        ->get()
        ->map(function ($invoice) {
            $payerName = optional(optional($invoice->tenancy)->tenant->user)->name ?? 'N/A';
            $itemsLabel = $invoice->items->count()
                ? $invoice->items->map(fn ($item) => ($item->item_type ?? 'Item') . ($item->description ? ' (' . $item->description . ')' : ''))->implode(', ')
                : '-';
            return [
                'id' => $invoice->id,
                'label' => $payerName . ' - Invoice #' . ($invoice->invoice_number ?? $invoice->id) . ': ' . $itemsLabel,
                'payer_name' => $payerName,
            ];
        });
    
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
            'payment_datetime' => $lastPayment->created_at ?? null,
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
    
    $estates = Estate::where('company_id', $company->id)->orderBy('name')->get();
    $units = Unit::where('company_id', $company->id)->with('estate')->get();
    
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
    
    $totalDraft = $invoices->where('status', 'draft')->sum('total_amount');
    $totalUnpaid = $invoices->where('status', 'unpaid')->sum('total_amount');
    $totalPartial = $invoices->where('status', 'partial')->sum('total_amount');
    $totalPaidAll = $invoices->where('status', 'paid')->sum('total_amount');
    
    $tenanciesNeedingMoveInInvoices = $activeTenancies->filter(function($tenancy) use ($invoices) {
        $hasMoveInInvoice = $invoices->where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'move_in')
            ->count() > 0;
        return !$hasMoveInInvoice && $tenancy->move_in_date;
    });
    
    // ================================================================
    // RECENT INVOICES - Limit to 5
    // ================================================================
    $recentInvoices = $invoices->take(5);
    
    // ================================================================
    // RECENT PAYMENTS - Get recent payments with proper data
    // ================================================================
    $recentPayments = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))
        ->with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
        ->orderBy('created_at', 'desc')
        ->take(10)
        ->get()
        ->map(function ($payment) {
            return [
                'id' => $payment->id,
                'invoice_id' => $payment->invoice_id ?? null,
                'tenant_name' => $payment->invoice?->tenancy?->tenant?->user?->name ?? 'N/A',
                'unit_number' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                'unit_id' => $payment->invoice?->tenancy?->unit?->id ?? null,
                'payer_name' => $payment->invoice?->tenancy?->tenant?->user?->name ?? 'Tenant Payment',
                'payment_method' => $payment->payment_method ?? 'cash',
                'payment_method_label' => $payment->payment_method_label ?? ucfirst($payment->payment_method ?? 'Cash'),
                'payment_datetime' => $payment->created_at,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method ?? 'cash',
                'date' => $payment->created_at ? $payment->created_at->format('M d, Y') : '-',
                'transaction_reference' => $payment->transaction_reference ?? $payment->external_reference ?? null,
                'external_reference' => $payment->external_reference ?? null,
                'status' => $payment->status ?? 'completed',
                'is_reconciled' => $payment->is_reconciled ?? false,
            ];
        });
    
    // ================================================================
    // WATER READINGS - Get units with water readings
    // ================================================================
    $waterReadings = Unit::where('company_id', $company->id)
        ->where('status', 'occupied')
        ->with('estate')
        ->orderBy('last_reading_date', 'desc')
        ->take(10)
        ->get()
        ->map(function ($unit) {
            $previousReading = (float) ($unit->previous_water_reading ?? 0);
            $currentReading = (float) ($unit->current_water_reading ?? 0);
            $consumption = max(0, $currentReading - $previousReading);
            $billingType = $unit->water_billing_type ?? 'consumption';
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            $charge = $billingType === 'flat' ? ((float) ($unit->water_charge ?? 0)) : ($consumption * $rate);
            
            return [
                'id' => null,
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number ?? 'N/A',
                'estate_name' => $unit->estate->name ?? 'N/A',
                'estate_id' => $unit->estate_id,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
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
    
    // ================================================================
    // UNITS WITH WATER READINGS - For the modal dropdown
    // ================================================================
    $unitsWithWaterReadings = Unit::where('company_id', $company->id)
        ->with('estate')
        ->where('status', 'occupied')
        ->get()
        ->map(function($unit) {
            $lastReading = $unit->waterReadings()->orderBy('reading_date', 'desc')->first();
            return [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'estate_name' => $unit->estate->name ?? 'N/A',
                'last_reading' => $lastReading ? (float) $lastReading->current_reading : (float) ($unit->current_water_reading ?? 0),
                'last_reading_date' => $lastReading ? $lastReading->reading_date->format('Y-m-d') : ($unit->last_reading_date ? $unit->last_reading_date->format('Y-m-d') : null),
            ];
        });
    
    $totalUnits = Unit::where('company_id', $company->id)->count();
    $occupiedUnits = Unit::where('company_id', $company->id)->where('status', 'occupied')->count();
    $availableUnits = Unit::where('company_id', $company->id)->where('status', 'available')->count();
    
    $monthlyRevenue = $this->getMonthlyRevenueForCompany($company->id);
    $paymentMethods = $this->getPaymentMethodStatsForCompany($company->id);
    $roleData = $this->getRoleSpecificData($user, $company);
    
    $currentUnit = null;
    if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
        $currentUnit = $user->tenant->activeTenancy->unit->load('estate');
    }

    
    
    // ================================================================
    // RETURN VIEW WITH ALL DATA
    // ================================================================
    return view('dashboard', compact(
        'user', 'company', 'stats', 'mappedInvoices', 'mappedActiveTenancies',
        'activeTenancies', 'users', 'paymentInvoices', 'totalDraft', 'totalUnpaid',
        'totalPartial', 'totalPaidAll', 'tenanciesNeedingMoveInInvoices',
        'recentInvoices', 'recentPayments', 'waterReadings', 'unitsWithWaterReadings',
        'totalUnits', 'occupiedUnits', 'availableUnits', 'monthlyRevenue', 
        'paymentMethods', 'roleData', 'outstandingBalance', 'totalPaid', 
        'units', 'currentUnit', 'estates'
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
        $mappedActiveTenancies = collect();
        $outstandingBalance = 0;
        $totalPaid = 0;
        
        return view('dashboard', compact('user', 'company', 'stats', 'roleData', 'mappedActiveTenancies', 'outstandingBalance', 'totalPaid'));
    }
    
    /**
     * ACCOUNTANT DASHBOARD - FIXED with proper data
     */
    private function accountantDashboard()
    {
    $user = auth()->user();
    $company = $user->company;
    
    if (!$company) {
        return $this->pendingVerificationView($user);
    }
    
    // ================================================================
    // STATS CARDS
    // ================================================================
    $stats = [
        'total_revenue' => Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount'),
        'total_expenses' => Expense::where('company_id', $company->id)->sum('amount'),
        'net_income' => Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))->sum('amount') - Expense::where('company_id', $company->id)->sum('amount'),
        'total_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->count(),
        'paid_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'paid')->count(),
        'unpaid_invoices' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->where('status', 'unpaid')->count(),
        'outstanding_amount' => Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))->whereIn('status', ['unpaid', 'partial'])->sum('total_amount'),
        'collection_rate' => $this->calculateCollectionRate($company->id),
        'total_tenants' => Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))->count(),
        'total_units' => Unit::where('company_id', $company->id)->count(),
        'occupied_units' => Unit::where('company_id', $company->id)->where('status', 'occupied')->count(),
        'vacant_units' => Unit::where('company_id', $company->id)->where('status', 'vacant')->count(),
        'occupancy_rate' => $this->calculateOccupancyRate($company->id),
        'total_consumption' => Unit::where('company_id', $company->id)->sum(DB::raw('GREATEST(0, COALESCE(current_water_reading, 0) - COALESCE(previous_water_reading, 0))')),
        
    ];
    
    // ================================================================
    // CHART DATA - REVENUE ANALYTICS
    // ================================================================
    
    // 1. Monthly Revenue (Bar Chart)
    $monthlyRevenue = $this->getMonthlyRevenueForCompany($company->id);
    
    // 2. Payment Methods (Doughnut Chart)
    $paymentMethods = $this->getPaymentMethodStatsForCompany($company->id);
    
    // 3. Monthly Revenue vs Expenses (Line Chart)
    $monthlyRevenueExpense = $this->getMonthlyRevenueExpenseForCompany($company->id);
    
    // 4. Invoice Status (Pie Chart)
    $invoiceStatus = $this->getInvoiceStatusBreakdown($company->id);
    
    // 5. Collection Rate (Radial Chart)
    $collectionRate = $this->calculateCollectionRate($company->id);
    
    // 6. Performance Metrics (Radar Chart)
    $performanceMetrics = $this->getPerformanceMetrics($company->id);
    
    // ================================================================
    // PENDING DEPOSITS - ALL transactions with confirmed = 0
    // ================================================================
    $pendingTransactions = $this->getPendingDeposits($company);
    
    // ================================================================
    // ALL TRANSACTIONS - ALL transactions (deposits AND withdrawals)
    // ================================================================
    $allTransactions = $this->getAllTransactions($company);
    
    // ================================================================
    // OVERDUE INVOICES - All overdue invoices
    // ================================================================
    $overdueInvoices = $this->getOverdueInvoices($company);
    
    // ================================================================
    // ROLE DATA
    // ================================================================
    $roleData = $this->getRoleSpecificData($user, $company);
    $roleData['overdueInvoices'] = $overdueInvoices;
    
    // ================================================================
    // REQUIRED FOR VIEW
    // ================================================================
    $mappedActiveTenancies = collect();
    $outstandingBalance = 0;
    $totalPaid = 0;

    $agingReport = $this->getAgingReport($company->id);

    
    // ================================================================
    // RETURN VIEW WITH ALL DATA
    // ================================================================
    return view('dashboard', compact(
        'user', 
        'company', 
        'stats', 
        'roleData',
        'monthlyRevenue', 
        'paymentMethods',
        'monthlyRevenueExpense',
        'invoiceStatus',
        'collectionRate',
        'performanceMetrics',
        'pendingTransactions',
        'allTransactions',
        'overdueInvoices',
        'mappedActiveTenancies', 
        'outstandingBalance', 
        'totalPaid',
        'agingReport'
    ));
}

    /**
     * Get all pending deposits (transactions with confirmed = 0)
     */
    private function getPendingDeposits($company)
    {
        // Get tenant IDs for this company
        $tenantIds = Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->pluck('id')
            ->toArray();
        
        if (empty($tenantIds)) {
            return collect();
        }
        
        return Transaction::where('type', 'deposit')
            ->where('confirmed', 0)
            ->where(function($query) use ($tenantIds) {
                $query->where(function($q) use ($tenantIds) {
                    $q->where('payable_type', 'App\Modules\Tenants\Models\Tenant')
                    ->whereIn('payable_id', $tenantIds);
                })->orWhere(function($q) use ($tenantIds) {
                    $q->where('payable_type', 'App\Models\Tenant')
                    ->whereIn('payable_id', $tenantIds);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($tx) {
                // Get tenant
                $tenant = null;
                if ($tx->payable_type === 'App\Modules\Tenants\Models\Tenant' || $tx->payable_type === 'App\Models\Tenant') {
                    $tenant = Tenant::with('user', 'activeTenancy.unit')->find($tx->payable_id);
                }
                
                $meta = $tx->meta ?? [];
                
                // Determine if this is a credit (deposit) or debit (withdrawal)
                $isCredit = $tx->type === 'deposit';
                
                return [
                    'id' => $tx->id,
                    'uuid' => $tx->uuid,
                    'type' => $tx->type, // 'deposit' or 'withdraw'
                    'credit' => $isCredit ? (float) $tx->amount : 0,
                    'debit' => !$isCredit ? (float) $tx->amount : 0,
                    'amount' => (float) $tx->amount,
                    'amount_formatted' => 'KES ' . number_format((float) $tx->amount, 2),
                    'description' => $meta['description'] ?? ($tx->type === 'deposit' ? 'Deposit' : 'Withdrawal'),
                    'tenant_name' => $tenant?->user?->name ?? 'Unknown Tenant',
                    'tenant_id' => $tenant?->id ?? null,
                    'tenant_unit' => $tenant?->activeTenancy?->unit?->unit_number ?? null,
                    'payment_method' => $meta['payment_method'] ?? 'Unknown',
                    'reference' => $meta['reference'] ?? substr($tx->uuid, 0, 8),
                    'phone_number' => $meta['phone_number'] ?? null,
                    'bill_month' => $meta['bill_month'] ?? null,
                    'notes' => $meta['notes'] ?? $meta['transaction_message'] ?? null,
                    'created_at' => $tx->created_at,
                    'created_at_formatted' => $tx->created_at ? $tx->created_at->format('M d, Y H:i') : '-',
                    'status' => 'Pending Approval',
                    'status_badge' => 'warning',
                    'is_pending' => true,  // CRITICAL: This enables the approve button
                    'is_reconciled' => false,
                    'requires_approval' => true,
                    'meta' => $meta,
                    'initiated_by' => $meta['initiated_by_name'] ?? null,
                    'payable_type' => $tx->payable_type,
                ];
            });
    }

    /**
     * Get all transactions (ALL confirmed transactions with credit/debit)
     */
    private function getAllTransactions($company)
    {
        $payments = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit', 'tenant.user'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Also get wallet transactions for this company
        $tenantIds = Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->pluck('id')
            ->toArray();
        
        $Transactions = collect();
        if (!empty($tenantIds)) {
            $Transactions = Transaction::where('confirmed', 1)
                ->where(function($query) use ($tenantIds) {
                    $query->where(function($q) use ($tenantIds) {
                        $q->where('payable_type', 'App\Modules\Tenants\Models\Tenant')
                        ->whereIn('payable_id', $tenantIds);
                    })->orWhere(function($q) use ($tenantIds) {
                        $q->where('payable_type', 'App\Models\Tenant')
                        ->whereIn('payable_id', $tenantIds);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function($tx) {
                    $tenant = null;
                    if ($tx->payable_type === 'App\Modules\Tenants\Models\Tenant' || $tx->payable_type === 'App\Models\Tenant') {
                        $tenant = Tenant::with('user', 'activeTenancy.unit')->find($tx->payable_id);
                    }
                    $meta = $tx->meta ?? [];
                    $isCredit = $tx->type === 'deposit';
                    
                    return [
                        'id' => 'wallet_' . $tx->id,
                        'uuid' => $tx->uuid,
                        'type' => $tx->type,
                        'credit' => $isCredit ? (float) $tx->amount : 0,
                        'debit' => !$isCredit ? (float) $tx->amount : 0,
                        'amount' => (float) $tx->amount,
                        'amount_formatted' => 'KES ' . number_format((float) $tx->amount, 2),
                        'description' => $meta['description'] ?? ($tx->type === 'deposit' ? 'Wallet Deposit' : 'Wallet Withdrawal'),
                        'tenant_name' => $tenant?->user?->name ?? 'System',
                        'tenant_id' => $tenant?->id ?? null,
                        'tenant_unit' => $tenant?->activeTenancy?->unit?->unit_number ?? null,
                        'payment_method' => $meta['payment_method'] ?? 'Wallet',
                        'reference' => $meta['reference'] ?? substr($tx->uuid, 0, 8),
                        'phone_number' => $meta['phone_number'] ?? null,
                        'bill_month' => $meta['bill_month'] ?? null,
                        'notes' => $meta['notes'] ?? $meta['transaction_message'] ?? null,
                        'created_at' => $tx->created_at,
                        'created_at_formatted' => $tx->created_at ? $tx->created_at->format('M d, Y H:i') : '-',
                        'status' => 'Completed',
                        'status_badge' => 'success',
                        'is_pending' => false,
                        'is_reconciled' => $meta['is_reconciled'] ?? false,
                        'requires_approval' => false,
                        'meta' => $meta,
                        'initiated_by' => $meta['initiated_by_name'] ?? null,
                        'payable_type' => $tx->payable_type,
                        'source' => 'wallet',
                    ];
                });
        }
        
        // Map payments with credit/debit
        $paymentTransactions = $payments->map(function ($payment) {
            $isCredit = true; // Payments are always credits
            $paymentMethod = $payment->payment_method ?? 'Unknown';
            
            // Determine if this is a deposit or payment
            if ($paymentMethod === 'wallet' || $paymentMethod === 'deposit') {
                $type = 'deposit';
                $description = 'Wallet Deposit';
            } elseif ($paymentMethod === 'withdrawal') {
                $type = 'withdraw';
                $description = 'Withdrawal';
            } else {
                $type = 'payment';
                $description = 'Payment - ' . ($payment->invoice?->invoice_number ?? 'Invoice');
            }
            
            return [
                'id' => $payment->id,
                'uuid' => $payment->uuid ?? 'pay_' . $payment->id,
                'type' => $type,
                'credit' => (float) $payment->amount,
                'debit' => 0,
                'amount' => (float) $payment->amount,
                'amount_formatted' => 'KES ' . number_format((float) $payment->amount, 2),
                'description' => $description,
                'tenant_name' => $payment->invoice?->tenancy?->tenant?->user?->name ?? $payment->tenant?->user?->name ?? 'N/A',
                'tenant_id' => $payment->invoice?->tenancy?->tenant_id ?? $payment->tenant_id ?? null,
                'tenant_unit' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                'payment_method' => $paymentMethod,
                'reference' => $payment->transaction_reference ?? $payment->external_reference ?? substr($payment->uuid ?? '', 0, 8),
                'phone_number' => null,
                'bill_month' => $payment->invoice?->billing_month ?? null,
                'notes' => $payment->notes ?? null,
                'created_at' => $payment->created_at,
                'created_at_formatted' => $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-',
                'status' => $payment->status ?? 'Completed',
                'status_badge' => ($payment->status ?? 'completed') === 'completed' ? 'success' : 'warning',
                'is_pending' => false,
                'is_reconciled' => $payment->is_reconciled ?? false,
                'requires_approval' => false,
                'meta' => $payment->meta ?? [],
                'initiated_by' => null,
                'payable_type' => 'App\Models\Payment',
                'source' => 'payment',
                'invoice_number' => $payment->invoice?->invoice_number ?? 'N/A',
            ];
        });
        
        // Merge and sort all transactions by created_at
        return $paymentTransactions->concat($Transactions)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Get all overdue invoices
     */
    private function getOverdueInvoices($company)
    {
        return Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->where('status', 'unpaid')
            ->where('billing_month', '<=', Carbon::now()->subMonth()->format('Y-m-01'))
            ->with(['tenancy.tenant.user', 'tenancy.unit', 'items', 'payments'])
            ->orderBy('billing_month', 'asc')
            ->get()
            ->map(function ($invoice) {
                $lastPayment = $invoice->payments->last();
                $paidAmount = (float) $invoice->payments->sum('amount');
                $totalAmount = (float) $invoice->total_amount;
                $balance = $totalAmount - $paidAmount;
                $daysOverdue = $invoice->billing_month ? Carbon::parse($invoice->billing_month)->diffInMonths(now()) : 0;
                
                // Determine urgency color
                $urgencyColor = 'text-yellow-600 dark:text-yellow-400';
                $urgencyBg = 'bg-yellow-100 dark:bg-yellow-900/30';
                if ($daysOverdue >= 6) {
                    $urgencyColor = 'text-red-600 dark:text-red-400';
                    $urgencyBg = 'bg-red-100 dark:bg-red-900/30';
                } elseif ($daysOverdue >= 3) {
                    $urgencyColor = 'text-orange-600 dark:text-orange-400';
                    $urgencyBg = 'bg-orange-100 dark:bg-orange-900/30';
                }
                
                return [
                    'id' => $invoice->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'unit_id' => $invoice->tenancy->unit->id ?? null,
                    'tenant_id' => $invoice->tenancy->tenant_id ?? null,
                    'payer_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'payment_method' => $lastPayment->payment_method ?? 'N/A',
                    'payment_method_label' => $lastPayment ? $lastPayment->payment_method_label : 'N/A',
                    'payment_datetime' => $lastPayment ? $lastPayment->created_at : null,
                    'total_amount' => $totalAmount,
                    'total_amount_formatted' => 'KES ' . number_format($totalAmount, 2),
                    'status' => $invoice->status,
                    'status_badge' => 'unpaid',
                    'billing_month' => $invoice->billing_month,
                    'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                    'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                    'tenancy_id' => $invoice->tenancy_id,
                    'days_overdue' => $daysOverdue,
                    'days_overdue_label' => $daysOverdue . ' month' . ($daysOverdue > 1 ? 's' : ''),
                    'urgency_color' => $urgencyColor,
                    'urgency_bg' => $urgencyBg,
                    'paid_amount' => $paidAmount,
                    'paid_amount_formatted' => 'KES ' . number_format($paidAmount, 2),
                    'balance' => $balance,
                    'balance_formatted' => 'KES ' . number_format($balance, 2),
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
    }
    
    
    /**
     * Get transaction description from meta
     */
    private function getTransactionDescription($tx)
    {
        $meta = $tx->meta ?? [];
        
        if ($tx->type === 'deposit') {
            if (isset($meta['payment_method']) && $meta['payment_method'] === 'manual') {
                return 'Manual Top-up - ' . ($meta['notes'] ?? 'Pending Approval');
            }
            if (isset($meta['payment_method']) && $meta['payment_method'] === 'message') {
                return 'Transaction Message - ' . ($meta['bill_month'] ?? '');
            }
            if (isset($meta['payment_method'])) {
                return ucfirst($meta['payment_method']) . ' Deposit';
            }
            return 'Wallet Deposit';
        }
        
        if ($tx->type === 'withdraw') {
            return 'Wallet Withdrawal';
        }
        
        return $tx->description ?? 'Transaction';
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
        $walletData = $this->getTenantWalletData($user);
        
        \Log::info('Wallet Data in tenantDashboard', [
            'balance' => $walletData['balance'],
            'total_spent' => $walletData['total_spent'],
            'rent_spent' => $walletData['rent_spent'],
            'water_spent' => $walletData['water_spent'],
            'electricity_spent' => $walletData['electricity_spent'],
            'balance_change' => $walletData['balance_change'],
            'full_wallet_number' => $walletData['full_wallet_number'],
        ]);
        
        $outstandingBalance = $roleData['outstandingBalance'] ?? 0;
        $totalPaid = $roleData['totalPaid'] ?? 0;
        $units = $roleData['units'] ?? [];
        $estates = $roleData['estates'] ?? [];
        $currentUnit = $roleData['currentUnit'] ?? null;
        $mappedActiveTenancies = collect();
        
        return view('dashboard', compact(
            'user', 'company', 'stats', 'roleData', 
            'outstandingBalance', 'totalPaid', 'mappedActiveTenancies',
            'units', 'estates', 'currentUnit', 'walletData'
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
    
    /**
     * Get Tenant Data
     */
    private function getTenantData($user)
    {
        $tenant = $user->tenant;
        $company = $user->company;
        
        if (!$tenant || !$tenant->activeTenancy) {
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
        
        $payments = Payment::whereHas('invoice', function ($query) use ($activeTenancy) {
                $query->where('tenancy_id', $activeTenancy->id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $payment->payment_method_label,
                    'payment_datetime' => $payment->created_at,
                    'date' => $payment->created_at ? $payment->created_at->format('M d, Y') : '-',
                    'transaction_reference' => $payment->transaction_reference,
                    'external_reference' => $payment->external_reference,
                    'invoice_id' => $payment->invoice_id,
                    'status' => $payment->status,
                    'status_badge' => $payment->status_badge,
                    'is_reconciled' => $payment->is_reconciled,
                ];
            });
        
        $totalPaid = (float) $payments->sum('amount');
        $totalBilled = (float) collect($invoices)->sum('total_amount');
        $outstandingBalance = $totalBilled - $totalPaid;
        
        $unit = $activeTenancy->unit;
        $waterInfo = [
            'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
            'current_reading' => (float) ($unit->current_water_reading ?? 0),
            'consumption' => max(0, ($unit->current_water_reading ?? 0) - ($unit->previous_water_reading ?? 0)),
            'rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
            'billing_type' => $unit->water_billing_type ?? 'consumption',
            'last_reading_date' => $unit->last_reading_date,
        ];
        
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
        
        $maintenanceRequests = Maintenance::where('tenant_id', $tenant->id)
            ->with('unit')
            ->latest()
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

    /**
     * Get Admin Data
     */
    private function getAdminData($company)
    {
        $recentInvoices = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['tenancy.tenant.user', 'tenancy.unit', 'items', 'payments'])
            ->latest()
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
                    'payment_method_label' => $lastPayment ? $lastPayment->payment_method_label : 'N/A',
                    'payment_datetime' => $lastPayment ? $lastPayment->created_at : null,
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
        
        $recentPayments = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
            ->latest('created_at')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'invoice_id' => $payment->invoice_id ?? null,
                    'tenant_name' => $payment->invoice?->tenancy?->tenant?->user?->name ?? 'N/A',
                    'unit_number' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                    'unit_id' => $payment->invoice?->tenancy?->unit?->id ?? null,
                    'payer_name' => $payment->tenant?->user?->name ?? 'Tenant Payment',
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $payment->payment_method_label,
                    'payment_datetime' => $payment->created_at,   
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'date' => $payment->created_at ? $payment->created_at->format('M d, Y') : '-',
                    'transaction_reference' => $payment->transaction_reference,
                    'external_reference' => $payment->external_reference,
                    'status' => $payment->status,
                    'is_reconciled' => $payment->is_reconciled,
                ];
            });
        
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

    /**
     * Get Accountant Data - FIXED
     */
    private function getAccountantData($company)
    {
        // Get tenant IDs for this company
        $tenantIds = Tenant::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->pluck('id')
            ->toArray();
        
        // ================================================================
        // PENDING TRANSACTIONS (confirmed = 0)
        // ================================================================
        $pendingTransactions = collect();
        if (!empty($tenantIds)) {
            $pendingTransactions = Transaction::where('type', 'deposit')
                ->where('confirmed', 0)
                ->where(function($query) use ($tenantIds) {
                    $query->where(function($q) use ($tenantIds) {
                        $q->where('payable_type', 'App\Modules\Tenants\Models\Tenant')
                        ->whereIn('payable_id', $tenantIds);
                    })->orWhere(function($q) use ($tenantIds) {
                        $q->where('payable_type', 'App\Models\Tenant')
                        ->whereIn('payable_id', $tenantIds);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($tx) {
                    $tenant = null;
                    if ($tx->payable_type === 'App\Modules\Tenants\Models\Tenant' || $tx->payable_type === 'App\Models\Tenant') {
                        $tenant = Tenant::with('user', 'activeTenancy.unit')->find($tx->payable_id);
                    }
                    $meta = $tx->meta ?? [];
                    $isCredit = $tx->type === 'deposit';
                    
                    return [
                        'id' => $tx->id,
                        'uuid' => $tx->uuid,
                        'type' => $tx->type,
                        'credit' => $isCredit ? (float) $tx->amount : 0,
                        'debit' => !$isCredit ? (float) $tx->amount : 0,
                        'amount' => (float) $tx->amount,
                        'description' => $meta['description'] ?? ($tx->type === 'deposit' ? 'Deposit' : 'Withdrawal'),
                        'tenant_name' => $tenant?->user?->name ?? 'Unknown Tenant',
                        'tenant_id' => $tenant?->id ?? null,
                        'tenant_unit' => $tenant?->activeTenancy?->unit?->unit_number ?? null,
                        'payment_method' => $meta['payment_method'] ?? 'Unknown',
                        'reference' => $meta['reference'] ?? substr($tx->uuid, 0, 8),
                        'phone_number' => $meta['phone_number'] ?? null,
                        'bill_month' => $meta['bill_month'] ?? null,
                        'notes' => $meta['notes'] ?? $meta['transaction_message'] ?? null,
                        'created_at' => $tx->created_at,
                        'created_at_formatted' => $tx->created_at ? $tx->created_at->format('M d, Y H:i') : '-',
                        'status' => 'Pending Approval',
                        'status_badge' => 'warning',
                        'is_pending' => true,
                        'is_reconciled' => false,
                        'running_balance' => 0,
                        'meta' => $meta,
                        'initiated_by' => $meta['initiated_by_name'] ?? null,
                    ];
                });
        }
        
        // ================================================================
        // CONFIRMED TRANSACTIONS (confirmed = 1)
        // ================================================================
        $confirmedTransactions = collect();
        if (!empty($tenantIds)) {
            $confirmedTransactions = Transaction::where('confirmed', 1)
                ->where(function($query) use ($tenantIds) {
                    $query->where(function($q) use ($tenantIds) {
                        $q->where('payable_type', 'App\Modules\Tenants\Models\Tenant')
                        ->whereIn('payable_id', $tenantIds);
                    })->orWhere(function($q) use ($tenantIds) {
                        $q->where('payable_type', 'App\Models\Tenant')
                        ->whereIn('payable_id', $tenantIds);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function($tx) {
                    $tenant = null;
                    if ($tx->payable_type === 'App\Modules\Tenants\Models\Tenant' || $tx->payable_type === 'App\Models\Tenant') {
                        $tenant = Tenant::with('user', 'activeTenancy.unit')->find($tx->payable_id);
                    }
                    $meta = $tx->meta ?? [];
                    $isCredit = $tx->type === 'deposit';
                    
                    return [
                        'id' => $tx->id,
                        'uuid' => $tx->uuid,
                        'type' => $tx->type,
                        'credit' => $isCredit ? (float) $tx->amount : 0,
                        'debit' => !$isCredit ? (float) $tx->amount : 0,
                        'amount' => (float) $tx->amount,
                        'description' => $meta['description'] ?? ($tx->type === 'deposit' ? 'Wallet Deposit' : 'Wallet Withdrawal'),
                        'tenant_name' => $tenant?->user?->name ?? 'System',
                        'tenant_id' => $tenant?->id ?? null,
                        'tenant_unit' => $tenant?->activeTenancy?->unit?->unit_number ?? null,
                        'payment_method' => $meta['payment_method'] ?? 'Wallet',
                        'reference' => $meta['reference'] ?? substr($tx->uuid, 0, 8),
                        'phone_number' => $meta['phone_number'] ?? null,
                        'bill_month' => $meta['bill_month'] ?? null,
                        'notes' => $meta['notes'] ?? $meta['transaction_message'] ?? null,
                        'created_at' => $tx->created_at,
                        'created_at_formatted' => $tx->created_at ? $tx->created_at->format('M d, Y H:i') : '-',
                        'status' => 'Completed',
                        'status_badge' => 'success',
                        'is_pending' => false,
                        'is_reconciled' => $meta['is_reconciled'] ?? false,
                        'running_balance' => 0,
                        'meta' => $meta,
                        'initiated_by' => $meta['initiated_by_name'] ?? null,
                    ];
                });
        }
        
        // ================================================================
        // MERGE TRANSACTIONS - FIXED: Both are collections of arrays
        // ================================================================
        $allTransactions = $pendingTransactions->concat($confirmedTransactions)
            ->sortByDesc('created_at')
            ->values();
        
        // Calculate running balance (oldest to newest)
        $sorted = $allTransactions->sortBy('created_at')->values();
        $balance = 0;
        foreach ($sorted as &$tx) {
            $balance += ($tx['credit'] ?? 0) - ($tx['debit'] ?? 0);
            $tx['running_balance'] = $balance;
        }
        $allTransactions = $sorted->sortByDesc('created_at')->values();
        
        // ================================================================
        // OVERDUE INVOICES
        // ================================================================
        $overdueInvoices = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $company->id))
            ->where('status', 'unpaid')
            ->where('billing_month', '<=', Carbon::now()->subMonth()->format('Y-m-01'))
            ->with(['tenancy.tenant.user', 'tenancy.unit', 'items', 'payments'])
            ->take(20)
            ->get()
            ->map(function ($invoice) {
                $lastPayment = $invoice->payments->last();
                $paidAmount = (float) $invoice->payments->sum('amount');
                $totalAmount = (float) $invoice->total_amount;
                $balance = $totalAmount - $paidAmount;
                $daysOverdue = $invoice->billing_month ? Carbon::parse($invoice->billing_month)->diffInMonths(now()) : 0;
                
                return [
                    'id' => $invoice->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'unit_id' => $invoice->tenancy->unit->id ?? null,
                    'tenant_id' => $invoice->tenancy->tenant_id ?? null,
                    'payer_name' => $invoice->tenancy->tenant->user->name ?? 'N/A',
                    'payment_method' => $lastPayment->payment_method ?? 'N/A',
                    'payment_method_label' => $lastPayment ? $lastPayment->payment_method_label : 'N/A',
                    'payment_datetime' => $lastPayment ? $lastPayment->created_at : null,
                    'total_amount' => $totalAmount,
                    'status' => $invoice->status,
                    'billing_month' => $invoice->billing_month,
                    'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                    'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                    'tenancy_id' => $invoice->tenancy_id,
                    'days_overdue' => $daysOverdue,
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
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
        
        return [
            'type' => 'accountant',
            'pendingTransactions' => $pendingTransactions->values(),
            'confirmedTransactions' => $confirmedTransactions->values(),
            'allTransactions' => $allTransactions,
            'overdueInvoices' => $overdueInvoices,
        ];
    }

    /**
     * Get Tenant Wallet Data
     */
    private function getTenantWalletData($user)
    {
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return $this->getDefaultWalletData();
        }
        
        try {
            $wallet = $tenant->wallet;
            $balance = (float) $tenant->balance;
            
            $walletId = $wallet ? $wallet->getKey() : null;
            $fullWalletNumber = $walletId ? str_pad((string) $walletId, 16, '0', STR_PAD_LEFT) : str_pad((string) $tenant->id, 16, '0', STR_PAD_LEFT);
            $maskedWalletNumber = '•••• •••• •••• ' . substr($fullWalletNumber, -4);
            
            $totalSpent = 0;
            $rentSpent = 0;
            $waterSpent = 0;
            $electricitySpent = 0;
            $otherSpent = 0;
            
            $payments = Payment::whereHas('invoice', function($q) use ($tenant) {
                    $q->whereHas('tenancy', function($sq) use ($tenant) {
                        $sq->where('tenant_id', $tenant->id);
                    });
                })
                ->where('status', 'completed')
                ->get();
            
            foreach ($payments as $payment) {
                $totalSpent += (float) $payment->amount;
                
                if ($payment->invoice) {
                    $invoice = $payment->invoice;
                    $items = $invoice->items;
                    
                    if ($items->count() > 0) {
                        $totalInvoiceAmount = (float) $invoice->total_amount;
                        $paymentAmount = (float) $payment->amount;
                        
                        foreach ($items as $item) {
                            $itemAmount = (float) $item->amount;
                            $portion = $totalInvoiceAmount > 0 ? $itemAmount / $totalInvoiceAmount : 0;
                            $allocatedAmount = $paymentAmount * $portion;
                            
                            $itemType = strtolower($item->item_type ?? $item->type ?? '');
                            
                            if (strpos($itemType, 'rent') !== false) {
                                $rentSpent += $allocatedAmount;
                            } elseif (strpos($itemType, 'water') !== false) {
                                $waterSpent += $allocatedAmount;
                            } elseif (strpos($itemType, 'electric') !== false || strpos($itemType, 'power') !== false) {
                                $electricitySpent += $allocatedAmount;
                            } else {
                                $otherSpent += $allocatedAmount;
                            }
                        }
                    } else {
                        $invoiceType = strtolower($invoice->invoice_type ?? '');
                        $description = strtolower($invoice->description ?? '');
                        
                        if (strpos($invoiceType, 'rent') !== false || strpos($description, 'rent') !== false) {
                            $rentSpent += (float) $payment->amount;
                        } elseif (strpos($invoiceType, 'water') !== false || strpos($description, 'water') !== false) {
                            $waterSpent += (float) $payment->amount;
                        } elseif (strpos($invoiceType, 'electric') !== false || strpos($invoiceType, 'power') !== false || strpos($description, 'electric') !== false) {
                            $electricitySpent += (float) $payment->amount;
                        } else {
                            $otherSpent += (float) $payment->amount;
                        }
                    }
                }
            }
            
            $totalSpent = round($totalSpent, 2);
            $rentSpent = round($rentSpent, 2);
            $waterSpent = round($waterSpent, 2);
            $electricitySpent = round($electricitySpent, 2);
            $otherSpent = round($otherSpent, 2);
            
            $balanceChange = $this->calculateBalanceChange($tenant);
            
            $transactions = collect();
            if ($tenant->transactions()) {
                $transactions = $tenant->transactions()
                    ->where('confirmed', 1)
                    ->orderBy('created_at', 'desc')
                    ->take(10)
                    ->get()
                    ->map(function($tx) {
                        return [
                            'id' => $tx->id,
                            'type' => $tx->type,
                            'amount' => (float) $tx->amount,
                            'description' => $tx->meta['description'] ?? ($tx->type === 'deposit' ? 'Deposit' : 'Withdrawal'),
                            'created_at' => $tx->created_at->toIso8601String(),
                            'confirmed' => (bool) $tx->confirmed,
                        ];
                    });
            }
            
            $userPhone = $user->phone ?? '0000000000';
            $formattedPhone = substr($userPhone, -4);
            
            $cards = [
                [
                    'id' => 1,
                    'cardholderName' => $user->name ?? 'Card Holder',
                    'cardNumber' => $formattedPhone,
                    'full_card_number' => $userPhone,
                    'expiry' => '--',
                    'cvc' => '--',
                    'active' => true,
                    'cardType' => 'mpesa',
                    'payment_method_id' => null,
                    'bgClass' => 'bg-gradient-to-br from-green-600 to-green-800 dark:from-green-800 dark:to-green-950',
                    'logo' => 'mpesa',
                    'display_name' => 'M-Pesa',
                ]
            ];
            
            return [
                'balance' => $balance,
                'formatted_balance' => 'KES ' . number_format($balance, 2),
                'wallet_id' => $wallet->uuid ?? null,
                'wallet_number' => $fullWalletNumber,
                'masked_wallet_number' => $maskedWalletNumber,
                'full_wallet_number' => $fullWalletNumber,
                'has_wallet' => (bool) $wallet,
                'total_spent' => $totalSpent,
                'rent_spent' => $rentSpent,
                'water_spent' => $waterSpent,
                'electricity_spent' => $electricitySpent,
                'other_spent' => $otherSpent,
                'balance_change' => $balanceChange,
                'transactions' => $transactions,
                'cards' => $cards,
                'user_phone' => $userPhone,
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error in getTenantWalletData: ' . $e->getMessage());
            return $this->getDefaultWalletData();
        }
    }

    private function getDefaultWalletData()
    {
        return [
            'balance' => 0,
            'formatted_balance' => 'KES 0.00',
            'wallet_id' => null,
            'wallet_number' => null,
            'masked_wallet_number' => null,
            'full_wallet_number' => null,
            'has_wallet' => false,
            'total_spent' => 0,
            'rent_spent' => 0,
            'water_spent' => 0,
            'electricity_spent' => 0,
            'other_spent' => 0,
            'balance_change' => 0,
            'transactions' => collect(),
            'cards' => [],
            'user_phone' => null,
        ];
    }

    private function calculateBalanceChange($tenant)
    {
        try {
            $now = now();
            $currentMonth = $now->month;
            $currentYear = $now->year;
            $lastMonth = $now->copy()->subMonth()->month;
            $lastMonthYear = $now->copy()->subMonth()->year;
            
            $currentMonthDeposits = $tenant->transactions()
                ->where('type', 'deposit')
                ->where('confirmed', 1)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->sum('amount');
            
            $currentMonthWithdrawals = $tenant->transactions()
                ->where('type', 'withdraw')
                ->where('confirmed', 1)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->sum('amount');
            
            $currentMonthNet = $currentMonthDeposits - $currentMonthWithdrawals;
            
            $lastMonthDeposits = $tenant->transactions()
                ->where('type', 'deposit')
                ->where('confirmed', 1)
                ->whereMonth('created_at', $lastMonth)
                ->whereYear('created_at', $lastMonthYear)
                ->sum('amount');
            
            $lastMonthWithdrawals = $tenant->transactions()
                ->where('type', 'withdraw')
                ->where('confirmed', 1)
                ->whereMonth('created_at', $lastMonth)
                ->whereYear('created_at', $lastMonthYear)
                ->sum('amount');
            
            $lastMonthNet = $lastMonthDeposits - $lastMonthWithdrawals;
            
            if ($lastMonthNet > 0) {
                return round((($currentMonthNet - $lastMonthNet) / $lastMonthNet) * 100, 1);
            } elseif ($currentMonthNet > 0) {
                return 100;
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

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

    private function getMonthlyRevenueForCompany($companyId)
    {
        $revenue = Payment::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'completed')
            ->whereYear('created_at', '>=', Carbon::now()->subYear()->year)
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
    $stats = Payment::select('payment_method', DB::raw('SUM(amount) as total'))
        ->whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
        ->where('status', 'completed')
        ->groupBy('payment_method')
        ->get()
        ->pluck('total', 'payment_method')
        ->toArray();
    
    // Format labels for display
    $formatted = [];
    $labels = [
        'wallet' => 'Wallet',
        'mpesa_stk' => 'M-Pesa STK',
        'mpesa_paybill' => 'M-Pesa Paybill',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'manual_topup' => 'Manual Top-up',
        'message_paste' => 'Transaction Message'
    ];
    
    foreach ($stats as $key => $value) {
        $formatted[$labels[$key] ?? ucfirst(str_replace('_', ' ', $key))] = $value;
    }
    
    return $formatted;
}

    private function getCommonStats($user = null)
    {
        $user = $user ?? auth()->user();
        $company = $user->company;
        
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
        return ['type' => 'sysadmin'];
    }
    
    private function getPropertyManagerData($company)
    {
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
        
        $allUnits = Unit::where('company_id', $company->id)->with('estate')->get();
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        
        $allReadings = WaterReading::whereHas('unit', fn($q) => $q->where('company_id', $company->id))
            ->with(['unit.estate', 'recordedBy'])
            ->orderBy('reading_date', 'asc')
            ->get();
        
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
        
        $currentMonthReadings = $currentMonthReadings->sortByDesc('consumption')->values();
        $totalConsumption = $currentMonthReadingsCollection->sum('consumption');
        
        $unitIdsWithCurrentMonthReading = $currentMonthReadingsCollection->pluck('unit_id')->toArray();
        
        $pendingReadings = $allUnits
            ->filter(function($unit) use ($unitIdsWithCurrentMonthReading) {
                return $unit->status === 'occupied' && !in_array($unit->id, $unitIdsWithCurrentMonthReading);
            })
            ->map(function($unit) {
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
        
        $readingsByUnit = [];
        foreach ($allReadings as $reading) {
            $unitId = $reading->unit_id;
            if (!isset($readingsByUnit[$unitId])) {
                $readingsByUnit[$unitId] = [];
            }
            $readingsByUnit[$unitId][] = $reading;
        }
        
        $historyReadingsList = [];
        foreach ($readingsByUnit as $unitId => $unitReadings) {
            $sortedReadings = collect($unitReadings)->sortBy('reading_date')->values();
            $unit = $allUnits->firstWhere('id', $unitId);
            if (!$unit) continue;
            $previousValue = (float) ($unit->previous_water_reading ?? 0);
            foreach ($sortedReadings as $reading) {
                $currentValue = (float) $reading->current_reading;
                $consumption = $reading->consumption ?: max(0, $currentValue - $previousValue);
                $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                $charge = $unit->water_billing_type === 'flat' ? ($unit->water_charge ?? 0) : ($consumption * $rate);
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
                $previousValue = $currentValue;
            }
        }
        
        $historyReadings = collect($historyReadingsList)->sortByDesc('reading_date')->values();
        $firstReading = $allReadings->sortBy('reading_date')->first();
        $lastReading = $allReadings->sortByDesc('reading_date')->first();
        $firstReadingDate = $firstReading ? $firstReading->reading_date->format('M Y') : 'N/A';
        $lastReadingDate = $lastReading ? $lastReading->reading_date->format('M Y') : 'N/A';
        $unitsWithHistoryCount = collect($historyReadingsList)->pluck('unit_id')->unique()->count();
        
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
        
        $estates = Estate::where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(function($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                ];
            });
        
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
        
        return [
            'type' => 'meter_reader',
            'pendingCount' => $pendingReadings->count(),
            'unitsNeedingReading' => $pendingReadings,
            'pendingReadings' => $pendingReadings,
            'currentMonthCount' => $currentMonthReadings->count(),
            'currentMonthReadings' => $currentMonthReadings,
            'unitsWithHistoryCount' => $unitsWithHistoryCount,
            'historyReadings' => $historyReadings,
            'firstReadingDate' => $firstReadingDate,
            'lastReadingDate' => $lastReadingDate,
            'totalConsumption' => $totalConsumption,
            'allWaterReadings' => $allReadings->count(),
            'todayReadings' => $allReadings->filter(fn($r) => $r->reading_date->isToday())->count(),
            'thisMonthReadings' => $currentMonthReadings->count(),
            'units' => $units,
            'estates' => $estates,
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
        $estates = Estate::where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(function($estate) {
                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                ];
            });
        
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

        $openRequests = $allRequests->filter(function($request) {
            return in_array($request['status'], ['open', 'pending']);
        })->values();

        $inProgressRequests = $allRequests->filter(function($request) {
            return $request['status'] === 'in_progress' || $request['status'] === 'pending_parts';
        })->values();

        $completedRequests = $allRequests->filter(function($request) {
            return in_array($request['status'], ['completed', 'resolved', 'cancelled']);
        })->values();
        
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
            'units' => $units,
        ];
    }
    
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

    private function getUserCards($user)
    {
        return [
            [
                'id' => 1,
                'cardholderName' => $user->name ?? 'Card Holder',
                'cardNumber' => '4983',
                'expiry' => '09/29',
                'cvc' => '659',
                'active' => true,
                'cardType' => 'mastercard',
                'bgClass' => 'bg-gradient-to-br from-gray-800 to-gray-900 dark:from-gray-900 dark:to-gray-950'
            ]
        ];
    }


    // CHARTS

    /**
     * Get chart data for AJAX requests
     */
    public function getChartData(Request $request)
    {
        $user = auth()->user();
        $company = $user->company;
        $interval = $request->get('interval', 'monthly');
        $chartType = $request->get('type', 'revenue');
        
        switch ($chartType) {
            case 'revenue':
                $data = $this->getRevenueChartData($company->id, $interval);
                break;
            case 'payment_methods':
                $data = $this->getPaymentMethodsData($company->id);
                break;
            case 'revenue_expense':
                $data = $this->getRevenueExpenseData($company->id, $interval);
                break;
            case 'invoice_status':
                $data = $this->getInvoiceStatusData($company->id);
                break;
            default:
                $data = $this->getRevenueChartData($company->id, $interval);
        }
        
        return response()->json([
            'success' => true,
            'chartData' => $data
        ]);
    }

    /**
     * Get revenue chart data with interval support
     */
    private function getRevenueChartData($companyId, $interval = 'monthly')
    {
        $query = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'completed');
        
        switch ($interval) {
            case 'daily':
                $data = $query->select(
                        DB::raw('DATE(created_at) as period'),
                        DB::raw('SUM(amount) as total')
                    )
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                
                $dates = $data->pluck('period')->map(fn($d) => date('M d', strtotime($d)))->toArray();
                $counts = $data->pluck('total')->toArray();
                break;
                
            case 'weekly':
                $data = $query->select(
                        DB::raw('YEARWEEK(created_at) as period'),
                        DB::raw('SUM(amount) as total')
                    )
                    ->where('created_at', '>=', now()->subWeeks(12))
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                
                $dates = $data->pluck('period')->map(fn($w) => 'Week ' . substr($w, -2))->toArray();
                $counts = $data->pluck('total')->toArray();
                break;
                
            case 'monthly':
            default:
                $data = $query->select(
                        DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'),
                        DB::raw('SUM(amount) as total')
                    )
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->groupBy('period')
                    ->orderBy('period', 'asc')
                    ->get();
                
                $dates = $data->pluck('period')->map(fn($m) => date('M Y', strtotime($m . '-01')))->toArray();
                $counts = $data->pluck('total')->toArray();
                break;
        }
        
        return [
            'dates' => $dates,
            'counts' => $counts,
            'interval' => $interval
        ];
    }

    /**
     * Get payment methods data for doughnut chart
     */
    private function getPaymentMethodsData($companyId)
    {
        $data = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'completed')
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();
        
        $labels = $data->pluck('payment_method')->map(function($method) {
            $labels = [
                'wallet' => 'Wallet',
                'mpesa_stk' => 'M-Pesa STK',
                'mpesa_paybill' => 'M-Pesa Paybill',
                'bank_transfer' => 'Bank Transfer',
                'cash' => 'Cash',
                'manual_topup' => 'Manual Top-up'
            ];
            return $labels[$method] ?? ucfirst($method);
        })->toArray();
        
        $values = $data->pluck('total')->toArray();
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get revenue vs expense data for line chart
     */
    private function getRevenueExpenseData($companyId, $interval = 'monthly')
    {
        // Get revenue
        $revenue = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'completed')
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'), DB::raw('SUM(amount) as total'))
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->get()
            ->pluck('total', 'period')
            ->toArray();
        
        // Get expenses
        $expenses = Expense::where('company_id', $companyId)
            ->select(DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as period'), DB::raw('SUM(amount) as total'))
            ->where('expense_date', '>=', now()->subMonths(12))
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->get()
            ->pluck('total', 'period')
            ->toArray();
        
        $dates = [];
        $revenueData = [];
        $expenseData = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $period = Carbon::now()->subMonths($i)->format('Y-m');
            $dates[] = Carbon::now()->subMonths($i)->format('M Y');
            $revenueData[] = $revenue[$period] ?? 0;
            $expenseData[] = $expenses[$period] ?? 0;
        }
        
        return [
            'dates' => $dates,
            'revenue' => $revenueData,
            'expenses' => $expenseData
        ];
    }

    /**
     * Get invoice status data for pie chart
     */
    private function getInvoiceStatusData($companyId)
    {
        $statuses = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
        
        $allStatuses = ['paid' => 0, 'unpaid' => 0, 'partial' => 0, 'draft' => 0];
        foreach ($statuses as $status => $count) {
            if (isset($allStatuses[$status])) {
                $allStatuses[$status] = $count;
            }
        }
        
        return [
            'labels' => array_keys($allStatuses),
            'values' => array_values($allStatuses)
        ];
    }


    /**
 * Get monthly revenue vs expenses for chart
 */
private function getMonthlyRevenueExpenseForCompany($companyId)
{
    $revenue = Payment::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(amount) as total')
        )
        ->whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
        ->where('status', 'completed')
        ->whereYear('created_at', '>=', Carbon::now()->subYear()->year)
        ->groupBy('month')
        ->orderBy('month', 'asc')
        ->get()
        ->pluck('total', 'month')
        ->toArray();
    
    $expenses = Expense::select(
            DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as month'),
            DB::raw('SUM(amount) as total')
        )
        ->where('company_id', $companyId)
        ->whereYear('expense_date', '>=', Carbon::now()->subYear()->year)
        ->groupBy('month')
        ->orderBy('month', 'asc')
        ->get()
        ->pluck('total', 'month')
        ->toArray();
    
    $result = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = Carbon::now()->subMonths($i)->format('Y-m');
        $result[$month] = [
            'revenue' => $revenue[$month] ?? 0,
            'expense' => $expenses[$month] ?? 0
        ];
    }
    return $result;
}

/**
 * Get invoice status breakdown for pie chart
 */
private function getInvoiceStatusBreakdown($companyId)
{
    $statuses = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $companyId))
        ->select('status', DB::raw('COUNT(*) as count'))
        ->groupBy('status')
        ->get()
        ->pluck('count', 'status')
        ->toArray();
    
    // Ensure all statuses are present with 0 if missing
    $allStatuses = ['paid' => 0, 'unpaid' => 0, 'partial' => 0, 'draft' => 0];
    foreach ($statuses as $status => $count) {
        if (isset($allStatuses[$status])) {
            $allStatuses[$status] = $count;
        }
    }
    
    return $allStatuses;
}

/**
 * Get performance metrics for radar chart
 */
private function getPerformanceMetrics($companyId)
{
    // Collection Rate
    $collectionRate = $this->calculateCollectionRate($companyId);
    
    // Occupancy Rate
    $occupancyRate = $this->calculateOccupancyRate($companyId);
    
    // On-time Payment Rate
    $onTimePayments = $this->calculateOnTimePaymentRate($companyId);
    
    // Tenant Retention Rate
    $tenantRetention = $this->calculateTenantRetentionRate($companyId);
    
    // Revenue Growth
    $revenueGrowth = $this->calculateRevenueGrowth($companyId);
    
    return [
        'Collection Rate' => round($collectionRate, 0),
        'Occupancy Rate' => round($occupancyRate, 0),
        'On-time Payments' => round($onTimePayments, 0),
        'Tenant Retention' => round($tenantRetention, 0),
        'Revenue Growth' => round($revenueGrowth, 0)
    ];
}

/**
 * Calculate on-time payment rate
 */
private function calculateOnTimePaymentRate($companyId)
{
    // Get all completed payments for this company with their invoices
    $payments = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
        ->where('status', 'completed')
        ->with('invoice')
        ->get();
    
    if ($payments->isEmpty()) {
        return 0;
    }
    
    $onTimeCount = 0;
    $totalCount = $payments->count();
    
    foreach ($payments as $payment) {
        $invoice = $payment->invoice;
        
        // If no invoice or no billing month, count as on-time (can't determine)
        if (!$invoice || !$invoice->billing_month) {
            $onTimeCount++;
            continue;
        }
        
        // Billing month end (e.g., 2025-01-31)
        $billingEnd = Carbon::parse($invoice->billing_month)->endOfMonth();
        
        // Grace period: 30 days after billing month end
        $gracePeriodEnd = $billingEnd->copy()->addDays(30);
        
        // Payment is on-time if created before or on grace period end
        if ($payment->created_at <= $gracePeriodEnd) {
            $onTimeCount++;
        }
    }
    
    return $totalCount > 0 ? round(($onTimeCount / $totalCount) * 100, 1) : 0;
}

/**
 * Calculate tenant retention rate
 */
private function calculateTenantRetentionRate($companyId)
{
    $tenants = Tenant::whereHas('user', fn($q) => $q->where('company_id', $companyId))
        ->with(['tenancies' => function($q) {
            $q->where('status', 'active');
        }])
        ->get();
    
    if ($tenants->isEmpty()) return 0;
    
    $total = $tenants->count();
    $retained = 0;
    $twelveMonthsAgo = Carbon::now()->subMonths(12);
    
    foreach ($tenants as $tenant) {
        $hasLongTermTenancy = $tenant->tenancies->contains(function($tenancy) use ($twelveMonthsAgo) {
            return $tenancy->move_in_date && Carbon::parse($tenancy->move_in_date) <= $twelveMonthsAgo;
        });
        
        if ($hasLongTermTenancy) {
            $retained++;
        }
    }
    
    return $total > 0 ? round(($retained / $total) * 100, 1) : 0;
}

/**
 * Calculate revenue growth
 */
private function calculateRevenueGrowth($companyId)
{
    $currentMonth = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
        ->whereMonth('created_at', Carbon::now()->month)
        ->whereYear('created_at', Carbon::now()->year)
        ->sum('amount');
    
    $previousMonth = Payment::whereHas('invoice.tenancy.unit', fn($q) => $q->where('company_id', $companyId))
        ->whereMonth('created_at', Carbon::now()->subMonth()->month)
        ->whereYear('created_at', Carbon::now()->subMonth()->year)
        ->sum('amount');
    
    if ($previousMonth == 0) return $currentMonth > 0 ? 100 : 0;
    return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
}

/**
 * Get aging report data for chart
 */
private function getAgingReport($companyId)
{
    $now = Carbon::now();
    $ranges = [
        '0-30 Days' => [$now->copy()->subDays(30), $now],
        '31-60 Days' => [$now->copy()->subDays(60), $now->copy()->subDays(31)],
        '61-90 Days' => [$now->copy()->subDays(90), $now->copy()->subDays(61)],
        '90+ Days' => [null, $now->copy()->subDays(91)]
    ];
    
    $agingData = [];
    
    foreach ($ranges as $label => [$start, $end]) {
        $query = Invoice::whereHas('tenancy.unit', fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['unpaid', 'partial']);
        
        if ($start && $end) {
            $query->whereBetween('billing_month', [$start, $end]);
        } elseif ($end) {
            $query->where('billing_month', '<=', $end);
        }
        
        $agingData[$label] = (float) $query->sum('total_amount');
    }
    
    return [
        'labels' => array_keys($agingData),
        'values' => array_values($agingData)
    ];
}
}