{{-- scharge invoice as a PDF (BillingController::pdf, dompdf). Same data as billing/invoice; tables only, since dompdf has no flex or grid. --}}
@php
  $cur = $inv->currency ?: 'KES';
  $money = fn ($v) => $cur.' '.number_format((float) $v, 2);
  $total = (float) $inv->amount;
  $paid = $inv->status === 'paid';
  $late = ! $paid && \Illuminate\Support\Carbon::parse($inv->due_date)->lt(now()->startOfDay());
  $rateTxt = rtrim(rtrim(number_format($vat['rate'], 2), '0'), '.');
  $logo = public_path('assets/images/brand/scharge-logo.svg');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 14mm 14mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #101828; line-height: 1.45; }
.muted { color: #667085; }
table { width: 100%; border-collapse: collapse; }
h1 { margin: 0; font-size: 18pt; }
.pill { display: inline-block; border-radius: 8pt; padding: 1pt 7pt; font-size: 8pt; font-weight: bold; }
.due { background: #FEF3F2; color: #B42318; } .open { background: #FFFAEB; color: #B54708; } .paid { background: #ECFDF3; color: #027A48; } .grey { background: #F2F4F7; color: #344054; }
.meta td { vertical-align: top; padding: 7pt 8pt 7pt 0; border-top: 1px solid #EAECF0; border-bottom: 1px solid #EAECF0; }
.lbl { display: block; font-size: 7.5pt; color: #667085; text-transform: uppercase; letter-spacing: .5pt; margin-bottom: 2pt; }
.lines th { text-align: left; font-size: 7.5pt; color: #667085; font-weight: normal; text-transform: uppercase; letter-spacing: .5pt; padding: 7pt 0; border-bottom: 1px solid #EAECF0; }
.lines td { padding: 3.5pt 0; border-bottom: 1px solid #F2F4F7; color: #667085; vertical-align: top; }
.r { text-align: right; white-space: nowrap; padding-left: 10pt !important; }
.totals { width: 52%; margin-left: 48%; margin-top: 10pt; }
.totals td { padding: 3pt 0; }
.grand td { border-top: 2px solid #101828; padding-top: 6pt; font-size: 12pt; font-weight: bold; }
.box { margin-top: 12pt; border: 1px solid #EAECF0; border-radius: 8pt; padding: 10pt 12pt; }
.box td { vertical-align: top; padding: 4pt 10pt 4pt 0; width: 50%; }
.note { margin-top: 14pt; padding: 9pt 11pt; background: #F9FAFB; border-radius: 8pt; color: #344054; }
</style>
</head>
<body>
<table>
  <tr>
    <td style="vertical-align:top">
      @if (is_file($logo))<img src="{{ $logo }}" style="height:26pt" alt="scharge">@else<b style="font-size:16pt;color:#DC2626">scharge</b>@endif
      <div class="muted" style="margin-top:6pt">scharge · Sharet Africa<br>info@sharet.africa · https://scharge.sharet.africa
        @if (! empty($pay['kra_pin']))<br>KRA PIN: {{ $pay['kra_pin'] }}@endif
        @if ($vat['mode'] === 'exclusive' && ! empty($pay['vat_number']))<br>VAT No.: {{ $pay['vat_number'] }}@endif</div>
    </td>
    <td style="vertical-align:top;text-align:right">
      <h1>{{ $vat['mode'] === 'exclusive' ? 'Tax invoice' : 'Invoice' }}</h1>
      <div style="font-weight:bold;margin:3pt 0 5pt">{{ $inv->invoice_number }}</div>
      <span class="pill {{ $paid ? 'paid' : ($late ? 'due' : 'open') }}">{{ $paid ? 'Paid' : ($late ? 'Overdue' : 'Unpaid') }}</span>
      <span class="pill grey">{{ $vat['mode'] === 'exclusive' ? 'VAT '.$rateTxt.'% exclusive' : 'Non-VAT' }}</span>
    </td>
  </tr>
</table>

<table class="meta" style="margin:12pt 0">
  <tr>
    <td style="width:34%"><span class="lbl">Billed to</span>{{ $inv->company_name }}@if ($inv->company_email)<br><span class="muted">{{ $inv->company_email }}</span>@endif @if ($inv->company_phone)<br><span class="muted">{{ $inv->company_phone }}</span>@endif @if (! empty($inv->company_tax_id))<br><span class="muted">KRA PIN: {{ $inv->company_tax_id }}</span>@endif</td>
    <td><span class="lbl">Issued</span>{{ \Illuminate\Support\Carbon::parse($inv->created_at)->format('j M Y') }}</td>
    <td><span class="lbl">Due</span>{{ \Illuminate\Support\Carbon::parse($inv->due_date)->format('j M Y') }}</td>
    <td style="width:30%"><span class="lbl">Plan</span>{{ $json['plan_name'] ?? $inv->plan_name ?? 'scharge subscription' }}@if (! empty($json['price_per_unit']))<br><span class="muted">{{ $cur }} {{ number_format((float) $json['price_per_unit']) }} per unit per month</span>@endif</td>
  </tr>
</table>

<table class="lines">
  <thead><tr><th>Description</th><th class="r">Units</th><th class="r">Rate / unit</th><th class="r">Amount</th></tr></thead>
  <tbody>
    @foreach ($items as $it)
      <tr>
        <td>{{ $it['description'] }}@if ($it['detail'])<br><span style="font-size:8pt">{{ $it['detail'] }}</span>@endif</td>
        <td class="r">{{ $it['units'] !== null ? number_format($it['units']) : '—' }}</td>
        <td class="r">{{ $it['rate'] !== null ? $cur.' '.number_format($it['rate'], 2) : '—' }}</td>
        <td class="r">{{ $money($it['amount']) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<table class="totals">
  <tr><td class="muted">Subtotal{{ $vat['mode'] === 'exclusive' ? ' (excl. VAT)' : '' }}</td><td class="r">{{ $money($vat['net']) }}</td></tr>
  @if ($vat['mode'] === 'exclusive')
    <tr><td class="muted">VAT ({{ $rateTxt }}%)</td><td class="r">{{ $money($vat['tax']) }}</td></tr>
  @else
    <tr><td class="muted">VAT ({{ $rateTxt }}%)</td><td class="r">{{ $money(0) }}</td></tr>
  @endif
  @if ($paid && $inv->paid_at)<tr><td class="muted">Paid</td><td class="r muted">{{ \Illuminate\Support\Carbon::parse($inv->paid_at)->format('j M Y') }}</td></tr>@endif
</table>
<table style="margin-top:6pt"><tr class="grand"><td>Total{{ $vat['mode'] === 'exclusive' ? ' (incl. VAT)' : '' }}</td><td class="r">{{ $money($total) }}</td></tr></table>

@if (! empty($json['note']))<div class="note">{{ $json['note'] }}</div>@endif
@unless ($paid)
  <div class="box">
    <b>Payment details · Sharet Pay</b>
    <table style="margin-top:6pt">
      <tr>
        <td><span class="lbl">1 · scharge to scharge</span>Pay from your scharge wallet: sign in at scharge.sharet.africa, open Billing and choose Pay from wallet. It settles instantly.</td>
        <td><span class="lbl">2 · M-Pesa (STK push)</span>Sign in, open Billing and choose Pay with Sharet Pay: enter the M-Pesa number and approve the prompt on the phone with your PIN.</td>
      </tr>
      <tr>
        <td><span class="lbl">Reference</span>{{ $inv->invoice_number }}<br><span class="muted">Amount: {{ $money($total) }}</span></td>
        <td><span class="lbl">Questions</span>{{ $pay['contact_email'] ?? 'sales@sharet.africa' }}@if (! empty($pay['contact_phone']))<br>{{ $pay['contact_phone'] }}@endif</td>
      </tr>
    </table>
  </div>
@endunless
</body>
</html>
