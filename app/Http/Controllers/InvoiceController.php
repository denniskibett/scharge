<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('tenancy.tenant', 'tenancy.unit', 'items', 'payments')
            ->orderBy('created_at', 'desc')
            ->get();
            
        $activeTenancies = Tenancy::where('status', 'active')
            ->with('tenant', 'unit')
            ->get();

           $users = Tenant::with('user')->get()->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => optional($tenant->user)->name ?? 'N/A',
                ];
            });
            
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

        
        $mappedInvoices = $invoices->map(function($invoice) {
            $paidAmount = (float) $invoice->payments->sum('amount');
            $remainingAmount = (float) ($invoice->total_amount - $paidAmount);
            
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
                'tenant_name' => $invoice->tenancy->tenant->user->name ?? '-',
                'tenant_id' => $invoice->tenancy->tenant_id ?? null,
                'unit_number' => $invoice->tenancy->unit->unit_number ?? '-',
                'unit_id' => $invoice->tenancy->unit_id ?? null,
                'invoice_type' => $invoice->invoice_type,
                'billing_month' => $invoice->billing_month,
                'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $invoice->status,
                'tenancy_id' => $invoice->tenancy_id,
                'created_at' => $invoice->created_at ? $invoice->created_at->getTimestamp() * 1000 : null,
                'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
                'items' => $invoice->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'item_type' => $item->item_type,
                        'amount' => (float) $item->amount,
                        'paid_amount' => (float) ($item->paid_amount ?? 0),
                        'remaining_amount' => (float) ($item->amount - ($item->paid_amount ?? 0)),
                    ];
                })->toArray(),
            ];
        });
        
        $mappedActiveTenancies = $activeTenancies->map(function($tenancy) {
            $nextBillingMonth = $this->getNextBillingMonth($tenancy);
            $canGenerateInvoice = $this->canGenerateInvoice($tenancy);
            $existingInvoiceForMonth = $this->hasInvoiceForMonth($tenancy, $nextBillingMonth);
            
            return [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->user->name ?? 'Unknown',
                'unit_number' => $tenancy->unit->unit_number ?? 'No Unit',
                'rent_amount' => $tenancy->unit->rent_amount ?? 0,
                'move_in_date' => $tenancy->move_in_date,
                'move_out_date' => $tenancy->move_out_date,
                'next_billing_month' => $nextBillingMonth,
                'can_generate_invoice' => $canGenerateInvoice,
                'has_existing_invoice' => $existingInvoiceForMonth,
                'existing_invoice_id' => $existingInvoiceForMonth ? $this->getExistingInvoiceId($tenancy, $nextBillingMonth) : null,
            ];
        });
        
        $totalInvoices = $invoices->count();
        $totalUnpaid = $invoices->where('status', 'unpaid')->sum('total_amount');
        $totalPaid = $invoices->where('status', 'paid')->sum('total_amount');
        $totalPartial = $invoices->where('status', 'partial')->sum('total_amount');
        $totalDraft = $invoices->where('status', 'draft')->sum('total_amount');
        $averageDays = $this->calculateAveragePaymentTime($invoices);
        
        $tenanciesNeedingMoveInInvoices = $activeTenancies->filter(function($tenancy) use ($invoices) {
            $hasMoveInInvoice = $invoices->where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'move_in')
                ->count() > 0;
            return !$hasMoveInInvoice && $tenancy->move_in_date;
        });
        
        return view('invoices.index', compact(
            'mappedInvoices',
            'mappedActiveTenancies',
            'invoices',
            'activeTenancies',
            'tenanciesNeedingMoveInInvoices',
            'totalInvoices',
            'totalUnpaid',
            'totalPaid',
            'totalPartial',
            'totalDraft',
            'averageDays',
            'users',
            'paymentInvoices'
        ));
    }


    private function getNextBillingMonth($tenancy)
    {
        $moveInDate = Carbon::parse($tenancy->move_in_date);
        $currentDate = Carbon::now();
        
        // Get ALL existing monthly invoices ordered by month
        $existingMonths = Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('status', '!=', 'cancelled')
            ->orderBy('billing_month', 'asc')
            ->pluck('billing_month')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m');
            })
            ->toArray();
        
        // Generate expected months from move-in to current date
        $expectedMonths = [];
        $startMonth = $moveInDate->copy()->startOfMonth();
        $endMonth = $currentDate->copy()->startOfMonth();
        
        while ($startMonth <= $endMonth) {
            $expectedMonths[] = $startMonth->format('Y-m');
            $startMonth->addMonth();
        }
        
        // Find the first missing month (gap)
        foreach ($expectedMonths as $expectedMonth) {
            if (!in_array($expectedMonth, $existingMonths)) {
                return $expectedMonth;
            }
        }
        
        // No gaps - next month is after the latest invoice
        if (!empty($existingMonths)) {
            $latestMonth = Carbon::parse(end($existingMonths) . '-01');
            return $latestMonth->addMonth()->format('Y-m');
        }
        
        // No invoices at all - use move-in date logic
        $moveInDay = $moveInDate->day;
        if ($moveInDay > 15) {
            return $moveInDate->copy()->addMonth()->format('Y-m');
        }
        return $moveInDate->format('Y-m');
    }
    
    /**
     * Check if an invoice can be generated for this tenancy
     */
    private function canGenerateInvoice($tenancy)
    {
        $now = Carbon::now();
        $moveInDate = Carbon::parse($tenancy->move_in_date);
        
        if ($moveInDate->gt($now)) {
            return false;
        }
        
        if ($tenancy->status !== 'active') {
            return false;
        }
        
        if ($tenancy->move_out_date && Carbon::parse($tenancy->move_out_date)->lt($now)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if an invoice already exists for a specific month
     */
    private function hasInvoiceForMonth($tenancy, $month)
    {
        return Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('billing_month', $month . '-01')
            ->exists();
    }
    
    /**
     * Get existing invoice ID for a specific month
     */
    private function getExistingInvoiceId($tenancy, $month)
    {
        $invoice = Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('billing_month', $month . '-01')
            ->first();
        
        return $invoice ? $invoice->id : null;
    }
    
    /**
     * Add an item to an existing invoice
     */
    public function addItemToInvoice(Request $request, Invoice $invoice)
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string|max:255',
                'item_type' => 'required|in:rent,water,service,garbage,security,other',
                'amount' => 'required|numeric|min:0.01',
            ]);
            
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add items to a paid invoice.'
                ], 422);
            }
            
            $item = $invoice->items()->create([
                'description' => $validated['description'],
                'item_type' => $validated['item_type'],
                'amount' => $validated['amount'],
            ]);
            
            $invoice->total_amount = $invoice->items()->sum('amount');
            $invoice->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Item added to invoice successfully',
                'item' => $item,
                'invoice' => $invoice->fresh('items')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding item: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update an invoice item
     */
    public function updateInvoiceItem(Request $request, Invoice $invoice, InvoiceItem $item)
    {
        try {
            if ($item->invoice_id !== $invoice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item does not belong to this invoice.'
                ], 422);
            }
            
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update items on a paid invoice.'
                ], 422);
            }
            
            $validated = $request->validate([
                'description' => 'sometimes|string|max:255',
                'amount' => 'sometimes|numeric|min:0.01',
            ]);
            
            $item->update($validated);
            
            $invoice->total_amount = $invoice->items()->sum('amount');
            $invoice->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'item' => $item->fresh(),
                'invoice' => $invoice->fresh('items')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating item: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove an invoice item
     */
    public function removeInvoiceItem(Invoice $invoice, InvoiceItem $item)
    {
        try {
            if ($item->invoice_id !== $invoice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item does not belong to this invoice.'
                ], 422);
            }
            
            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove items from a paid invoice.'
                ], 422);
            }
            
            $item->delete();
            
            $invoice->total_amount = $invoice->items()->sum('amount');
            $invoice->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully',
                'invoice' => $invoice->fresh('items')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing item: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get invoice data for editing/adding items
     */
    public function getInvoiceForEditing(Invoice $invoice)
    {
        $invoice->load('items', 'tenancy.unit', 'tenancy.tenant.user');
        
        return response()->json([
            'success' => true,
            'invoice' => $invoice,
            'can_add_items' => !in_array($invoice->status, ['paid']),
            'next_billing_month' => $this->getNextBillingMonth($invoice->tenancy),
        ]);
    }

    /**
     * Check if a new invoice can be generated for a tenancy
     */
    public function checkInvoiceGenerationStatus(Tenancy $tenancy)
    {
        $nextBillingMonth = $this->getNextBillingMonth($tenancy);
        $hasExistingInvoice = $this->hasInvoiceForMonth($tenancy, $nextBillingMonth);
        $canGenerate = $this->canGenerateInvoice($tenancy);
        
        $response = [
            'success' => true,
            'can_generate' => $canGenerate && !$hasExistingInvoice,
            'has_existing' => $hasExistingInvoice,
            'next_billing_month' => $nextBillingMonth,
            'next_billing_month_formatted' => Carbon::parse($nextBillingMonth . '-01')->format('F Y'),
            'move_in_date' => $tenancy->move_in_date,
            'move_in_date_formatted' => Carbon::parse($tenancy->move_in_date)->format('M d, Y'),
        ];
        
        $now = Carbon::now();
        $moveInDate = Carbon::parse($tenancy->move_in_date);
        
        if ($moveInDate->gt($now)) {
            $response['warning'] = 'Tenancy has not started yet. Invoices can only be generated from ' . $moveInDate->format('M Y');
            $response['can_generate'] = false;
        }
        
        if ($tenancy->move_out_date && Carbon::parse($tenancy->move_out_date)->lt($now)) {
            $response['warning'] = 'This tenancy has ended. No new invoices can be generated.';
            $response['can_generate'] = false;
        }
        
        return response()->json($response);
    }
    
    /**
     * Force generate an invoice for a specific month (with alert confirmation)
     */
    public function forceGenerateInvoice(Request $request, Tenancy $tenancy)
    {
        try {
            $validated = $request->validate([
                'billing_month' => 'required|date_format:Y-m',
                'reason' => 'required|string|min:5',
            ]);
            
            $billingMonth = $validated['billing_month'] . '-01';
            
            $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $billingMonth)
                ->first();
            
            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'An invoice already exists for this month.',
                    'existing_invoice_id' => $existingInvoice->id
                ], 422);
            }
            
            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => $billingMonth,
                'total_amount' => $tenancy->unit->rent_amount ?? 0,
                'status' => 'unpaid',
                'notes' => "Force generated: {$validated['reason']}"
            ]);
            
            if ($tenancy->unit->rent_amount > 0) {
                $invoice->items()->create([
                    'item_type' => 'rent',
                    'description' => 'Monthly Rent',
                    'amount' => $tenancy->unit->rent_amount,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Invoice force generated successfully',
                'invoice' => $invoice->load('items'),
                'warning' => 'This invoice was generated outside the normal billing cycle.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateAveragePaymentTime($invoices)
    {
        $paidInvoices = $invoices->where('status', 'paid');
        if ($paidInvoices->isEmpty()) {
            return 0;
        }
        
        $totalDays = 0;
        foreach ($paidInvoices as $invoice) {
            if ($invoice->created_at && $invoice->payments->isNotEmpty()) {
                $firstPaymentDate = $invoice->payments->min('payment_date');
                if ($firstPaymentDate) {
                    $days = $invoice->created_at->diffInDays($firstPaymentDate);
                    $totalDays += $days;
                }
            }
        }
        
        return $totalDays > 0 ? round($totalDays / $paidInvoices->count()) : 0;
    }

    public function getInvoiceData(Tenancy $tenancy)
    {
        $unit = $tenancy->unit;
        $estate = $unit->estate;
        
        $hasUnitWater = ($unit->water_charge ?? 0) > 0;
        $hasEstateWater = ($estate->water_rate ?? 0) > 0;
        
        $waterSource = 'none';
        $waterRate = 0;
        
        if ($hasUnitWater) {
            $waterSource = 'unit';
            $waterRate = $unit->water_charge;
        } elseif ($hasEstateWater) {
            $waterSource = 'estate';
            $waterRate = $estate->water_rate;
        }
        
        $effectiveService = ($unit->service_charge ?? 0) > 0 ? $unit->service_charge : ($estate->service_charge ?? 0);
        $effectiveGarbage = ($unit->garbage_charge ?? 0) > 0 ? $unit->garbage_charge : ($estate->garbage_charge ?? 0);
        $effectiveSecurity = ($unit->security_charge ?? 0) > 0 ? $unit->security_charge : ($estate->security_charge ?? 0);
        
        $nextBillingMonth = $this->getNextBillingMonth($tenancy);
        $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('billing_month', $nextBillingMonth . '-01')
            ->first();
        
        return response()->json([
            'success' => true,
            'tenancy' => [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->user->name ?? 'Unknown',
                'unit_number' => $unit->unit_number,
                'estate_name' => $estate->name,
                'move_in_date' => $tenancy->move_in_date ? Carbon::parse($tenancy->move_in_date)->format('M d, Y') : 'N/A',
            ],
            'rent_amount' => $unit->rent_amount ?? 0,
            'service_charge' => $effectiveService,
            'garbage_charge' => $effectiveGarbage,
            'security_charge' => $effectiveSecurity,
            'has_water_config' => $waterSource !== 'none',
            'water_source' => $waterSource,
            'water_rate' => $waterRate,
            'previous_reading' => $unit->previous_water_reading ?? 0,
            'current_reading' => $unit->current_water_reading ?? 0,
            'next_billing_month' => $nextBillingMonth,
            'next_billing_month_formatted' => Carbon::parse($nextBillingMonth . '-01')->format('F Y'),
            'has_existing_invoice' => $existingInvoice ? true : false,
            'existing_invoice_id' => $existingInvoice ? $existingInvoice->id : null,
            'can_generate' => $this->canGenerateInvoice($tenancy) && !$existingInvoice,
        ]);
    }

    public function storeForTenancy(Request $request, Tenancy $tenancy)
    {
        try {
            $validated = $request->validate([
                'billing_month' => 'required|date_format:Y-m',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string|max:255',
                'items.*.item_type' => 'required|in:rent,water,service,garbage,security,other',
                'items.*.amount' => 'required|numeric|min:0.01',
            ]);

            if ($tenancy->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create invoice for an inactive tenancy.'
                ], 422);
            }
            
            $moveInDate = Carbon::parse($tenancy->move_in_date);
            $billingMonthDate = Carbon::parse($validated['billing_month'] . '-01');
            
            if ($billingMonthDate->lt($moveInDate->startOfMonth())) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot generate invoice for {$billingMonthDate->format('F Y')}. Tenancy started on {$moveInDate->format('M d, Y')}."
                ], 422);
            }
            
            $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $validated['billing_month'] . '-01')
                ->first();
            
            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'An invoice already exists for this month.',
                    'existing_invoice_id' => $existingInvoice->id,
                    'can_add_items' => true
                ], 422);
            }

            $totalAmount = array_sum(array_column($validated['items'], 'amount'));

            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => $validated['billing_month'] . '-01',
                'total_amount' => $totalAmount,
                'status' => 'unpaid',
            ]);

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'item_type' => $item['item_type'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }
            
            $hasWaterItem = collect($validated['items'])->contains(function($item) {
                return $item['item_type'] === 'water';
            });
            
            if ($hasWaterItem && ($tenancy->unit->current_water_reading > 0)) {
                $tenancy->unit->update([
                    'previous_water_reading' => $tenancy->unit->current_water_reading,
                    'current_water_reading' => 0,
                    'last_reading_date' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice->load('items')
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error creating invoice for tenancy: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tenancy_id' => $tenancy->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        $tenancies = Tenancy::with('tenant.user', 'unit')->where('status', 'active')->get();
        return view('invoices.create', compact('tenancies'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenancy_id' => 'required|exists:tenancies,id',
                'invoice_type' => 'required|in:move_in,monthly,move_out',
                'item_type' => 'nullable|required_if:invoice_type,monthly|in:rent,power,internet,water,security,garbage',
                'description' => 'required|string|max:255',
                'billing_month' => 'required|date_format:Y-m',
                'amount' => 'required|numeric|min:0.01',
                'status' => 'required|in:draft,unpaid,partial,paid',
                'notes' => 'nullable|string',
            ]);

            if ($validated['invoice_type'] === 'monthly') {
                $existingInvoice = Invoice::where('tenancy_id', $validated['tenancy_id'])
                    ->where('invoice_type', 'monthly')
                    ->where('billing_month', $validated['billing_month'] . '-01')
                    ->whereHas('items', function($q) use ($validated) {
                        $q->where('item_type', $validated['item_type']);
                    })
                    ->first();

                if ($existingInvoice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An invoice of this type already exists for the selected tenancy and billing month.'
                    ], 422);
                }
            }

            $invoice = Invoice::create([
                'tenancy_id' => $validated['tenancy_id'],
                'invoice_type' => $validated['invoice_type'],
                'billing_month' => $validated['billing_month'] . '-01',
                'total_amount' => $validated['amount'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($validated['invoice_type'] === 'monthly') {
                $invoice->items()->create([
                    'item_type' => $validated['item_type'],
                    'description' => $validated['description'],
                    'amount' => $validated['amount'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error creating invoice: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

public function show(Invoice $invoice)
{
    // Load relationships
    $invoice->load([
        'tenancy.tenant.user', 
        'tenancy.unit.estate', 
        'items', 
        'payments'
    ]);
    
    // Calculate paid amount from payments
    $paidAmount = $invoice->payments->sum('amount');
    
    // Also get invoices for any dropdowns if needed
    $invoices = Invoice::with('items', 'tenancy.tenant.user')
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
            ];
        });
    
    // Return view with all required variables
    return view('invoices.show', compact('invoice', 'paidAmount', 'invoices'));
}

    public function edit(Invoice $invoice)
    {
        $tenancies = Tenancy::with('tenant.user', 'unit')->get();
        return view('invoices.edit', compact('invoice', 'tenancies'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_type' => 'required|in:move_in,monthly,move_out,other',
            'billing_month' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|in:draft,unpaid,partial,paid',
        ]);

        $invoice->update($validated);
        
        return back()->with('success', 'Invoice updated successfully');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->items()->delete();
        $invoice->payments()->delete();
        $invoice->delete();
        
        return back()->with('success', 'Invoice deleted successfully');
    }

    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,bank_transfer,credit_card',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'],
            'notes' => $validated['notes'],
        ]);

        $totalPaid = $invoice->payments()->sum('amount');
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $invoice->update(['status' => 'partial']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
            'payment' => $payment,
            'invoice' => $invoice->fresh()
        ]);
    }

    public function generateSingleInvoice(Request $request)
    {
        try {
            $request->validate([
                'tenancy_id' => 'required|exists:tenancies,id'
            ]);
            
            $tenancy = Tenancy::with('tenant.user', 'unit')->findOrFail($request->tenancy_id);
            
            $currentMonth = Carbon::now()->format('Y-m');
            $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $currentMonth . '-01')
                ->first();
                
            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice already exists for this tenancy and month'
                ], 400);
            }
            
            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => $currentMonth . '-01',
                'total_amount' => $tenancy->unit->rent_amount ?? 0,
                'status' => 'unpaid',
            ]);
            
            if ($tenancy->unit->rent_amount > 0) {
                $invoice->items()->create([
                    'item_type' => 'rent',
                    'description' => 'Monthly Rent',
                    'amount' => $tenancy->unit->rent_amount,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'invoice' => $invoice->load('tenancy.tenant.user', 'tenancy.unit'),
                'message' => 'Invoice generated successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error generating invoice: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the invoice. Please check logs.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateAllInvoices(Request $request)
    {
        $activeTenancies = Tenancy::where('status', 'active')->get();
        $generatedCount = 0;
        $alreadyGenerated = 0;
        $generatedInvoices = [];
        $currentMonth = Carbon::now()->format('Y-m');
        
        foreach ($activeTenancies as $tenancy) {
            if (empty($tenancy->unit->rent_amount) || $tenancy->unit->rent_amount <= 0) {
                continue;
            }
            
            $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $currentMonth . '-01')
                ->first();
                
            if ($existingInvoice) {
                $alreadyGenerated++;
                continue;
            }
            
            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => $currentMonth . '-01',
                'total_amount' => $tenancy->unit->rent_amount,
                'status' => 'unpaid',
            ]);
            
            $invoice->items()->create([
                'item_type' => 'rent',
                'description' => 'Monthly Rent',
                'amount' => $tenancy->unit->rent_amount,
            ]);
            
            $generatedCount++;
            $generatedInvoices[] = $invoice->load('tenancy.tenant', 'tenancy.unit');
        }
        
        return response()->json([
            'success' => true,
            'generated_count' => $generatedCount,
            'already_generated' => $alreadyGenerated,
            'invoices' => $generatedInvoices,
            'message' => "Generated $generatedCount invoices. $alreadyGenerated already existed."
        ]);
    }

    public function bulkCreate(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validate([
                'invoice_type' => 'required|in:move_in,monthly,move_out',
                'item_type' => 'nullable|required_if:invoice_type,monthly|in:rent,power,internet,water,security,garbage',
                'amount' => 'required|numeric|min:0.01',
                'billing_month' => 'required|date_format:Y-m',
                'apply_to' => 'required|in:bulk,single',
                'tenancy_id' => 'nullable|required_if:apply_to,single|exists:tenancies,id',
                'description' => 'nullable|string',
            ]);

            $billingMonth = Carbon::parse($validated['billing_month'] . '-01');
            $description = $validated['description'] ?? $this->generateBulkDescription($validated);
            $createdCount = 0;
            $skippedCount = 0;
            $errors = [];
            
            if ($validated['apply_to'] === 'bulk') {
                $tenancies = Tenancy::where('status', 'active')
                    ->with(['unit', 'tenant.user'])
                    ->get();
                
                foreach ($tenancies as $tenancy) {
                    try {
                        if (!$tenancy->unit || !$tenancy->tenant) {
                            $skippedCount++;
                            $errors[] = "Skipped tenancy {$tenancy->id}: No unit or tenant assigned";
                            continue;
                        }
                        
                        $existingInvoiceQuery = Invoice::where('tenancy_id', $tenancy->id)
                            ->where('invoice_type', $validated['invoice_type'])
                            ->whereDate('billing_month', $billingMonth);
                        
                        if ($validated['invoice_type'] === 'monthly' && isset($validated['item_type'])) {
                            $existingInvoiceQuery->whereHas('items', function($q) use ($validated) {
                                $q->where('item_type', $validated['item_type']);
                            });
                        }
                        
                        $existingInvoice = $existingInvoiceQuery->first();
                        
                        if ($existingInvoice) {
                            $skippedCount++;
                            $errors[] = "Skipped tenancy {$tenancy->id} ({$tenancy->unit->unit_number}): Invoice already exists";
                            continue;
                        }
                        
                        $invoice = Invoice::create([
                            'tenancy_id' => $tenancy->id,
                            'invoice_type' => $validated['invoice_type'],
                            'billing_month' => $billingMonth,
                            'total_amount' => $validated['amount'],
                            'status' => 'unpaid',
                            'notes' => 'Created via bulk invoice generation',
                        ]);
                        
                        if ($validated['invoice_type'] === 'monthly') {
                            $invoice->items()->create([
                                'item_type' => $validated['item_type'],
                                'description' => $description,
                                'amount' => $validated['amount'],
                            ]);
                        }
                        
                        $createdCount++;
                        
                    } catch (\Exception $e) {
                        $skippedCount++;
                        $errors[] = "Failed for tenancy {$tenancy->id}: " . $e->getMessage();
                        \Log::error("Failed to create invoice for tenancy {$tenancy->id}: " . $e->getMessage());
                    }
                }
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => "Bulk invoice creation completed.",
                    'created_count' => $createdCount,
                    'skipped_count' => $skippedCount,
                    'already_exists' => $skippedCount,
                    'count' => $createdCount,
                    'failed' => 0,
                    'errors' => $errors
                ]);
                
            } else {
                $tenancy = Tenancy::with(['unit', 'tenant.user'])->findOrFail($validated['tenancy_id']);
                
                $existingInvoiceQuery = Invoice::where('tenancy_id', $tenancy->id)
                    ->where('invoice_type', $validated['invoice_type'])
                    ->whereDate('billing_month', $billingMonth);
                
                if ($validated['invoice_type'] === 'monthly' && isset($validated['item_type'])) {
                    $existingInvoiceQuery->whereHas('items', function($q) use ($validated) {
                        $q->where('item_type', $validated['item_type']);
                    });
                }
                
                $existingInvoice = $existingInvoiceQuery->first();
                
                if ($existingInvoice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An invoice of this type already exists for the selected tenancy and billing month.',
                        'already_exists' => true,
                        'skipped_count' => 1
                    ], 422);
                }
                
                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => $validated['invoice_type'],
                    'billing_month' => $billingMonth,
                    'total_amount' => $validated['amount'],
                    'status' => 'unpaid',
                    'notes' => 'Created via bulk invoice',
                ]);
                
                if ($validated['invoice_type'] === 'monthly') {
                    $invoice->items()->create([
                        'item_type' => $validated['item_type'],
                        'description' => $description,
                        'amount' => $validated['amount'],
                    ]);
                }
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice created successfully',
                    'invoice' => $invoice,
                    'count' => 1,
                    'created_count' => 1
                ]);
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Error in bulk invoice creation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkExistingInvoices(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenancy_ids' => 'required|array',
                'tenancy_ids.*' => 'exists:tenancies,id',
                'invoice_type' => 'required|in:move_in,monthly,move_out',
                'item_type' => 'nullable|required_if:invoice_type,monthly|in:rent,power,internet,water,security,garbage',
                'billing_month' => 'required|date_format:Y-m',
            ]);

            $billingMonth = Carbon::parse($validated['billing_month'] . '-01');
            
            $existingInvoices = Invoice::whereIn('tenancy_id', $validated['tenancy_ids'])
                ->where('invoice_type', $validated['invoice_type'])
                ->whereDate('billing_month', $billingMonth)
                ->when($validated['invoice_type'] === 'monthly', function ($query) use ($validated) {
                    return $query->whereHas('items', function ($q) use ($validated) {
                        $q->where('item_type', $validated['item_type']);
                    });
                })
                ->pluck('tenancy_id')
                ->toArray();
            
            $remainingTenancies = array_diff($validated['tenancy_ids'], $existingInvoices);
            
            $remainingTenancyDetails = Tenancy::whereIn('id', $remainingTenancies)
                ->with(['unit', 'tenant.user'])
                ->get()
                ->map(function($tenancy) {
                    return [
                        'id' => $tenancy->id,
                        'unit_number' => $tenancy->unit->unit_number ?? 'N/A',
                        'tenant_name' => $tenancy->tenant->user->name ?? 'Unknown',
                        'status' => $tenancy->status,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'existing_count' => count($existingInvoices),
                'remaining_count' => count($remainingTenancies),
                'remaining_tenancies' => $remainingTenancyDetails,
                'existing_tenancy_ids' => $existingInvoices,
                'remaining_tenancy_ids' => array_values($remainingTenancies),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error checking existing invoices: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking existing invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateBulkDescription($data)
    {
        $invoiceType = $data['invoice_type'];
        $itemType = $data['item_type'] ?? null;
        
        $labels = [
            'rent' => 'Rent',
            'power' => 'Power/Electricity',
            'internet' => 'Internet',
            'water' => 'Water',
            'security' => 'Security',
            'garbage' => 'Garbage Collection'
        ];
        
        if ($invoiceType === 'monthly' && $itemType) {
            $itemLabel = $labels[$itemType] ?? ucfirst($itemType);
            
            switch($itemType) {
                case 'rent':
                    return 'Monthly Rent';
                case 'water':
                case 'power':
                case 'internet':
                    return $itemLabel . ' Charges';
                case 'security':
                case 'garbage':
                    return $itemLabel . ' Service Charge';
                default:
                    return $itemLabel . ' Charges';
            }
        } else if ($invoiceType === 'move_in') {
            return 'Move In Charges';
        } else if ($invoiceType === 'move_out') {
            return 'Move Out Charges';
        }
        
        return 'Invoice Charges';
    }

    public function indexForTenancy(Request $request, Tenancy $tenancy)
    {
        $invoices = $tenancy->invoices()
            ->with(['items', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'invoices' => $invoices
            ]);
        }
        
        return view('invoices.index-for-tenancy', compact('tenancy', 'invoices'));
    }

    public function updateWaterReadingAfterInvoice(Tenancy $tenancy)
    {
        $unit = $tenancy->unit;
        
        $unit->update([
            'previous_water_reading' => $unit->current_water_reading,
            'current_water_reading' => 0,
            'last_reading_date' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Water readings updated after invoice generation'
        ]);
    }

    public function getBillingHistory(Tenancy $tenancy)
    {
        $invoices = Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('status', '!=', 'cancelled')
            ->orderBy('billing_month', 'asc')
            ->get();
        
        $moveInDate = Carbon::parse($tenancy->move_in_date);
        $currentDate = Carbon::now();
        $expectedMonths = [];
        
        // Generate expected months from move_in_date to current date
        $startMonth = $moveInDate->copy()->startOfMonth();
        $endMonth = $currentDate->copy()->startOfMonth();
        
        while ($startMonth <= $endMonth) {
            $expectedMonths[] = $startMonth->format('Y-m');
            $startMonth->addMonth();
        }
        
        // Find existing months
        $existingMonths = $invoices->map(function($invoice) {
            return Carbon::parse($invoice->billing_month)->format('Y-m');
        })->toArray();
        
        // Find missing months
        $missingMonths = array_diff($expectedMonths, $existingMonths);
        
        // Group invoices by month (to detect duplicates)
        $invoicesByMonth = [];
        foreach ($invoices as $invoice) {
            $month = Carbon::parse($invoice->billing_month)->format('Y-m');
            if (!isset($invoicesByMonth[$month])) {
                $invoicesByMonth[$month] = [];
            }
            $invoicesByMonth[$month][] = [
                'id' => $invoice->id,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'items' => $invoice->items->map(function($item) {
                    return [
                        'description' => $item->description,
                        'item_type' => $item->item_type,
                        'amount' => $item->amount
                    ];
                })
            ];
        }
        
        // Check for duplicates
        $duplicateMonths = [];
        foreach ($invoicesByMonth as $month => $monthInvoices) {
            if (count($monthInvoices) > 1) {
                $duplicateMonths[$month] = $monthInvoices;
            }
        }
        
        return response()->json([
            'success' => true,
            'move_in_date' => $moveInDate->format('Y-m-d'),
            'move_in_date_formatted' => $moveInDate->format('F Y'),
            'current_date' => $currentDate->format('Y-m-d'),
            'expected_months' => $expectedMonths,
            'existing_months' => $existingMonths,
            'missing_months' => $missingMonths,
            'has_gaps' => count($missingMonths) > 0,
            'duplicate_months' => $duplicateMonths,
            'invoices_by_month' => $invoicesByMonth,
            'next_expected_month' => !empty($expectedMonths) ? end($expectedMonths) : null,
            'next_billing_month' => $this->getNextBillingMonth($tenancy),
        ]);
    }

    /**
     * Generate invoices for all missing months
     */
    public function generateMissingInvoices(Request $request, Tenancy $tenancy)
    {
        try {
            $validated = $request->validate([
                'months' => 'required|array',
                'months.*' => 'date_format:Y-m',
                'items' => 'required|array',
                'items.*.description' => 'required|string',
                'items.*.item_type' => 'required|string',
                'items.*.amount' => 'required|numeric|min:0',
            ]);
            
            $generatedInvoices = [];
            $errors = [];
            
            DB::beginTransaction();
            
            foreach ($validated['months'] as $month) {
                // Check if invoice already exists for this month
                $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                    ->where('invoice_type', 'monthly')
                    ->where('billing_month', $month . '-01')
                    ->first();
                
                if ($existingInvoice) {
                    $errors[] = "Invoice for {$month} already exists. Skipping.";
                    continue;
                }
                
                $totalAmount = array_sum(array_column($validated['items'], 'amount'));
                
                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => 'monthly',
                    'billing_month' => $month . '-01',
                    'total_amount' => $totalAmount,
                    'status' => 'unpaid',
                    'notes' => "Generated for missing month: {$month}",
                ]);
                
                foreach ($validated['items'] as $item) {
                    $invoice->items()->create([
                        'item_type' => $item['item_type'],
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }
                
                $generatedInvoices[] = $invoice->load('items');
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Generated " . count($generatedInvoices) . " invoices for missing months",
                'generated_count' => count($generatedInvoices),
                'error_count' => count($errors),
                'errors' => $errors,
                'invoices' => $generatedInvoices,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error generating invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getInvoiceDetails(Invoice $invoice)
    {
        try {
            $invoice->load([
                'tenancy.tenant.user', 
                'tenancy.unit.estate', 
                'items', 
                'payments'
            ]);
            
            // Calculate remaining amount
            $paidAmount = (float) $invoice->payments->sum('amount');
            $remainingAmount = (float) ($invoice->total_amount - $paidAmount);
            
            return response()->json([
                'success' => true,
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
                    'tenant_id' => $invoice->tenancy->tenant_id,
                    'tenant_name' => $invoice->tenancy->tenant->user->name ?? 'Unknown',
                    'unit_number' => $invoice->tenancy->unit->unit_number ?? 'N/A',
                    'unit_id' => $invoice->tenancy->unit_id,
                    'estate_name' => $invoice->tenancy->unit->estate->name ?? 'N/A',
                    'billing_month' => $invoice->billing_month,
                    'billing_month_formatted' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('F Y') : '-',
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'status' => $invoice->status,
                    'payment_percentage' => $invoice->payment_percentage,
                    'items' => $invoice->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                            'paid_amount' => (float) ($item->paid_amount ?? 0),
                            'remaining_amount' => (float) ($item->amount - ($item->paid_amount ?? 0)),
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching invoice details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch invoice details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateMissingInvoicesBulk(Request $request, Tenancy $tenancy)
    {
        try {
            $validated = $request->validate([
                'months_data' => 'required|array',
                'months_data.*.month' => 'required|date_format:Y-m',
                'months_data.*.items' => 'required|array|min:1',
                'months_data.*.items.*.description' => 'required|string',
                'months_data.*.items.*.item_type' => 'required|string',
                'months_data.*.items.*.amount' => 'required|numeric|min:0',
            ]);
            
            $generatedInvoices = [];
            $errors = [];
            $waterReadingUpdates = [];
            
            DB::beginTransaction();
            
            foreach ($validated['months_data'] as $monthData) {
                $month = $monthData['month'];
                
                // Check if invoice already exists for this month
                $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                    ->where('invoice_type', 'monthly')
                    ->where('billing_month', $month . '-01')
                    ->first();
                
                if ($existingInvoice) {
                    $errors[] = "Invoice for {$month} already exists. Skipping.";
                    continue;
                }
                
                $totalAmount = array_sum(array_column($monthData['items'], 'amount'));
                
                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => 'monthly',
                    'billing_month' => $month . '-01',
                    'total_amount' => $totalAmount,
                    'status' => 'unpaid',
                    'notes' => "Generated for missing month: {$month}",
                ]);
                
                foreach ($monthData['items'] as $item) {
                    $itemData = [
                        'item_type' => $item['item_type'],
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ];
                    
                    // Add metadata for water items
                    if ($item['item_type'] === 'water' && isset($item['metadata'])) {
                        $itemData['metadata'] = $item['metadata'];
                        
                        // Track water reading updates for the unit
                        if (isset($item['metadata']['current_reading'])) {
                            $waterReadingUpdates[$month] = [
                                'current_reading' => $item['metadata']['current_reading'],
                                'previous_reading' => $item['metadata']['previous_reading'] ?? $tenancy->unit->previous_water_reading,
                            ];
                        }
                    }
                    
                    $invoice->items()->create($itemData);
                }
                
                $generatedInvoices[] = $invoice->load('items');
            }
            
            // Update water readings for the unit if any water invoices were generated
            if (!empty($waterReadingUpdates)) {
                $lastUpdate = end($waterReadingUpdates);
                $tenancy->unit->update([
                    'previous_water_reading' => $lastUpdate['current_reading'],
                    'current_water_reading' => 0,
                    'last_reading_date' => now(),
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Generated " . count($generatedInvoices) . " invoices for missing months",
                'generated_count' => count($generatedInvoices),
                'error_count' => count($errors),
                'errors' => $errors,
                'invoices' => $generatedInvoices,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Bulk missing invoices error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tenancy_id' => $tenancy->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating invoices: ' . $e->getMessage()
            ], 500);
        }
    }


    public function resolveDuplicateInvoices(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => 'required|date_format:Y-m',
                'tenancy_id' => 'required|exists:tenancies,id',
                'keep_invoice_id' => 'required|exists:invoices,id'
            ]);
            
            $month = $validated['month'] . '-01';
            $tenancyId = $validated['tenancy_id'];
            $keepInvoiceId = $validated['keep_invoice_id'];
            
            // Find all duplicates for this month
            $duplicates = Invoice::where('tenancy_id', $tenancyId)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $month)
                ->where('id', '!=', $keepInvoiceId)
                ->get();
            
            $deletedCount = 0;
            
            DB::beginTransaction();
            
            foreach ($duplicates as $duplicate) {
                // Delete associated items first
                $duplicate->items()->delete();
                // Delete any payments
                $duplicate->payments()->delete();
                // Delete the invoice
                $duplicate->delete();
                $deletedCount++;
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Kept invoice #{$keepInvoiceId} and deleted {$deletedCount} duplicate invoice(s).",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error resolving duplicates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve duplicate invoices by keeping one and deleting others
     */
    public function resolveDuplicates(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => 'required|string',
                'tenancy_id' => 'required|exists:tenancies,id',
                'keep_invoice_id' => 'required|exists:invoices,id'
            ]);
            
            $month = $validated['month'] . '-01';
            $tenancyId = $validated['tenancy_id'];
            $keepInvoiceId = $validated['keep_invoice_id'];
            
            // Find all duplicates for this month
            $duplicates = Invoice::where('tenancy_id', $tenancyId)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $month)
                ->where('id', '!=', $keepInvoiceId)
                ->get();
            
            $deletedCount = 0;
            
            DB::beginTransaction();
            
            foreach ($duplicates as $duplicate) {
                // Delete associated items
                $duplicate->items()->delete();
                // Delete any payments
                $duplicate->payments()->delete();
                // Delete the invoice
                $duplicate->delete();
                $deletedCount++;
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Kept invoice #{$keepInvoiceId} and deleted {$deletedCount} duplicate invoice(s).",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Duplicate resolution error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error resolving duplicates: ' . $e->getMessage()
            ], 500);
        }
    }

}