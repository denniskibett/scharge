<?php
// app/Http/Controllers/MpesaController.php

namespace App\Http\Controllers;

use App\Services\MpesaService;
use App\Models\MpesaStk;
use App\Models\Payment;
use App\Modules\Payments\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
        $validated = $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'account_reference' => 'required|string|max:13',
            'description' => 'nullable|string|max:100',
        ]);

        $result = $this->mpesaService->stkPush(
            $validated['phone'],
            $validated['amount'],
            $validated['account_reference'],
            $validated['description'] ?? 'Payment',
            auth()->id(),
            null,
            null
        );

        if ($result['success']) {
            return redirect()->back()
                ->with('success', '✅ STK Push sent! Please check your phone and enter your M-Pesa PIN.')
                ->with('checkout_request_id', $result['checkout_request_id'] ?? null)
                ->with('stk_id', $result['stk_id'] ?? null);
        }

        return redirect()->back()
            ->with('error', '❌ STK Push failed: ' . $result['message']);
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
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stkCallback(Request $request)
    {
        $payload = $request->all();
        
        Log::info('📨 M-Pesa STK Callback Received', [
            'payload' => $payload,
            'timestamp' => now()->toISOString()
        ]);
        
        if (isset($payload['Body']['stkCallback'])) {
            $callback = $payload['Body']['stkCallback'];
            
            $resultCode = $callback['ResultCode'] ?? null;
            $resultDesc = $callback['ResultDesc'] ?? null;
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            $merchantRequestId = $callback['MerchantRequestID'] ?? null;
            
            Log::info('📊 Callback details', [
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $merchantRequestId
            ]);
            
            // ✅ Find the STK record by checkout_request_id
            $mpesaStk = MpesaStk::where('checkout_request_id', $checkoutRequestId)->first();
            
            if (!$mpesaStk) {
                Log::warning('⚠️ No mpesa_stks record found for checkout_request_id', [
                    'checkout_request_id' => $checkoutRequestId
                ]);
                
                // Try by merchant_request_id as fallback
                $mpesaStk = MpesaStk::where('merchant_request_id', $merchantRequestId)->first();
                
                if (!$mpesaStk) {
                    Log::error('❌ No mpesa_stks record found for merchant_request_id either', [
                        'merchant_request_id' => $merchantRequestId
                    ]);
                    // Still return success to avoid retries
                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
                }
            }
            
            Log::info('✅ Found mpesa_stks record', [
                'id' => $mpesaStk->id,
                'checkout_request_id' => $mpesaStk->checkout_request_id,
                'invoice_id' => $mpesaStk->invoice_id
            ]);
            
            // ✅ Update the STK record with callback results
            $mpesaStk->response_code = $resultCode; // 0 = success, 1+ = failure
            $mpesaStk->result_code = $resultCode;
            $mpesaStk->response_description = $resultDesc;
            $mpesaStk->customer_message = $resultCode == 0
                ? 'Payment completed successfully'
                : 'Payment failed: ' . $resultDesc;
            
            // Update status
            $mpesaStk->status = ($resultCode == 0) ? 'completed' : 'failed';
            
            // Extract metadata if success
            $metadata = [];
            if ($resultCode == 0) {
                if (isset($callback['CallbackMetadata']['Item'])) {
                    foreach ($callback['CallbackMetadata']['Item'] as $item) {
                        $metadata[$item['Name']] = $item['Value'] ?? null;
                    }
                }
                
                $mpesaStk->mpesa_receipt_number = $metadata['MpesaReceiptNumber'] ?? null;
                $mpesaStk->transaction_date = isset($metadata['TransactionDate'])
                    ? Carbon::createFromFormat('YmdHis', (string) $metadata['TransactionDate'])
                    : null;
                
                Log::info('💰 Payment details extracted', [
                    'amount' => $metadata['Amount'] ?? null,
                    'receipt' => $mpesaStk->mpesa_receipt_number,
                    'phone' => $metadata['PhoneNumber'] ?? null,
                    'transaction_date' => $mpesaStk->transaction_date
                ]);
            }
            
            // Save metadata
            $existingMeta = $mpesaStk->metadata ?? [];
            $existingMeta['callback_result'] = [
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'metadata' => $metadata,
                'callback_payload' => $payload,
                'processed_at' => now()->toISOString(),
            ];
            $mpesaStk->metadata = $existingMeta;
            $mpesaStk->save();
            
            Log::info('✅ mpesa_stks record updated', [
                'id' => $mpesaStk->id,
                'response_code' => $mpesaStk->response_code,
                'status' => $mpesaStk->status,
                'receipt' => $mpesaStk->mpesa_receipt_number
            ]);
            
            // ✅ Process payment if success
            if ($resultCode == 0) {
                $this->processSuccessfulPayment($mpesaStk, $metadata);
            } else {
                Log::error('❌ M-Pesa payment failed', [
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                    'checkout_request_id' => $checkoutRequestId,
                    'stk_id' => $mpesaStk->id
                ]);
            }
        } else {
            Log::warning('⚠️ Invalid callback payload structure', [
                'payload_keys' => array_keys($payload)
            ]);
        }
        
        // Always return success to M-Pesa
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Process a successful M-Pesa payment
     *
     * @param MpesaStk $mpesaStk
     * @param array $metadata
     * @return void
     */
    protected function processSuccessfulPayment(MpesaStk $mpesaStk, array $metadata)
    {
        try {
            $amount = $metadata['Amount'] ?? $mpesaStk->amount;
            $receiptNumber = $metadata['MpesaReceiptNumber'] ?? null;
            $phoneNumber = $metadata['PhoneNumber'] ?? $mpesaStk->phone_number;
            $invoiceId = $mpesaStk->invoice_id;
            
            Log::info('💰 Processing successful payment', [
                'stk_id' => $mpesaStk->id,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'receipt' => $receiptNumber
            ]);
            
            if (!$invoiceId) {
                Log::warning('⚠️ No invoice_id in STK record, cannot process payment');
                return;
            }
            
            $invoice = Invoice::find($invoiceId);
            if (!$invoice) {
                Log::warning('⚠️ Invoice not found', ['invoice_id' => $invoiceId]);
                return;
            }
            
            if ($invoice->status === 'paid') {
                Log::warning('⚠️ Invoice already paid', ['invoice_id' => $invoiceId]);
                return;
            }
            
            // Check duplicate payment
            $existingPayment = Payment::where('external_reference', $receiptNumber)
                ->orWhere('meta->mpesa_receipt', $receiptNumber)
                ->first();
            if ($existingPayment) {
                Log::warning('⚠️ Payment already exists for this receipt', [
                    'receipt' => $receiptNumber,
                    'payment_id' => $existingPayment->id
                ]);
                // Update STK with existing payment ID
                $mpesaStk->payment_id = $existingPayment->id;
                $mpesaStk->save();
                return;
            }
            
            // Create payment record
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenancy->tenant_id ?? null,
                'user_id' => $mpesaStk->user_id,
                'amount' => $amount,
                'payment_method' => 'mpesa_stk',
                'transaction_reference' => $mpesaStk->checkout_request_id,
                'external_reference' => $receiptNumber,
                'payer_name' => $invoice->tenancy?->tenant?->user?->name ?? 'M-Pesa Payment',
                'status' => 'completed',
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'payment_month' => $invoice->billing_month ?? now()->format('Y-m'),
                'payment_datetime' => now(),
                'meta' => [
                    'mpesa_stk_id' => $mpesaStk->id,
                    'mpesa_receipt' => $receiptNumber,
                    'mpesa_phone' => $phoneNumber,
                    'checkout_request_id' => $mpesaStk->checkout_request_id,
                    'merchant_request_id' => $mpesaStk->merchant_request_id,
                    'source' => 'mpesa_stk_push',
                    'callback_timestamp' => now()->toISOString(),
                    'response_code' => 0,
                    'transaction_date' => $mpesaStk->transaction_date?->toISOString(),
                ]
            ]);
            
            Log::info('✅ Payment record created', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'receipt' => $receiptNumber
            ]);
            
            // Update invoice
            $invoice->total_paid = ($invoice->total_paid ?? 0) + $amount;
            $invoice->status = $invoice->total_paid >= $invoice->total_amount ? 'paid' : 'partial';
            $invoice->save();
            
            Log::info('✅ Invoice updated', [
                'invoice_id' => $invoice->id,
                'new_status' => $invoice->status,
                'total_paid' => $invoice->total_paid,
                'remaining' => $invoice->remaining_amount
            ]);
            
            // Link payment to STK
            $mpesaStk->payment_id = $payment->id;
            $meta = $mpesaStk->metadata ?? [];
            $meta['payment_id'] = $payment->id;
            $meta['payment_processed_at'] = now()->toISOString();
            $meta['invoice_status_after'] = $invoice->status;
            $mpesaStk->metadata = $meta;
            $mpesaStk->save();
            
            Log::info('✅ Payment processing completed successfully', [
                'stk_id' => $mpesaStk->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'response_code' => 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Failed to process successful payment: ' . $e->getMessage(), [
                'stk_id' => $mpesaStk->id ?? null,
                'invoice_id' => $mpesaStk->invoice_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * B2B Result URL
     */
    public function b2bResult(Request $request)
    {
        $payload = $request->all();
        Log::info('M-Pesa B2B Result Received', $payload);
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * B2B Queue Timeout URL
     */
    public function b2bQueueTimeout(Request $request)
    {
        $payload = $request->all();
        Log::warning('M-Pesa B2B Queue Timeout', $payload);
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * C2B Confirmation
     */
    public function confirmation(Request $request)
    {
        Log::info('M-Pesa C2B Confirmation Received', $request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * C2B Validation
     */
    public function validation(Request $request)
    {
        Log::info('M-Pesa C2B Validation Received', $request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }
}