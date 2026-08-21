<?php

namespace App\Http\Controllers;

use App\Models\MpesaStk;
use App\Models\Payment;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Tenants\Models\Tenant;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicInvoiceController extends Controller
{
    /**
     * Show the public invoice payment page
     */
    public function show($invoiceId)
    {
        $invoice = Invoice::with(['tenancy.tenant.user', 'tenancy.unit.estate', 'items'])
            ->findOrFail($invoiceId);
        
        // Calculate paid amount
        $paidAmount = $invoice->payments()->where('status', 'completed')->sum('amount');
        $balanceDue = max(0, $invoice->total_amount - $paidAmount);
        
        // If invoice is already paid, show paid message
        if ($invoice->status === 'paid') {
            return view('public.invoice-paid', compact('invoice'));
        }
        
        // Get tenant phone number if available
        $phone = $invoice->tenancy?->tenant?->user?->phone ?? null;
        
        return view('public.invoice-payment', compact('invoice', 'balanceDue', 'phone'));
    }

    public function pay(Request $request, $invoiceId)
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoice = Invoice::with(['tenancy.tenant.user'])->findOrFail($invoiceId);
        
        if ($invoice->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This invoice is already paid.'
            ], 400);
        }

        $amount = (float) $request->amount;
        $phone = $this->formatPhoneNumber($request->phone);
        
        $tenant = $invoice->tenancy?->tenant;
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found for this invoice.'
            ], 400);
        }

        // Get the callback URL being used
        $callbackUrl = env('MPESA_CALLBACK_URL');
        
        // Debug: Log the callback URL
        Log::info('📱 STK Push Initiated with Callback URL', [
            'callback_url' => $callbackUrl,
            'invoice_id' => $invoice->id,
            'phone' => $phone,
            'amount' => $amount
        ]);
        
        // Write to debug file
        file_put_contents(
            storage_path('logs/stk_push_debug.log'),
            now() . ' - STK Push Initiated' . PHP_EOL .
            'Callback URL: ' . $callbackUrl . PHP_EOL .
            'Invoice ID: ' . $invoice->id . PHP_EOL .
            'Phone: ' . $phone . PHP_EOL .
            'Amount: ' . $amount . PHP_EOL .
            str_repeat('-', 80) . PHP_EOL,
            FILE_APPEND
        );

        try {
            $mpesa = new MpesaService();
            $reference = 'INV-' . $invoice->id . '-' . time();
            
            $result = $mpesa->stkPush(
                $phone,
                $amount,
                $reference,
                "Payment for Invoice #{$invoice->id}",
                $invoice->id,
                auth()->id()
            );

            // Log the FULL response from Safaricom
            Log::info('📤 STK Push FULL Response', [
                'result' => $result,
                'checkout_request_id' => $result['checkout_request_id'] ?? null,
                'merchant_request_id' => $result['merchant_request_id'] ?? null,
                'callback_url' => $callbackUrl,
                'invoice_id' => $invoice->id,
                'phone' => $phone,
                'amount' => $amount
            ]);

            // Write response to debug file
            file_put_contents(
                storage_path('logs/stk_push_debug.log'),
                now() . ' - STK Push Response' . PHP_EOL .
                'Success: ' . ($result['success'] ? 'YES' : 'NO') . PHP_EOL .
                'Checkout Request ID: ' . ($result['checkout_request_id'] ?? 'N/A') . PHP_EOL .
                'Merchant Request ID: ' . ($result['merchant_request_id'] ?? 'N/A') . PHP_EOL .
                'Message: ' . ($result['message'] ?? 'N/A') . PHP_EOL .
                'Full Response: ' . json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL .
                str_repeat('=', 80) . PHP_EOL,
                FILE_APPEND
            );

            if ($result['success']) {
                // Store checkout ID in session
                session([
                    'mpesa_invoice_id' => $invoice->id,
                    'mpesa_checkout_id' => $result['checkout_request_id'],
                    'mpesa_phone' => $phone,
                    'mpesa_amount' => $amount,
                    'mpesa_tenant_id' => $tenant->id,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'STK Push sent to your phone!',
                    'checkout_request_id' => $result['checkout_request_id'],
                    'invoice_id' => $invoice->id,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initiate payment. Please try again.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('STK Push error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Write error to debug file
            file_put_contents(
                storage_path('logs/stk_push_debug.log'),
                now() . ' - STK Push ERROR' . PHP_EOL .
                'Error: ' . $e->getMessage() . PHP_EOL .
                'Trace: ' . $e->getTraceAsString() . PHP_EOL .
                str_repeat('=', 80) . PHP_EOL,
                FILE_APPEND
            );

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request, $invoiceId, $checkoutId)
    {
        try {
            $mpesa = new MpesaService();
            $result = $mpesa->queryStatus($checkoutId);

            Log::info('Status check', [
                'invoice_id' => $invoiceId,
                'checkout_id' => $checkoutId,
                'result' => $result
            ]);

            // Check if payment was successful
            if ($result['success'] && isset($result['status'])) {
                $status = $result['status'];
                
                // If status is 0 or completed, payment was successful
                if ($status === '0' || $status === 'completed' || $status === 'success') {
                    // Check if payment record exists in mpesa_stks
                    $mpesaStk = MpesaStk::where('checkout_request_id', $checkoutId)->first();
                    
                    if ($mpesaStk && $mpesaStk->isSuccessful()) {
                        // Process the payment if not already processed
                        if (!$mpesaStk->payment) {
                            $this->processSuccessfulPayment($mpesaStk);
                        }
                        
                        return response()->json([
                            'success' => true,
                            'status' => 'completed',
                            'message' => 'Payment completed successfully!',
                            'data' => $result['data'] ?? null
                        ]);
                    }
                }
            }

            // Still pending
            return response()->json([
                'success' => false,
                'status' => 'pending',
                'message' => 'Payment is still processing. Please check your phone.'
            ]);

        } catch (\Exception $e) {
            Log::error('Status check error: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'checkout_id' => $checkoutId
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to check payment status.'
            ], 500);
        }
    }

    /**
     * Process a successful payment
     */
    protected function processSuccessfulPayment($mpesaStk)
    {
        if (!$mpesaStk->isSuccessful()) {
            return ['success' => false, 'message' => 'Payment not successful'];
        }

        if ($mpesaStk->payment) {
            return ['success' => false, 'message' => 'Payment already processed'];
        }

        if (!$mpesaStk->invoice_id) {
            return ['success' => false, 'message' => 'No invoice associated with this payment'];
        }

        $invoice = Invoice::find($mpesaStk->invoice_id);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }

        // Check if invoice is already paid
        if ($invoice->status === 'paid') {
            return ['success' => false, 'message' => 'Invoice already paid'];
        }

        DB::beginTransaction();

        try {
            // Get tenant
            $tenant = $invoice->tenancy?->tenant;
            
            if (!$tenant) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Tenant not found for invoice'];
            }

            $amount = (float) $mpesaStk->amount;
            
            // Get current wallet balance
            $balanceBefore = 0;
            try {
                if (method_exists($tenant, 'balance')) {
                    $balanceBefore = (float) $tenant->balance;
                }
            } catch (\Exception $e) {
                Log::warning('Could not get wallet balance', ['error' => $e->getMessage()]);
            }

            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'payment_method' => 'mpesa_stk',
                'source' => 'public_link',
                'amount' => $amount,
                'wallet_balance_before' => $balanceBefore,
                'wallet_balance_after' => $balanceBefore + $amount,
                'transaction_reference' => $mpesaStk->checkout_request_id,
                'external_reference' => $mpesaStk->mpesa_receipt_number,
                'status' => 'completed',
                'is_reconciled' => false,
                'meta' => [
                    'mpesa_stk_id' => $mpesaStk->id,
                    'mpesa_receipt' => $mpesaStk->mpesa_receipt_number,
                    'transaction_date' => $mpesaStk->transaction_date,
                    'phone_number' => $mpesaStk->phone_number,
                    'payment_source' => 'public_link',
                    'checkout_request_id' => $mpesaStk->checkout_request_id,
                    'merchant_request_id' => $mpesaStk->merchant_request_id,
                    'paid_at' => now()->toISOString(),
                ]
            ]);

            // Add to Bavix Wallet
            try {
                if (method_exists($tenant, 'deposit')) {
                    $tenant->deposit($amount, [
                        'description' => "M-Pesa payment for invoice #{$invoice->invoice_number}",
                        'meta' => [
                            'payment_id' => $payment->id,
                            'mpesa_receipt' => $mpesaStk->mpesa_receipt_number,
                            'checkout_request_id' => $mpesaStk->checkout_request_id,
                            'source' => 'mpesa_payment',
                        ]
                    ]);
                    
                    Log::info('✅ Wallet deposit successful', [
                        'tenant_id' => $tenant->id,
                        'amount' => $amount
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ Wallet deposit failed: ' . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'amount' => $amount
                ]);
                // Don't rollback - payment is still valid
            }

            // Update invoice total paid
            $invoice->total_paid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
            
            // Update invoice status
            if ($invoice->total_paid >= $invoice->total_amount) {
                $invoice->status = 'paid';
            } elseif ($invoice->total_paid > 0) {
                $invoice->status = 'partial';
            }
            $invoice->save();

            DB::commit();

            Log::info('✅ Payment processed successfully', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'receipt' => $mpesaStk->mpesa_receipt_number
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'invoice' => $invoice
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Failed to process payment: ' . $e->getMessage(), [
                'stk_id' => $mpesaStk->id,
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
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
}