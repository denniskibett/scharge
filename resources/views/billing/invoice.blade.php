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
td { padding: 12px 0; border-bottom: 1px solid #F2F4F7; vertical-align: top; }
td.amt, th.amt { text-align: right; white-space: nowrap; }
td small { display: block; color: var(--muted); }
.totals { margin-left: auto; width: min(320px, 100%); margin-top: 16px; }
.totals div { display: flex; justify-content: space-between; padding: 6px 0; }
.totals .grand { border-top: 2px solid var(--ink); margin-top: 6px; padding-top: 10px; font-size: 18px; font-weight: 700; }
.note { margin-top: 26px; padding: 14px 16px; border-radius: 12px; background: #F9FAFB; color: #344054; }
.actions { max-width: 820px; margin: 0 auto 28px; display: flex; gap: 10px; justify-content: flex-end; padding: 0 4px; }
.btn { font: inherit; font-weight: 600; border-radius: 10px; padding: 10px 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
.btn.red { background: var(--red); border-color: var(--red); color: #fff; }
@media print { body { background: #fff; } .page { border: 0; margin: 0; max-width: none; } .actions { display: none; } }
</style>
</head>
<body>
<div class="page">
  <div class="top">
    <div>
      <img src="{{ asset('assets/images/brand/scharge-logo.svg') }}" alt="scharge">
      <p class="muted" style="margin:10px 0 0">scharge · Sharet Africa<br>info@sharet.africa · https://scharge.sharet.africa</p>
    </div>
    <div style="text-align:right">
      <h1>Invoice</h1>
      <p style="margin:4px 0 6px;font-weight:600">{{ $inv->invoice_number }}</p>
      <span class="pill {{ $paid ? 'paid' : ($late ? 'due' : 'open') }}">{{ $paid ? 'Paid' : ($late ? 'Overdue' : 'Unpaid') }}</span>
    </div>
  </div>

  <div class="meta">
    <div><b>Billed to</b>{{ $inv->company_name }}@if ($inv->company_email)<br><span class="muted">{{ $inv->company_email }}</span>@endif @if ($inv->company_phone)<br><span class="muted">{{ $inv->company_phone }}</span>@endif</div>
    <div><b>Issued</b>{{ \Illuminate\Support\Carbon::parse($inv->created_at)->format('j M Y') }}</div>
    <div><b>Due</b>{{ \Illuminate\Support\Carbon::parse($inv->due_date)->format('j M Y') }}</div>
    <div><b>Plan</b>{{ $json['plan_name'] ?? $inv->plan_name ?? 'scharge subscription' }}@if (! empty($json['price_per_unit']))<br><span class="muted">{{ $cur }} {{ number_format((float) $json['price_per_unit']) }} per unit per month</span>@endif</div>
  </div>

  @if (! empty($json['billing_start']) && ! empty($json['billing_end']))
    <p class="muted" style="margin:-10px 0 18px">Period: {{ \Illuminate\Support\Carbon::parse($json['billing_start'])->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($json['billing_end'])->format('j M Y') }}@if (! empty($json['property'])) · {{ $json['property'] }}@endif</p>
  @endif

  <table>
    <thead><tr><th>Description</th><th class="amt">Amount</th></tr></thead>
    <tbody>
      @foreach ($items as $it)
        <tr><td>{{ $it['description'] }}@if ($it['detail'])<small>{{ $it['detail'] }}</small>@endif</td><td class="amt">{{ $money($it['amount']) }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <div class="totals">
    @if (abs($sum - $total) > 0.009)<div><span class="muted">Lines</span><span>{{ $money($sum) }}</span></div>@endif
    <div class="grand"><span>Total</span><span>{{ $money($total) }}</span></div>
    @if ($paid && $inv->paid_at)<div class="muted"><span>Paid</span><span>{{ \Illuminate\Support\Carbon::parse($inv->paid_at)->format('j M Y') }}</span></div>@endif
  </div>

  @if (! empty($json['note']))<div class="note">{{ $json['note'] }}</div>@endif
  @unless ($paid)
    <div class="note"><b>How to pay:</b> sign in to scharge and open <b>Billing</b> to pay with Sharet Pay (M-Pesa or card) or from your scharge wallet. Quote {{ $inv->invoice_number }} on any bank transfer.</div>
  @endunless
</div>
<div class="actions">
  @unless ($staff)<a class="btn" href="{{ route('billing.index') }}">Back to Billing</a>@endunless
  <button class="btn red" type="button" onclick="window.print()">Print / Save as PDF</button>
</div>
</body>
</html>
