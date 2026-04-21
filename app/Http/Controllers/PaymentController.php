<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Invoice;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

    public function index()
    {
        // Load payments with related invoice items and tenant user
        $payments = Payment::with([
            'tenancy.tenant.user',
            'invoice.items',
        ])->get();

        // Map payments to structured data
        $paymentsData = $payments->map(function ($payment) {
            // Get payer name from payment, tenancy->tenant->user, or fallback
            $payerName = $payment->payer_name
                ?? optional(optional(optional($payment->tenancy)->tenant)->user)->name
                ?? 'N/A';

            $invoice = $payment->invoice;

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
                'transaction_id' => $payment->transaction_id,
                'transaction_message' => $payment->transaction_message,
                'paid_to' => $payment->paid_to,
                'payment_datetime' => optional($payment->payment_datetime)->toISOString(),
                'payment_month' => $payment->payment_month,
                'created_at' => optional($payment->created_at)->toISOString(),
                'updated_at' => optional($payment->updated_at)->toISOString(),
                'items' => $items,
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
            ];
        });

        // Return to view
        return view('payments.index', compact(
            'paymentsData',
            'users',
            'invoices'
        ));
    }

    public function create()
    {
        // This method can be used if you want a separate create page
        // But we're using modal, so redirect to index
        return redirect()->route('payments.index');
    }

    public function edit(Payment $payment)
    {
        // Load payment with relationships
        $payment->load([
            'tenancy.tenant.user',
            'invoice.items',
        ]);

        // Get payer name
        $payerName = $payment->payer_name 
            ?? optional(optional(optional($payment->tenancy)->tenant)->user)->name 
            ?? 'N/A';

        // Prepare payment data for the edit form
        $paymentData = [
            'id' => $payment->id,
            'tenancy_id' => $payment->tenancy_id,
            'invoice_id' => $payment->invoice_id,
            'payer_name' => $payerName,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'transaction_id' => $payment->transaction_id,
            'transaction_message' => $payment->transaction_message,
            'paid_to' => $payment->paid_to,
            'payment_datetime' => $payment->payment_datetime ? $payment->payment_datetime->format('Y-m-d\TH:i') : null,
            'payment_month' => $payment->payment_month,
        ];

        // Get users for dropdown
        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });

        // Get invoices for dropdown
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
            ];
        });

        // If it's an AJAX request, return JSON for the modal
        if (request()->wantsJson()) {
            return response()->json([
                'payment' => $paymentData,
                'users' => $users,
                'invoices' => $invoices,
            ]);
        }

        // Otherwise return the edit view
        return view('payments.edit', compact('payment', 'paymentData', 'users', 'invoices'));
    }

    public function show(Payment $payment)
    {
        // Load payment with all necessary relationships
        $payment->load([
            'tenancy.tenant.user', // Load tenancy -> tenant -> user
            'invoice.items', // Load invoice with items
            'invoice.tenancy.tenant.user', // Load invoice tenancy chain
        ]);

        // Get payer name with fallback logic
        $payerName = $payment->payer_name 
            ?? optional(optional(optional($payment->tenancy)->tenant)->user)->name 
            ?? 'N/A';

        // Prepare data for the view
        $paymentData = [
            'id' => $payment->id,
            'tenancy_id' => $payment->tenancy_id,
            'invoice_id' => $payment->invoice_id,
            'payer_name' => $payerName,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'transaction_id' => $payment->transaction_id,
            'transaction_message' => $payment->transaction_message,
            'paid_to' => $payment->paid_to,
            'payment_datetime' => $payment->payment_datetime,
            'payment_month' => $payment->payment_month,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
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

        // If it's an AJAX request, return JSON for the modal
        if (request()->wantsJson()) {
            return response()->json([
                'payment' => $paymentData,
            ]);
        }

        // Get users for dropdown
        $users = Tenant::with('user')->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => optional($tenant->user)->name ?? 'N/A',
            ];
        });

        // Get invoices for dropdown
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
            'transaction_id' => 'nullable|string|max:255',
            'transaction_message' => 'nullable|string',
            'paid_to' => 'nullable|string|max:255',
            'payer_name' => 'nullable|string|max:255',
            'payment_datetime' => 'required|date',
            'payment_month' => 'required|string|max:255',
        ]);

        // Create the payment
        $payment = Payment::create($validated);

        // If payment is linked to an invoice, update invoice status
        if ($payment->invoice_id) {
            $invoice = Invoice::with('payments')->find($payment->invoice_id);
            
            if ($invoice) {
                $this->updateInvoiceStatus($invoice);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully!',
                'payment' => $payment->load('tenancy.tenant.user', 'invoice.items'),
            ]);
        }

        return redirect()->route('payments.index')->with('success', 'Payment created successfully!');
    }
    
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'tenancy_id' => 'required|exists:tenancies,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:mpesa,bank,cash',
            'transaction_id' => 'nullable|string|max:255',
            'transaction_message' => 'nullable|string',
            'paid_to' => 'nullable|string|max:255',
            'payer_name' => 'nullable|string|max:255',
            'payment_datetime' => 'required|date',
            'payment_month' => 'required|string|max:255',
        ]);

        // Store old invoice_id before update (in case it's changing)
        $oldInvoiceId = $payment->invoice_id;
        
        // Update the payment
        $payment->update($validated);
        
        // Update status for old invoice (if changed)
        if ($oldInvoiceId && $oldInvoiceId != $payment->invoice_id) {
            $oldInvoice = Invoice::with('payments')->find($oldInvoiceId);
            if ($oldInvoice) {
                $this->updateInvoiceStatus($oldInvoice);
            }
        }
        
        // Update status for new/current invoice
        if ($payment->invoice_id) {
            $invoice = Invoice::with('payments')->find($payment->invoice_id);
            if ($invoice) {
                $this->updateInvoiceStatus($invoice);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully!',
                'payment' => $payment->load('tenancy.tenant.user', 'invoice.items'),
            ]);
        }

        return redirect()->route('payments.index')->with('success', 'Payment updated successfully!');
    }
    
    public function destroy(Payment $payment)
    {
        // Store invoice_id before deleting
        $invoiceId = $payment->invoice_id;
        
        // Delete the payment
        $payment->delete();
        
        // Update invoice status if payment was linked to an invoice
        if ($invoiceId) {
            $invoice = Invoice::with('payments')->find($invoiceId);
            if ($invoice) {
                $this->updateInvoiceStatus($invoice);
            }
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully!',
            ]);
        }

        return redirect()->route('payments.index')->with('success', 'Payment deleted successfully!');
    }

    // Helper method to update invoice status
    private function updateInvoiceStatus($invoice)
    {
        $totalPaid = $invoice->payments->sum('amount');
        
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->status = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $invoice->total_amount) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'unpaid';
        }
        
        $invoice->save();
        return $invoice;
    }

    public function getCreateData()
    {
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
            ];
        });
        
        return response()->json([
            'success' => true,
            'users' => $users,
            'invoices' => $invoices,
        ]);
    }
}