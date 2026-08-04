<?php
// app/Http/Controllers/MpesaController.php

namespace App\Http\Controllers;

use App\Services\MpesaService;
use App\Models\PendingMpesaPayment;
use App\Models\Payment;
use App\Modules\Payments\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Show the M-Pesa payment form
     */
    public function showPaymentForm()
    {
        return view('sms.mpesa.payment');
    }

    /**
     * Initiate STK Push from web form
     */
    public function stkPush(Request $request)
    {
        // Log the request
        Log::info('📱 STK Push form submitted to MpesaController', [
            'all' => $request->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);
        
        try {
            // Validate the request
            $validated = $request->validate([
                'phone' => 'required|string',
                'amount' => 'required|numeric|min:1',
                'account_reference' => 'nullable|string|max:13',
                'description' => 'nullable|string|max:100',
                'invoice_id' => 'nullable|exists:invoices,id',
                'tenant_id' => 'nullable|exists:tenants,id',
            ]);
            
            // If no account_reference provided, generate one
            if (empty($validated['account_reference'])) {
                $validated['account_reference'] = 'INV-' . ($validated['invoice_id'] ?? '') . '-' . time();
            }
            
            Log::info('✅ Validation passed', $validated);
            
            // Format phone number
            $phone = $this->mpesaService->formatPhoneNumber($validated['phone']);
            
            Log::info('📞 Formatted phone', ['phone' => $phone]);
            
            // Send STK Push
            $result = $this->mpesaService->stkPush(
                $phone,
                $validated['amount'],
                $validated['account_reference'],
                $validated['description'] ?? 'Payment'
            );
            
            Log::info('📤 STK Push result', $result);

            if ($result['success']) {
                // Save pending payment to database
                if (isset($result['checkout_request_id'])) {
                    try {
                        $pending = PendingMpesaPayment::create([
                            'checkout_request_id' => $result['checkout_request_id'],
                            'merchant_request_id' => $result['merchant_request_id'] ?? null,
                            'invoice_id' => $validated['invoice_id'] ?? null,
                            'tenant_id' => $validated['tenant_id'] ?? null,
                            'phone_number' => $phone,
                            'amount' => $validated['amount'],
                            'status' => 'pending',
                            'created_at' => now()
                        ]);
                        
                        Log::info('✅ Pending payment saved', [
                            'pending_id' => $pending->id,
                            'checkout_request_id' => $result['checkout_request_id']
                        ]);
                    } catch (\Exception $e) {
                        Log::error('❌ Failed to save pending payment: ' . $e->getMessage());
                    }
                }
                
                return redirect()->back()
                    ->with('success', '✅ STK Push sent! Please check your phone and enter your M-Pesa PIN.')
                    ->with('checkout_request_id', $result['checkout_request_id'] ?? null);
            }

            return redirect()->back()
                ->with('error', '❌ STK Push failed: ' . $result['message']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Validation failed', ['errors' => $e->errors()]);
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('❌ STK Push exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()
                ->with('error', '❌ Error: ' . $e->getMessage());
        }
    }

    /**
     * Query STK Push Status
     */
    public function queryStatus(Request $request)
    {
        $validated = $request->validate([
            'checkout_request_id' => 'required|string'
        ]);

        $result = $this->mpesaService->queryStatus($validated['checkout_request_id']);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], 400);
    }

    /**
     * B2B Payment (Business PayBill)
     */
    public function b2bPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'party_b' => 'required|string',
            'account_reference' => 'required|string|max:13',
            'requester' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $result = $this->mpesaService->payBill(
            $validated['amount'],
            $validated['party_b'],
            $validated['account_reference'],
            $validated['requester'] ?? null
        );

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], 400);
    }

    // =========================================================
    // 📨 STK PUSH CALLBACK (M-Pesa calls this)
    // =========================================================

    /**
     * STK Push Callback - M-Pesa sends payment confirmation here
     */
    public function stkCallback(Request $request)
    {
        $payload = $request->all();
        
        Log::info('📨 M-Pesa STK Callback Received', $payload);
        
        try {
            if (!isset($payload['Body']['stkCallback'])) {
                Log::warning('Invalid callback payload - no stkCallback');
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
            }

            $callback = $payload['Body']['stkCallback'];
            $resultCode = $callback['ResultCode'] ?? null;
            $resultDesc = $callback['ResultDesc'] ?? 'Unknown';
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            $merchantRequestId = $callback['MerchantRequestID'] ?? null;
            
            Log::info('📊 Callback details', [
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'checkout_request_id' => $checkoutRequestId
            ]);
            
            // Find pending transaction
            $pending = PendingMpesaPayment::where('checkout_request_id', $checkoutRequestId)->first();
            
            if (!$pending) {
                Log::error('❌ Pending payment NOT FOUND for: ' . $checkoutRequestId);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found']);
            }
            
            Log::info('✅ Found pending payment', [
                'pending_id' => $pending->id,
                'invoice_id' => $pending->invoice_id,
                'amount' => $pending->amount
            ]);

            if ($pending->status === 'completed') {
                Log::warning('⚠️ Payment already processed');
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
            }

            if ($resultCode == 0) {
                // Payment successful
                $metadata = [];
                if (isset($callback['CallbackMetadata']['Item'])) {
                    foreach ($callback['CallbackMetadata']['Item'] as $item) {
                        $metadata[$item['Name']] = $item['Value'] ?? null;
                    }
                }
                
                $amount = $metadata['Amount'] ?? $pending->amount;
                $receiptNumber = $metadata['MpesaReceiptNumber'] ?? null;
                $phoneNumber = $metadata['PhoneNumber'] ?? $pending->phone_number;
                
                Log::info('✅ Payment successful', [
                    'amount' => $amount,
                    'receipt' => $receiptNumber,
                    'phone' => $phoneNumber
                ]);

                if ($pending->invoice_id) {
                    $invoice = Invoice::with('tenancy.tenant.user')->find($pending->invoice_id);
                    
                    if ($invoice) {
                        // Check if already paid
                        if ($invoice->status === 'paid') {
                            Log::warning('Invoice already paid', ['invoice_id' => $invoice->id]);
                            $pending->update([
                                'status' => 'completed',
                                'mpesa_receipt' => $receiptNumber,
                                'completed_at' => now()
                            ]);
                            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
                        }
                        
                        // Create payment record
                        $payment = Payment::create([
                            'invoice_id' => $invoice->id,
                            'tenant_id' => $invoice->tenancy->tenant_id ?? $pending->tenant_id ?? null,
                            'amount' => $amount,
                            'payment_method' => 'mpesa_paybill',
                            'payment_date' => now(),
                            'reference_number' => $receiptNumber,
                            'external_reference' => $receiptNumber,
                            'payer_name' => $invoice->tenancy->tenant->user->name ?? 'M-Pesa Payment',
                            'notes' => "M-Pesa STK Push payment. Transaction: {$receiptNumber}",
                            'status' => 'completed',
                            'is_reconciled' => true,
                            'reconciled_at' => now(),
                            'meta' => [
                                'mpesa_receipt' => $receiptNumber,
                                'mpesa_phone' => $phoneNumber,
                                'checkout_request_id' => $checkoutRequestId,
                                'merchant_request_id' => $merchantRequestId,
                                'source' => 'mpesa_stk_push'
                            ]
                        ]);
                        
                        // Update invoice status
                        $totalPaid = ($invoice->total_paid ?? 0) + $amount;
                        $invoice->total_paid = $totalPaid;
                        $invoice->status = $totalPaid >= $invoice->total_amount ? 'paid' : 'partial';
                        $invoice->paid_at = $invoice->status === 'paid' ? now() : $invoice->paid_at;
                        $invoice->save();
                        
                        // Update pending record
                        $pending->update([
                            'status' => 'completed',
                            'mpesa_receipt' => $receiptNumber,
                            'completed_at' => now()
                        ]);
                        
                        Log::info('✅ Invoice marked as paid', [
                            'invoice_id' => $invoice->id,
                            'receipt' => $receiptNumber,
                            'payment_id' => $payment->id
                        ]);
                    } else {
                        Log::error('❌ Invoice not found', ['invoice_id' => $pending->invoice_id]);
                        $pending->update([
                            'status' => 'failed',
                            'error_message' => 'Invoice not found'
                        ]);
                    }
                } else {
                    // No invoice linked
                    $pending->update([
                        'status' => 'completed',
                        'mpesa_receipt' => $receiptNumber,
                        'completed_at' => now()
                    ]);
                    Log::info('No invoice linked to this payment', ['pending_id' => $pending->id]);
                }
            } else {
                // Payment failed
                Log::error('❌ M-Pesa payment failed', [
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc
                ]);
                
                $pending->update([
                    'status' => 'failed',
                    'error_message' => $resultDesc,
                    'failed_at' => now()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * B2B Result URL - M-Pesa calls this for B2B transactions
     */
    public function b2bResult(Request $request)
    {
        $payload = $request->all();
        Log::info('M-Pesa B2B Result Received', $payload);
        
        if (isset($payload['Result'])) {
            $result = $payload['Result'];
            $resultCode = $result['ResultCode'] ?? null;
            $resultDesc = $result['ResultDesc'] ?? null;
            $transactionId = $result['TransactionID'] ?? null;
            
            if ($resultCode == 0) {
                Log::info('✅ B2B Payment successful', [
                    'transaction_id' => $transactionId,
                    'result_desc' => $resultDesc
                ]);
            } else {
                Log::error('❌ B2B Payment failed', [
                    'transaction_id' => $transactionId,
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc
                ]);
            }
        }
        
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * B2B Queue Timeout URL - M-Pesa calls this on timeout
     */
    public function b2bQueueTimeout(Request $request)
    {
        $payload = $request->all();
        Log::warning('M-Pesa B2B Queue Timeout', $payload);
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
}