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
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get all invoices for the dashboard
        $invoices = Invoice::with('tenancy.tenant.user', 'tenancy.unit', 'items', 'payments')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get active tenancies for bulk invoice creation
        $activeTenancies = Tenancy::where('status', 'active')
            ->with('tenant.user', 'unit')
            ->get();
        
        // Get users for tenant selection
        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });
        
        // Get payment invoices for dropdown
        $paymentInvoices = Invoice::with('items', 'tenancy.tenant.user')->get()->map(function ($invoice) {
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
        
        // Map invoices for the table - Standard format
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
        
        // Get all units for the maintenance modal
        $units = Unit::with('estate')->get();
        
        // ========== FETCH MAINTENANCE REQUESTS FOR ALL ROLES ==========
        $maintenanceRequests = collect();
        
        if ($user->hasRole('maintenance')) {
            // Maintenance staff sees all requests
            $maintenanceRequests = Maintenance::with(['unit', 'tenant.user'])
                ->latest()
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'request_number' => $request->request_number,
                        'unit_number' => $request->unit->unit_number ?? 'N/A',
                        'tenant_name' => $request->tenant->user->name ?? 'N/A',
                        'title' => $request->title,
                        'description' => $request->description,
                        'priority' => $request->priority,
                        'status' => $request->status,
                        'created_at' => $request->created_at,
                        'created_at_formatted' => $request->created_at ? $request->created_at->format('M d, Y H:i') : '-',
                    ];
                });
        } elseif ($user->hasRole('tenant') && $user->tenant) {
            // Tenants only see their own requests
            $maintenanceRequests = Maintenance::where('tenant_id', $user->tenant->id)
                ->with(['unit', 'tenant.user'])
                ->latest()
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'request_number' => $request->request_number,
                        'unit_number' => $request->unit->unit_number ?? 'N/A',
                        'tenant_name' => $request->tenant->user->name ?? 'N/A',
                        'title' => $request->title,
                        'description' => $request->description,
                        'priority' => $request->priority,
                        'status' => $request->status,
                        'created_at' => $request->created_at,
                        'created_at_formatted' => $request->created_at ? $request->created_at->format('M d, Y H:i') : '-',
                    ];
                });
        } elseif ($user->hasAnyRole(['super_admin', 'admin', 'property_manager'])) {
            // Admins and property managers see all requests
            $maintenanceRequests = Maintenance::with(['unit', 'tenant.user'])
                ->latest()
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'request_number' => $request->request_number,
                        'unit_number' => $request->unit->unit_number ?? 'N/A',
                        'tenant_name' => $request->tenant->user->name ?? 'N/A',
                        'title' => $request->title,
                        'description' => $request->description,
                        'priority' => $request->priority,
                        'status' => $request->status,
                        'created_at' => $request->created_at,
                        'created_at_formatted' => $request->created_at ? $request->created_at->format('M d, Y H:i') : '-',
                    ];
                });
        }
        
        // Get current unit for tenant (for auto-fill in modal)
        $currentUnit = null;
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $currentUnit = $user->tenant->activeTenancy->unit->load('estate');
        }
        
        // Get role-specific data
        $roleData = $this->getRoleSpecificData($user);
        
        // Add maintenance data to roleData for consistency
        $roleData['maintenanceRequests'] = $maintenanceRequests;
        $roleData['units'] = $units;
        $roleData['currentUnit'] = $currentUnit;
        
        $stats = $this->getCommonStats($user);
        
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
        $recentPayments = Payment::with('invoice.tenancy.tenant.user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // Unit statistics
        $totalUnits = Unit::count();
        $occupiedUnits = Unit::where('status', 'occupied')->count();
        $availableUnits = Unit::where('status', 'available')->count();
        
        // Payment statistics for charts
        $monthlyRevenue = $this->getMonthlyRevenue();
        $paymentMethods = $this->getPaymentMethodStats();
        
        return view('dashboard', compact(
            'mappedInvoices',
            'mappedActiveTenancies',
            'activeTenancies',
            'users',
            'paymentInvoices',
            'totalDraft',
            'totalUnpaid',
            'totalPartial',
            'totalPaidAll',
            'tenanciesNeedingMoveInInvoices',
            'recentInvoices',
            'recentPayments',
            'totalUnits',
            'occupiedUnits',
            'availableUnits',
            'monthlyRevenue',
            'paymentMethods',
            'roleData',
            'stats',
            'outstandingBalance',
            'totalPaid',
            'units',
            'maintenanceRequests',
            'currentUnit'
        ));
    }
    
    private function getRoleSpecificData($user)
    {
        // Using string comparison instead of constants
        $roleName = $user->role ? $user->role->name : 'guest';
        
        switch ($roleName) {
            case 'super_admin':
            case 'admin':
                return $this->getAdminData();
            case 'property_manager':
                return $this->getPropertyManagerData();
            case 'accountant':
                return $this->getAccountantData();
            case 'tenant':
                return $this->getTenantData();
            case 'meter_reader':
                return $this->getMeterReaderData();
            case 'cleaning_staff':
                return $this->getCleaningStaffData();
            case 'maintenance':
                return $this->getMaintenanceData();
            case 'security':
                return $this->getSecurityData();
            default:
                return ['type' => 'guest'];
        }
    }
    
