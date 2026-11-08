<?php

namespace App\Services;

use App\Modules\Payments\Models\Invoice;
use App\Models\MpesaStk;
use App\Models\Payment;
use App\Services\InvoicePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a successful STK push into a Payment against a tenant invoice.
 *
 * Used by both the Safaricom callback and the public invoice status poll, so
 * it must be safe to call more than once for the same STK record:
 * the STK row is locked and an existing Payment for the same checkout ID or
 * M-Pesa receipt short-circuits.
 *
 * Wallet ledger: the full amount is deposited (money in), then the part that
 * covers the invoice is withdrawn against it (money applied). Anything over
 * the invoice balance stays in the wallet as tenant credit. The invoice is
 * never "paid" and the wallet credited for the same shillings.
 */
class MpesaSettlementService
{
    public function settle(MpesaStk $stk): array
    {
        if (! $stk->isSuccessful() || ! $stk->invoice_id) {
            return ['success' => false, 'message' => 'Nothing to settle'];
        }

        try {
            return DB::transaction(function () use ($stk) {
                /** @var MpesaStk $stk */
                $stk = MpesaStk::whereKey($stk->id)->lockForUpdate()->firstOrFail();

                $alreadySettled = Payment::where('transaction_reference', $stk->checkout_request_id)
                    ->when($stk->mpesa_receipt_number, fn ($q) => $q->orWhere('external_reference', $stk->mpesa_receipt_number))
                    ->exists();

                if ($alreadySettled) {
                    return ['success' => false, 'message' => 'Payment already processed'];
                }

                $invoice = Invoice::whereKey($stk->invoice_id)->lockForUpdate()->first();
                $tenant = $invoice?->tenancy?->tenant;

                if (! $invoice || ! $tenant) {
                    $this->flag($stk, 'invoice_or_tenant_missing');

                    return ['success' => false, 'message' => 'Invoice or tenant not found'];
                }

                $payment = app(InvoicePaymentService::class)->apply($invoice, (float) $stk->amount, [
                    'method' => 'mpesa_stk',
                    'source' => 'public_link',
                    'transaction_reference' => $stk->checkout_request_id,
                    'external_reference' => $stk->mpesa_receipt_number,
                    'label' => "M-Pesa {$stk->mpesa_receipt_number}",
                    'meta' => [
                        'mpesa_stk_id' => $stk->id,
                        'mpesa_receipt' => $stk->mpesa_receipt_number,
                        'transaction_date' => $stk->transaction_date,
                        'phone_number' => $stk->phone_number,
                        'merchant_request_id' => $stk->merchant_request_id,
                    ],
                ]);

                Log::info('M-Pesa payment settled', [
                    'stk_id' => $stk->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'to_wallet' => $payment->meta['amount_to_wallet'] ?? 0,
                ]);

                return ['success' => true, 'payment' => $payment, 'invoice' => $invoice];
            });
        } catch (\Throwable $e) {
            // The STK row (with receipt) is already saved, so a failed settlement
            // can be retried; flag it so it shows up for reconciliation.
            $this->flag($stk, 'settlement_failed: '.$e->getMessage());
            Log::error('M-Pesa settlement failed', ['stk_id' => $stk->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Settlement failed'];
        }
    }

    private function flag(MpesaStk $stk, string $reason): void
    {
        $stk->forceFill([
            'metadata' => array_merge($stk->metadata ?? [], [
                'needs_reconciliation' => true,
                'reconciliation_reason' => $reason,
                'flagged_at' => now()->toDateTimeString(),
            ]),
        ])->save();
    }
}
