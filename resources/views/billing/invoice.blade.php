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
<title>{{ $inv->invoice_number }} · scharge</title>
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
.meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin: 28px 0; padding: 18px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
.meta b { display: block; font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; padding: 10px 0; border-bottom: 1px solid var(--line); }
td { padding: 12px 0; border-bottom: 1px solid #F2F4F7; vertical-align: top; color: var(--muted); }
td.amt, th.amt, td.num, th.num { text-align: right; white-space: nowrap; }
td.num, th.num { padding-left: 14px; }
th.num { color: var(--muted); }
td small { display: block; color: var(--muted); }
.sub td { color: var(--ink); }
.grand td { border-top: 2px solid var(--ink); border-bottom: 0; padding-top: 12px; font-size: 18px; font-weight: 700; color: var(--ink) !important; }
.note { margin-top: 26px; padding: 14px 16px; border-radius: 12px; background: #F9FAFB; color: #344054; }
.actions { max-width: 820px; margin: 0 auto 28px; display: flex; gap: 10px; justify-content: flex-end; padding: 0 4px; }
.btn { font: inherit; font-weight: 600; border-radius: 10px; padding: 10px 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
.btn.red { background: var(--red); border-color: var(--red); color: #fff; }
@page { size: A4 portrait; margin: 14mm 14mm 16mm; }
@media print { body { background: #fff; font-size: 12px; } .page { border: 0; border-radius: 0; margin: 0; padding: 0; max-width: none; box-shadow: none; } .actions, .toast, .pay-card { display: none !important; } tr, .note, .pay { break-inside: avoid; } }
.pay { margin: 0 0 20px; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; }
.pay-card { max-width: 820px; margin: 28px auto -12px; }
.pay h3 { margin: 0 0 10px; font-size: 14px; }
.pay-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
.pay-grid > div > b:first-child { display: block; font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
.toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #101828; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13px; opacity: 0; transition: opacity .2s; pointer-events: none; }
.toast.show { opacity: 1; }
</style>
</head>
<body>
  @unless ($paid)
    <div class="pay pay-card">
      <h3>Payment details · Sharet Pay</h3>
      <div class="pay-grid">
        <div><b>1 · scharge to scharge</b>Pay from your scharge wallet: sign in at scharge.sharet.africa, open <strong>Billing</strong> and choose <strong>Pay from wallet</strong>. It settles instantly.</div>
        <div><b>2 · M-Pesa (STK push)</b>Sign in, open <strong>Billing</strong> and choose <strong>Pay with Sharet Pay</strong>: enter the M-Pesa number and approve the prompt on the phone with your PIN.</div>
        <div><b>Reference</b>{{ $inv->invoice_number }}<br><span class="muted">Amount: {{ $money($total) }}</span></div>
        <div><b>Questions</b>{{ $pay['contact_email'] ?? 'sales@sharet.africa' }}@if (! empty($pay['contact_phone']))<br>{{ $pay['contact_phone'] }}@endif</div>
      </div>
    </div>
  @endunless
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
      <p style="margin:4px 0 6px;font-weight:600">{{ $inv->invoice_number }}</p>
      <span class="pill {{ $paid ? 'paid' : ($late ? 'due' : 'open') }}">{{ $paid ? 'Paid' : ($late ? 'Overdue' : 'Unpaid') }}</span>
      <span class="pill" style="background:#F2F4F7;color:#344054">{{ $vat['mode'] === 'exclusive' ? 'VAT '.rtrim(rtrim(number_format($vat['rate'], 2), '0'), '.').'% exclusive' : 'Non-VAT' }}</span>
    </div>
  </div>

  <div class="meta">
    <div><b>Billed to</b>{{ $inv->company_name }}@if ($inv->company_email)<br><span class="muted">{{ $inv->company_email }}</span>@endif @if ($inv->company_phone)<br><span class="muted">{{ $inv->company_phone }}</span>@endif @if (! empty($inv->company_tax_id))<br><span class="muted">KRA PIN: {{ $inv->company_tax_id }}</span>@endif</div>
    <div><b>Issued</b>{{ \Illuminate\Support\Carbon::parse($inv->created_at)->format('j M Y') }}</div>
    <div><b>Due</b>{{ \Illuminate\Support\Carbon::parse($inv->due_date)->format('j M Y') }}</div>
    <div><b>Plan</b>{{ $json['plan_name'] ?? $inv->plan_name ?? 'scharge subscription' }}@if (! empty($json['price_per_unit']))<br><span class="muted">{{ $cur }} {{ number_format((float) $json['price_per_unit']) }} per unit per month</span>@endif</div>
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
      @if ($paid && $inv->paid_at)<tr class="sub"><td></td><td></td><td class="num muted">Paid</td><td class="amt muted">{{ \Illuminate\Support\Carbon::parse($inv->paid_at)->format('j M Y') }}</td></tr>@endif
    </tfoot>
  </table>

  @if (! empty($json['note']))<div class="note">{{ $json['note'] }}</div>@endif
</div>
@if ($staff && ! $public && ! $paid)
<form method="POST" action="{{ route('billing.invoice.vat', $inv->id) }}" class="actions" style="justify-content:flex-start;align-items:center;gap:8px">
  @csrf
  <span class="muted" style="margin-right:4px">VAT (scharge staff):</span>
  <button class="btn {{ $vat['mode'] === 'none' ? 'red' : '' }}" name="mode" value="none" type="submit">Non-VAT</button>
  <button class="btn {{ $vat['mode'] === 'exclusive' ? 'red' : '' }}" name="mode" value="exclusive" type="submit">VAT exclusive ({{ rtrim(rtrim(number_format($pay['vat_rate'] ?? 16, 2), '0'), '.') }}%)</button>
  <span class="muted">For VAT-registered customers: VAT is added on top of the prices.</span>
</form>
@endif
<div class="actions">
  @if (! $public && ! $staff)<a class="btn" href="{{ route('billing.index') }}">Back to Billing</a>@endif
  @unless ($public)<a class="btn" href="{{ $publicUrl }}" target="_blank" rel="noopener">View public page</a>@endunless
  <button class="btn" type="button" onclick="copyLink(@js($publicUrl), 'Public link copied')">Copy public link</button>
  @unless ($public)<button class="btn" type="button" onclick="copyLink(@js($privateUrl), 'Link copied (sign-in needed)')">Copy this link</button>@endunless
  <a class="btn" href="{{ $pdfUrl }}">Download PDF</a>
  <button class="btn red" type="button" onclick="window.print()">Print (A4)</button>
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