private function getCommonStats($user = null)
{
    $user = $user ?? auth()->user();
    
    $stats = [
        'total_units' => Unit::count(),
        'occupied_units' => Unit::where('status', 'occupied')->count(),
        'vacant_units' => Unit::where('status', 'vacant')->count(),
        'total_tenants' => Tenant::count(),
        'active_tenancies' => Tenancy::where('status', 'active')->count(),
        'total_invoices' => Invoice::count(),
        'paid_invoices' => Invoice::where('status', 'paid')->count(),
        'unpaid_invoices' => Invoice::where('status', 'unpaid')->count(),
        'partial_invoices' => Invoice::where('status', 'partial')->count(),
        'total_revenue' => Payment::sum('amount'),
        'total_expenses' => Expense::sum('amount'),
        'net_income' => Payment::sum('amount') - Expense::sum('amount'),
        'occupancy_rate' => Unit::count() > 0 ? round((Unit::where('status', 'occupied')->count() / Unit::count()) * 100, 2) : 0,
        'total_consumption' => max(0, Unit::sum('current_water_reading') - Unit::sum('previous_water_reading')),
        'monthly_consumption' => Unit::whereMonth('last_reading_date', Carbon::now()->month)->sum('current_water_reading'),
        'outstanding_invoices' => Invoice::whereIn('status', ['unpaid', 'partial'])->sum('total_amount'),
        'collection_rate' => $this->calculateCollectionRate(),
        'units_needing_reading' => Unit::where('status', 'occupied')
            ->where(function($q) {
                $q->whereNull('last_reading_date')
                  ->orWhere('last_reading_date', '<=', Carbon::now()->subDays(30));
            })->count(),
        'total_occupied_units' => Unit::where('status', 'occupied')->count(),
    ];
    
    // Add tenant water readings - FIX: Always set defaults for tenant
    if ($user && $user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
        $unit = $user->tenant->activeTenancy->unit;
        $stats['tenant_previous_reading'] = (float) ($unit->previous_water_reading ?? 0);
        $stats['tenant_current_reading'] = (float) ($unit->current_water_reading ?? 0);
        $stats['tenant_consumption'] = max(0, $stats['tenant_current_reading'] - $stats['tenant_previous_reading']);
        $stats['tenant_unit_number'] = $unit->unit_number ?? 'N/A';
        $stats['tenant_water_billing_type'] = $unit->water_billing_type ?? 'consumption';
    } else {
        // Set default values for non-tenant users (prevents undefined array key errors)
        $stats['tenant_previous_reading'] = 0;
        $stats['tenant_current_reading'] = 0;
        $stats['tenant_consumption'] = 0;
        $stats['tenant_unit_number'] = 'N/A';
        $stats['tenant_water_billing_type'] = 'consumption';
    }
    
    return $stats;
}
    
    /**
     * Get Admin/Super Admin specific data
     */
    private function getAdminData()
    {
        // Recent invoices
        $recentInvoices = Invoice::with(['tenancy.tenant.user', 'tenancy.unit', 'items', 'payments'])
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
            })->toArray();
        
        // Recent payments
        $recentPayments = Payment::with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
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
            })->toArray();
        
        // Recent water readings
        $recentReadings = Unit::whereNotNull('current_water_reading')
            ->where('current_water_reading', '>', 0)
            ->with('estate')
            ->take(10)
            ->get()
            ->map(function ($unit) {
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'current_reading' => (float) $unit->current_water_reading,
                    'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
                    'consumption' => (float) ($unit->current_water_reading - ($unit->previous_water_reading ?? 0)),
                    'reading_date' => $unit->last_reading_date,
                ];
            })->toArray();
        
        return [
            'type' => 'admin',
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
            'recentReadings' => $recentReadings,
        ];
    }

    /**
     * Get Accountant specific data
     */
    private function getAccountantData()
    {
        // Overdue invoices
        $overdueInvoices = Invoice::where('status', 'unpaid')
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
            })->toArray();
        
        // Recent transactions
        $recentTransactions = Payment::with(['invoice.tenancy.tenant.user', 'invoice.tenancy.unit'])
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
            })->toArray();
        
        return [
            'type' => 'accountant',
            'overdueInvoices' => $overdueInvoices,
            'recentTransactions' => $recentTransactions,
        ];
    }

    /**
     * Get Tenant specific data
     */
    private function getTenantData()
    {
        $user = auth()->user();
        $tenant = $user->tenant;
        
        if (!$tenant || !$tenant->activeTenancy) {
            return [
                'type' => 'tenant',
                'has_tenancy' => false,
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name ?? 'N/A',
                ] : null,
                'invoices' => [],
                'waterInfo' => ['previous_reading' => 0, 'current_reading' => 0, 'consumption' => 0],
                'outstandingBalance' => 0,
                'totalPaid' => 0,
            ];
        }
        
        $activeTenancy = $tenant->activeTenancy;
        
        // Get invoices
        $invoices = Invoice::where('tenancy_id', $activeTenancy->id)
            ->with('items', 'payments')
            ->orderBy('billing_month', 'desc')
            ->get()
            ->map(function ($invoice) use ($activeTenancy) {
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
                    'paid_amount' => (float) $invoice->payments->sum('amount'),
                    'balance' => (float) ($invoice->total_amount - $invoice->payments->sum('amount')),
                    'items' => $invoice->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                        ];
                    })->toArray(),
                ];
            })->toArray();
        
        // Get payments
        $payments = Payment::whereHas('invoice', function ($query) use ($activeTenancy) {
                $query->where('tenancy_id', $activeTenancy->id);
            })
            ->orderBy('payment_datetime', 'desc')
            ->get();
        
        $totalPaid = (float) $payments->sum('amount');
        $totalBilled = (float) collect($invoices)->sum('total_amount');
        $outstandingBalance = $totalBilled - $totalPaid;
        
        // Water reading info
        $unit = $activeTenancy->unit;
        $waterInfo = [
            'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
            'current_reading' => (float) ($unit->current_water_reading ?? 0),
            'consumption' => (float) (($unit->current_water_reading ?? 0) - ($unit->previous_water_reading ?? 0)),
            'rate' => (float) ($unit->water_charge ?? 0),
        ];
        
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
            'payments' => $payments->toArray(),
            'totalPaid' => $totalPaid,
            'totalBilled' => $totalBilled,
            'outstandingBalance' => $outstandingBalance,
            'waterInfo' => $waterInfo,
        ];
    }
    
    /**
     * Get Property Manager specific data
     */
    private function getPropertyManagerData()
    {
        // Long term vacant units
        $longTermVacant = Unit::where('status', 'vacant')
            ->where('updated_at', '<=', Carbon::now()->subDays(30))
            ->with('estate')
            ->take(10)
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'rent_amount' => $unit->rent_amount,
                    'vacant_days' => Carbon::parse($unit->updated_at)->diffInDays(now()),
                ];
            });
        
        // Recent water readings
        $recentReadings = Unit::whereNotNull('current_water_reading')
            ->where('current_water_reading', '>', 0)
            ->with('estate')
            ->take(10)
            ->get()
            ->map(function ($unit) {
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'current_reading' => (float) $unit->current_water_reading,
                    'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
                    'consumption' => (float) ($unit->current_water_reading - ($unit->previous_water_reading ?? 0)),
                    'reading_date' => $unit->last_reading_date,
                ];
            });
        
        return [
            'type' => 'property_manager',
            'longTermVacant' => $longTermVacant,
            'recentReadings' => $recentReadings,
        ];
    }
    
    private function getMeterReaderData()
    {
        $unitsNeedingReading = Unit::where('status', 'occupied')
            ->where(function($query) {
                $query->whereNull('last_reading_date')
                    ->orWhere('last_reading_date', '<=', Carbon::now()->subDays(30));
            })
            ->with('estate')
            ->take(20)
            ->get()
            ->map(function ($unit) {
                return [
                    'unit_id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'current_reading' => (float) ($unit->current_water_reading ?? 0),
                    'previous_reading' => (float) ($unit->previous_water_reading ?? 0),
                    'consumption' => (float) $unit->calculateWaterConsumption(),
                    'last_reading_date' => $unit->last_reading_date,
                    'reading_date' => $unit->last_reading_date,
                    'rate' => (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50),
                ];
            });
        
        return [
            'type' => 'meter_reader',
            'unitsNeedingReading' => $unitsNeedingReading,
        ];
    }
    
    /**
     * Get Cleaning Staff specific data
     */
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
    
    private function getMaintenanceData()
    {
        $user = auth()->user();
        
        // Get all units for the modal dropdown (for staff)
        $units = Unit::with('estate')->get();
        
        // Get current unit for tenant (auto-fill)
        $currentUnit = null;
        if ($user->hasRole('tenant') && $user->tenant && $user->tenant->activeTenancy) {
            $currentUnit = $user->tenant->activeTenancy->unit->load('estate');
        }
        
        // Get maintenance requests based on user role
        if ($user->hasRole('maintenance')) {
            $requests = Maintenance::with(['unit', 'tenant.user'])
                ->latest()
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'request_number' => $request->request_number,
                        'unit_number' => $request->unit->unit_number ?? 'N/A',
                        'tenant_name' => $request->tenant->user->name ?? 'N/A',
                        'title' => $request->title,
                        'description' => $request->description,
                        'priority' => $request->priority,
                        'status' => $request->status,
                        'created_at' => $request->created_at,
                    ];
                })
                ->toArray();
        } else {
            // For tenants, only show their own requests
            $tenant = $user->tenant;
            $requests = [];
            
            if ($tenant && $tenant->activeTenancy) {
                $requests = Maintenance::where('tenant_id', $tenant->id)
                    ->with('unit')
                    ->latest()
                    ->get()
                    ->map(function ($request) {
                        return [
                            'id' => $request->id,
                            'request_number' => $request->request_number,
                            'unit_number' => $request->unit->unit_number ?? 'N/A',
                            'tenant_name' => $request->tenant->user->name ?? 'N/A',
                            'title' => $request->title,
                            'description' => $request->description,
                            'priority' => $request->priority,
                            'status' => $request->status,
                            'created_at' => $request->created_at,
                        ];
                    })
                    ->toArray();
            }
        }
        
        return [
            'type' => 'maintenance',
            'maintenanceRequests' => $requests,
            'units' => $units,
            'currentUnit' => $currentUnit,
        ];
    }
        
