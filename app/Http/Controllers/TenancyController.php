<?php

namespace App\Http\Controllers;

use App\Modules\Properties\Models\Tenancy;
use App\Models\Payment;
use App\Models\User;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Role;
use App\Modules\Properties\Models\LeaseAgreement;
use App\Modules\Properties\Models\HouseChecklist;
use App\Modules\Properties\Models\TenancyCharge;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\InvoiceItem;  
use App\Models\Maintenance;
use App\Models\Estate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class TenancyController extends Controller
{
    public function index()
    {
        $tenancies = Tenancy::with(['tenant.user', 'unit.estate', 'charges'])->get();
        
        // Format data for Alpine.js
        $tenanciesData = $tenancies->map(function ($tenancy) {
            $totalPaid = $tenancy->payments()->sum('amount');
            $totalInvoiced = $tenancy->invoices()->sum('total_amount');
            $balance = $totalInvoiced - $totalPaid;
            
            // Get deposit from tenancy_charges
            $depositCharge = $tenancy->charges()
                ->where('charge_type', 'deposit')
                ->where('status', '!=', 'refunded')
                ->first();
            $depositAmount = $depositCharge ? (float) $depositCharge->amount : 0;
            
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
                'deposit_amount' => $depositAmount,
                'outstanding_balance' => $balance,
                'total_paid' => $totalPaid,
                'total_invoiced' => $totalInvoiced,
                'move_in_date_formatted' => $tenancy->move_in_date 
                    ? \Carbon\Carbon::parse($tenancy->move_in_date)->format('M d, Y') 
                    : null,
                'move_out_date_formatted' => $tenancy->move_out_date 
                    ? \Carbon\Carbon::parse($tenancy->move_out_date)->format('M d, Y') 
                    : null,
                'duration' => $tenancy->move_in_date 
                    ? \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(
                        $tenancy->move_out_date ?? now(), true
                    ) 
                    : null,
            ];
        });
        
        // Get ALL tenants
        $allTenants = Tenant::with('user')->get();
        
        // Get tenant IDs that have ACTIVE tenancies
        $tenantIdsWithActiveTenancies = Tenancy::where('status', 'active')
            ->pluck('tenant_id')
            ->toArray();
        
        $availableTenants = $allTenants->filter(function ($tenant) use ($tenantIdsWithActiveTenancies) {
            return !in_array($tenant->id, $tenantIdsWithActiveTenancies);
        })->values();
        
        // FIX: Get payments and invoices through tenancies relationship
        $availableUsersFormatted = $availableTenants->map(function ($tenant) {
            // Get all tenancy IDs for this tenant
            $tenancyIds = Tenancy::where('tenant_id', $tenant->id)->pluck('id')->toArray();
            
            // Calculate total paid through invoices via payments
            $totalPaid = 0;
            $totalInvoiced = 0;
            
            if (!empty($tenancyIds)) {
                // Get payments through invoices for all tenancies
                $totalPaid = Payment::whereHas('invoice', function($query) use ($tenancyIds) {
                    $query->whereIn('tenancy_id', $tenancyIds);
                })->sum('amount');
                
                // Get total invoiced
                $totalInvoiced = Invoice::whereIn('tenancy_id', $tenancyIds)->sum('total_amount');
            }
            
            $balance = $totalInvoiced - $totalPaid;
            $activeTenancies = Tenancy::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->count();
            
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
                'outstanding_balance' => $balance,
                'total_paid' => $totalPaid,
                'total_invoiced' => $totalInvoiced,
                'active_tenancies' => $activeTenancies,
                'label' => ($tenant->user->name ?? 'Unknown') . ' (' . ($tenant->user->phone ?? 'No Phone') . ')' . 
                        ($hasEndedTenancy ? ' (Previous Tenant)' : '') .
                        ($balance > 0 ? ' - Balance: KES ' . number_format($balance, 2) : ''),
            ];
        });
        
        // Get guest users for new tenant creation
        $guestRole = Role::where('name', 'guest')->first();
        $allUsers = collect();
        
        if ($guestRole) {
            $allUsers = User::where('role_id', $guestRole->id)
                ->select('id', 'name', 'email', 'phone')
                ->get();
        }
        
        $allUsersFormatted = $allUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'label' => $user->name . ' (' . $user->phone . ')',
            ];
        });

        $allUnits = Unit::with('estate')
            ->select('id', 'unit_number', 'estate_id', 'unit_type', 'rent_amount', 
                    'water_charge', 'service_charge', 'garbage_charge', 'security_charge', 'status')
            ->get()
            ->map(function ($unit) {
                // Get maintenance count for this unit
                $maintenanceCount = Maintenance::where('unit_id', $unit->id)
                    ->whereIn('status', ['pending', 'open', 'in_progress'])
                    ->count();
                    
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
                    'maintenance_count' => $maintenanceCount,
                    'label' => $unit->unit_number . ' - ' . ($unit->estate->name ?? 'No Estate') . ', (' . $unit->unit_type . ')',
                ];
            });

        $vacantUnits = $allUnits->filter(function ($unit) {
            return $unit['status'] === 'vacant';
        })->values();
        
        $estates = Estate::orderBy('name')->get();

        return view('tenancies.index', [
            'tenanciesData' => $tenanciesData,
            'availableUsers' => $availableUsersFormatted,
            'allUsersFormatted' => $allUsersFormatted,
            'allUnits' => $allUnits,
            'units' => $vacantUnits,
            'vacantUnits' => $vacantUnits,
            'estates' => $estates,
        ]);
    }
    
