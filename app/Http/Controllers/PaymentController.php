<?php
// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PendingMpesaPayment;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Payments\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected $walletService;
    
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function index()
    {
        $user = Auth::user();
        $company = $user->company;
        
        // Get payments based on user role
        if ($user->hasRole('sysadmin')) {
            $payments = Payment::with(['tenant.user', 'invoice', 'invoice.items', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else if ($company) {
            // For company-specific users, filter payments by company through invoice->tenancy->unit
            $payments = Payment::whereHas('invoice.tenancy.unit', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->with(['tenant.user', 'invoice', 'invoice.items', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $payments = collect();
        }
        
        // Get tenants for the dropdown
        $tenants = [];
        if ($company) {
            $tenants = Tenant::whereHas('user', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->with('user', 'activeTenancy.unit')
                ->get()
                ->map(function($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->user->name ?? 'Unknown',
                        'unit_number' => $tenant->activeTenancy?->unit?->unit_number ?? 'No Unit',
                    ];
                });
        }
        
        // Get invoices for the dropdown
        $invoices = [];
        if ($company) {
            $invoices = Invoice::whereHas('tenancy.unit', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('tenancy.tenant.user')
                ->orderBy('billing_month', 'desc')
                ->get()
                ->map(function($invoice) {
                    $tenantName = $invoice->tenancy?->tenant?->user?->name ?? 'Unknown';
                    $invoiceNumber = $invoice->invoice_number ?? 'INV-' . $invoice->id;
                    $billingMonth = $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '';
                    return [
                        'id' => $invoice->id,
                        'label' => $tenantName . ' - ' . $invoiceNumber . ($billingMonth ? ' (' . $billingMonth . ')' : ''),
                        'total_amount' => (float) $invoice->total_amount,
                        'remaining_amount' => (float) $invoice->remaining_amount,
                    ];
                });
        }
        
        // Map payments to structured data for the frontend
        $paymentsData = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payer_name' => $payment->payer_name,
                'tenant_id' => $payment->tenant_id,
                'tenant_name' => $payment->tenant?->user?->name ?? 'N/A',
                'unit_number' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                'invoice_id' => $payment->invoice_id,
                'invoice_label' => $payment->invoice_label,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_method_label' => $payment->payment_method_label,
                'transaction_reference' => $payment->transaction_reference,
                'external_reference' => $payment->external_reference,
                'paid_to' => $payment->paid_to,
                'payment_datetime' => $payment->created_at ? $payment->created_at->toISOString() : null,
                'created_at' => $payment->created_at ? $payment->created_at->toISOString() : null,
                'status' => $payment->status,
                'status_badge' => $payment->status_badge,
                'is_reconciled' => $payment->is_reconciled,
                'wallet_balance_before' => (float) ($payment->wallet_balance_before ?? 0),
                'wallet_balance_after' => (float) ($payment->wallet_balance_after ?? 0),
                'created_at_formatted' => $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-',
                'meta' => $payment->meta,
            ];
        });
        
        return view('payments.index', compact('paymentsData', 'tenants', 'invoices'));
    }

    public function show($id)
    {
        try {
            $user = Auth::user();
            $company = $user->company;
            
            $payment = Payment::with(['tenant.user', 'invoice.items', 'invoice.tenancy.unit.estate', 'user'])
                ->findOrFail($id);
            
            // Check authorization
            if (!$user->hasRole('sysadmin') && $company) {
                $paymentCompany = $payment->invoice?->tenancy?->unit?->estate?->company_id;
                if ($paymentCompany != $company->id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized to view this payment'
                    ], 403);
                }
            }
            
            return response()->json([
                'success' => true,
                'payment' => [
                    'id' => $payment->id,
                    'payer_name' => $payment->payer_name,
                    'tenant_name' => $payment->tenant?->user?->name ?? 'N/A',
                    'tenant_phone' => $payment->tenant?->user?->phone ?? 'N/A',
                    'invoice_number' => $payment->invoice?->invoice_number ?? 'INV-' . ($payment->invoice_id ?? 'N/A'),
                    'invoice_label' => $payment->invoice_label,
                    'amount' => (float) $payment->amount,
                    'formatted_amount' => 'KES ' . number_format($payment->amount, 2),
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $payment->payment_method_label,
                    'transaction_reference' => $payment->transaction_reference,
                    'external_reference' => $payment->external_reference,
                    'paid_to' => $payment->paid_to,
                    'payment_datetime' => $payment->created_at ? $payment->created_at->format('M d, Y H:i:s') : '-',
                    'status' => $payment->status,
                    'status_badge' => $payment->status_badge,
                    'is_reconciled' => $payment->is_reconciled,
                    'reconciled_at' => $payment->reconciled_at ? $payment->reconciled_at->format('M d, Y H:i') : null,
                    'wallet_balance_before' => (float) ($payment->wallet_balance_before ?? 0),
                    'wallet_balance_after' => (float) ($payment->wallet_balance_after ?? 0),
                    'invoice_items' => $payment->invoice?->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                            'paid_amount' => (float) ($item->paid_amount ?? 0),
                        ];
                    }) ?? [],
                    'unit_number' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                    'estate_name' => $payment->invoice?->tenancy?->unit?->estate?->name ?? 'N/A',
                    'company_name' => $payment->invoice?->tenancy?->unit?->estate?->company?->name ?? 'N/A',
                    'created_at' => $payment->created_at->toISOString(),
                    'updated_at' => $payment->updated_at->toISOString(),
                    'meta' => $payment->meta,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Payment not found'
            ], 404);
        }
    }

    /**
     * Store a new payment - Handles both regular and M-Pesa STK Push payments
     */
    public function store(Request $request)
    {
        $request->validate([
            'tenancy_id' => 'sometimes|exists:tenancies,id',
            'tenant_id' => 'required_without:tenancy_id|exists:tenants,id',
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,mpesa_paybill,mpesa',
            'external_reference' => 'nullable|string',
            'payment_datetime' => 'required_if:payment_method,cash,bank_transfer|nullable|date',
            'payment_month' => 'required_if:payment_method,cash,bank_transfer|nullable|string',
            'notes' => 'nullable|string',
            'mpesa_phone' => 'required_if:payment_method,mpesa,mpesa_paybill|nullable|string',
        ]);
        
        try {
            // Get tenant and invoice
            $tenant = Tenant::find($request->tenant_id);
            if (!$tenant && $request->tenancy_id) {
                $tenant = Tenant::whereHas('activeTenancy', function($q) use ($request) {
                    $q->where('id', $request->tenancy_id);
                })->first();
            }
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            
            // =========================================================
            // 📱 M-PESA PAYBILL - STK PUSH
            // =========================================================
            if (in_array($request->payment_method, ['mpesa', 'mpesa_paybill'])) {
                return $this->processMpesaPayment($request, $tenant, $invoice);
            }
            
            // =========================================================
            // 💰 REGULAR PAYMENT (Cash, Bank Transfer)
            // =========================================================
            return $this->processRegularPayment($request, $tenant, $invoice);
            
        } catch (\Exception $e) {
            Log::error('Error creating payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process M-Pesa STK Push payment
     */
    protected function processMpesaPayment($request, $tenant, $invoice)
    {
        // Get phone number
        $phone = $request->mpesa_phone ?? $tenant->user->phone ?? null;
        
        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number required for M-Pesa payment. Please enter your M-Pesa phone number.'
            ], 400);
        }
        
        // Format phone number
        $phone = $this->formatPhoneNumber($phone);
        
        Log::info('📱 Processing M-Pesa payment', [
            'phone' => $phone,
            'amount' => $request->amount,
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id
        ]);
        
        // Send STK Push
        $mpesa = new \App\Services\MpesaService();
        $result = $mpesa->stkPush(
            $phone,
            $request->amount,
            $invoice->invoice_number ?? 'INV-' . $invoice->id,
            "Payment for Invoice #{$invoice->id}"
        );
        
        Log::info('📤 STK Push result', ['result' => $result]);
        
        if ($result['success']) {
            // Store in pending_mpesa_payments table (backup for callback)
            try {
                PendingMpesaPayment::create([
                    'checkout_request_id' => $result['checkout_request_id'],
                    'merchant_request_id' => $result['merchant_request_id'] ?? null,
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $tenant->id,
                    'phone_number' => $phone,
                    'amount' => $request->amount,
                    'status' => 'pending',
                    'created_at' => now()
                ]);
                Log::info('✅ Pending payment stored in database', [
                    'checkout_request_id' => $result['checkout_request_id'],
                    'invoice_id' => $invoice->id
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Failed to store pending payment: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Also store in session for immediate use
            session([
                'mpesa_invoice_id' => $invoice->id,
                'mpesa_checkout_id' => $result['checkout_request_id'],
                'mpesa_phone' => $phone,
                'mpesa_amount' => $request->amount,
                'mpesa_tenant_id' => $tenant->id,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '✅ STK Push sent to ' . $phone . '! Please check your phone.',
                'checkout_request_id' => $result['checkout_request_id'],
                'is_mpesa' => true,
                'phone' => $phone
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => '❌ STK Push failed: ' . ($result['message'] ?? 'Please try again.')
        ], 400);
    }

    /**
     * Process regular payment (Cash, Bank Transfer)
     */
    protected function processRegularPayment($request, $tenant, $invoice)
    {
        // Validate required fields for regular payment
        if (!$request->payment_datetime || !$request->payment_month) {
            return response()->json([
                'success' => false,
                'message' => 'Payment date and month are required for this payment method.'
            ], 400);
        }
        
        // Check if invoice belongs to this tenant
        if ($invoice->tenancy->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice does not belong to this tenant'
            ], 400);
        }
        
        // Process the direct payment using wallet service
        $result = $this->walletService->processDirectPayment(
            tenant: $tenant,
            invoice: $invoice,
            amount: $request->amount,
            paymentMethod: $request->payment_method,
            externalReference: $request->external_reference ?? 'MANUAL-' . time(),
            meta: [
                'notes' => $request->notes,
                'payment_datetime' => $request->payment_datetime,
                'payment_month' => $request->payment_month,
                'source' => 'admin_panel',
                'created_by' => auth()->id(),
                'created_by_name' => auth()->user()->name,
            ]
        );
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Payment processed successfully!',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'wallet_balance' => $result['wallet_balance'],
                    'amount_paid_to_invoice' => $result['amount_paid_to_invoice'],
                    'amount_added_to_wallet' => $result['amount_added_to_wallet'],
                    'invoice' => $result['invoice'],
                ]
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Payment processing failed'
        ], 400);
    }

    /**
     * Format phone number for M-Pesa
     */
    private function formatPhoneNumber($phone)
    {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        
        // If starts with 7, add 254
        if (str_starts_with($phone, '7')) {
            $phone = '254' . $phone;
        }
        
        // If starts with +, remove it
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        
        return $phone;
    }

    /**
     * Get invoices for a specific tenant (AJAX endpoint)
     */
    public function getTenantInvoices($tenantId)
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            
            $invoices = Invoice::whereHas('tenancy', function($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                })
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('items')
                ->orderBy('billing_month', 'asc')
                ->get()
                ->map(function($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
                        'billing_month' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                        'total_amount' => (float) $invoice->total_amount,
                        'remaining_amount' => (float) $invoice->remaining_amount,
                        'status' => $invoice->status,
                        'items' => $invoice->items->map(function($item) {
                            return [
                                'id' => $item->id,
                                'description' => $item->description,
                                'amount' => (float) $item->amount,
                                'paid_amount' => (float) ($item->paid_amount ?? 0),
                            ];
                        }),
                    ];
                });
            
            return response()->json([
                'success' => true,
                'invoices' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tenant invoices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices'
            ], 500);
        }
    }

    /**
     * Check M-Pesa STK Push payment status (AJAX endpoint)
     */
    public function checkMpesaStatus(Request $request)
    {
        $request->validate([
            'checkout_request_id' => 'required|string',
        ]);

        try {
            $mpesa = new \App\Services\MpesaService();
            $result = $mpesa->queryStatus($request->checkout_request_id);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'status' => $result['status'] ?? 'pending',
                    'message' => $result['message'],
                    'data' => $result['data']
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Status check failed'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('M-Pesa status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status'
            ], 500);
        }
    }

    /**
     * Update an existing payment (for status/reconciliation updates)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'sometimes|string|in:pending,completed,failed,cancelled,refunded',
            'is_reconciled' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);
        
        try {
            $payment = Payment::findOrFail($id);
            
            $updateData = [];
            
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }
            
            if ($request->has('is_reconciled')) {
                $updateData['is_reconciled'] = $request->is_reconciled;
                if ($request->is_reconciled) {
                    $updateData['reconciled_at'] = now();
                    $updateData['reconciled_by'] = Auth::id();
                }
            }
            
            // Merge meta updates
            $meta = $payment->meta ?? [];
            $meta['updated_at'] = now()->toISOString();
            $meta['updated_by'] = Auth::id();
            $meta['updated_by_name'] = Auth::user()->name;
            if ($request->has('notes')) {
                $meta['update_notes'] = $request->notes;
            }
            $updateData['meta'] = $meta;
            
            $payment->update($updateData);
            
            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment'
            ], 500);
        }
    }

    /**
     * Delete a payment
     */
    public function destroy($id)
    {
        try {
            $payment = Payment::findOrFail($id);
            
            // Don't allow deletion of completed payments that affect wallet balance
            if ($payment->status === Payment::STATUS_COMPLETED && $payment->payment_method === Payment::METHOD_WALLET) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed wallet payments as they affect balance history'
                ], 400);
            }
            
            $payment->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment'
            ], 500);
        }
    }
}