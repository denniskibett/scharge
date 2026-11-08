<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MpesaStk;
use App\Models\Payment;
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

                $amount = (float) $stk->amount;
                $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->total_paid);
                $toInvoice = min($amount, $outstanding);
                $toCredit = $amount - $toInvoice;

                $balanceBefore = (float) $tenant->balance;

                $tenant->deposit($amount, [
                    'description' => "M-Pesa {$stk->mpesa_receipt_number} received",
                    'source' => 'mpesa_stk',
                    'invoice_id' => $invoice->id,
                    'mpesa_receipt' => $stk->mpesa_receipt_number,
                    'checkout_request_id' => $stk->checkout_request_id,
                ]);

                if ($toInvoice > 0) {
                    $tenant->withdraw($toInvoice, [
                        'description' => "Applied to invoice #{$invoice->invoice_number}",
                        'source' => 'mpesa_stk',
                        'invoice_id' => $invoice->id,
                        'mpesa_receipt' => $stk->mpesa_receipt_number,
                    ]);
                }

                $tenant->refresh();

                $payment = Payment::create([
                    'tenant_id' => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'payment_method' => 'mpesa_stk',
                    'source' => 'public_link',
                    'amount' => $amount,
                    'wallet_balance_before' => $balanceBefore,
                    'wallet_balance_after' => (float) $tenant->balance,
                    'transaction_reference' => $stk->checkout_request_id,
                    'external_reference' => $stk->mpesa_receipt_number,
                    'status' => 'completed',
                    'is_reconciled' => true,
                    'reconciled_at' => now(),
                    'meta' => [
                        'mpesa_stk_id' => $stk->id,
                        'mpesa_receipt' => $stk->mpesa_receipt_number,
                        'amount_received' => $amount,
                        'amount_to_invoice' => $toInvoice,
                        'amount_to_wallet' => $toCredit,
                        'transaction_date' => $stk->transaction_date,
                        'phone_number' => $stk->phone_number,
                        'merchant_request_id' => $stk->merchant_request_id,
                        'paid_at' => now()->toISOString(),
                    ],
                ]);

                // Overpayments sit in the wallet as credit, so cap what the invoice shows as paid.
                $invoice->total_paid = min(
                    (float) $invoice->total_amount,
                    (float) $invoice->payments()->where('status', 'completed')->sum('amount')
                );
                $invoice->status = $invoice->total_paid >= $invoice->total_amount
                    ? 'paid'
                    : ($invoice->total_paid > 0 ? 'partial' : $invoice->status);
                $invoice->save();

                Log::info('M-Pesa payment settled', [
                    'stk_id' => $stk->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'to_invoice' => $toInvoice,
                    'to_wallet' => $toCredit,
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
