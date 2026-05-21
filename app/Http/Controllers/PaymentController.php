<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{

    public function index()
    {
        // Load payments with related invoice items and tenant user
        $payments = Payment::with([
            'tenancy.tenant.user',
            'invoice.items',
            'transaction',
            'invoiceItems'
        ])->orderBy('created_at', 'desc')->get();

        // Map payments to structured data
        $paymentsData = $payments->map(function ($payment) {
            // Get payer name from payment, transaction, tenancy->tenant->user, or fallback
            $payerName = $payment->payer_name
                ?? optional($payment->transaction)->parsed_payer_name
                ?? optional(optional(optional($payment->tenancy)->tenant)->user)->name
                ?? 'N/A';

            $invoice = $payment->invoice;
            
            // Get reference number from payment or transaction
            $referenceNumber = $payment->reference_number 
                ?? optional($payment->transaction)->parsed_reference_number 
                ?? 'N/A';

            // Get paid items breakdown
            $paidItems = $payment->invoiceItems->map(function ($item) {
                return [
                    'type' => $item->item_type,
                    'description' => $item->description,
                    'amount' => (float) $item->paid_amount,
                ];
            });

            // Build items label
            $itemsLabel = '-';
            if ($invoice && $invoice->items->count()) {
                $itemsLabel = $invoice->items
                    ->map(fn ($item) =>
                        ($item->item_type ?? 'Item') .
                        ($item->description ? ' (' . $item->description . ')' : '')
                    )
                    ->implode(', ');
            }

            // Structured items array for frontend use
            $items = $invoice?->items?->map(function ($item) {
                return [
                    'type' => $item->item_type ?? 'Item',
                    'description' => $item->description ?? '-',
                    'amount' => $item->amount,
                ];
            }) ?? [];

            return [
                'id' => $payment->id,
                'tenancy_id' => $payment->tenancy_id,
                'invoice_id' => $payment->invoice_id,
                'payer_name' => $payerName,
                'invoice_label' => $invoice
                    ? $payerName . ' - Invoice #' . ($invoice->invoice_number ?? $invoice->id) . ': ' . $itemsLabel
                    : '-',
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'reference_number' => $referenceNumber,
                'transaction_message' => $payment->transaction_message,
                'paid_to' => $payment->paid_to,
                'payment_datetime' => optional($payment->payment_datetime)->toISOString(),
                'payment_month' => $payment->payment_month,
                'status' => $payment->status ?? 'pending',
                'created_at' => optional($payment->created_at)->toISOString(),
                'updated_at' => optional($payment->updated_at)->toISOString(),
                'items' => $items,
                'paid_items_breakdown' => $paidItems,
            ];
        });

        // Users for dropdown
        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });

        // Invoices with items for Add Payment dropdown
        $invoices = Invoice::with('items', 'tenancy.tenant.user')->get()->map(function ($invoice) {
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
                'total_amount' => (float) $invoice->total_amount,
                'outstanding_balance' => (float) ($invoice->total_amount - ($invoice->payments->sum('amount') ?? 0)),
                'tenant_name' => $payerName,
                'unit_number' => optional(optional($invoice->tenancy)->unit)->unit_number ?? 'N/A',
                'estate_name' => optional(optional(optional($invoice->tenancy)->property)->estate)->name ?? 'N/A',
                'billing_month' => $invoice->billing_month ?? date('F Y'),
            ];
        });

        // Check if current user is a tenant (role_id = 9)
        $isTenant = Auth::user()->role_id == 9;

        // Return to view
        return view('payments.index', compact(
            'paymentsData',
            'users',
            'invoices',
            'isTenant'
        ));
    }

    public function create()
    {
        return redirect()->route('payments.index');
    }

    public function edit(Payment $payment)
    {
        $payment->load([
            'tenancy.tenant.user',
            'invoice.items',
            'transaction',
            'invoiceItems'
        ]);

        $payerName = $payment->payer_name 
            ?? optional($payment->transaction)->parsed_payer_name
            ?? optional(optional(optional($payment->tenancy)->tenant)->user)->name 
            ?? 'N/A';

        $referenceNumber = $payment->reference_number
            ?? optional($payment->transaction)->parsed_reference_number
            ?? 'N/A';

        $paymentData = [
            'id' => $payment->id,
            'tenancy_id' => $payment->tenancy_id,
            'invoice_id' => $payment->invoice_id,
            'payer_name' => $payerName,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference_number' => $referenceNumber,
            'transaction_message' => $payment->transaction_message,
            'paid_to' => $payment->paid_to,
            'payment_datetime' => $payment->payment_datetime ? $payment->payment_datetime->format('Y-m-d\TH:i') : null,
            'payment_month' => $payment->payment_month,
            'status' => $payment->status ?? 'pending',
        ];

        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });

        $invoices = Invoice::with('items', 'tenancy.tenant.user')->get()->map(function ($invoice) {
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
                'total_amount' => (float) $invoice->total_amount,
            ];
        });

        if (request()->wantsJson()) {
            return response()->json([
                'payment' => $paymentData,
                'users' => $users,
                'invoices' => $invoices,
            ]);
        }

        return view('payments.edit', compact('payment', 'paymentData', 'users', 'invoices'));
    }

    public function show(Payment $payment)
    {
        $payment->load([
            'tenancy.tenant.user',
            'invoice.items',
            'invoice.tenancy.tenant.user',
            'transaction',
            'invoiceItems'
        ]);

        $payerName = $payment->payer_name 
            ?? optional($payment->transaction)->parsed_payer_name
            ?? optional(optional(optional($payment->tenancy)->tenant)->user)->name 
            ?? 'N/A';

        $referenceNumber = $payment->reference_number
            ?? optional($payment->transaction)->parsed_reference_number
            ?? 'N/A';

        // Get detailed item breakdown
        $itemBreakdown = $payment->invoiceItems->map(function ($item) {
            return [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'total_amount' => (float) $item->amount,
                'paid_from_this_payment' => (float) $item->paid_amount,
                'still_owing' => (float) ($item->amount - $item->paid_amount),
                'invoice_item_id' => $item->id,
            ];
        });

        $paymentData = [
            'id' => $payment->id,
            'tenancy_id' => $payment->tenancy_id,
            'invoice_id' => $payment->invoice_id,
            'payer_name' => $payerName,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference_number' => $referenceNumber,
            'transaction_message' => $payment->transaction_message,
            'paid_to' => $payment->paid_to,
            'payment_datetime' => $payment->payment_datetime,
            'payment_month' => $payment->payment_month,
            'status' => $payment->status ?? 'pending',
            'verification_notes' => $payment->verification_notes,
            'verified_by' => $payment->verified_by,
            'verified_at' => $payment->verified_at,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'item_breakdown' => $itemBreakdown,
            'tenancy' => $payment->tenancy ? [
                'id' => $payment->tenancy->id,
                'tenant' => $payment->tenancy->tenant ? [
                    'id' => $payment->tenancy->tenant->id,
                    'user' => $payment->tenancy->tenant->user ? [
                        'id' => $payment->tenancy->tenant->user->id,
                        'name' => $payment->tenancy->tenant->user->name,
                    ] : null,
                ] : null,
            ] : null,
            'invoice' => $payment->invoice ? [
                'id' => $payment->invoice->id,
                'invoice_number' => $payment->invoice->invoice_number,
                'total_amount' => $payment->invoice->total_amount,
                'status' => $payment->invoice->status,
                'items' => $payment->invoice->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'amount' => $item->amount,
                    ];
                }),
            ] : null,
        ];

        if (request()->wantsJson()) {
            return response()->json([
                'payment' => $paymentData,
            ]);
        }

        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });

        $invoices = Invoice::with('items', 'tenancy.tenant.user')->get()->map(function ($invoice) {
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
                'total_amount' => (float) $invoice->total_amount,
            ];
        });

        return view('payments.show', compact('payment', 'paymentData', 'users', 'invoices'));
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'tenancy_id' => 'required|exists:tenancies,id',
        'invoice_id' => 'nullable|exists:invoices,id',
        'amount' => 'required|numeric|min:0.01',
        'payment_method' => 'required|in:mpesa,bank,cash',
        'reference_number' => 'nullable|string|max:255',
        'transaction_message' => 'required|string',
        'paid_to' => 'nullable|string|max:255',
        'payer_name' => 'nullable|string|max:255',
        'payment_datetime' => 'required|date',
        'payment_month' => 'required|string|max:255',
    ]);

    $userRoleId = Auth::user()->role_id ?? 0;
    $isTenant = ($userRoleId == 9);
    
    $status = $isTenant ? 'pending' : 'verified';
    
    DB::beginTransaction();
    
    try {
        // Step 1: Create the transaction record
        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'tenancy_id' => $validated['tenancy_id'],
            'raw_message' => $validated['transaction_message'],
            'parsed_amount' => $validated['amount'],
            'parsed_reference_number' => $validated['reference_number'] ?? null,
            'parsed_payment_method' => $validated['payment_method'],
            'parsed_payment_datetime' => $validated['payment_datetime'],
            'parsed_payer_name' => $validated['payer_name'],
            'parsed_paid_to' => $validated['paid_to'],
            'parsed_payment_month' => $validated['payment_month'],
            'status' => $status,
            'remaining_amount' => $validated['amount'],
        ]);

        // Step 2: Create payment record linked to transaction
        $payment = Payment::create([
            'tenancy_id' => $validated['tenancy_id'],
            'invoice_id' => $validated['invoice_id'] ?? null,
            'transaction_id' => $transaction->id,
            'reference_number' => $validated['reference_number'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'transaction_message' => $validated['transaction_message'],
            'paid_to' => $validated['paid_to'],
            'payer_name' => $validated['payer_name'],
            'payment_datetime' => $validated['payment_datetime'],
            'payment_month' => $validated['payment_month'],
            'status' => $status,
        ]);

        // Step 3: If verified (admin/accountant), allocate to invoice items
        if ($status === 'verified') {
            $this->allocatePaymentToItems($payment, $validated['invoice_id'] ?? null);
            // Update transaction as allocated (or partial if remaining)
            $remainingAfterAllocation = $payment->amount - $payment->invoiceItems()->sum('paid_amount');
            $transaction->update([
                'status' => $remainingAfterAllocation > 0 ? 'partial' : 'allocated',
                'remaining_amount' => $remainingAfterAllocation
            ]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => $isTenant 
                ? 'Payment submitted for verification! An accountant will review it within 24 hours.'
                : 'Payment recorded and allocated successfully!',
            'payment' => $payment->load('tenancy.tenant.user', 'invoice.items', 'transaction', 'invoiceItems'),
            'requires_verification' => $isTenant,
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Payment creation failed: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'validated' => $validated
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to create payment: ' . $e->getMessage(),
        ], 500);
    }
}
    
    /**
     * Verify a pending payment (Accountant action)
     * This also updates the associated transaction
     */
    public function verify(Request $request, Payment $payment)
    {
        $request->validate([
            'status' => 'required|in:verified,rejected',
            'verification_notes' => 'nullable|string',
            'adjust_amount' => 'nullable|numeric|min:0',
            'invoice_id' => 'nullable|exists:invoices,id',
        ]);
        
        DB::beginTransaction();
        
        try {
            // If an invoice was selected during verification, update the payment
            if ($request->has('invoice_id') && $request->invoice_id) {
                $payment->invoice_id = $request->invoice_id;
            }
            
            // Update payment
            $payment->status = $request->status;
            $payment->verified_by = Auth::id();
            $payment->verified_at = now();
            $payment->verification_notes = $request->verification_notes;
            $payment->save();
            
            // Update associated transaction
            $transaction = $payment->transaction;
            if ($transaction) {
                $transaction->status = $request->status === 'verified' ? 'verified' : 'rejected';
                $transaction->verified_by = Auth::id();
                $transaction->verified_at = now();
                $transaction->verification_notes = $request->verification_notes;
                
                if ($request->has('adjust_amount') && $request->adjust_amount > 0) {
                    $transaction->parsed_amount = $request->adjust_amount;
                    $transaction->remaining_amount = $request->adjust_amount;
                }
                $transaction->save();
            }
            
            // If verified, allocate to invoice items
            if ($payment->status === 'verified') {
                $this->allocatePaymentToItems($payment, $payment->invoice_id);
                if ($transaction) {
                    $transaction->update(['status' => 'allocated', 'remaining_amount' => 0]);
                }
            }
            
            DB::commit();
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment ' . $request->status . ' successfully!',
                    'payment' => $payment->load('transaction', 'invoiceItems', 'invoice'),
                ]);
            }
            
            return redirect()->route('payments.index')->with('success', 'Payment ' . $request->status . ' successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment: ' . $e->getMessage(),
                ], 500);
            }
            
            return back()->with('error', 'Failed to verify payment: ' . $e->getMessage());
        }
    }
    
    /**
     * Allocate payment to invoice ITEMS (granular level)
     * This is the key method that enables tracking payments per line item
     * 
     * @param Payment $payment
     * @param int|null $specificInvoiceId - Optional specific invoice ID to allocate to
     */



    protected function allocatePaymentToItems(Payment $payment, $specificInvoiceId = null)
    {
        $remainingAmount = $payment->amount;
        
        // Use specific invoice_id if provided, otherwise use payment's invoice_id
        $invoiceId = $specificInvoiceId ?? $payment->invoice_id;
        
        if ($invoiceId) {
            // Single invoice allocation - allocate to its items
            $invoice = Invoice::with('items')->find($invoiceId);
            if ($invoice) {
                \Log::info('Allocating to specific invoice', [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $payment->id,
                    'amount' => $remainingAmount
                ]);
                
                // Get unpaid items for this invoice, ordered by priority
                $items = InvoiceItem::where('invoice_id', $invoice->id)
                    ->whereRaw('COALESCE(paid_amount, 0) < amount')
                    ->orderByRaw("FIELD(item_type, 'rent', 'service', 'power', 'water', 'internet', 'security', 'garbage', 'other')")
                    ->get();
                
                foreach ($items as $item) {
                    if ($remainingAmount <= 0) break;
                    
                    $itemOutstanding = $item->amount - ($item->paid_amount ?? 0);
                    if ($itemOutstanding <= 0) continue;
                    
                    $toPayOnItem = min($remainingAmount, $itemOutstanding);
                    
                    // UPDATE the existing invoice item - NOT create new one
                    $item->paid_amount = ($item->paid_amount ?? 0) + $toPayOnItem;
                    // Store the MOST RECENT payment_id (for reference, but accumulated paid_amount tracks total)
                    $item->payment_id = $payment->id;
                    
                    // Use update() instead of save() to be explicit
                    $item->update([
                        'paid_amount' => $item->paid_amount,
                        'payment_id' => $payment->id
                    ]);
                    
                    \Log::info('Allocated to item', [
                        'item_id' => $item->id,
                        'item_type' => $item->item_type,
                        'amount' => $toPayOnItem,
                        'new_paid_amount' => $item->paid_amount,
                        'total_outstanding_after' => $item->amount - $item->paid_amount
                    ]);
                    
                    $remainingAmount -= $toPayOnItem;
                }
                
                // Update invoice totals from items
                $this->updateInvoiceTotalsFromItems($invoice);
            } else {
                \Log::warning('Invoice not found for allocation', ['invoice_id' => $invoiceId]);
            }
        } else {
            // Auto-allocate to oldest unpaid invoices and their items
            \Log::info('Auto-allocating to oldest invoices', [
                'tenancy_id' => $payment->tenancy_id,
                'payment_id' => $payment->id,
                'amount' => $remainingAmount
            ]);
            
            $invoices = Invoice::where('tenancy_id', $payment->tenancy_id)
                ->where('status', '!=', 'paid')
                ->orderBy('created_at', 'asc')
                ->get();
            
            foreach ($invoices as $invoice) {
                if ($remainingAmount <= 0) break;
                
                // Get unpaid items for this invoice
                $items = InvoiceItem::where('invoice_id', $invoice->id)
                    ->whereRaw('COALESCE(paid_amount, 0) < amount')
                    ->orderByRaw("FIELD(item_type, 'rent', 'service', 'power', 'water', 'internet', 'security', 'garbage', 'other')")
                    ->get();
                
                foreach ($items as $item) {
                    if ($remainingAmount <= 0) break;
                    
                    $itemOutstanding = $item->amount - ($item->paid_amount ?? 0);
                    if ($itemOutstanding <= 0) continue;
                    
                    $toPayOnItem = min($remainingAmount, $itemOutstanding);
                    
                    // UPDATE the existing invoice item
                    $item->paid_amount = ($item->paid_amount ?? 0) + $toPayOnItem;
                    $item->payment_id = $payment->id;
                    
                    $item->update([
                        'paid_amount' => $item->paid_amount,
                        'payment_id' => $payment->id
                    ]);
                    
                    \Log::info('Auto-allocated to item', [
                        'invoice_id' => $invoice->id,
                        'item_id' => $item->id,
                        'item_type' => $item->item_type,
                        'amount' => $toPayOnItem,
                        'new_paid_amount' => $item->paid_amount
                    ]);
                    
                    $remainingAmount -= $toPayOnItem;
                }
                
                // Update invoice totals from items
                $this->updateInvoiceTotalsFromItems($invoice);
            }
        }
        
        // Update the payment record to reflect the invoice_id if it was NULL and we allocated
        if (!$payment->invoice_id && $invoiceId) {
            $payment->invoice_id = $invoiceId;
            $payment->save();
            \Log::info('Updated payment with invoice_id', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoiceId
            ]);
        }
        
        // Update the associated transaction with remaining amount
        if ($payment->transaction) {
            $payment->transaction->update([
                'remaining_amount' => $remainingAmount,
                'status' => $remainingAmount > 0 ? 'partial' : 'allocated'
            ]);
        }
        
        // If there's remaining amount (overpayment), log it for credit handling
        if ($remainingAmount > 0) {
            \Log::info('Overpayment detected - will be available for future allocation', [
                'payment_id' => $payment->id,
                'tenancy_id' => $payment->tenancy_id,
                'remaining_amount' => $remainingAmount,
                'original_amount' => $payment->amount,
                'allocated_amount' => $payment->amount - $remainingAmount
            ]);
            
            // OPTIONAL: Create a credit record or just leave on transaction.remaining_amount
        }
        
        return $payment;
    }

    protected function updateInvoiceTotalsFromItems(Invoice $invoice)
    {
        // Refresh the invoice to get latest items with their paid_amount
        $invoice->refresh();
        
        $totalPaid = $invoice->items()->sum('paid_amount');
        $invoice->total_paid = $totalPaid;
        
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->status = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $invoice->total_amount) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'unpaid';
        }
        
        $invoice->save();
        
        \Log::info('Invoice totals updated', [
            'invoice_id' => $invoice->id,
            'total_amount' => $invoice->total_amount,
            'total_paid' => $totalPaid,
            'status' => $invoice->status
        ]);
        
        return $invoice;
    }

    
    /**
     * Update a payment - handles both payment and transaction updates
     */
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'tenancy_id' => 'required|exists:tenancies,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:mpesa,bank,cash',
            'reference_number' => 'nullable|string|max:255',
            'transaction_message' => 'required|string',
            'paid_to' => 'nullable|string|max:255',
            'payer_name' => 'nullable|string|max:255',
            'payment_datetime' => 'required|date',
            'payment_month' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        
        try {
            $oldInvoiceId = $payment->invoice_id;
            
            // Update payment
            $payment->update($validated);
            
            // Update associated transaction
            $transaction = $payment->transaction;
            if ($transaction) {
                $transaction->update([
                    'parsed_amount' => $validated['amount'],
                    'parsed_reference_number' => $validated['reference_number'] ?? null,
                    'parsed_payment_method' => $validated['payment_method'],
                    'parsed_payment_datetime' => $validated['payment_datetime'],
                    'parsed_payer_name' => $validated['payer_name'],
                    'parsed_paid_to' => $validated['paid_to'],
                    'parsed_payment_month' => $validated['payment_month'],
                    'remaining_amount' => $validated['amount'],
                ]);
            }
            
            // Recalculate invoice statuses for old and new invoices
            if ($oldInvoiceId && $oldInvoiceId != $payment->invoice_id) {
                $oldInvoice = Invoice::with('items')->find($oldInvoiceId);
                if ($oldInvoice) {
                    $this->updateInvoiceTotalsFromItems($oldInvoice);
                }
            }
            
            if ($payment->invoice_id) {
                $invoice = Invoice::with('items')->find($payment->invoice_id);
                if ($invoice) {
                    $this->updateInvoiceTotalsFromItems($invoice);
                }
            }
            
            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment updated successfully!',
                    'payment' => $payment->load('tenancy.tenant.user', 'invoice.items', 'transaction', 'invoiceItems'),
                ]);
            }

            return redirect()->route('payments.index')->with('success', 'Payment updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update payment: ' . $e->getMessage(),
                ], 500);
            }
            
            return back()->with('error', 'Failed to update payment: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete a payment - also handles associated transaction and item allocations
     */
    public function destroy(Payment $payment)
    {
        $invoiceId = $payment->invoice_id;
        $transactionId = $payment->transaction_id;
        
        DB::beginTransaction();
        
        try {
            // First, remove this payment's allocation from invoice items
            InvoiceItem::where('payment_id', $payment->id)->update(['payment_id' => null, 'paid_amount' => 0]);
            
            // Delete the payment
            $payment->delete();
            
            // Check if transaction has other payments (for partial allocations)
            $otherPayments = Payment::where('transaction_id', $transactionId)
                ->where('id', '!=', $payment->id)
                ->count();
            
            // If no other payments, delete or mark the transaction
            if ($otherPayments === 0 && $transactionId) {
                $transaction = Transaction::find($transactionId);
                if ($transaction) {
                    $transaction->delete();
                }
            }
            
            // Recalculate invoice totals
            if ($invoiceId) {
                $invoice = Invoice::with('items')->find($invoiceId);
                if ($invoice) {
                    $this->updateInvoiceTotalsFromItems($invoice);
                }
            }
            
            DB::commit();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment deleted successfully!',
                ]);
            }

            return redirect()->route('payments.index')->with('success', 'Payment deleted successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete payment: ' . $e->getMessage(),
                ], 500);
            }
            
            return back()->with('error', 'Failed to delete payment: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to update invoice status based on payments (legacy, use updateInvoiceTotalsFromItems)
     */
    private function updateInvoiceStatus($invoice)
    {
        return $this->updateInvoiceTotalsFromItems($invoice);
    }

    /**
     * Get invoice details for the modal (AJAX endpoint)
     */
    public function getInvoiceDetails($invoiceId)
    {
        $invoice = Invoice::with(['items', 'tenancy.tenant.user', 'tenancy.unit', 'tenancy.property.estate'])
            ->findOrFail($invoiceId);
        
        $totalPaid = $invoice->items->sum('paid_amount');
        
        // Get detailed item breakdown for the modal
        $itemBreakdown = $invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => $item->item_type,
                'description' => $item->description,
                'amount' => (float) $item->amount,
                'paid' => (float) ($item->paid_amount ?? 0),
                'outstanding' => (float) ($item->amount - ($item->paid_amount ?? 0)),
            ];
        });
        
        return response()->json([
            'success' => true,
            'tenant_name' => optional(optional($invoice->tenancy)->tenant->user)->name ?? 'N/A',
            'unit_number' => optional(optional($invoice->tenancy)->unit)->unit_number ?? 'N/A',
            'estate_name' => optional(optional(optional($invoice->tenancy)->property)->estate)->name ?? 'N/A',
            'total_amount' => (float) $invoice->total_amount,
            'outstanding_balance' => (float) ($invoice->total_amount - $totalPaid),
            'billing_month_formatted' => $invoice->billing_month ?? date('F Y'),
            'invoice_number' => $invoice->invoice_number,
            'items' => $itemBreakdown,
        ]);
    }

    public function getCreateData()
    {
        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });
        
        $invoices = Invoice::with(['items', 'tenancy.tenant.user'])->get()->map(function ($invoice) {
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
                'total_amount' => (float) $invoice->total_amount,
            ];
        });
        
        return response()->json([
            'success' => true,
            'users' => $users,
            'invoices' => $invoices,
        ]);
    }
    
    /**
     * Get pending payments for verification (Accountant view)
     */
    public function getPendingPayments()
    {
        $pendingPayments = Payment::with(['tenancy.tenant.user', 'invoice', 'transaction', 'invoiceItems'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($payment) {
                $referenceNumber = $payment->reference_number
                    ?? optional($payment->transaction)->parsed_reference_number
                    ?? 'N/A';
                
                // Get items this payment would cover
                $proposedItems = $payment->invoiceItems->map(function ($item) {
                    return [
                        'type' => $item->item_type,
                        'description' => $item->description,
                        'amount' => (float) $item->paid_amount,
                    ];
                });
                    
                return [
                    'id' => $payment->id,
                    'tenancy_id' => $payment->tenancy_id,
                    'tenant_name' => optional(optional(optional($payment->tenancy)->tenant)->user)->name ?? 'N/A',
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $referenceNumber,
                    'transaction_message' => $payment->transaction_message,
                    'payment_datetime' => $payment->payment_datetime,
                    'payment_month' => $payment->payment_month,
                    'proposed_allocation' => $proposedItems,
                    'created_at' => $payment->created_at,
                ];
            });
        
        return response()->json([
            'success' => true,
            'pending_payments' => $pendingPayments,
            'count' => $pendingPayments->count(),
        ]);
    }
    
    /**
     * Get payments by transaction (for viewing multiple payments from one transaction)
     */
    public function getByTransaction($transactionId)
    {
        $payments = Payment::with(['invoice', 'invoiceItems'])
            ->where('transaction_id', $transactionId)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'invoice_number' => optional($payment->invoice)->invoice_number ?? 'N/A',
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'items_covered' => $payment->invoiceItems->count(),
                ];
            });
        
        return response()->json([
            'success' => true,
            'payments' => $payments,
            'total_amount' => $payments->sum('amount'),
        ]);
    }
    
    /**
     * Get detailed payment breakdown by invoice items
     */
    public function getPaymentBreakdown($paymentId)
    {
        $payment = Payment::with(['invoice', 'invoiceItems' => function($query) use ($paymentId) {
            $query->where('payment_id', $paymentId);
        }])->findOrFail($paymentId);
        
        $breakdown = [
            'payment_id' => $payment->id,
            'total_amount' => (float) $payment->amount,
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => optional($payment->invoice)->invoice_number,
            'payment_date' => $payment->payment_datetime,
            'reference_number' => $payment->reference_number,
            'payment_method' => $payment->payment_method,
            'items' => [],
        ];
        
        if ($payment->invoiceItems->count() > 0) {
            foreach ($payment->invoiceItems as $item) {
                $breakdown['items'][] = [
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'item_total' => (float) $item->amount,
                    'paid_from_this_payment' => (float) $item->paid_amount,
                    'still_owing_on_item' => (float) ($item->amount - $item->paid_amount),
                    'invoice_item_id' => $item->id,
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'breakdown' => $breakdown,
        ]);
    }
    
    /**
     * Get invoice items for a specific invoice (for allocation during verification)
     */
    public function getInvoiceItemsForAllocation($invoiceId)
    {
        $invoice = Invoice::with('items')->findOrFail($invoiceId);
        
        $items = $invoice->items->map(function ($item) {
            $outstanding = $item->amount - ($item->paid_amount ?? 0);
            return [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'total_amount' => (float) $item->amount,
                'paid_amount' => (float) ($item->paid_amount ?? 0),
                'outstanding' => (float) max(0, $outstanding),
                'is_fully_paid' => $outstanding <= 0,
            ];
        });
        
        return response()->json([
            'success' => true,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => (float) $invoice->total_amount,
            'total_paid' => (float) ($invoice->total_paid ?? 0),
            'items' => $items,
        ]);
    }
}