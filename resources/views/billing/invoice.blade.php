{{-- One scharge bill as a printable invoice (BillingController::show). $inv (invoice + company), $json, $items, $staff --}}
@php
  $cur = $inv->currency ?: 'KES';
  $money = fn ($v) => $cur.' '.number_format((float) $v, 2);
  $total = (float) $inv->amount;
  $sum = $items->sum('amount');
  $paid = $inv->status === 'paid';
  $late = ! $paid && \Illuminate\Support\Carbon::parse($inv->due_date)->lt(now()->startOfDay());
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ \App\Support\InvoiceNo::of($inv->id) }} · scharge</title>
<link rel="icon" href="{{ asset('assets/images/brand/favicon.svg') }}">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --red: #DC2626; --ink: #101828; --muted: #667085; --line: #EAECF0; }
* { box-sizing: border-box; }
body { margin: 0; background: #F2F4F7; font: 14px/1.55 Outfit, system-ui, sans-serif; color: var(--ink); }
.page { max-width: 820px; margin: 28px auto; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: clamp(22px, 4vw, 44px); }
.top { display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; align-items: flex-start; }
.top img { height: 34px; }
h1 { margin: 0; font-size: 26px; letter-spacing: -.02em; }
.muted { color: var(--muted); }
.pill { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 600; }
.pill.due { background: #FEF3F2; color: #B42318; }
.pill.open { background: #FFFAEB; color: #B54708; }
.pill.paid { background: #ECFDF3; color: #027A48; }
.meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 18px 32px; margin: 28px 0; padding: 18px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
.meta .info { text-align: right; }
.plan-name { margin-bottom: 8px; font-weight: 500; } .plan-name .muted { font-weight: 400; font-size: 13px; }
.meta dl { margin: 0; display: grid; grid-template-columns: auto auto; justify-content: end; column-gap: 18px; row-gap: 6px; }
.meta dt { color: var(--muted); font-size: 13px; } .meta dd { margin: 0; }
.meta b { display: block; font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; padding: 10px 0; border-bottom: 1px solid var(--line); }
td { padding: 12px 0; border-bottom: 1px solid #F2F4F7; vertical-align: top; color: var(--muted); }
td.amt, th.amt, td.num, th.num { text-align: right; white-space: nowrap; }
td.num, th.num { padding-left: 14px; }
th.num { color: var(--muted); }
td small { display: block; color: var(--muted); }
.sub td { color: var(--ink); }
.bal td { font-weight: 700; } .bal td.amt { color: {{ $balance > 0 ? '#B42318' : '#027A48' }}; }
.grand td { border-top: 2px solid var(--ink); border-bottom: 0; padding-top: 12px; font-size: 18px; font-weight: 700; color: var(--ink) !important; }
.note { margin-top: 26px; padding: 14px 16px; border-radius: 12px; background: #F9FAFB; color: #344054; }
.cards { max-width: 820px; margin: 28px auto -12px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.cards .back { grid-column: 1 / -1; font-size: 13px; color: var(--muted); text-decoration: none; }
.card { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px; display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.card-head { display: flex; align-items: center; gap: 10px; }
.card-head img { border-radius: 9px; flex: none; }
.card-head b { display: block; font-size: 15px; } .card-head span { font-size: 12.5px; color: var(--muted); }
.pay-amt { font-size: 24px; font-weight: 700; letter-spacing: -.01em; display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px; }
.pay-amt span { font-size: 13px; font-weight: 400; color: var(--muted); } .pay-amt strong { color: var(--red); font-weight: 600; }
.btn.wide { display: flex; justify-content: center; margin-top: auto; }
.paid-note { margin-top: auto; background: #ECFDF3; color: #027A48; border-radius: 10px; padding: 10px 12px; font-size: 14px; }
.share-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.share-btns .btn { text-align: center; }
.lbl { font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; margin-bottom: -6px; }
.pub { display: flex; gap: 6px; }
.link-url { flex: 1; font: 12px/1 ui-monospace, Menlo, monospace; color: #344054; padding: 9px 10px; border: 1px solid var(--line); border-radius: 9px; background: #F9FAFB; min-width: 0; text-overflow: ellipsis; }
.vat-form { display: flex; gap: 6px; align-items: center; margin: 0; font-size: 13px; padding-top: 10px; border-top: 1px solid #F2F4F7; }
.btn.on { background: var(--ink); border-color: var(--ink); color: #fff; }
.btn.icon { display: inline-grid; place-items: center; width: 36px; height: 36px; padding: 0; color: #344054; flex: none; }
.btn.icon:hover { color: var(--red); border-color: #FECACA; }
.btn.xs { padding: 5px 10px; font-size: 12.5px; }
@media (max-width: 680px) { .meta .info { text-align: left; } .meta dl { justify-content: start; } .cards { grid-template-columns: 1fr; } .top > div:last-child { text-align: left !important; } }
.btn.sm { padding: 7px 12px; font-size: 13px; }
.btn { font: inherit; font-weight: 600; border-radius: 10px; padding: 10px 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
.btn.red { background: var(--red); border-color: var(--red); color: #fff; }
@page { size: A4 portrait; margin: 10mm 12mm 10mm; }
@media print {
  body { font-size: 10.5px; line-height: 1.4; }
  .top img { height: 24px; } .top p { margin-top: 6px !important; }
  h1 { font-size: 18px; } .pill { font-size: 9px; padding: 1px 7px; }
  .meta { margin: 10px 0; padding: 8px 0; gap: 8px; flex-wrap: nowrap; } .meta dl { row-gap: 2px; } .meta dt { font-size: 9.5px; }
  .meta b { font-size: 8.5px; margin-bottom: 2px; }
  .page > p.muted { margin: -4px 0 6px !important; }
  th { padding: 6px 0; font-size: 8.5px; } td { padding: 5px 0; }
  .grand td { font-size: 13px; padding-top: 6px; }
  thead { display: table-row-group; }
}
@media print { body { background: #fff; } .page { border: 0; border-radius: 0; margin: 0; padding: 0; max-width: none; box-shadow: none; } .cards, .toast { display: none !important; } tr, .note, .pay { break-inside: avoid; } }
.sp-link { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--red); text-decoration: none; }
.sp-link img { border-radius: 5px; }
.sp-link:hover { text-decoration: underline; }
.payqr { margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--line); display: flex; gap: 16px; align-items: center; }
.payqr .qr { width: 104px; height: 104px; image-rendering: pixelated; border: 1px solid var(--line); border-radius: 10px; padding: 4px; flex: none; }
.full-link { font: 13px/1.3 ui-monospace, Menlo, monospace; color: var(--ink); word-break: break-all; }
.handle { color: var(--red); font-weight: 600; text-decoration: none; }
.toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #101828; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13px; opacity: 0; transition: opacity .2s; pointer-events: none; }
.toast.show { opacity: 1; }
@media print { .payqr { margin-top: 10px; padding-top: 8px; gap: 12px; } .payqr .qr { width: 84px; height: 84px; } .full-link { font-size: 10px; } .payqr .muted, .payqr div, .plan-name .muted, .meta dt { font-size: 9.5px !important; } .plan-name { margin-bottom: 4px; } }
</style>
</head>
<body>
<div class="cards">
  @if (! $public && ! $staff)<a class="back" href="{{ route('billing.index') }}">← Billing</a>@endif
  <section class="card pay-card">
    <div class="card-head"><img src="{{ asset('images/integrations/sharet.svg') }}" alt="" width="36" height="36"><div><b>Sharet Pay</b><span>{{ $paid ? 'Paid'.($inv->paid_at ? ' · '.\Illuminate\Support\Carbon::parse($inv->paid_at)->format('j M Y') : '') : 'M-Pesa or Sharet to Sharet' }}</span></div></div>
    <div class="pay-amt">{{ $money($total) }}@if (! $paid && $handle)<span>to <strong>{{ $handle }}</strong></span>@endif</div>
    @unless ($paid)
      <a class="btn red wide" href="{{ $payUrl }}" target="_blank" rel="noopener">Pay with Sharet Pay</a>
    @else
      <div class="paid-note">Thank you. This invoice is paid.</div>
    @endunless
  </section>
  <section class="card share-card">
    <div class="card-head"><div><b>Invoice {{ \App\Support\InvoiceNo::of($inv->id) }}</b><span>Download, print or share</span></div></div>
    <div class="share-btns">
      <a class="btn" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Download PDF</a>
      <button class="btn" type="button" onclick="window.print()">Print</button>
    </div>
    <label class="lbl" for="pub-link">Public link</label>
    <div class="pub">
      <input id="pub-link" class="link-url" type="text" readonly value="{{ $publicUrl }}" onclick="this.select()">
      <button class="btn icon" type="button" title="Copy public link" aria-label="Copy public link" onclick="copyLink(@js($publicUrl), 'Public link copied')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
    </div>
    @if ($staff && ! $public && ! $paid)
      <form method="POST" action="{{ route('billing.invoice.vat', $inv->id) }}" class="vat-form">
        @csrf
        <span class="muted">VAT</span>
        <button class="btn xs {{ $vat['mode'] === 'none' ? 'on' : '' }}" name="mode" value="none" type="submit">0%</button>
        <button class="btn xs {{ $vat['mode'] === 'exclusive' ? 'on' : '' }}" name="mode" value="exclusive" type="submit">{{ rtrim(rtrim(number_format($pay['vat_rate'] ?? 16, 2), '0'), '.') }}% exclusive</button>
      </form>
    @endif
  </section>
</div>
<div class="page">
  <div class="top">
    <div>
      <img src="{{ asset('assets/images/brand/scharge-logo.svg') }}" alt="scharge">
      <p class="muted" style="margin:10px 0 0">scharge · Sharet Africa<br>info@sharet.africa · https://scharge.sharet.africa
        @if (! empty($pay['kra_pin']))<br>KRA PIN: {{ $pay['kra_pin'] }}@endif
        @if ($vat['mode'] === 'exclusive' && ! empty($pay['vat_number']))<br>VAT No.: {{ $pay['vat_number'] }}@endif</p>
    </div>
    <div style="text-align:right">
      <h1>{{ $vat['mode'] === 'exclusive' ? 'Tax invoice' : 'Invoice' }}</h1>
      <p style="margin:4px 0 6px;font-weight:600">{{ \App\Support\InvoiceNo::of($inv->id) }}</p>
      <span class="pill {{ $paid ? 'paid' : ($late ? 'due' : 'open') }}">{{ $paid ? 'Paid' : ($late ? 'Overdue' : 'Unpaid') }}</span>
      <span class="pill" style="background:#F2F4F7;color:#344054">{{ $vat['mode'] === 'exclusive' ? 'VAT '.rtrim(rtrim(number_format($vat['rate'], 2), '0'), '.').'% exclusive' : 'Non-VAT' }}</span>
    </div>
  </div>

  <div class="meta">
    <div><b>Billed to</b>{{ $inv->company_name }}@if ($inv->company_email)<br><span class="muted">{{ $inv->company_email }}</span>@endif @if ($inv->company_phone)<br><span class="muted">{{ $inv->company_phone }}</span>@endif @if (! empty($inv->company_tax_id))<br><span class="muted">KRA PIN: {{ $inv->company_tax_id }}</span>@endif</div>
    <div class="info">
      <b>Plan</b>
      <div class="plan-name">{{ $json['plan_name'] ?? $inv->plan_name ?? 'scharge subscription' }}@if (! empty($json['price_per_unit']))<br><span class="muted">{{ $cur }} {{ number_format((float) $json['price_per_unit']) }} per unit per month</span>@endif</div>
      <dl>
        <dt>Issued:</dt><dd>{{ \Illuminate\Support\Carbon::parse($inv->created_at)->format('D, d/m/Y') }}</dd>
        <dt>Due:</dt><dd>{{ \Illuminate\Support\Carbon::parse($inv->due_date)->format('d/m/Y') }}</dd>
      </dl>
    </div>
  </div>


  @if (! empty($json['billing_start']) && ! empty($json['billing_end']))
    <p class="muted" style="margin:-10px 0 18px">Period: {{ \Illuminate\Support\Carbon::parse($json['billing_start'])->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($json['billing_end'])->format('j M Y') }}@if (! empty($json['property'])) · {{ $json['property'] }}@endif</p>
  @endif

  <table>
    <thead><tr><th>Description</th><th class="num">Units</th><th class="num">Rate / unit</th><th class="amt">Amount</th></tr></thead>
    <tbody>
      @foreach ($items as $it)
        <tr>
          <td>{{ $it['description'] }}@if ($it['detail'])<small>{{ $it['detail'] }}</small>@endif</td>
          <td class="num">{{ $it['units'] !== null ? number_format($it['units']) : '—' }}</td>
          <td class="num">{{ $it['rate'] !== null ? $cur.' '.number_format($it['rate'], 2) : '—' }}</td>
          <td class="amt">{{ $money($it['amount']) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="sub"><td></td><td></td><td class="num muted">Subtotal{{ $vat['mode'] === 'exclusive' ? ' (excl. VAT)' : '' }}</td><td class="amt">{{ $money($vat['net']) }}</td></tr>
      <tr class="sub"><td></td><td></td><td class="num muted">VAT ({{ $vat['mode'] === 'exclusive' ? rtrim(rtrim(number_format($vat['rate'], 2), '0'), '.') : '0' }}%)</td><td class="amt">{{ $money($vat['tax']) }}</td></tr>
      <tr class="grand"><td></td><td></td><td class="num">Total{{ $vat['mode'] === 'exclusive' ? ' (incl. VAT)' : '' }}</td><td class="amt">{{ $money($total) }}</td></tr>
      <tr class="sub"><td></td><td></td><td class="num muted">Paid @if ($paid && $inv->paid_at) · {{ \Illuminate\Support\Carbon::parse($inv->paid_at)->format('d/m/Y') }}@endif</td><td class="amt">{{ $money($paidAmount) }}</td></tr>
      <tr class="sub bal"><td></td><td></td><td class="num">Balance due</td><td class="amt">{{ $money($balance) }}</td></tr>
    </tfoot>
  </table>

  @unless ($paid)
    <div class="payqr">
      @if ($payQr)<img class="qr" src="{{ $payQr }}" alt="QR code: pay with Sharet Pay" width="104" height="104">@endif
      <div>
        <div class="sp-link"><img src="{{ asset('images/integrations/sharet.svg') }}" alt="" width="18" height="18">Pay with Sharet Pay</div>
        <div class="muted" style="font-size:12.5px;margin:2px 0 6px">Scan the code or open the link: M-Pesa or Sharet to Sharet</div>
        <a class="full-link" href="{{ $payUrl }}" target="_blank" rel="noopener">{{ $payUrl }}</a>
        @if ($handle)<div style="margin-top:4px;font-size:13px">Pay to <a class="handle" href="{{ $payUrl }}" target="_blank" rel="noopener">{{ $handle }}</a> · {{ $money($total) }}</div>@endif
      </div>
    </div>
  @endunless
  @if (! empty($json['note']))<div class="note">{{ $json['note'] }}</div>@endif
</div>
<div class="toast" id="toast"></div>
<script>
function copyLink(url, msg) {
  const done = () => { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 1800); };
  if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done, () => { prompt('Copy this link:', url); }); } else { prompt('Copy this link:', url); }
}
</script>
</body>
</html>
