<?php

namespace App\Services;

use App\Modules\Payments\Models\Invoice;
use App\Models\Payment;

/**
 * Applies money that has already arrived (M-Pesa STK, Sharet pay link, …) to a tenant invoice.
 * Call inside a DB transaction with the invoice row locked; callers handle idempotency.
 *
 * Wallet ledger: the full amount is deposited (money in), then the part that covers the
 * invoice is withdrawn against it (money applied). Overpayment stays as wallet credit, so the
 * invoice is never marked paid AND the wallet credited for the same shillings.
 */
class InvoicePaymentService
{
    /**
     * @param  array{method: string, source: string, transaction_reference: ?string, external_reference: ?string, label: string, meta?: array}  $p
     */
    public function apply(Invoice $invoice, float $amount, array $p): Payment
    {
        $tenant = $invoice->tenancy?->tenant;
        if (! $tenant) {
            throw new \RuntimeException("Invoice {$invoice->id} has no tenant");
        }

        $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->total_paid);
        $toInvoice = min($amount, $outstanding);
        $toCredit = $amount - $toInvoice;
        $balanceBefore = (float) $tenant->balance;
        $ledgerMeta = ['source' => $p['source'], 'invoice_id' => $invoice->id, 'reference' => $p['external_reference']];

        $tenant->deposit($amount, ['description' => "{$p['label']} received"] + $ledgerMeta);
        if ($toInvoice > 0) {
            $tenant->withdraw($toInvoice, ['description' => "Applied to invoice #".($invoice->invoice_number ?? $invoice->id)] + $ledgerMeta);
        }
        $tenant->refresh();

        $payment = Payment::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'payment_method' => $p['method'],
            'source' => $p['source'],
            'amount' => $amount,
            'wallet_balance_before' => $balanceBefore,
            'wallet_balance_after' => (float) $tenant->balance,
            'transaction_reference' => $p['transaction_reference'],
            'external_reference' => $p['external_reference'],
            'status' => 'completed',
            'is_reconciled' => true,
            'reconciled_at' => now(),
            'meta' => array_merge($p['meta'] ?? [], [
                'amount_received' => $amount,
                'amount_to_invoice' => $toInvoice,
                'amount_to_wallet' => $toCredit,
                'paid_at' => now()->toISOString(),
            ]),
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

        return $payment;
    }
}
