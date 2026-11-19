{{-- scharge invoice as a PDF (BillingController::pdf, dompdf). Same data as billing/invoice; tables only, since dompdf has no flex or grid. --}}
@php
  $cur = $inv->currency ?: 'KES';
  $money = fn ($v) => $cur.' '.number_format((float) $v, 2);
  $total = (float) $inv->amount;
  $paid = $inv->status === 'paid';
  $late = ! $paid && \Illuminate\Support\Carbon::parse($inv->due_date)->lt(now()->startOfDay());
  $rateTxt = rtrim(rtrim(number_format($vat['rate'], 2), '0'), '.');
  $logo = public_path('assets/images/brand/scharge-logo.png');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@font-face { font-family: 'Outfit'; font-weight: normal; src: url('{{ resource_path('fonts/outfit/Outfit-Regular.ttf') }}') format('truetype'); }
@font-face { font-family: 'Outfit'; font-weight: bold; src: url('{{ resource_path('fonts/outfit/Outfit-Bold.ttf') }}') format('truetype'); }
@page { size: A4 portrait; margin: 10mm 12mm 10mm; }
body { font-family: 'Outfit', DejaVu Sans, sans-serif; font-size: 9.5pt; color: #101828; line-height: 1.25; }
.muted { color: #667085; }
table { width: 100%; border-collapse: collapse; }
h1 { margin: 0; font-size: 18pt; }
.pill { display: inline-block; border-radius: 8pt; padding: 1pt 7pt; font-size: 8pt; font-weight: bold; }
.due { background: #FEF3F2; color: #B42318; } .open { background: #FFFAEB; color: #B54708; } .paid { background: #ECFDF3; color: #027A48; } .grey { background: #F2F4F7; color: #344054; }
.meta > tr > td, .meta > tbody > tr > td { vertical-align: top; padding: 5pt 8pt 5pt 0; border-top: 1px solid #EAECF0; border-bottom: 1px solid #EAECF0; }
.kv { width: auto; margin-left: auto; } .kv td { padding: 0.5pt 0; border: 0; vertical-align: top; text-align: right; }
.kv td.k { color: #667085; padding-right: 12pt; white-space: nowrap; }
.lbl { display: block; font-size: 7.5pt; color: #667085; text-transform: uppercase; letter-spacing: .5pt; margin-bottom: 2pt; }
.lines th { text-align: left; font-size: 7.5pt; color: #667085; font-weight: normal; text-transform: uppercase; letter-spacing: .5pt; padding: 5pt 0; border-bottom: 1px solid #EAECF0; }
.lines td { padding: 2.6pt 0; border-bottom: 1px solid #F2F4F7; color: #667085; vertical-align: top; }
.r { text-align: right; white-space: nowrap; padding-left: 10pt !important; }
.lines .sub td { color: #101828; padding: 1pt 0; border-bottom: 0; }
.lines .first td { padding-top: 6pt; }

.lines .grand td { border-top: 2px solid #101828; border-bottom: 0; padding: 4pt 0 0; line-height: 1.1; font-size: 12pt; font-weight: bold; color: {{ $balance > 0 ? '#B42318' : '#027A48' }}; }
.box { margin: 0 0 6pt; border: 1px solid #EAECF0; border-radius: 6pt; padding: 6pt 10pt; }
.box td { vertical-align: top; padding: 1pt 10pt 0 0; font-size: 8pt; line-height: 1.3; }
.lines-wrap { position: relative; }
.qr-abs { position: absolute; left: 0; bottom: 0; width: 56%; }
.qrt { width: auto; } .qrt td { border: 0 !important; padding: 0 6pt 0 0 !important; vertical-align: bottom; color: #101828; }
.payline { margin-top: 10pt; padding-top: 6pt; border-top: 1px solid #EAECF0; color: #344054; font-size: 9pt; }
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
      <div style="font-weight:bold;margin:3pt 0 5pt">{{ \App\Support\InvoiceNo::of($inv->id) }}</div>
      <span class="pill {{ $paid ? 'paid' : ($late ? 'due' : 'open') }}">{{ $paid ? 'Paid' : ($late ? 'Overdue' : 'Unpaid') }}</span>
      <span class="pill grey">{{ $vat['mode'] === 'exclusive' ? 'VAT '.$rateTxt.'% exclusive' : 'Non-VAT' }}</span>
    </td>
  </tr>
</table>

<table class="meta" style="margin:6pt 0 8pt">
  <tr>
    <td style="width:50%"><span class="lbl">Billed to</span>{{ $inv->company_name }}@if ($inv->company_email)<br><span class="muted">{{ $inv->company_email }}</span>@endif @if ($inv->company_phone)<br><span class="muted">{{ $inv->company_phone }}</span>@endif @if (! empty($inv->company_tax_id))<br><span class="muted">KRA PIN: {{ $inv->company_tax_id }}</span>@endif</td>
    <td style="text-align:right;padding-right:0">
      <span class="lbl">Plan</span>
      <div style="margin-bottom:3pt">{{ $json['plan_name'] ?? $inv->plan_name ?? 'scharge subscription' }}@if (! empty($json['price_per_unit']))<br><span class="muted" style="font-size:8.5pt">{{ $cur }} {{ number_format((float) $json['price_per_unit']) }} per unit per month</span>@endif</div>
      <table class="kv">
        <tr><td class="k">Issued:</td><td class="v">{{ \Illuminate\Support\Carbon::parse($inv->created_at)->format('D, d/m/Y') }}</td></tr>
        <tr><td class="k">Due:</td><td class="v">{{ \Illuminate\Support\Carbon::parse($inv->due_date)->format('d/m/Y') }}</td></tr>
      </table>
    </td>
  </tr>
</table>

<div class="lines-wrap">
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
  <tfoot>
    <tr class="sub first"><td>&nbsp;</td><td>&nbsp;</td><td class="r">Subtotal{{ $vat['mode'] === 'exclusive' ? ' (excl. VAT)' : '' }}</td><td class="r">{{ $money($vat['net']) }}</td></tr>
    <tr class="sub"><td>&nbsp;</td><td>&nbsp;</td><td class="r">VAT ({{ $vat['mode'] === 'exclusive' ? $rateTxt : '0' }}%)</td><td class="r">{{ $money($vat['tax']) }}</td></tr>
    <tr class="sub tot"><td>&nbsp;</td><td>&nbsp;</td><td class="r">Total{{ $vat['mode'] === 'exclusive' ? ' (incl. VAT)' : '' }}</td><td class="r">{{ $money($total) }}</td></tr>
    <tr class="sub"><td>&nbsp;</td><td>&nbsp;</td><td class="r">Paid @if ($paid && $inv->paid_at) · {{ \Illuminate\Support\Carbon::parse($inv->paid_at)->format('d/m/Y') }}@endif</td><td class="r">{{ $money($paidAmount) }}</td></tr>
    <tr class="grand"><td>&nbsp;</td><td>&nbsp;</td><td class="r">Balance</td><td class="r">{{ $money($balance) }}</td></tr>
  </tfoot>
</table>
<div class="qr-abs">@unless ($paid)<table class="qrt"><tr>@if ($payQr)<td style="width:58pt"><img src="{{ $payQr }}" style="width:54pt;height:54pt" alt=""></td>@endif<td><div>@if (is_file(public_path('images/integrations/sharet.png')))<img src="{{ public_path('images/integrations/sharet.png') }}" style="width:11pt;height:11pt;vertical-align:-2pt;margin-right:4pt" alt="">@endif<b style="color:#DC2626">Pay with Sharet Pay</b></div>
        <div class="muted" style="font-size:8pt;margin:1pt 0 4pt">Scan the code or open the link: M-Pesa or Sharet to Sharet</div>
        <a href="{{ $payUrl }}" style="color:#101828;text-decoration:none;font-size:9pt">{{ $payUrl }}</a>
        @if ($handle)<div style="margin-top:2pt;font-size:8.5pt">Pay to <a href="{{ $payUrl }}" style="color:#DC2626;font-weight:bold;text-decoration:none">{{ $handle }}</a> · {{ $money($total) }}</div>@endif</td></tr></table>@endunless</div>
</div>

@unless ($paid)
@endunless
@if (! empty($json['note']))<div class="note">{{ $json['note'] }}</div>@endif

</body>
</html>