/**
 * Get Security specific data with full CRUD support
 */
private function getSecurityData()
{
    $user = auth()->user();
    
    // Get all units for the dropdown
    $units = Unit::with('estate')->get();
    
    // Get security logs based on user role
    if ($user->hasAnyRole(['super_admin', 'admin', 'security'])) {
        $accessLogs = SecurityLog::with('unit')
            ->latest('access_time')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'datetime' => $log->access_time,
                    'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y H:i') : '-',
                    'unit_id' => $log->unit_id,
                    'unit_number' => $log->unit->unit_number ?? 'N/A',
                    'person_name' => $log->visitor_name_snapshot ?? $log->person_name,
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
    } else {
        // Tenants only see logs for their unit
        $tenant = $user->tenant;
        $accessLogs = collect();
        $pendingLogs = collect();
        $todayLogs = collect();
        
        if ($tenant && $tenant->activeTenancy) {
            $unitId = $tenant->activeTenancy->unit_id;
            $accessLogs = SecurityLog::where('unit_id', $unitId)
                ->with('unit')
                ->latest('access_time')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'datetime' => $log->access_time,
                        'datetime_formatted' => $log->access_time ? $log->access_time->format('M d, Y H:i') : '-',
                        'unit_id' => $log->unit_id,
                        'unit_number' => $log->unit->unit_number ?? 'N/A',
                        'person_name' => $log->visitor_name_snapshot ?? $log->person_name,
                        'access_type' => $log->access_type,
                        'access_type_label' => $log->access_type_label,
                        'status' => $log->status,
                        'verified_by' => $log->approved_by ?? $log->verified_by ?? 'System',
                        'notes' => $log->notes,
                        'purpose' => $log->purpose,
                        'created_at' => $log->created_at,
                    ];
                });
                
            $todayLogs = $accessLogs->filter(function($log) {
                return Carbon::parse($log['datetime'])->isToday();
            })->values();
        }
    }
    
    return [
        'type' => 'security',
        'accessLogs' => $accessLogs,
        'pendingLogs' => $pendingLogs,
        'todayLogs' => $todayLogs,
        'units' => $units,
    ];
}
    
    /**
     * Get monthly revenue for charts
     */
    private function getMonthlyRevenue()
    {
        $revenue = Payment::select(
                DB::raw('DATE_FORMAT(payment_datetime, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->whereYear('payment_datetime', '>=', Carbon::now()->subYear()->year)
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->pluck('total', 'month')
            ->toArray();
        
        // Fill in missing months with zero
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $result[$month] = $revenue[$month] ?? 0;
        }
        
        return $result;
    }
    
    /**
     * Get payment method statistics for charts
     */
    private function getPaymentMethodStats()
    {
        return Payment::select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->pluck('total', 'payment_method')
            ->toArray();
    }
    
    /**
     * Helper: Calculate collection rate
     */
    private function calculateCollectionRate()
    {
        $totalBilled = Invoice::sum('total_amount');
        $totalCollected = Payment::sum('amount');
        
        if ($totalBilled == 0) {
            return 0;
        }
        
        return round(($totalCollected / $totalBilled) * 100, 2);
    }

    // Add this to your DashboardController.php


}