<?php

namespace App\Http\Controllers;

use App\Models\MpesaStk;
use App\Services\MpesaService;
use App\Services\MpesaSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    private $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Safaricom STK push callback (POST /pay).
     *
     * Always acknowledges with ResultCode 0 so Safaricom stops retrying; failures
     * are flagged on the MpesaStk row for reconciliation instead.
     */
    public function handleCallback(Request $request, MpesaSettlementService $settlement)
    {
        $allowed = config('mpesa.callback_ips', []);
        if (! empty($allowed) && ! in_array($request->ip(), $allowed, true)) {
            Log::warning('M-Pesa callback rejected: IP not allowed', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Forbidden'], 403);
        }

        $callback = $request->input('Body.stkCallback') ?? $request->input('stkCallback');

        if (! is_array($callback)) {
            Log::warning('M-Pesa callback with invalid structure', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback data'], 400);
        }

        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $merchantRequestId = $callback['MerchantRequestID'] ?? null;
        $resultCode = isset($callback['ResultCode']) ? (string) $callback['ResultCode'] : null;
        $resultDesc = $callback['ResultDesc'] ?? null;

        Log::info('M-Pesa callback received', [
            'checkout_request_id' => $checkoutRequestId,
            'result_code' => $resultCode,
            'ip' => $request->ip(),
        ]);

        try {
            $stk = MpesaStk::query()
                ->when($checkoutRequestId, fn ($q) => $q->where('checkout_request_id', $checkoutRequestId))
                ->when(! $checkoutRequestId && $merchantRequestId, fn ($q) => $q->where('merchant_request_id', $merchantRequestId))
                ->first();

            if (! $stk) {
                // Never create rows from unsolicited callbacks; we only settle STK pushes we started.
                Log::warning('M-Pesa callback for unknown transaction', [
                    'checkout_request_id' => $checkoutRequestId,
                    'merchant_request_id' => $merchantRequestId,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $update = [
                'response_code' => $resultCode,
                'response_description' => $resultDesc,
                'customer_message' => $resultDesc,
                'metadata' => array_merge($stk->metadata ?? [], [
                    'full_callback' => $callback,
                    'processed_at' => now()->toDateTimeString(),
                ]),
            ];

            if ($resultCode === '0') {
                foreach ($callback['CallbackMetadata']['Item'] ?? [] as $item) {
                    $value = $item['Value'] ?? null;
                    match ($item['Name'] ?? null) {
                        'MpesaReceiptNumber' => $update['mpesa_receipt_number'] = $value,
                        'TransactionDate' => $update['transaction_date'] = $value ? date('Y-m-d H:i:s', strtotime((string) $value)) : null,
                        'Amount' => $update['amount'] = (float) $value,
                        'PhoneNumber' => $update['phone_number'] = (string) $value,
                        default => null,
                    };
                }
            }

            // A duplicate callback must not overwrite a receipt we already have.
            if ($stk->mpesa_receipt_number && $stk->isSuccessful()) {
                unset($update['mpesa_receipt_number'], $update['amount'], $update['response_code']);
            }

            $stk->update($update);

            if ($stk->isSuccessful() && $stk->invoice_id) {
                $settlement->settle($stk);
            }
        } catch (\Throwable $e) {
            Log::error('M-Pesa callback processing error', [
                'checkout_request_id' => $checkoutRequestId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

}