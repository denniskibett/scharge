<?php

namespace App\Services\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Every Pay press on a bill (website or app) and how it ended (docs/APP_DATA.md): the M-Pesa prompt was sent,
 * the tenant paid, cancelled, typed a wrong PIN, had too little money, did not answer; or the Sharet page opened
 * and was paid. Shows why rent is not paid, not only what was.
 */
class PaymentAttempts
{
    /** Safaricom STK result codes → outcome. */
    public const RESULTS = ['0' => 'paid', '1032' => 'cancelled', '2001' => 'wrong_pin', '1' => 'no_funds', '1037' => 'timeout', '1036' => 'timeout', '1025' => 'failed', '9999' => 'failed'];

    public const LABELS = ['started' => 'Pressed Pay', 'prompt_sent' => 'Prompt sent, no answer yet', 'redirected' => 'Opened the payment page', 'paid' => 'Paid',
        'cancelled' => 'Cancelled on the phone', 'wrong_pin' => 'Wrong M-Pesa PIN', 'no_funds' => 'Not enough money', 'timeout' => 'Did not answer the prompt', 'failed' => 'Could not start'];

    public static function ready(): bool
    {
        return Schema::hasTable('payment_attempts');
    }

    /** Wrap the public Pay button: record the press, run it, record how it started. */
    public function around($invoice, float $amount, string $channel, \Closure $pay)
    {
        $id = null;
        if (self::ready() && $invoice) {
            $id = DB::table('payment_attempts')->insertGetId(['company_id' => $invoice->owningCompanyId(), 'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenancy?->tenant_id, 'channel' => $channel, 'amount' => round($amount, 2), 'status' => 'started',
                'created_at' => now(), 'updated_at' => now()]);
        }
        $response = $pay();
        if ($id && $response instanceof JsonResponse) {
            $body = $response->getData(true);
            $update = match (true) {
                ! ($body['success'] ?? false) => ['status' => 'failed', 'reason' => mb_substr((string) ($body['message'] ?? ''), 0, 255), 'finished_at' => now()],
                isset($body['checkout_request_id']) => ['status' => 'prompt_sent', 'method' => 'stk', 'checkout_request_id' => $body['checkout_request_id']],
                isset($body['redirect']) => ['status' => 'redirected', 'method' => 'sharet',
                    'sharet_request_id' => DB::table('sharet_payment_requests')->where('invoice_id', $invoice->id)->where('pay_url', $body['redirect'])->value('id')],
                default => [],
            };
            DB::table('payment_attempts')->where('id', $id)->update($update + ['updated_at' => now()]);
        }

        return $response;
    }

    /** An M-Pesa prompt got its answer (MpesaStk updated). */
    public static function stkResult(?string $checkoutId, ?string $code, ?string $desc): void
    {
        if (! self::ready() || ! $checkoutId || $code === null || $code === '') {
            return;
        }
        try {
            DB::table('payment_attempts')->where('checkout_request_id', $checkoutId)->whereIn('status', ['started', 'prompt_sent'])->update([
                'status' => self::RESULTS[$code] ?? 'failed', 'result_code' => mb_substr($code, 0, 10), 'reason' => $desc ? mb_substr($desc, 0, 255) : null,
                'finished_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Payment attempt not updated', ['error' => $e->getMessage()]);
        }
    }

    /** A Sharet pay link changed status (SharetPaymentRequest updated). */
    public static function sharetResult(int $requestId, string $status): void
    {
        if (! self::ready() || ! in_array($status, ['paid', 'expired', 'failed', 'cancelled'], true)) {
            return;
        }
        DB::table('payment_attempts')->where('sharet_request_id', $requestId)->where('status', 'redirected')->update([
            'status' => $status === 'paid' ? 'paid' : ($status === 'expired' ? 'timeout' : 'failed'), 'finished_at' => now(), 'updated_at' => now()]);
    }
}
