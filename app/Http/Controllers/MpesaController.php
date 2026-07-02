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
            $validated['description'] ?? 'Payment'
        );

        if ($result['success']) {
            return redirect()->back()
                ->with('success', '✅ STK Push sent! Please check your phone and enter your M-Pesa PIN.')
                ->with('checkout_request_id', $result['checkout_request_id'] ?? null);
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
     */
    public function stkCallback(Request $request)
    {
        $payload = $request->all();
        
        Log::info('M-Pesa STK Callback Received', $payload);
        
        if (isset($payload['Body']['stkCallback'])) {
            $callback = $payload['Body']['stkCallback'];
            
            $resultCode = $callback['ResultCode'] ?? null;
            $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
            
            Log::info('Callback details', [
                'result_code' => $resultCode,
                'checkout_request_id' => $checkoutRequestId
            ]);
            
            if ($resultCode == 0) {
                // Extract payment details
                $metadata = [];
                if (isset($callback['CallbackMetadata']['Item'])) {
                    foreach ($callback['CallbackMetadata']['Item'] as $item) {
                        $metadata[$item['Name']] = $item['Value'] ?? null;
                    }
                }
                
                $amount = $metadata['Amount'] ?? null;
                $receiptNumber = $metadata['MpesaReceiptNumber'] ?? null;
                $phoneNumber = $metadata['PhoneNumber'] ?? null;
                
                Log::info('Payment details extracted', [
                    'amount' => $amount,
                    'receipt' => $receiptNumber,
                    'phone' => $phoneNumber
                ]);
                
                // Try to find invoice from session first
                $invoiceId = session('mpesa_invoice_id');
                $pending = null;
                
                // If not found in session, try database
                if (!$invoiceId) {
                    $pending = PendingMpesaPayment::where('checkout_request_id', $checkoutRequestId)->first();
                    if ($pending) {
                        $invoiceId = $pending->invoice_id;
                        Log::info('Found pending payment in database', [
                            'invoice_id' => $invoiceId,
                            'checkout_request_id' => $checkoutRequestId
                        ]);
                    }
                }
                
                if ($invoiceId) {
                    $invoice = Invoice::find($invoiceId);
                    
                    if ($invoice && $invoice->status !== 'paid') {
                        try {
                            // Create payment record
                            $payment = Payment::create([
                                'invoice_id' => $invoice->id,
                                'tenant_id' => $invoice->tenancy->tenant_id ?? ($pending->tenant_id ?? null),
                                'amount' => $amount ?? $pending->amount ?? $invoice->remaining_amount ?? $invoice->total_amount,
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
                                    'source' => 'mpesa_stk_push'
                                ]
                            ]);
                            
                            // Update invoice status
                            $invoice->total_paid = ($invoice->total_paid ?? 0) + $payment->amount;
                            $invoice->status = $invoice->total_paid >= $invoice->total_amount ? 'paid' : 'partial';
                            $invoice->save();
                            
                            // Update pending payment status
                            if ($pending) {
                                $pending->update(['status' => 'completed']);
                            }
                            
                            Log::info('✅ Invoice marked as paid', [
                                'invoice_id' => $invoice->id,
                                'receipt' => $receiptNumber,
                                'payment_id' => $payment->id,
                                'source' => $pending ? 'database' : 'session'
                            ]);
                            
                            // Clear session
                            session()->forget(['mpesa_invoice_id', 'mpesa_checkout_id']);
                            
                        } catch (\Exception $e) {
                            Log::error('❌ Failed to create payment: ' . $e->getMessage(), [
                                'invoice_id' => $invoiceId,
                                'receipt' => $receiptNumber
                            ]);
                        }
                    } else {
                        Log::warning('Invoice not found or already paid', [
                            'invoice_id' => $invoiceId,
                            'status' => $invoice->status ?? 'not found'
                        ]);
                    }
                } else {
                    Log::warning('No invoice found for callback', [
                        'checkout_request_id' => $checkoutRequestId,
                        'session_invoice_id' => session('mpesa_invoice_id')
                    ]);
                }
            } else {
                Log::error('❌ M-Pesa payment failed', [
                    'result_code' => $resultCode,
                    'checkout_request_id' => $checkoutRequestId
                ]);
            }
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