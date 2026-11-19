{{-- Pay a scharge invoice with Sharet Pay (InvoicePayController). $inv, $row (BillingPayment or null), $error, $handle, $payee,
     $invoiceUrl, $startUrl, $statusJson, $stkNumber --}}
@php
  $cur = $inv->currency ?: 'KES';
  $amount = $row ? (float) $row->amount : (float) $inv->amount;
  $status = $row?->status ?? ($inv->status === 'paid' ? 'paid' : null);
  if ($status === 'open' && $row?->expires_at && $row->expires_at->isPast()) { $status = 'expired'; }
  $label = ['open' => 'Waiting for payment', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'failed' => 'Failed'][$status] ?? null;
  $via = ['mpesa' => 'M-Pesa', 'mpesa_via_wallet' => 'M-Pesa', 'sharet' => 'Sharet balance', 'wallet' => 'scharge wallet', 'sandbox' => 'test'][$row?->paid_via] ?? null;
@endphp
<!doctype html>
<html lang="en">
<script>if (window.self !== window.top) document.documentElement.classList.add('embed');</script>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Pay {{ $inv->invoice_number }} · Sharet Pay</title>
<link rel="icon" href="{{ asset('assets/images/brand/favicon.svg') }}">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --red: #DC2626; --ink: #101828; --muted: #667085; --line: #EAECF0; }
* { box-sizing: border-box; }
body { margin: 0; background: #F2F4F7; font: 15px/1.55 Outfit, system-ui, sans-serif; color: var(--ink); }
.wrap { max-width: 520px; margin: 32px auto; padding: 0 16px; }
.card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 24px; }
.head { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.head img { width: 34px; height: 34px; border-radius: 9px; }
.head b { font-size: 17px; display: block; } .head span { font-size: 13px; color: var(--muted); }
.amount { font-size: 34px; font-weight: 700; letter-spacing: -.02em; margin: 4px 0 2px; }
.muted { color: var(--muted); }
dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin: 18px 0; padding: 14px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); font-size: 14px; }
dt { color: var(--muted); } dd { margin: 0; text-align: right; font-weight: 500; word-break: break-all; }
.pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 4px 12px; font-size: 13px; font-weight: 600; }
.pill.open { background: #FFFAEB; color: #B54708; } .pill.paid { background: #ECFDF3; color: #027A48; } .pill.bad { background: #FEF3F2; color: #B42318; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.pill.open .dot { animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: .3; } }
.opts { display: grid; gap: 10px; margin-top: 6px; }
.opt { display: flex; align-items: center; gap: 12px; border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; text-decoration: none; color: var(--ink); transition: border-color .15s, transform .1s; }
.opt:hover { border-color: #FCA5A5; } .opt:active { transform: scale(.99); }
.opt b { display: block; } .opt span { font-size: 13px; color: var(--muted); }
.opt .go { margin-left: auto; color: var(--red); font-weight: 600; white-space: nowrap; }
.ic { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; flex: none; }
.btn { display: inline-flex; justify-content: center; font: inherit; font-weight: 600; border-radius: 12px; padding: 12px 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
.btn.red { background: var(--red); border-color: var(--red); color: #fff; }
.note { font-size: 13px; color: var(--muted); margin: 14px 0 0; }
.err { background: #FEF3F2; color: #B42318; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: 14px; }
.foot { text-align: center; margin-top: 16px; font-size: 13px; }
.foot a { color: var(--muted); }
html.embed body { background: #F2F4F7; } html.embed .wrap { margin: 0; padding: 16px; max-width: none; } html.embed .card { border: 0; } html.embed .foot { display: none; }
.opt { padding: 14px 16px; } .opt > span:first-child { padding-right: 8px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <img src="{{ asset('images/integrations/sharet.svg') }}" alt="">
      <div><b>Sharet Pay</b><span>Invoice {{ $inv->invoice_number }} · {{ $inv->company_name }}</span></div>
    </div>

    @if ($error)<div class="err">{{ $error }}</div>@endif

    <div class="muted" style="font-size:13px">Amount</div>
    <div class="amount">{{ $cur }} {{ number_format($amount, 2) }}</div>
    @if ($label)
      <span id="pill" class="pill {{ $status === 'paid' ? 'paid' : ($status === 'open' ? 'open' : 'bad') }}"><span class="dot"></span><span id="pill-text">{{ $label }}</span></span>
    @endif

    <dl>
      <dt>Pay to</dt><dd>{{ $payee }}@if ($handle)<br><span style="color:var(--red)">{{ $handle }}</span>@endif</dd>
      @if ($row)<dt>Sharet ref</dt><dd>{{ $row->sharet_id }}</dd>@endif
      @if ($status === 'paid' && $row?->paid_at)<dt>Paid</dt><dd>{{ $row->paid_at->timezone('Africa/Nairobi')->format('j M Y, H:i') }}@if ($via) · {{ $via }}@endif</dd>@endif
      @if ($status === 'paid' && $row?->sharet_transaction)<dt>Transaction</dt><dd>{{ $row->sharet_transaction }}</dd>@endif
      @if ($status === 'open' && $row?->expires_at)<dt>Link valid until</dt><dd>{{ $row->expires_at->timezone('Africa/Nairobi')->format('j M Y, H:i') }}</dd>@endif
    </dl>

    @if ($status === 'open' && $row?->pay_url)
      <div class="opts">
        <a class="opt" href="{{ $row->pay_url }}" target="_blank" rel="noopener">
          <span><b>M-Pesa (STK push)</b><span>Enter your M-Pesa number, approve the prompt with your PIN @if ($stkNumber) · {{ $stkNumber }}@endif</span></span>
          <span class="go">Pay →</span>
        </a>
        <a class="opt" href="{{ $row->pay_url }}" target="_blank" rel="noopener">
          <span><b>Sharet to Sharet</b><span>Sign in to Sharet and pay {{ $handle ?: 'scharge' }} from your balance</span></span>
          <span class="go">Pay →</span>
        </a>
      </div>
      <p class="note">Sharet opens in a new tab for your M-Pesa number or Sharet sign-in. Keep this open: it turns <b>Paid</b> on its own once Sharet confirms, and this link always shows the status.</p>
    @elseif ($status === 'paid')
      <p style="margin:0">Thank you. The invoice is paid and your scharge account is up to date.</p>
    @elseif ($inv->status !== 'paid')
      <p style="margin:0 0 14px">This payment link is {{ strtolower($label ?? 'not available') }}. Start a new one to pay this invoice.</p>
      <a class="btn red" href="{{ $startUrl }}">Start a new payment</a>
    @endif
  </div>
  <div class="foot"><a href="{{ $invoiceUrl }}">View invoice {{ $inv->invoice_number }}</a></div>
</div>
@if ($status === 'open' && $statusJson)
<script>
(() => {
  const url = @js($statusJson);
  let tries = 0;
  const check = async () => {
    try {
      const j = await (await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' })).json();
      if (j.status && j.status !== 'open') { location.reload(); return true; }
    } catch (e) {}
    return false;
  };
  const tick = async () => { tries++; if (!(await check())) setTimeout(tick, tries < 40 ? 6000 : 20000); };
  setTimeout(tick, 6000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
})();
</script>
@endif
</body>
</html>
