<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Attributes\Log;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('tenancy.tenant', 'tenancy.unit', 'items', 'payments')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Get all active tenancies for the dropdown
        $activeTenancies = Tenancy::where('status', 'active')
            ->with('tenant', 'unit')
            ->get();
        
        // Map invoices for JavaScript/JSON consumption
        $mappedInvoices = $invoices->map(function($invoice) {
            return [
                'id' => $invoice->id,
                'tenant_name' => $invoice->tenancy->tenant->user->name ?? '-',
                'tenant_id' => $invoice->tenancy->tenant_id ?? null,
                'unit_number' => $invoice->tenancy->unit->unit_number ?? '-',
                'unit_id' => $invoice->tenancy->unit_id ?? null,
                'invoice_type' => $invoice->invoice_type,
                'billing_month' => $invoice->billing_month,
                'billing_month_formatted' => $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'tenancy_id' => $invoice->tenancy_id,
                'created_at' => $invoice->created_at ? $invoice->created_at->getTimestamp() * 1000 : null,
                'created_at_formatted' => $invoice->created_at ? $invoice->created_at->format('M d, Y') : '-',
            ];
        });
        
        // Map active tenancies for the create modal
        $mappedActiveTenancies = $activeTenancies->map(function($tenancy) {
            return [
                'id' => $tenancy->id,
                'tenant_name' => $tenancy->tenant->name ?? 'Unknown',
                'unit_number' => $tenancy->unit->unit_number ?? 'No Unit',
                'rent_amount' => $tenancy->rent_amount ?? 0,
                'move_in_date' => $tenancy->move_in_date,
                'move_out_date' => $tenancy->move_out_date,
            ];
        });
        
        // Calculate statistics for the overview
        $totalInvoices = $invoices->count();
        $totalUnpaid = $invoices->where('status', 'unpaid')->sum('total_amount');
        $totalPaid = $invoices->where('status', 'paid')->sum('total_amount');
        $totalPartial = $invoices->where('status', 'partial')->sum('total_amount');
        $totalDraft = $invoices->where('status', 'draft')->sum('total_amount');
        
        // Calculate average time to get paid
        $averageDays = $this->calculateAveragePaymentTime($invoices);
        
        // Get tenancies that need move-in invoices generated
        $tenanciesNeedingMoveInInvoices = $activeTenancies->filter(function($tenancy) use ($invoices) {
            // Check if this tenancy already has a move_in invoice
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
            'averageDays'
        ));
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


    public function generateSingleInvoice(Request $request)
    {
        try {
            $request->validate([
                'tenancy_id' => 'required|exists:tenancies,id'
            ]);
            
            $tenancy = Tenancy::with('tenant.user', 'unit')->findOrFail($request->tenancy_id);
            
            // Check if invoice already exists for this month
            $currentMonth = Carbon::now()->format('Y-m');
            $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'monthly')
                ->whereYear('billing_month', Carbon::now()->year)
                ->whereMonth('billing_month', Carbon::now()->month)
                ->first();
                
            if ($existingInvoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice already exists for this tenancy and month'
                ], 400);
            }
            
            // Create invoice with proper date format
            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => Carbon::now()->startOfMonth(),
                'total_amount' => $tenancy->rent_amount ?? 0,
                'status' => 'unpaid',
            ]);
            
            // Add rent item
            if ($tenancy->rent_amount > 0) {
                $invoice->items()->create([
                    'description' => 'Monthly Rent',
                    'amount' => $tenancy->rent_amount,
                    'item_type' => 'rent',
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
            // Skip if no rent amount
            if (empty($tenancy->rent_amount) || $tenancy->rent_amount <= 0) {
                continue;
            }
            
            // Check if invoice already exists for this month
            $existingInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('invoice_type', 'monthly')
                ->whereYear('billing_month', Carbon::now()->year)
                ->whereMonth('billing_month', Carbon::now()->month)
                ->first();
                
            if ($existingInvoice) {
                $alreadyGenerated++;
                continue;
            }
            
            // Create invoice
            $invoice = Invoice::create([
                'tenancy_id' => $tenancy->id,
                'invoice_type' => 'monthly',
                'billing_month' => Carbon::now()->format('Y-m-01'),
                'total_amount' => $tenancy->rent_amount,
                'status' => 'unpaid',
            ]);
            
            // Add rent item
            $invoice->items()->create([
                'description' => 'Monthly Rent',
                'amount' => $tenancy->rent_amount,
                'item_type' => 'rent',
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
                // Get all active tenancies
                $tenancies = Tenancy::where('status', 'active')
                    ->with(['unit', 'tenant.user'])
                    ->get();
                
                foreach ($tenancies as $tenancy) {
                    try {
                        // Skip tenancies with no unit or tenant
                        if (!$tenancy->unit || !$tenancy->tenant) {
                            $skippedCount++;
                            $errors[] = "Skipped tenancy {$tenancy->id}: No unit or tenant assigned";
                            continue;
                        }
                        
                        // Check if invoice already exists
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
                        
                        // Create new invoice
                        $invoice = Invoice::create([
                            'tenancy_id' => $tenancy->id,
                            'invoice_type' => $validated['invoice_type'],
                            'billing_month' => $billingMonth,
                            'total_amount' => $validated['amount'],
                            'status' => 'unpaid',
                            'notes' => 'Created via bulk invoice generation',
                        ]);
                        
                        // Add invoice item
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
                // Single tenancy
                $tenancy = Tenancy::with(['unit', 'tenant.user'])->findOrFail($validated['tenancy_id']);
                
                // Check if invoice already exists
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
                
                // Create new invoice
                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => $validated['invoice_type'],
                    'billing_month' => $billingMonth,
                    'total_amount' => $validated['amount'],
                    'status' => 'unpaid',
                    'notes' => 'Created via bulk invoice',
                ]);
                
                // Add invoice item
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
            
            // Get tenancy details for the remaining ones
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

    private function generateDescription($data)
    {
        $serviceType = $data['service_type'];
        $specificService = $data['specific_service'] ?? null;
        
        switch ($serviceType) {
            case 'rent':
                return 'Monthly Rent';
            case 'utility':
                return $specificService ? ucfirst($specificService) . ' Charges' : 'Utility Charges';
            case 'service_charge':
                return $specificService ? ucfirst($specificService) . ' Service Charge' : 'Service Charge';
            default:
                return 'Additional Charges';
        }
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

            // Check if invoice already exists for this tenancy, item_type, and month
            if ($validated['invoice_type'] === 'monthly') {
                $existingInvoice = Invoice::where('tenancy_id', $validated['tenancy_id'])
                    ->where('invoice_type', 'monthly')
                    ->where('billing_month', $validated['billing_month'])
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

            // Create invoice
            $invoice = Invoice::create([
                'tenancy_id' => $validated['tenancy_id'],
                'invoice_type' => $validated['invoice_type'],
                'billing_month' => $validated['billing_month'],
                'total_amount' => $validated['amount'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Add invoice item for monthly invoices
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
        $invoice->load('tenancy.tenant', 'tenancy.unit', 'items', 'payments');
        return view('invoices.show', compact('invoice'));
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
        
        // Create payment
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'],
            'notes' => $validated['notes'],
        ]);

        // Update invoice status
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


    private function invoiceAlreadyExists($tenancyId, $invoiceType, $itemType, $billingMonth)
    {
        $query = Invoice::where('tenancy_id', $tenancyId)
            ->where('invoice_type', $invoiceType)
            ->whereDate('billing_month', $billingMonth);
        
        if ($invoiceType === 'monthly') {
            $query->where('item_type', $itemType);
        }
        
        return $query->exists();
    }
}