{{-- Tenant invoice on the shared sheet (pdf/sheet). From DocumentPdf::invoice:
     $invoice, $lines [type, name, total], $party, $facts, $payNotes, $currency, $fromRows, $title, $number, $docDate --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $total = (float) $invoice->total_amount;
    $paid = min($total, (float) $invoice->total_paid);
    $balance = max(0, round($total - $paid, 2));
@endphp
@include('pdf.sheet', ['sheet' => [
    'id' => $invoice->id,
    'boxes' => [['label' => 'Bill To', 'rows' => array_merge([$party['name']], $party['lines'])], ['label' => 'From', 'rows' => $fromRows]],
    'facts' => $facts,
    'columns' => [['Item', 90, 'left', 4, 0], ['Description', 395, 'left', 4, 0], ['Amount', 91, 'right', 19.4, 2]],
    'rows' => array_map(fn ($l) => [$l['type'], $l['name'], $money($l['total'])], $lines),
    'notes' => $payNotes,
    'currency' => $currency,
    'totals' => [
        'small' => array_values(array_filter([$paid > 0 ? ['Paid', $money($paid)] : null])),
        'main' => ['Total', $money($total)],
        'due' => ['Balance Due', $money($balance)],
    ],
]])
