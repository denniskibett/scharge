<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\InvoiceItem;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            
            // Get water item status
            $waterItem = $invoice->items->firstWhere('item_type', 'water');
            $waterStatus = 'none';
            $waterUnits = 0;
            
            if ($waterItem) {
                $waterUnits = (float) ($waterItem->water_units_used ?? 0);
                if ($waterUnits > 0) {
                    $waterStatus = 'synced';
                } elseif ($invoice->status !== 'paid') {
                    $waterStatus = 'pending';
                } else {
                    $waterStatus = 'needs_review';
                }
            }
            
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
                'water_status' => $waterStatus,
                'water_units' => $waterUnits,
                'items' => $invoice->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'item_type' => $item->item_type,
                        'amount' => (float) $item->amount,
                        'paid_amount' => (float) ($item->paid_amount ?? 0),
                        'remaining_amount' => (float) ($item->amount - ($item->paid_amount ?? 0)),
                        'water_units_used' => (float) ($item->water_units_used ?? 0),
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
        
        // Get water sync stats
        $waterStats = $this->getWaterSyncStats($invoices);
        
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
            'paymentInvoices',
            'waterStats'
        ));
    }

    /**
     * Get water sync statistics for invoices
     */
    private function getWaterSyncStats($invoices)
    {
        $totalWithWater = 0;
        $synced = 0;
        $pending = 0;
        $needsReview = 0;
        
        foreach ($invoices as $invoice) {
            $waterItem = $invoice->items->firstWhere('item_type', 'water');
            if ($waterItem) {
                $totalWithWater++;
                if ($waterItem->water_units_used && $waterItem->water_units_used > 0) {
                    $synced++;
                } elseif ($invoice->status !== 'paid') {
                    $pending++;
                } else {
                    $needsReview++;
                }
            }
        }
        
        return [
            'total_with_water' => $totalWithWater,
            'synced' => $synced,
            'pending' => $pending,
            'needs_review' => $needsReview,
        ];
    }

    /**
     * CENTRALIZED: Update invoice status based on payments
     * This method should be called whenever invoice items or payments change
     */
    private function updateInvoiceStatus(Invoice $invoice)
    {
        $totalPaid = (float) $invoice->payments()->sum('amount');
        $totalAmount = (float) $invoice->total_amount;
        
        // Update total_paid on invoice
        $invoice->total_paid = $totalPaid;
        
        // Determine status based on payment vs total
        if ($totalPaid >= $totalAmount && $totalAmount > 0) {
            $invoice->status = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $totalAmount) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'unpaid';
        }
        
        // If total amount is 0, mark as paid (no charge)
        if ($totalAmount == 0) {
            $invoice->status = 'paid';
        }
        
        $invoice->save();
        
        return $invoice;
    }

    /**
     * CENTRALIZED: Update invoice total and status after item changes
     */
    private function recalculateInvoice(Invoice $invoice)
    {
        // Recalculate total from all items
        $newTotal = (float) $invoice->items()->sum('amount');
        $invoice->total_amount = $newTotal;
        
        // Update status based on payments
        return $this->updateInvoiceStatus($invoice);
    }

    private function getNextBillingMonth($tenancy)
    {
        $moveInDate = Carbon::parse($tenancy->move_in_date);
        $currentDate = Carbon::now();
        
        $existingMonths = Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('status', '!=', 'cancelled')
            ->orderBy('billing_month', 'asc')
            ->pluck('billing_month')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m');
            })
            ->toArray();
        
        $expectedMonths = [];
        $startMonth = $moveInDate->copy()->startOfMonth();
        $endMonth = $currentDate->copy()->startOfMonth();
        
        while ($startMonth <= $endMonth) {
            $expectedMonths[] = $startMonth->format('Y-m');
            $startMonth->addMonth();
        }
        
        foreach ($expectedMonths as $expectedMonth) {
            if (!in_array($expectedMonth, $existingMonths)) {
                return $expectedMonth;
            }
        }
        
        if (!empty($existingMonths)) {
            $latestMonth = Carbon::parse(end($existingMonths) . '-01');
            return $latestMonth->addMonth()->format('Y-m');
        }
        
        $moveInDay = $moveInDate->day;
        if ($moveInDay > 15) {
            return $moveInDate->copy()->addMonth()->format('Y-m');
        }
        return $moveInDate->format('Y-m');
    }
    
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
    
    private function hasInvoiceForMonth($tenancy, $month)
    {
        return Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('billing_month', $month . '-01')
            ->exists();
    }
    
    private function getExistingInvoiceId($tenancy, $month)
    {
        $invoice = Invoice::where('tenancy_id', $tenancy->id)
            ->where('invoice_type', 'monthly')
            ->where('billing_month', $month . '-01')
            ->first();
        
        return $invoice ? $invoice->id : null;
    }
    
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
            
            // Recalculate invoice total and status
            $this->recalculateInvoice($invoice);
            
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
            
            // Recalculate invoice total and status
            $this->recalculateInvoice($invoice);
            
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
            
            // Recalculate invoice total and status
            $this->recalculateInvoice($invoice);
            
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

    /**
     * Get water reading for a specific month
     * For billing_month = June, we need the MAY reading (month - 1)
     */
    private function getWaterReadingForMonth($unit, Carbon $billingMonth)
    {
        // For billing_month = June, we need the MAY reading
        $readingMonth = $billingMonth->copy()->subMonth();
        
        // Try to get reading for the month before billing month
        $reading = WaterReading::where('unit_id', $unit->id)
            ->whereYear('reading_date', $readingMonth->year)
            ->whereMonth('reading_date', $readingMonth->month)
            ->first();
        
        // If no reading for previous month, try to find the closest reading before the billing month
        if (!$reading) {
            $reading = WaterReading::where('unit_id', $unit->id)
                ->where('reading_date', '<', $billingMonth->copy()->startOfMonth())
                ->orderBy('reading_date', 'desc')
                ->first();
        }
        
        if ($reading) {
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            $previousReading = (float) $reading->previous_reading;
            $currentReading = (float) $reading->current_reading;
            $consumption = $reading->consumption ?: max(0, $currentReading - $previousReading);
            $charge = $unit->water_billing_type === 'flat' ? ($unit->water_charge ?? 0) : ($consumption * $rate);
            
            // Build a rich description
            $readingMonth = $reading->reading_date->format('M Y');
            $billingMonthFormatted = $billingMonth->format('M Y');
            
            if ($unit->water_billing_type === 'flat') {
                $description = sprintf(
                    "Water Charge - %s (Flat Rate: KES %.2f) [Billed for %s]",
                    $readingMonth,
                    $charge,
                    $billingMonthFormatted
                );
            } else {
                $description = sprintf(
                    "Water Charge - %s (Previous: %.2f m³, Current: %.2f m³, Consumption: %.2f m³ @ KES %.2f/m³) [Billed for %s]",
                    $readingMonth,
                    $previousReading,
                    $currentReading,
                    $consumption,
                    $rate,
                    $billingMonthFormatted
                );
            }
            
            return [
                'reading_id' => $reading->id,
                'consumption' => $consumption,
                'charge' => $charge,
                'rate' => $rate,
                'reading_date' => $reading->reading_date->format('Y-m-d'),
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'description' => $description,
            ];
        }
        
        return null;
    }
    
    public function storeForTenancy(Request $request, Tenancy $tenancy)
    {
        try {
            $validated = $request->validate([
                'billing_month' => 'required|date_format:Y-m',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string|max:255',
                'items.*.item_type' => 'required|in:rent,water,service,garbage,security,other',
                'items.*.amount' => 'required|numeric|min:0',
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

            $unit = $tenancy->unit;
            $totalAmount = 0;
            $processedItems = [];
            $waterWarnings = [];

            foreach ($validated['items'] as $item) {
                $amount = $item['amount'];
                $itemData = [
                    'item_type' => $item['item_type'],
                    'description' => $item['description'],
                    'amount' => $amount,
                ];
                
                // If this is a water item, try to fetch from readings
                if ($item['item_type'] === 'water') {
                    $waterData = $this->getWaterReadingForMonth($unit, $billingMonthDate);
                    
                    if ($waterData) {
                        // Check if consumption is 0
                        if ($waterData['consumption'] == 0 && $unit->water_billing_type !== 'flat') {
                            // Zero consumption - set amount to 0 but keep the item
                            $itemData['amount'] = 0;
                            $itemData['water_units_used'] = 0;
                            $itemData['description'] = sprintf(
                                "Water Charge - %s (Previous: %.2f m³, Current: %.2f m³, Consumption: 0.00 m³ - No consumption) [Billed for %s]",
                                $waterData['reading_date'],
                                $waterData['previous_reading'],
                                $waterData['current_reading'],
                                $billingMonthDate->format('M Y')
                            );
                            $waterWarnings[] = "Zero water consumption for {$billingMonthDate->format('F Y')}. Water charge set to 0.";
                        } else {
                            $itemData['amount'] = $waterData['charge'];
                            $itemData['water_units_used'] = $waterData['consumption'];
                            $itemData['description'] = $waterData['description'];
                            $amount = $waterData['charge'];
                        }
                    } else {
                        $waterWarnings[] = "No water reading found for {$billingMonthDate->format('F Y')}. Water charge entered manually.";
                        $itemData['water_units_used'] = 0;
                    }
                }
                
                $totalAmount += $itemData['amount'];
                $processedItems[] = $itemData;
            }

            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => $validated['billing_month'] . '-01',
                'total_amount' => $totalAmount,
                'status' => 'unpaid',
            ]);

            foreach ($processedItems as $itemData) {
                $invoice->items()->create($itemData);
            }
            
            // Update water readings after invoice creation
            $hasWaterItem = collect($processedItems)->contains(function($item) {
                return $item['item_type'] === 'water' && ($item['water_units_used'] ?? 0) > 0;
            });
            
            if ($hasWaterItem && ($unit->current_water_reading > 0)) {
                $unit->update([
                    'previous_water_reading' => $unit->current_water_reading,
                    'current_water_reading' => 0,
                    'last_reading_date' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice->load('items'),
                'water_warnings' => $waterWarnings
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating invoice for tenancy: ' . $e->getMessage(), [
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
            Log::error('Error creating invoice: ' . $e->getMessage(), [
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
        $invoice->load([
            'tenancy.tenant.user', 
            'tenancy.unit.estate', 
            'items', 
            'payments'
        ]);
        
        $paidAmount = $invoice->payments->sum('amount');
        
        // Check water sync status for each item
        $waterItems = $invoice->items->filter(function($item) {
            return $item->item_type === 'water';
        });
        
        $waterSynced = $waterItems->every(function($item) {
            return ($item->water_units_used ?? 0) > 0;
        });
        
        $waterSyncStatus = 'none';
        if ($waterItems->count() > 0) {
            if ($waterSynced) {
                $waterSyncStatus = 'synced';
            } elseif ($invoice->status !== 'paid') {
                $waterSyncStatus = 'pending';
            } else {
                $waterSyncStatus = 'needs_review';
            }
        }
        
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
        
        return view('invoices.show', compact(
            'invoice', 
            'paidAmount', 
            'invoices',
            'waterSyncStatus',
            'waterSynced'
        ));
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

        // Update invoice status based on new payment
        $this->updateInvoiceStatus($invoice);

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
            Log::error('Error generating invoice: ' . $e->getMessage(), [
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
                        Log::error("Failed to create invoice for tenancy {$tenancy->id}: " . $e->getMessage());
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
            
            Log::error('Error in bulk invoice creation: ' . $e->getMessage(), [
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
            Log::error('Error checking existing invoices: ' . $e->getMessage());
            
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
        
        $startMonth = $moveInDate->copy()->startOfMonth();
        $endMonth = $currentDate->copy()->startOfMonth();
        
        while ($startMonth <= $endMonth) {
            $expectedMonths[] = $startMonth->format('Y-m');
            $startMonth->addMonth();
        }
        
        $existingMonths = $invoices->map(function($invoice) {
            return Carbon::parse($invoice->billing_month)->format('Y-m');
        })->toArray();
        
        $missingMonths = array_diff($expectedMonths, $existingMonths);
        
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
            
            $paidAmount = (float) $invoice->payments->sum('amount');
            $remainingAmount = (float) ($invoice->total_amount - $paidAmount);
            
            // Include water sync status
            $waterItems = $invoice->items->filter(function($item) {
                return $item->item_type === 'water';
            });
            
            $waterSynced = $waterItems->every(function($item) {
                return ($item->water_units_used ?? 0) > 0;
            });
            
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
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'status' => $invoice->status,
                    'payment_percentage' => $invoice->payment_percentage,
                    'water_synced' => $waterSynced,
                    'items' => $invoice->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                            'paid_amount' => (float) ($item->paid_amount ?? 0),
                            'remaining_amount' => (float) ($item->amount - ($item->paid_amount ?? 0)),
                            'water_units_used' => (float) ($item->water_units_used ?? 0),
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
                    
                    if ($item['item_type'] === 'water' && isset($item['metadata'])) {
                        $itemData['metadata'] = $item['metadata'];
                        
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
            Log::error('Bulk missing invoices error: ' . $e->getMessage(), [
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
            
            $duplicates = Invoice::where('tenancy_id', $tenancyId)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $month)
                ->where('id', '!=', $keepInvoiceId)
                ->get();
            
            $deletedCount = 0;
            
            DB::beginTransaction();
            
            foreach ($duplicates as $duplicate) {
                $duplicate->items()->delete();
                $duplicate->payments()->delete();
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
            
            $duplicates = Invoice::where('tenancy_id', $tenancyId)
                ->where('invoice_type', 'monthly')
                ->where('billing_month', $month)
                ->where('id', '!=', $keepInvoiceId)
                ->get();
            
            $deletedCount = 0;
            
            DB::beginTransaction();
            
            foreach ($duplicates as $duplicate) {
                $duplicate->items()->delete();
                $duplicate->payments()->delete();
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
            Log::error('Duplicate resolution error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error resolving duplicates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reconcile a single invoice's water charges (including paid invoices)
     * This will update the water item with the correct reading and adjust the invoice total
     */
    public function reconcileSingleInvoice(Request $request, Invoice $invoice)
    {
        try {
            $unit = $invoice->tenancy->unit;
            $waterItem = $invoice->items()->where('item_type', 'water')->first();
            
            if (!$waterItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'No water item found on this invoice'
                ], 404);
            }
            
            $billingMonth = Carbon::parse($invoice->billing_month);
            $previousMonth = $billingMonth->copy()->subMonth();
            
            // Get reading from previous month (billing logic: month - 1)
            $reading = WaterReading::where('unit_id', $unit->id)
                ->whereYear('reading_date', $previousMonth->year)
                ->whereMonth('reading_date', $previousMonth->month)
                ->first();
            
            if (!$reading) {
                $reading = WaterReading::where('unit_id', $unit->id)
                    ->where('reading_date', '<', $billingMonth->copy()->startOfMonth())
                    ->orderBy('reading_date', 'desc')
                    ->first();
            }
            
            if (!$reading) {
                return response()->json([
                    'success' => false,
                    'message' => "No water reading found for {$previousMonth->format('F Y')}"
                ], 404);
            }
            
            $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
            $previousReading = (float) $reading->previous_reading;
            $currentReading = (float) $reading->current_reading;
            $consumption = $reading->consumption ?: max(0, $currentReading - $previousReading);
            
            if ($unit->water_billing_type === 'flat') {
                $calculatedCharge = (float) ($unit->water_charge ?? 0);
                $consumption = 0;
            } else {
                $calculatedCharge = $consumption * $rate;
            }
            
            $readingMonth = $reading->reading_date->format('M Y');
            $billingMonthFormatted = $billingMonth->format('M Y');
            
            $description = sprintf(
                "Water Charge - %s (Previous: %.2f m³, Current: %.2f m³, Consumption: %.2f m³ @ KES %.2f/m³) [Billed for %s]",
                $readingMonth,
                $previousReading,
                $currentReading,
                $consumption,
                $rate,
                $billingMonthFormatted
            );
            
            $oldAmount = $waterItem->amount;
            
            // Update the water item
            $waterItem->update([
                'amount' => $calculatedCharge,
                'water_units_used' => $consumption,
                'description' => $description,
            ]);
            
            // Recalculate invoice total and status using centralized method
            $this->recalculateInvoice($invoice);
            
            $newTotal = $invoice->total_amount;
            $paidAmount = (float) $invoice->payments()->sum('amount');
            
            $response = [
                'success' => true,
                'message' => 'Invoice reconciled successfully',
                'invoice' => $invoice->load('items'),
                'consumption' => $consumption,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'charge' => $calculatedCharge,
                'old_charge' => $oldAmount,
                'new_total' => $newTotal,
                'paid_amount' => $paidAmount,
                'status' => $invoice->status,
                'description' => $description,
                'overpaid' => $paidAmount > $newTotal ? ($paidAmount - $newTotal) : 0,
                'underpaid' => $newTotal > $paidAmount ? ($newTotal - $paidAmount) : 0,
            ];
            
            // Add warning if overpaid or underpaid
            if ($paidAmount > $newTotal) {
                $response['warning'] = "Invoice is overpaid by KES " . number_format($paidAmount - $newTotal, 2) . ". Consider issuing a credit note.";
            } elseif ($paidAmount > 0 && $paidAmount < $newTotal) {
                $response['warning'] = "Invoice is partially paid. Balance due: KES " . number_format($newTotal - $paidAmount, 2);
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('Reconcile single invoice error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'invoice_id' => $invoice->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error reconciling invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reconcile water charges for all invoices in a month
     * Now includes paid invoices and handles status adjustments
     */
    public function bulkReconcileWaterCharges(Request $request)
    {
        try {
            $validated = $request->validate([
                'billing_month' => 'required|date_format:Y-m',
                'estate_id' => 'nullable|exists:estates,id',
                'invoice_ids' => 'nullable|array',
                'invoice_ids.*' => 'exists:invoices,id',
                'include_paid' => 'nullable|boolean',
            ]);
            
            $billingMonth = Carbon::parse($validated['billing_month'] . '-01');
            $includePaid = $validated['include_paid'] ?? true; // Default to true to include all
            
            $results = [];
            $updated = 0;
            $noReading = 0;
            $alreadyCorrect = 0;
            $zeroConsumption = 0;
            $statusChanged = 0;
            
            $query = Invoice::with(['tenancy.unit', 'items'])
                ->where('billing_month', $billingMonth->format('Y-m') . '-01')
                ->where('invoice_type', 'monthly');
            
            // Include paid invoices if specified (default: true)
            if (!$includePaid) {
                $query->where('status', '!=', 'paid');
            }
            
            if (isset($validated['estate_id']) && !empty($validated['estate_id'])) {
                $query->whereHas('tenancy.unit', function($q) use ($validated) {
                    $q->where('estate_id', $validated['estate_id']);
                });
            }
            
            if (isset($validated['invoice_ids']) && !empty($validated['invoice_ids'])) {
                $query->whereIn('id', $validated['invoice_ids']);
            }
            
            $invoices = $query->get();
            
            DB::beginTransaction();
            
            foreach ($invoices as $invoice) {
                $unit = $invoice->tenancy->unit;
                $waterItem = $invoice->items()->where('item_type', 'water')->first();
                
                if (!$waterItem) {
                    continue;
                }
                
                // For billing_month = June, we need the MAY reading
                $previousMonth = $billingMonth->copy()->subMonth();
                
                // Get the reading from the previous month
                $reading = WaterReading::where('unit_id', $unit->id)
                    ->whereYear('reading_date', $previousMonth->year)
                    ->whereMonth('reading_date', $previousMonth->month)
                    ->first();
                
                // If no reading for previous month, try to find the closest reading before the billing month
                if (!$reading) {
                    $reading = WaterReading::where('unit_id', $unit->id)
                        ->where('reading_date', '<', $billingMonth->copy()->startOfMonth())
                        ->orderBy('reading_date', 'desc')
                        ->first();
                }
                
                if ($reading) {
                    $rate = $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50;
                    $previousReading = (float) $reading->previous_reading;
                    $currentReading = (float) $reading->current_reading;
                    $consumption = $reading->consumption ?: max(0, $currentReading - $previousReading);
                    
                    // Calculate charge based on billing type
                    if ($unit->water_billing_type === 'flat') {
                        $calculatedCharge = (float) ($unit->water_charge ?? 0);
                        $consumption = 0;
                    } else {
                        $calculatedCharge = $consumption * $rate;
                    }
                    
                    // Create detailed description with ALL information
                    $readingMonth = $reading->reading_date->format('M Y');
                    $billingMonthFormatted = $billingMonth->format('M Y');
                    
                    if ($unit->water_billing_type === 'flat') {
                        $description = sprintf(
                            "Water Charge - %s (Flat Rate: KES %.2f) [Billed for %s]",
                            $readingMonth,
                            $calculatedCharge,
                            $billingMonthFormatted
                        );
                    } else {
                        $description = sprintf(
                            "Water Charge - %s (Previous: %.2f m³, Current: %.2f m³, Consumption: %.2f m³ @ KES %.2f/m³) [Billed for %s]",
                            $readingMonth,
                            $previousReading,
                            $currentReading,
                            $consumption,
                            $rate,
                            $billingMonthFormatted
                        );
                    }
                    
                    // Check if consumption is 0
                    if ($consumption == 0 && $unit->water_billing_type !== 'flat') {
                        $zeroConsumption++;
                        
                        $waterItem->update([
                            'amount' => 0,
                            'water_units_used' => 0,
                            'description' => sprintf(
                                "Water Charge - %s (Previous: %.2f m³, Current: %.2f m³, Consumption: 0.00 m³ - No consumption) [Billed for %s]",
                                $readingMonth,
                                $previousReading,
                                $currentReading,
                                $billingMonthFormatted
                            ),
                        ]);
                        
                        // Recalculate invoice total and status
                        $this->recalculateInvoice($invoice);
                        
                        $results[] = [
                            'invoice_id' => $invoice->id,
                            'unit_number' => $unit->unit_number,
                            'status' => 'zero_consumption',
                            'consumption' => 0,
                            'previous_reading' => $previousReading,
                            'current_reading' => $currentReading,
                            'invoice_status' => $invoice->status,
                            'message' => 'Zero water consumption for this month. Water charge set to 0.',
                        ];
                        
                        continue;
                    }
                    
                    // Check if the charge matches OR if description needs updating
                    $needsUpdate = false;
                    $oldAmount = $waterItem->amount;
                    $currentDescription = $waterItem->description ?? '';
                    
                    // Check if amount differs
                    if (abs($waterItem->amount - $calculatedCharge) > 0.01) {
                        $needsUpdate = true;
                    }
                    
                    // Check if description is missing Previous/Current readings
                    if (strpos($currentDescription, 'Previous:') === false) {
                        $needsUpdate = true;
                    }
                    
                    if ($needsUpdate) {
                        $waterItem->update([
                            'amount' => $calculatedCharge,
                            'water_units_used' => $consumption,
                            'description' => $description,
                        ]);
                        
                        // Recalculate invoice total and status
                        $this->recalculateInvoice($invoice);
                        
                        $results[] = [
                            'invoice_id' => $invoice->id,
                            'unit_number' => $unit->unit_number,
                            'status' => 'updated',
                            'old_charge' => $oldAmount,
                            'new_charge' => $calculatedCharge,
                            'consumption' => $consumption,
                            'previous_reading' => $previousReading,
                            'current_reading' => $currentReading,
                            'rate' => $rate,
                            'reading_date' => $reading->reading_date->format('Y-m-d'),
                            'billing_month' => $billingMonth->format('Y-m'),
                            'invoice_status' => $invoice->status,
                            'description' => $description,
                            'overpaid' => $invoice->total_paid > $invoice->total_amount ? ($invoice->total_paid - $invoice->total_amount) : 0,
                            'underpaid' => $invoice->total_amount > $invoice->total_paid ? ($invoice->total_amount - $invoice->total_paid) : 0,
                        ];
                        $updated++;
                    } else {
                        $results[] = [
                            'invoice_id' => $invoice->id,
                            'unit_number' => $unit->unit_number,
                            'status' => 'already_correct',
                            'charge' => $waterItem->amount,
                            'consumption' => $consumption,
                            'previous_reading' => $previousReading,
                            'current_reading' => $currentReading,
                            'reading_date' => $reading->reading_date->format('Y-m-d'),
                            'billing_month' => $billingMonth->format('Y-m'),
                            'invoice_status' => $invoice->status,
                            'description' => $waterItem->description,
                        ];
                        $alreadyCorrect++;
                    }
                } else {
                    // No reading found
                    $results[] = [
                        'invoice_id' => $invoice->id,
                        'unit_number' => $unit->unit_number,
                        'status' => 'no_reading',
                        'invoice_status' => $invoice->status,
                        'message' => "No water reading found for {$previousMonth->format('F Y')} (month before billing month {$billingMonth->format('F Y')})",
                        'charge' => $waterItem->amount,
                    ];
                    $noReading++;
                }
            }
            
            DB::commit();
            
            $message = "Reconciled {$invoices->count()} invoices: {$updated} updated, {$alreadyCorrect} correct, {$zeroConsumption} zero consumption, {$noReading} missing readings";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'results' => $results,
                'summary' => [
                    'total' => $invoices->count(),
                    'updated' => $updated,
                    'correct' => $alreadyCorrect,
                    'zero_consumption' => $zeroConsumption,
                    'no_reading' => $noReading,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk water reconciliation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error reconciling water charges: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate final water reading for move-out tenancy
     */
    public function generateMoveOutWaterReading(Tenancy $tenancy)
    {
        $unit = $tenancy->unit;
        
        $lastReading = WaterReading::where('unit_id', $unit->id)
            ->orderBy('reading_date', 'desc')
            ->first();
        
        $reading = WaterReading::create([
            'unit_id' => $unit->id,
            'previous_reading' => $lastReading ? $lastReading->current_reading : ($unit->current_water_reading ?? 0),
            'current_reading' => $unit->current_water_reading ?? 0,
            'consumption' => $unit->calculateWaterConsumption(),
            'rate_applied' => $unit->custom_water_rate ?? $unit->estate->water_rate ?? 50,
            'charge' => $unit->calculateWaterCharge(),
            'billing_type' => $unit->water_billing_type ?? 'consumption',
            'reading_date' => $tenancy->move_out_date,
            'recorded_by' => auth()->id(),
            'notes' => 'Move-out reading for tenancy #' . $tenancy->id,
        ]);
        
        return $reading;
    }

    /**
     * Handle water reading when a tenancy ends
     */
    public function handleTenancyEnd(Request $request, Tenancy $tenancy)
    {
        try {
            $validated = $request->validate([
                'final_reading' => 'required|numeric|min:0',
                'move_out_date' => 'required|date',
                'charge_to' => 'required|in:tenant,management',
            ]);
            
            $unit = $tenancy->unit;
            
            $reading = $this->generateMoveOutWaterReading($tenancy);
            
            if ($validated['charge_to'] === 'management') {
                $invoice = Invoice::create([
                    'tenancy_id' => null,
                    'invoice_type' => 'move_out',
                    'billing_month' => $tenancy->move_out_date,
                    'total_amount' => $reading->charge,
                    'status' => 'unpaid',
                    'notes' => "Water charge from moved-out tenancy #{$tenancy->id}",
                ]);
                
                $invoice->items()->create([
                    'item_type' => 'water',
                    'description' => sprintf(
                        "Water reading at move-out (Previous: %.2f m³, Current: %.2f m³, Consumption: %.2f m³)",
                        $reading->previous_reading,
                        $reading->current_reading,
                        $reading->consumption
                    ),
                    'amount' => $reading->charge,
                    'water_units_used' => $reading->consumption,
                ]);
                
                $message = "Water charge of KES {$reading->charge} has been assigned to management";
            } else {
                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => 'move_out',
                    'billing_month' => $tenancy->move_out_date,
                    'total_amount' => $reading->charge,
                    'status' => 'unpaid',
                    'notes' => "Final water reading at move-out",
                ]);
                
                $invoice->items()->create([
                    'item_type' => 'water',
                    'description' => sprintf(
                        "Final water reading (Previous: %.2f m³, Current: %.2f m³, Consumption: %.2f m³)",
                        $reading->previous_reading,
                        $reading->current_reading,
                        $reading->consumption
                    ),
                    'amount' => $reading->charge,
                    'water_units_used' => $reading->consumption,
                ]);
                
                $message = "Final water invoice of KES {$reading->charge} has been generated for tenant";
            }
            
            $this->updateUnitWithLatestReadings($unit);
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'reading' => $reading,
                'invoice' => $invoice,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Tenancy end water handling error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error handling tenancy end: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update unit with latest readings
     */
    private function updateUnitWithLatestReadings($unit)
    {
        $lastTwoReadings = WaterReading::where('unit_id', $unit->id)
            ->orderBy('reading_date', 'desc')
            ->take(2)
            ->get();
        
        if ($lastTwoReadings->count() >= 2) {
            $mostRecent = $lastTwoReadings[0];
            $secondMostRecent = $lastTwoReadings[1];
            
            $unit->update([
                'previous_water_reading' => $secondMostRecent->current_reading,
                'current_water_reading' => $mostRecent->current_reading,
                'last_reading_date' => $mostRecent->reading_date
            ]);
        } elseif ($lastTwoReadings->count() == 1) {
            $mostRecent = $lastTwoReadings[0];
            
            $unit->update([
                'previous_water_reading' => 0,
                'current_water_reading' => $mostRecent->current_reading,
                'last_reading_date' => $mostRecent->reading_date
            ]);
        }
    }

    /**
     * Update invoice payment status based on payments
     */
    public function updatePaymentStatus(Invoice $invoice)
    {
        $totalPaid = (float) $invoice->payments()->sum('amount');
        $totalAmount = (float) $invoice->total_amount;
        
        if ($totalPaid >= $totalAmount && $totalAmount > 0) {
            $invoice->status = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $totalAmount) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'unpaid';
        }
        
        $invoice->save();
        
        return $invoice;
    }

    /**
     * Reconcile a payment - updates external_reference from transaction meta
     */
    public function reconcilePayment(Request $request, Payment $payment)
    {
        try {
            $validated = $request->validate([
                'external_reference' => 'nullable|string|max:255',
                'transaction_reference' => 'nullable|string|max:255',
                'is_reconciled' => 'sometimes|boolean',
            ]);
            
            $updated = false;
            
            // If external_reference is provided, update it
            if (isset($validated['external_reference']) && $validated['external_reference']) {
                $payment->external_reference = $validated['external_reference'];
                $updated = true;
            }
            
            // If transaction_reference is provided, update it
            if (isset($validated['transaction_reference']) && $validated['transaction_reference']) {
                $payment->transaction_reference = $validated['transaction_reference'];
                $updated = true;
            }
            
            // If is_reconciled is provided, update it
            if (isset($validated['is_reconciled'])) {
                $payment->is_reconciled = (bool) $validated['is_reconciled'];
                if ($payment->is_reconciled) {
                    $payment->reconciled_at = now();
                    $payment->reconciled_by = auth()->id();
                }
                $updated = true;
            }
            
            if ($updated) {
                $payment->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment reconciled successfully',
                    'payment' => $payment,
                    'external_reference' => $payment->external_reference,
                    'transaction_reference' => $payment->transaction_reference,
                    'is_reconciled' => $payment->is_reconciled,
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No changes provided to update',
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Reconcile payment error: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error reconciling payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-reconcile a payment by fetching from transaction meta
     */
    public function autoReconcilePayment(Request $request, Payment $payment)
    {
        try {
            // If payment has a transaction_reference, try to get the external reference from the transaction
            if ($payment->transaction_reference) {
                $transaction = \Bavix\Wallet\Models\Transaction::where('uuid', $payment->transaction_reference)->first();
                
                if ($transaction) {
                    $meta = $transaction->meta ?? [];
                    $externalRef = null;
                    
                    // Try different sources for external reference
                    if (isset($meta['reference'])) {
                        $externalRef = $meta['reference'];
                    } elseif (isset($meta['external_reference'])) {
                        $externalRef = $meta['external_reference'];
                    } elseif (isset($meta['parsed_data']['transaction_id'])) {
                        $externalRef = $meta['parsed_data']['transaction_id'];
                    } elseif (isset($meta['transaction_id'])) {
                        $externalRef = $meta['transaction_id'];
                    }
                    
                    if ($externalRef) {
                        $payment->external_reference = $externalRef;
                        $payment->is_reconciled = true;
                        $payment->reconciled_at = now();
                        $payment->reconciled_by = auth()->id();
                        
                        // Also update meta with reconciliation info
                        $paymentMeta = $payment->meta ?? [];
                        $paymentMeta['auto_reconciled_at'] = now()->toISOString();
                        $paymentMeta['auto_reconciled_by'] = auth()->id();
                        $paymentMeta['auto_reconciled_from_transaction'] = $transaction->uuid;
                        $payment->meta = $paymentMeta;
                        
                        $payment->save();
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'Payment auto-reconciled successfully',
                            'payment' => $payment,
                            'external_reference' => $payment->external_reference,
                        ]);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'No external reference found in transaction meta',
                            'meta' => $meta,
                        ], 404);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaction not found for this payment',
                        'transaction_reference' => $payment->transaction_reference,
                    ], 404);
                }
            }
            
            // If no transaction_reference, try to find by matching the external_reference pattern
            // This handles cases where the payment was created before the reference was linked
            $paymentsWithoutRef = Payment::whereNull('external_reference')
                ->whereNotNull('transaction_reference')
                ->get();
            
            $reconciledCount = 0;
            foreach ($paymentsWithoutRef as $p) {
                $transaction = \Bavix\Wallet\Models\Transaction::where('uuid', $p->transaction_reference)->first();
                if ($transaction) {
                    $meta = $transaction->meta ?? [];
                    $externalRef = $meta['reference'] ?? $meta['parsed_data']['transaction_id'] ?? null;
                    if ($externalRef) {
                        $p->external_reference = $externalRef;
                        $p->is_reconciled = true;
                        $p->reconciled_at = now();
                        $p->reconciled_by = auth()->id();
                        $p->save();
                        $reconciledCount++;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Reconciled {$reconciledCount} payments",
                'reconciled_count' => $reconciledCount,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Auto-reconcile payment error: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error auto-reconciling payment: ' . $e->getMessage()
            ], 500);
        }
    }
}