public function show(Tenancy $tenancy)
{
    $tenancy->loadCount(['invoices']);
    
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
        },
        'invoices' => function ($query) {
            $query->orderBy('created_at', 'desc')->with('items', 'payments');
        },
        'leaseAgreement',
        'charges',
    ]);
    
    // Get checklist items
    $checklistItems = HouseChecklist::where('tenancy_id', $tenancy->id)->get();
    
    // If no checklist exists, create default ones
    if ($checklistItems->isEmpty()) {
        $defaultItems = HouseChecklist::getDefaultChecklistItems();
        foreach ($defaultItems as $roomData) {
            foreach ($roomData['items'] as $item) {
                HouseChecklist::create([
                    'tenancy_id' => $tenancy->id,
                    'checklist_type' => 'move_in',
                    'room' => $roomData['room'],
                    'item' => $item,
                    'condition_before' => 'good',
                    'status' => 'pending',
                ]);
            }
        }
        $checklistItems = HouseChecklist::where('tenancy_id', $tenancy->id)->get();
    }
    
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
    
    // Get maintenance records for this unit
    $maintenanceRecords = Maintenance::where('unit_id', $tenancy->unit_id)
        ->with(['tenant.user', 'assignedStaff'])
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();
    
    // Calculate financial summary
    $totalInvoiced = $tenancy->invoices()->sum('total_amount');
    $totalPaid = Payment::whereHas('invoice', function($query) use ($tenancy) {
        $query->where('tenancy_id', $tenancy->id);
    })->sum('amount');
    $balance = $totalInvoiced - $totalPaid;
    
    $totalMonthlyPayment = ($tenancy->unit->rent_amount ?? 0) + 
                        ($tenancy->unit->water_charge ?? 0) + 
                        ($tenancy->unit->service_charge ?? 0) + 
                        ($tenancy->unit->garbage_charge ?? 0) + 
                        ($tenancy->unit->security_charge ?? 0);
    
    $moveIn = $tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date) : null;
    $moveOut = $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date) : null;
    $duration = $moveIn ? $moveIn->diffForHumans($moveOut ?? now(), true) : 'N/A';
    
    // Get tenant financial history through tenancies
    $tenantTenancyIds = Tenancy::where('tenant_id', $tenancy->tenant_id)->pluck('id')->toArray();
    $tenantTotalInvoiced = Invoice::whereIn('tenancy_id', $tenantTenancyIds)->sum('total_amount');
    $tenantTotalPaid = Payment::whereHas('invoice', function($query) use ($tenantTenancyIds) {
        $query->whereIn('tenancy_id', $tenantTenancyIds);
    })->sum('amount');
    $tenantBalance = $tenantTotalInvoiced - $tenantTotalPaid;
    
    // Get all invoices for this tenancy with details
    $invoices = $tenancy->invoices()->with(['items', 'payments', 'tenancy.tenant.user'])->get()->map(function($invoice) {
        $waterItem = $invoice->items->firstWhere('item_type', 'water');
        $paidAmount = (float) $invoice->payments->sum('amount');
        $remainingAmount = (float) ($invoice->total_amount - $paidAmount);
        $tenantName = $invoice->tenancy->tenant->user->name ?? 'N/A';
        
        // Build items label for the dropdown
        $itemsLabel = $invoice->items->count()
            ? $invoice->items->map(fn($item) => ($item->item_type ?? 'Item') . ($item->description ? ' (' . $item->description . ')' : ''))->implode(', ')
            : '-';
        
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
            'billing_month' => $invoice->billing_month,
            'billing_month_formatted' => $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') : '-',
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'balance' => $remainingAmount,
            'status' => $invoice->status,
            'water_synced' => $waterItem && $waterItem->water_units_used > 0,
            'water_units' => $waterItem ? (float) $waterItem->water_units_used : 0,
            'payer_name' => $tenantName,
            'tenant_name' => $tenantName,
            'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
            'estate_name' => $invoice->tenancy->unit->estate->name ?? 'N/A',
            'label' => $tenantName . ' - Invoice #' . ($invoice->invoice_number ?? $invoice->id) . ': ' . $itemsLabel,
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
    
    // Get ALL invoices for the payment modal dropdown (for all tenancies)
    $allInvoices = Invoice::with(['tenancy.tenant.user', 'tenancy.unit', 'items'])
        ->where('status', '!=', 'paid')
        ->get()
        ->map(function ($inv) {
            $payerName = optional(optional($inv->tenancy)->tenant->user)->name ?? 'N/A';
            $itemsLabel = $inv->items->count()
                ? $inv->items->map(fn($item) => ($item->item_type ?? 'Item') . ($item->description ? ' (' . $item->description . ')' : ''))->implode(', ')
                : '-';
            return [
                'id' => $inv->id,
                'label' => $payerName . ' - Invoice #' . ($inv->invoice_number ?? $inv->id) . ': ' . $itemsLabel,
                'payer_name' => $payerName,
                'total_amount' => (float) $inv->total_amount,
                'tenant_name' => $payerName,
                'unit_number' => $inv->tenancy->unit->unit_number ?? 'N/A',
                'estate_name' => $inv->tenancy->unit->estate->name ?? 'N/A',
                'billing_month' => $inv->billing_month ? \Carbon\Carbon::parse($inv->billing_month)->format('F Y') : '-',
                'outstanding_balance' => (float) ($inv->total_amount - $inv->payments->sum('amount')),
            ];
        });
    
    return view('tenancies.show', compact(
        'tenancy', 
        'unitTenancyHistory', 
        'duration',
        'totalInvoiced',
        'totalPaid',
        'balance',
        'totalMonthlyPayment',
        'checklistItems',
        'maintenanceRecords',
        'tenantTotalInvoiced',
        'tenantTotalPaid',
        'tenantBalance',
        'invoices',
        'allInvoices'
    ));
}

    public function edit(Tenancy $tenancy)
    {
        $tenancy->load(['tenant.user', 'unit.estate', 'charges', 'leaseAgreement']);
        
        // Get deposit from charges
        $depositCharge = $tenancy->charges()
            ->where('charge_type', 'deposit')
            ->where('status', '!=', 'refunded')
            ->first();
        $depositAmount = $depositCharge ? (float) $depositCharge->amount : 0;
        
        // Get paid deposit
        $depositPaid = $tenancy->charges()
            ->where('charge_type', 'deposit')
            ->where('status', 'paid')
            ->sum('amount');
        
        // Get refunded deposit
        $depositRefunded = $tenancy->charges()
            ->where('charge_type', 'deposit')
            ->where('status', 'refunded')
            ->sum('amount');
        
        if (request()->wantsJson()) {
            // Get checklist items
            $checklistItems = HouseChecklist::where('tenancy_id', $tenancy->id)->get();
            
            return response()->json([
                'tenancy' => [
                    'id' => $tenancy->id,
                    'tenant_id' => $tenancy->tenant_id,
                    'tenant_name' => $tenancy->tenant->user->name ?? null,
                    'tenant_phone' => $tenancy->tenant->user->phone ?? null,
                    'tenant_email' => $tenancy->tenant->user->email ?? null,
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
                    'notes' => $tenancy->notes,
                    'deposit_amount' => $depositAmount,
                    'deposit_paid' => $depositPaid,
                    'deposit_refunded' => $depositRefunded,
                    'lease_agreement' => $tenancy->leaseAgreement,
                    'charges' => $tenancy->charges,
                ],
                'checklist' => $checklistItems,
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
            'deposit_amount' => 'nullable|numeric|min:0',
            'generate_invoice' => 'nullable|in:yes,draft,no',
            'lease_terms' => 'nullable|string',
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
                if (!isset($validated['new_tenant_phone']) || !$validated['new_tenant_phone']) {
                    throw new \Exception('Phone number is required for new tenant');
                }
                
                $email = $validated['new_tenant_email'] ?? $this->generateTenantEmail($validated['new_tenant_name']);
                
                if (User::where('email', $email)->exists()) {
                    $baseEmail = strtolower(str_replace(' ', '.', $validated['new_tenant_name']));
                    $counter = 1;
                    while (User::where('email', $email)->exists()) {
                        $email = $baseEmail . $counter . '@tenant.com';
                        $counter++;
                    }
                }
                
                $guestRole = Role::where('name', 'guest')->first();
                
                $user = User::create([
                    'name' => $validated['new_tenant_name'],
                    'email' => $email,
                    'phone' => $validated['new_tenant_phone'],
                    'password' => Hash::make('00000000'),
                    'role_id' => $guestRole ? $guestRole->id : null,
                ]);
                
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'notes' => $validated['notes'] ?? null,
                ]);
                
                $tenantId = $tenant->id;
            } else {
                throw new \Exception('Please select a tenant or provide new tenant details');
            }

            $unit = Unit::find($validated['unit_id']);
            if (!$unit) {
                throw new \Exception('Unit not found');
            }
            
            if ($unit->status !== 'vacant') {
                throw new \Exception('Selected unit is not vacant');
            }

            // Calculate deposit amount (default to 1 month rent)
            $depositAmount = $validated['deposit_amount'] ?? ($unit->rent_amount ?? 0);
            $firstMonthRent = $unit->rent_amount ?? 0;

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

            // Create lease agreement
            $leaseAgreement = LeaseAgreement::create([
                'tenancy_id' => $tenancy->id,
                'agreement_number' => LeaseAgreement::generateAgreementNumber(),
                'start_date' => $validated['move_in_date'],
                'rent_amount' => $unit->rent_amount ?? 0,
                'deposit_amount' => $depositAmount,
                'terms' => $validated['lease_terms'] ?? null,
                'status' => 'draft',
            ]);

            // Create tenancy charges (deposit and first month rent)
            $charges = [];
            
            // Security Deposit
            if ($depositAmount > 0) {
                $charges[] = TenancyCharge::create([
                    'tenancy_id' => $tenancy->id,
                    'charge_type' => 'deposit',
                    'description' => 'Security Deposit (1 Month Rent)',
                    'amount' => $depositAmount,
                    'is_refundable' => true,
                    'status' => 'pending',
                ]);
            }
            
            // First Month Rent
            if ($firstMonthRent > 0) {
                $charges[] = TenancyCharge::create([
                    'tenancy_id' => $tenancy->id,
                    'charge_type' => 'rent',
                    'description' => 'First Month Rent',
                    'amount' => $firstMonthRent,
                    'is_refundable' => false,
                    'status' => 'pending',
                ]);
            }

            // Generate invoice if requested
            $invoice = null;
            if (isset($validated['generate_invoice']) && $validated['generate_invoice'] !== 'no') {
                $totalAmount = $depositAmount + $firstMonthRent;
                
                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => 'move_in',
                    'billing_month' => $validated['move_in_date'],
                    'total_amount' => $totalAmount,
                    'status' => $validated['generate_invoice'] === 'draft' ? 'draft' : 'unpaid',
                    'notes' => 'Move-in charges: Deposit + First Month Rent',
                ]);
                
                // Add items to invoice - use 'other' for deposit
                if ($depositAmount > 0) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_type' => 'deposit',
                        'description' => 'Security Deposit (Refundable)',
                        'amount' => $depositAmount,
                    ]);
                }
                
                if ($firstMonthRent > 0) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_type' => 'rent',
                        'description' => 'First Month Rent',
                        'amount' => $firstMonthRent,
                    ]);
                }
                
                // Link charges to invoice
                foreach ($charges as $charge) {
                    $charge->update(['invoice_id' => $invoice->id]);
                }
            }

            DB::commit();

            $tenancy->load(['tenant.user', 'unit', 'leaseAgreement', 'charges']);

            return response()->json([
                'success' => true,
                'message' => 'Tenancy created successfully!',
                'tenancy' => [
                    'id' => $tenancy->id,
                    'tenant_name' => $tenancy->tenant->user->name ?? null,
                    'unit_number' => $tenancy->unit->unit_number ?? null,
                    'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                    'deposit_amount' => $depositAmount,
                    'total_monthly_payment' => ($tenancy->unit->rent_amount ?? 0) + 
                                            ($tenancy->unit->water_charge ?? 0) + 
                                            ($tenancy->unit->service_charge ?? 0) + 
                                            ($tenancy->unit->garbage_charge ?? 0) + 
                                            ($tenancy->unit->security_charge ?? 0),
                    'move_in_date' => $tenancy->move_in_date,
                    'status' => $tenancy->status,
                    'lease_agreement' => $leaseAgreement,
                    'invoice' => $invoice ? $invoice->load('items') : null,
                    'charges' => $charges,
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
                $user = User::create([
                    'name' => $data['tenant_name'],
                    'email' => $data['tenant_email'],
                    'phone' => $data['tenant_phone'] ?? null,
                    'password' => Hash::make('00000000'),
                ]);

                $guestRole = Role::where('name', 'guest')->first();
                if ($guestRole) {
                    $user->roles()->attach($guestRole->id);
                }

                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'notes' => $data['notes'] ?? null,
                ]);

                $tenancy = Tenancy::create([
                    'tenant_id' => $tenant->id,
                    'unit_id' => $data['unit_id'],
                    'move_in_date' => $data['move_in_date'],
                    'notes' => $data['notes'] ?? null,
                    'status' => 'active',
                ]);

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

        $oldUnitId = $tenancy->unit_id;
        
        $tenancy->update([
            'unit_id' => $validated['unit_id'],
            'move_in_date' => $validated['move_in_date'],
            'move_out_date' => $validated['move_out_date'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $tenancy->notes,
        ]);

        // Handle unit status changes
        if ($tenancy->status === 'ended') {
            Unit::where('id', $tenancy->unit_id)->update(['status' => 'vacant']);
            if ($oldUnitId && $oldUnitId != $tenancy->unit_id) {
                Unit::where('id', $oldUnitId)->update(['status' => 'vacant']);
            }
        } else if ($tenancy->status === 'active') {
            Unit::where('id', $tenancy->unit_id)->update(['status' => 'occupied']);
            if ($oldUnitId && $oldUnitId != $tenancy->unit_id) {
                Unit::where('id', $oldUnitId)->update(['status' => 'vacant']);
            }
        }
        
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
                'notes' => $tenancy->notes,
            ]
        ]);
    }

    public function destroy(Tenancy $tenancy)
    {
        DB::transaction(function () use ($tenancy) {
            // 1. Delete house checklists
            HouseChecklist::where('tenancy_id', $tenancy->id)->delete();
            
            // 2. Delete lease agreement
            LeaseAgreement::where('tenancy_id', $tenancy->id)->delete();
            
            // 3. Delete tenancy charges
            TenancyCharge::where('tenancy_id', $tenancy->id)->delete();
            
            // 4. Handle invoices - either delete or dissociate
            $invoices = Invoice::where('tenancy_id', $tenancy->id)->get();
            foreach ($invoices as $invoice) {
                // Delete invoice items first
                $invoice->items()->delete();
                // Then delete the invoice
                $invoice->delete();
            }
            
            // 5. Update unit status
            $tenancy->unit->update(['status' => 'vacant']);
            
            // 6. Finally delete the tenancy
            $tenancy->delete();
        });

        return response()->json([
            'success' => true, 
            'message' => 'Tenancy and all related records deleted successfully'
        ]);
    }

    // Update house checklist
    public function updateChecklist(Request $request, Tenancy $tenancy)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'exists:house_checklists,id',
            'items.*.condition' => 'required|in:excellent,good,fair,poor,damaged',
            'items.*.notes' => 'nullable|string',
            'items.*.status' => 'required|in:pending,completed,needs_repair',
        ]);

        foreach ($validated['items'] as $itemData) {
            $checklist = HouseChecklist::find($itemData['id']);
            if ($checklist && $checklist->tenancy_id == $tenancy->id) {
                $checklist->update([
                    'condition_before' => $itemData['condition'],
                    'notes' => $itemData['notes'] ?? $checklist->notes,
                    'status' => $itemData['status'],
                    'completed_by' => $itemData['status'] === 'completed' ? auth()->id() : null,
                    'completed_at' => $itemData['status'] === 'completed' ? now() : null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Checklist updated successfully',
        ]);
    }

    // Generate lease agreement PDF
    public function generateLeaseAgreement(Tenancy $tenancy)
    {
        $tenancy->load(['tenant.user', 'unit.estate', 'leaseAgreement']);
        
        // If no lease agreement exists, create one
        if (!$tenancy->leaseAgreement) {
            $agreement = LeaseAgreement::create([
                'tenancy_id' => $tenancy->id,
                'agreement_number' => LeaseAgreement::generateAgreementNumber(),
                'start_date' => $tenancy->move_in_date,
                'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                'deposit_amount' => $tenancy->deposit_amount ?? 0,
                'status' => 'draft',
            ]);
        } else {
            $agreement = $tenancy->leaseAgreement;
        }

        // Return agreement data for PDF generation
        return response()->json([
            'success' => true,
            'agreement' => $agreement,
            'tenant' => $tenancy->tenant,
            'unit' => $tenancy->unit,
            'tenancy' => $tenancy,
        ]);
    }

    // Get tenant financial summary for modal
    public function getTenantSummary($tenantId)
    {
        $tenant = Tenant::with('user')->findOrFail($tenantId);
        
        // Get all tenancy IDs for this tenant
        $tenancyIds = Tenancy::where('tenant_id', $tenantId)->pluck('id')->toArray();
        
        // Calculate totals through tenancies
        $totalInvoiced = Invoice::whereIn('tenancy_id', $tenancyIds)->sum('total_amount');
        $totalPaid = Payment::whereHas('invoice', function($query) use ($tenancyIds) {
            $query->whereIn('tenancy_id', $tenancyIds);
        })->sum('amount');
        $balance = $totalInvoiced - $totalPaid;
        
        $activeTenancies = Tenancy::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
        
        // Get recent invoices
        $recentInvoices = Invoice::whereIn('tenancy_id', $tenancyIds)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'date' => $invoice->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'success' => true,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->user->name ?? 'Unknown',
                'phone' => $tenant->user->phone ?? '',
                'email' => $tenant->user->email ?? '',
                'outstanding_balance' => $balance,
                'total_paid' => $totalPaid,
                'total_invoiced' => $totalInvoiced,
                'active_tenancies' => $activeTenancies,
                'recent_invoices' => $recentInvoices,
            ]
        ]);
    }

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