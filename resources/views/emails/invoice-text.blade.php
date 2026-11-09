@php
  $tenancy = $invoice->tenancy;
  $first = \Illuminate\Support\Str::of($tenancy?->tenant?->user?->name ?? '')->before(' ')->trim()->value() ?: 'there';
  $due = max(0, (float) $invoice->total_amount - (float) $invoice->total_paid);
  $isPaid = $kind === 'paid';
  $figure = $isPaid ? (float) ($amount ?? $invoice->total_amount) : (float) ($amount ?? $due);
  $base = rtrim(preg_replace('#^http://#', 'https://', (string) config('app.url')), '/');
@endphp
{{ $isPaid ? 'Thank you, '.$first.'.' : 'Hi '.$first.', your bill is ready.' }}

{{ \App\Mail\InvoiceEmail::subjectFor($invoice) }}
{{ $isPaid ? 'Amount paid' : 'Amount due' }}: KES {{ number_format($figure, 2) }}
@foreach ($invoice->items->filter(fn ($i) => (float) $i->amount != 0) as $item)
- {{ $item->description ?: ucfirst(str_replace('_', ' ', (string) $item->item_type)) }}: KES {{ number_format($item->amount, 2) }}
@endforeach
@if ($isPaid)
This bill is fully paid. Keep this email as your receipt.@if ($reference) Transaction ID: {{ $reference }}@endif

@elseif ($payUrl)
Pay securely: {{ $payUrl }}

@endif
View this bill: {{ $base }}/public/invoice/{{ $invoice->id }}

scharge · Sharet Africa · Nairobi, Kenya
