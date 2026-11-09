<?php

namespace App\Http\Controllers;

use App\Models\MpesaStk;
use App\Models\Payment;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Tenants\Models\Tenant;
use App\Services\Integrations;
use App\Services\MpesaService;
use App\Services\Sharet\SharetCollections;
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
        $invoice = Invoice::with(['tenancy.tenant.user', 'tenancy.unit.property', 'items'])
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

        // Where does this payment go?
        //  1. the landlord's own Daraja app (Integrations) -> STK prompt, money to their paybill;
        //  2. otherwise, once scharge's Sharet account is set up -> a Sharet pay link
        //     (rail: scharge wallet, swept to the landlord; or the landlord's connected account);
        //  3. otherwise scharge's platform Daraja app (the original behaviour).
        if (! Integrations::daraja($invoice->owningCompanyId()) && SharetCollections::available()) {
            try {
                $link = app(SharetCollections::class)->payLink($invoice, $amount, $phone);

                return response()->json([
                    'success' => true,
                    'provider' => 'sharet',
                    'redirect' => $link->pay_url,
                    'message' => 'Opening the secure Sharet payment page…',
                ]);
            } catch (\Throwable $e) {
                Log::error('Sharet pay link failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);

                return response()->json(['success' => false, 'message' => 'We could not start the payment. Please try again shortly.'], 400);
            }
        }

        // Get the callback URL being used
        $callbackUrl = config('mpesa.callbacks.callback_url');
        
        // Debug: Log the callback URL
        Log::info('STK Push Initiated with Callback URL', [
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
            $mpesa = MpesaService::forCompany($invoice->owningCompanyId());
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
            Log::info('STK Push FULL Response', [
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
            $mpesa = MpesaService::forCheckout($checkoutId);
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
     * Settle a successful STK push (idempotent; shared with the Safaricom callback).
     */
    protected function processSuccessfulPayment($mpesaStk)
    {
        return app(\App\Services\MpesaSettlementService::class)->settle($mpesaStk);
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