{{-- Payment receipt on the shared sheet (pdf/sheet). From DocumentPdf::receipt:
     $payment, $invoice (or null), $party, $invoiceNumber, $currency, $fromRows, $title, $number, $docDate --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $method = match ($payment->payment_method) {
        'mpesa_stk', 'mpesa_paybill' => 'M-Pesa', 'sharet' => 'Sharet (M-Pesa / card)', 'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash', 'wallet' => 'Wallet balance', default => ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
    };
    $toWallet = (float) ($payment->meta['amount_to_wallet'] ?? 0);
@endphp
@include('pdf.sheet', ['sheet' => [
    'id' => $payment->id,
    'boxes' => [['label' => 'Received From', 'rows' => array_merge([$party['name']], $party['lines'])], ['label' => 'Received By', 'rows' => $fromRows]],
    'facts' => [['Method', $method], ['Reference', $payment->external_reference ?: $payment->transaction_reference], ['For', $invoiceNumber]],
    'columns' => [['Date', 90, 'center', 0, 0], ['Description', 304, 'left', 4, 0], ['Reference', 91, 'center', 0, 0], ['Amount', 91, 'right', 19.4, 2]],
    'rows' => array_values(array_filter([
        [$docDate, $invoice ? 'Payment for invoice '.$invoiceNumber : 'Payment received', $payment->external_reference ?: '', $money($payment->amount)],
        $toWallet > 0 ? ['', 'Of which kept as credit on your account', '', $money($toWallet)] : null,
    ])),
    'notes' => ['This receipt was generated electronically and needs no signature.', 'Thank you for your payment.'],
    'currency' => $currency,
    'totals' => $invoice
        ? ['small' => [['Invoice total', $money($invoice->total_amount)]], 'main' => ['Received', $money($payment->amount)], 'due' => ['Balance Due', $money(max(0, (float) $invoice->total_amount - (float) $invoice->total_paid))]]
        : ['small' => [], 'main' => ['Received', $money($payment->amount)], 'due' => null],
]])
