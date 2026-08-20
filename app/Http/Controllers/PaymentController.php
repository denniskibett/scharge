<?php

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

    // ... other methods ...

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
            'payment_datetime' => 'nullable|date',
            'payment_month' => 'nullable|string',
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
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
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
            //    For cash/bank payments, we deposit the money into the wallet first,
            //    then pay the invoice from the wallet.
            //    This ensures the wallet balance reflects the payment.
            // =========================================================
            return $this->processDirectPayment($request, $tenant, $invoice);
            
        } catch (\Exception $e) {
            Log::error('Error creating payment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Direct Payment (Cash, Bank Transfer)
     * This deposits the money into the wallet first, then pays the invoice
     */
    protected function processDirectPayment($request, $tenant, $invoice)
    {
        $amount = (float) $request->amount;
        $paymentMethod = $request->payment_method;
        $externalReference = $request->external_reference ?? 'MANUAL-' . time();
        $notes = $request->notes;
        
        Log::info('Processing direct payment', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'external_reference' => $externalReference,
        ]);
        
        // Check if invoice belongs to this tenant
        if ($invoice->tenancy->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice does not belong to this tenant'
            ], 400);
        }
        
        $remainingInvoiceAmount = $invoice->remaining_amount;
        $amountToInvoice = min($amount, $remainingInvoiceAmount);
        $amountToWallet = $amount - $amountToInvoice;
        
        Log::info('Direct payment breakdown', [
            'amount' => $amount,
            'amount_to_invoice' => $amountToInvoice,
            'amount_to_wallet' => $amountToWallet,
            'remaining_invoice_amount' => $remainingInvoiceAmount,
        ]);
        
        // Get current balance before any changes
        $tenant->refresh();
        $balanceBefore = (float) $tenant->balance;
        
        Log::info('Wallet balance before deposit', [
            'tenant_id' => $tenant->id,
            'balance_before' => $balanceBefore,
        ]);
        
        DB::beginTransaction();
        
        try {
            // Step 1: Deposit the FULL amount into the tenant's wallet
            $depositMeta = [
                'description' => "Payment received for invoice #{$invoice->invoice_number}",
                'payment_method' => $paymentMethod,
                'external_reference' => $externalReference,
                'invoice_id' => $invoice->id,
                'payment_type' => 'direct_payment',
                'notes' => $notes,
                'source' => 'admin_panel',
                'created_by' => auth()->id(),
                'created_by_name' => auth()->user()->name,
            ];
            
            $depositResult = $this->walletService->deposit(
                $tenant,
                $amount,  // Deposit full amount
                $depositMeta
            );
            
            if (!$depositResult['success']) {
                DB::rollBack();
                Log::error('Deposit failed', [
                    'error' => $depositResult['error'] ?? 'Unknown error',
                    'tenant_id' => $tenant->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to deposit funds: ' . ($depositResult['error'] ?? 'Unknown error')
                ], 400);
            }
            
            Log::info('Deposit successful', [
                'deposit_amount' => $amount,
                'balance_after_deposit' => $depositResult['balance_after'],
            ]);
            
            // Step 2: Pay the invoice from the wallet (only the amount needed for invoice)
            $paymentRecord = null;
            if ($amountToInvoice > 0) {
                $payResult = $this->walletService->payInvoice(
                    $tenant,
                    $invoice,
                    $amountToInvoice,
                    [
                        'description' => "Payment for invoice #{$invoice->invoice_number}",
                        'external_reference' => $externalReference,
                        'payment_method' => $paymentMethod,
                        'notes' => $notes,
                        'source' => 'admin_panel',
                        'created_by' => auth()->id(),
                        'created_by_name' => auth()->user()->name,
                        // Pass the deposit transaction info to link the two transactions
                        'deposit_transaction_uuid' => $depositResult['transaction_uuid'] ?? null,
                        'deposit_transaction_id' => $depositResult['transaction_id'] ?? null,
                    ]
                );
                
                if (!$payResult['success']) {
                    DB::rollBack();
                    Log::error('Invoice payment failed', [
                        'error' => $payResult['error'] ?? 'Unknown error',
                        'tenant_id' => $tenant->id,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to pay invoice: ' . ($payResult['error'] ?? 'Unknown error')
                    ], 400);
                }
                
                // Get the payment record created by payInvoice()
                if (isset($payResult['payment_id'])) {
                    $paymentRecord = Payment::find($payResult['payment_id']);
                }
            }
            
            // Get final balance
            $tenant->refresh();
            $finalBalance = (float) $tenant->balance;
            
            Log::info('Direct payment completed successfully', [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'amount_to_invoice' => $amountToInvoice,
                'amount_to_wallet' => $amountToWallet,
                'final_balance' => $finalBalance,
                'deposit_transaction' => $depositResult['transaction_uuid'] ?? null,
                'payment_transaction' => $payResult['transaction_uuid'] ?? null,
                'payment_id' => $paymentRecord ? $paymentRecord->id : null,
            ]);
            
            // Refresh invoice to update status
            $invoice->refresh();
            
            DB::commit();
            
            // Determine response message
            $message = "Payment of KES " . number_format($amount, 2) . " processed successfully.";
            if ($amountToWallet > 0) {
                $message .= " KES " . number_format($amountToWallet, 2) . " added to wallet balance.";
            }
            if ($amountToInvoice > 0) {
                $message .= " Invoice #{$invoice->invoice_number} paid: KES " . number_format($amountToInvoice, 2);
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'payment_id' => $paymentRecord ? $paymentRecord->id : null,
                    'wallet_balance' => $finalBalance,
                    'amount_paid_to_invoice' => $amountToInvoice,
                    'amount_added_to_wallet' => $amountToWallet,
                    'payment' => $paymentRecord,
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'remaining_amount' => $invoice->remaining_amount,
                        'total_paid' => $invoice->total_paid,
                        'status' => $invoice->status,
                    ],
                    'deposit_transaction' => $depositResult,
                    'payment_transaction' => $payResult ?? null,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Direct payment failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage()
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