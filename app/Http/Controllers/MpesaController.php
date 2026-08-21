<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MpesaStk;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    private $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Handle M-Pesa Callback - Update ONLY MpesaStk
     * This matches the working Aiyotickets implementation
     */
    public function handleCallback(Request $request)
    {
        // Log EVERYTHING that comes in
        Log::info('🔔 M-Pesa Callback Received', [
            'full_request' => $request->all(),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        // Log raw input for debugging
        $rawContent = $request->getContent();
        Log::info('🔔 M-Pesa Raw Input', ['raw' => $rawContent]);

        // Write to separate log file for debugging
        file_put_contents(
            storage_path('logs/mpesa_callback.log'),
            now() . PHP_EOL .
            'RAW: ' . $rawContent . PHP_EOL .
            'JSON: ' . json_encode($request->all(), JSON_PRETTY_PRINT) .
            PHP_EOL . "----------------" . PHP_EOL,
            FILE_APPEND
        );

        try {
            $data = $request->all();
            
            // Extract callback data - handle both formats
            $callbackData = $data['Body']['stkCallback'] ?? $data['stkCallback'] ?? null;
            
            if (!$callbackData) {
                Log::error('❌ Invalid callback structure', ['received_data' => $data]);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data'], 400);
            }

            // Log the full callback data
            Log::info('========== CALLBACK FULL DATA ==========');
            Log::info(json_encode($callbackData, JSON_PRETTY_PRINT));
            
            $checkoutRequestId = $callbackData['CheckoutRequestID'] ?? null;
            $merchantRequestId = $callbackData['MerchantRequestID'] ?? null;
            $resultCode = $callbackData['ResultCode'] ?? null;
            $resultDesc = $callbackData['ResultDesc'] ?? null;
            
            Log::info('📊 Callback Details', [
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $merchantRequestId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
            ]);
            
            if (!$checkoutRequestId && !$merchantRequestId) {
                Log::error('❌ No transaction identifiers in callback');
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Missing transaction identifiers'], 400);
            }
            
            // Find the transaction
            $mpesaStk = MpesaStk::where('checkout_request_id', $checkoutRequestId)
                ->orWhere('merchant_request_id', $merchantRequestId)
                ->first();
                
            if (!$mpesaStk) {
                Log::warning('⚠️ Transaction not found in database', [
                    'checkout_request_id' => $checkoutRequestId,
                    'merchant_request_id' => $merchantRequestId
                ]);
                
                $mpesaStk = MpesaStk::create([
                    'checkout_request_id' => $checkoutRequestId,
                    'merchant_request_id' => $merchantRequestId,
                    'response_code' => (string) $resultCode,
                    'response_description' => $resultDesc,
                    'customer_message' => $resultDesc ?? 'Unknown transaction',
                ]);
                
                Log::info('✅ Created new MpesaStk record for unknown transaction', [
                    'id' => $mpesaStk->id
                ]);
            }
            
            // Prepare update data - FULL OBJECT from Safaricom
            $updateData = [
                'response_code' => (string) $resultCode,
                'response_description' => $resultDesc,
                'customer_message' => $resultDesc ?? null,
                // Store the FULL callback object in the metadata field
                'metadata' => array_merge($mpesaStk->metadata ?? [], [
                    'full_callback' => $callbackData,
                    'raw_request' => $data,
                    'processed_at' => now()->toDateTimeString()
                ])
            ];
            
            $receiptNumber = null;
            $amount = null;
            $transactionDate = null;
            $phoneNumber = null;
            
            // ============================================
            // 🔥 PAYMENT SUCCESSFUL - Extract Metadata
            // ============================================
            if ($resultCode == 0 && isset($callbackData['CallbackMetadata'])) {
                Log::info('💰 Payment successful! Processing metadata...');
                
                $metadata = $callbackData['CallbackMetadata'];
                
                if (isset($metadata['Item']) && is_array($metadata['Item'])) {
                    Log::info('📋 Items found', [
                        'items' => $metadata['Item'],
                        'count' => count($metadata['Item'])
                    ]);
                    
                    foreach ($metadata['Item'] as $item) {
                        $name = $item['Name'] ?? null;
                        $value = $item['Value'] ?? null;
                        
                        Log::info('🔍 Processing item', [
                            'name' => $name,
                            'value' => $value,
                            'has_value' => isset($item['Value'])
                        ]);
                        
                        switch ($name) {
                            case 'MpesaReceiptNumber':
                                $receiptNumber = $value;
                                $updateData['mpesa_receipt_number'] = $receiptNumber;
                                Log::info('✅ Receipt number found', ['receipt' => $receiptNumber]);
                                break;
                            case 'TransactionDate':
                                $transactionDate = $value;
                                $updateData['transaction_date'] = date('Y-m-d H:i:s', strtotime($value));
                                Log::info('✅ Transaction date found', ['date' => $transactionDate]);
                                break;
                            case 'Amount':
                                $amount = $value;
                                $updateData['amount'] = (float) $value;
                                Log::info('✅ Amount found', ['amount' => $amount]);
                                break;
                            case 'PhoneNumber':
                                $phoneNumber = $value;
                                $updateData['phone_number'] = (string) $value;
                                Log::info('✅ Phone number found', ['phone' => $phoneNumber]);
                                break;
                            default:
                                Log::info('ℹ️ Unknown metadata item', ['name' => $name, 'value' => $value]);
                                break;
                        }
                    }
                } else {
                    Log::error('❌ No Item array found in metadata', [
                        'metadata' => $metadata
                    ]);
                }
            } elseif ($resultCode == 0) {
                Log::warning('⚠️ Payment successful but no CallbackMetadata');
            } else {
                Log::warning('❌ Payment failed or pending', [
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc
                ]);
            }
            
            // Update the record
            $mpesaStk->update($updateData);
            
            Log::info('✅ MpesaStk updated successfully', [
                'mpesa_stk_id' => $mpesaStk->id,
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'checkout_request_id' => $checkoutRequestId
            ]);
            
            // ============================================
            // 🔥 PROCESS PAYMENT AND UPDATE WALLET
            // ============================================
            if ($resultCode == 0 && $mpesaStk->invoice_id) {
                $this->processSuccessfulPayment($mpesaStk);
            }
            
            // Always return success to M-Pesa
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            
        } catch (\Exception $e) {
            Log::error('❌ Callback processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            
            // Even if we have an error, return success to M-Pesa to avoid retries
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Callback received']);
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

        if ($invoice->status === 'paid') {
            return ['success' => false, 'message' => 'Invoice already paid'];
        }

        DB::beginTransaction();

        try {
            $tenant = $invoice->tenancy?->tenant;
            
            if (!$tenant) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Tenant not found for invoice'];
            }

            $amount = (float) $mpesaStk->amount;
            
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

            // Update invoice
            $invoice->total_paid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
            
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
     * Check transaction status - Frontend polls this
     */
    public function checkTransactionStatus(Request $request, $checkoutRequestId)
    {
        try {
            $transaction = MpesaStk::where('checkout_request_id', $checkoutRequestId)
                ->orWhere('merchant_request_id', $checkoutRequestId)
                ->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Determine status
            $status = 'pending';
            $isReadyForOrder = false;
            
            if ($transaction->response_code === '0') {
                $status = 'completed';
                $isReadyForOrder = true;
            } elseif ($transaction->response_code === '2' || $transaction->response_code === '1032') {
                $status = 'failed';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $transaction->id,
                    'status' => $status,
                    'result_code' => $transaction->response_code,
                    'result_desc' => $transaction->response_description,
                    'receipt_number' => $transaction->mpesa_receipt_number,
                    'amount' => $transaction->amount,
                    'phone_number' => $transaction->phone_number,
                    'transaction_date' => $transaction->transaction_date,
                    'is_ready_for_order' => $isReadyForOrder,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking transaction status'
            ], 500);
        }
    }
}