{{-- Public invoice page once the bill is fully paid, in the website's design. --}}
@php
  $tenancy = $invoice->tenancy;
  $unit = $tenancy?->unit;
  $property = $unit?->property;
  $month = $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : null;
@endphp
@component('public.partials.invoice-shell', ['title' => 'Invoice #'.$invoice->id.' paid'])
<section class="pay-card">
  <header class="pay-head">
    <div class="pay-head-row">
      <span class="pay-kicker">Invoice #{{ $invoice->id }}{{ $month ? ' · '.$month : '' }}</span>
      <span class="pay-pill pay-pill--paid">Paid</span>
    </div>
    <div class="pay-amount">
      <div class="label">Amount paid</div>
      <div class="value"><small>KES</small>{{ number_format($invoice->total_amount, 2) }}</div>
    </div>
  </header>
  <div class="pay-body">
    <div class="pay-done">
      <span class="tick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></span>
      <h2>This bill is fully paid</h2>
      <p>Thank you. Nothing more to pay on this invoice.</p>
    </div>
    <dl class="pay-rows">
      <dt>Tenant</dt><dd>{{ $tenancy?->tenant?->user?->name ?? '–' }}</dd>
      @if ($property)<dt>Property</dt><dd>{{ $property->name }}</dd>@endif
      @if ($unit)<dt>Unit</dt><dd>{{ $unit->unit_number }}</dd>@endif
      <div class="total" style="display:contents"><dt>Total</dt><dd>KES {{ number_format($invoice->total_amount, 2) }}</dd></div>
    </dl>
  </div>
</section>
@endcomponent
