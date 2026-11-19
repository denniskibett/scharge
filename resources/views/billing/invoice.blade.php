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
.links { max-width: 820px; margin: 28px auto -12px; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 16px 18px; display: grid; gap: 14px; }
.sp-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.sp-row img { border-radius: 10px; flex: none; }
.sp-text { flex: 1; min-width: 180px; } .sp-text b { display: block; font-size: 15px; } .sp-text span { font-size: 13px; color: var(--muted); } .sp-text strong { color: var(--red); font-weight: 600; }
.sp-opts { display: flex; gap: 8px; flex-wrap: wrap; }
.link-list { display: grid; gap: 6px; padding-top: 12px; border-top: 1px solid #F2F4F7; }
.link-row { display: grid; grid-template-columns: 96px 1fr auto auto; gap: 6px; align-items: center; }
.link-lbl { font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
.link-url { font: 12px/1 ui-monospace, Menlo, monospace; color: #344054; padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; background: #F9FAFB; min-width: 0; width: 100%; text-overflow: ellipsis; }
.link-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding-top: 12px; border-top: 1px solid #F2F4F7; }
.vat-form { display: flex; gap: 6px; align-items: center; margin: 0; font-size: 13px; }
.btn.on { background: var(--ink); border-color: var(--ink); color: #fff; }
.btn.icon { display: inline-grid; place-items: center; width: 34px; height: 34px; padding: 0; color: #344054; }
.btn.icon:hover { color: var(--red); border-color: #FECACA; }
.btn.sm { padding: 7px 12px; font-size: 13px; }
.pm { position: fixed; inset: 0; background: rgb(0 0 0 / .5); display: grid; place-items: center; padding: 16px; z-index: 50; animation: fade .15s ease; }
.pm[hidden] { display: none; }
.pm-box { position: relative; width: min(520px, 100%); height: min(720px, calc(100vh - 32px)); background: #F2F4F7; border-radius: 20px; overflow: hidden; box-shadow: 0 24px 60px -20px rgb(0 0 0 / .45); animation: rise .2s cubic-bezier(.2,.7,.2,1); }
.pm-box iframe { width: 100%; height: 100%; border: 0; display: block; }
.pm-x { position: absolute; top: 10px; right: 12px; z-index: 2; width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--line); background: #fff; font-size: 20px; line-height: 1; cursor: pointer; color: #344054; }
@keyframes fade { from { opacity: 0; } } @keyframes rise { from { opacity: 0; transform: translateY(10px) scale(.98); } }
@media (max-width: 640px) { .link-row { grid-template-columns: 1fr auto auto; } .link-lbl { grid-column: 1 / -1; } }
.btn { font: inherit; font-weight: 600; border-radius: 10px; padding: 10px 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
.btn.red { background: var(--red); border-color: var(--red); color: #fff; }
@page { size: A4 portrait; margin: 14mm 14mm 16mm; }
@media print { body { background: #fff; font-size: 12px; } .page { border: 0; border-radius: 0; margin: 0; padding: 0; max-width: none; box-shadow: none; } .links, .toast, .pm { display: none !important; } tr, .note, .pay { break-inside: avoid; } }
.sp-link { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--red); text-decoration: none; }
.sp-link img { border-radius: 5px; }
.sp-link:hover { text-decoration: underline; }
.payline { margin-top: 22px; padding-top: 14px; border-top: 1px solid var(--line); display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; color: #344054; font-size: 13px; }
.toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #101828; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13px; opacity: 0; transition: opacity .2s; pointer-events: none; }
.toast.show { opacity: 1; }
</style>
</head>
<body>
@php
  $publicPdf = \Illuminate\Support\Facades\URL::signedRoute('billing.invoice.public.pdf', ['invoice' => $inv->id]);
  $links = array_values(array_filter([
    ['Public link', $publicUrl, 'Anyone with this link can view the invoice'],
    $public ? null : ['Sign-in link', $privateUrl, 'Owner, accountant or scharge staff'],
    ['PDF link', $publicPdf, 'Downloads the PDF, no sign-in'],
  ]));
@endphp
<div class="links">
  @unless ($paid)
    <div class="sp-row">
      <img src="{{ asset('images/integrations/sharet.svg') }}" alt="Sharet Pay" width="40" height="40">
      <div class="sp-text"><b>Sharet Pay</b><span>Pay {{ $money($total) }}@if ($handle) to <strong>{{ $handle }}</strong>@endif</span></div>
      <div class="sp-opts">
        <a class="btn sm" href="{{ $payUrl }}#mpesa" target="_blank" rel="noopener" data-pay>M-Pesa STK push</a>
        <a class="btn sm red" href="{{ $payUrl }}" target="_blank" rel="noopener" data-pay>Sharet to Sharet</a>
      </div>
    </div>
  @else
    <div class="sp-row"><img src="{{ asset('images/integrations/sharet.svg') }}" alt="" width="40" height="40"><div class="sp-text"><b>Paid</b><span>@if ($inv->paid_at){{ \Illuminate\Support\Carbon::parse($inv->paid_at)->format('j M Y') }} · @endif Thank you</span></div></div>
  @endunless
  <div class="link-list">
    @foreach ($links as [$label, $url, $hint])
      <div class="link-row" title="{{ $hint }}">
        <span class="link-lbl">{{ $label }}</span>
        <input class="link-url" type="text" readonly value="{{ $url }}" onclick="this.select()" aria-label="{{ $label }}">
        <button class="btn icon" type="button" title="Copy {{ strtolower($label) }}" aria-label="Copy {{ strtolower($label) }}" onclick="copyLink(@js($url), @js($label.' copied'))"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
        <a class="btn icon" href="{{ $url }}" target="_blank" rel="noopener" title="Open {{ strtolower($label) }}" aria-label="Open {{ strtolower($label) }}"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg></a>
      </div>
    @endforeach
  </div>
  <div class="link-actions">
    @if (! $public && ! $staff)<a class="btn sm" href="{{ route('billing.index') }}">Back to Billing</a>@endif
    @if ($staff && ! $public && ! $paid)
      <form method="POST" action="{{ route('billing.invoice.vat', $inv->id) }}" class="vat-form">
        @csrf
        <span class="muted">VAT</span>
        <button class="btn sm {{ $vat['mode'] === 'none' ? 'on' : '' }}" name="mode" value="none" type="submit">0%</button>
        <button class="btn sm {{ $vat['mode'] === 'exclusive' ? 'on' : '' }}" name="mode" value="exclusive" type="submit">{{ rtrim(rtrim(number_format($pay['vat_rate'] ?? 16, 2), '0'), '.') }}% exclusive</button>
      </form>
    @endif
    <span style="flex:1"></span>
    <a class="btn sm" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Download PDF</a>
    <button class="btn sm" type="button" onclick="window.print()">Print (A4)</button>
  </div>
</div>
@unless ($paid)
<div class="pm" id="payModal" hidden>
  <div class="pm-box" role="dialog" aria-modal="true" aria-label="Pay with Sharet Pay">
    <button class="pm-x" type="button" aria-label="Close" onclick="closePay()">×</button>
    <iframe id="payFrame" title="Sharet Pay" src="about:blank"></iframe>
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

  @unless ($paid)
    <div class="payline">
      <a class="sp-link" href="{{ $payUrl }}" target="_blank" rel="noopener" data-pay><img src="{{ asset('images/integrations/sharet.svg') }}" alt="" width="18" height="18">Pay with Sharet Pay →</a>
      <span class="muted">M-Pesa STK push or Sharet to Sharet @if ($handle) ({{ $handle }})@endif</span>
    </div>
  @endunless
  @if (! empty($json['note']))<div class="note">{{ $json['note'] }}</div>@endif
</div>
<div class="toast" id="toast"></div>
<script>
const payModal = document.getElementById('payModal');
function openPay(url) { if (!payModal) return true; document.getElementById('payFrame').src = url; payModal.hidden = false; document.body.style.overflow = 'hidden'; return false; }
function closePay() { payModal.hidden = true; document.getElementById('payFrame').src = 'about:blank'; document.body.style.overflow = ''; }
document.querySelectorAll('[data-pay]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); openPay(a.href); }));
if (payModal) { payModal.addEventListener('click', (e) => { if (e.target === payModal) closePay(); }); document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !payModal.hidden) closePay(); }); }
function copyLink(url, msg) {
  const done = () => { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 1800); };
  if (navigator.clipboard) { navigator.clipboard.writeText(url).then(done, () => { prompt('Copy this link:', url); }); } else { prompt('Copy this link:', url); }
}
</script>
</body>
</html>
