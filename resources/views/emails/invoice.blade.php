{{-- Bill emails (App\Mail\InvoiceEmail) in the "Ink" direction, like the email code: dark header carries the figure.
     $kind: 'shared' (bill + pay button) | 'paid' (receipt). Table layout and inline styles for Gmail/Outlook. --}}
@php
  $tenancy = $invoice->tenancy;
  $user = $tenancy?->tenant?->user;
  $first = \Illuminate\Support\Str::of($user->name ?? '')->before(' ')->trim()->value() ?: 'there';
  $unit = $tenancy?->unit;
  $property = $unit?->property;
  $company = ($cid = $invoice->owningCompanyId()) ? \App\Models\Company::find($cid) : null;
  $month = $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : null;
  $paidTotal = (float) $invoice->total_paid;
  $due = max(0, (float) $invoice->total_amount - $paidTotal);
  $isPaid = $kind === 'paid';
  $figure = $isPaid ? (float) ($amount ?? $invoice->total_amount) : (float) ($amount ?? $due);
  $now = now()->timezone('Africa/Nairobi');
  $base = rtrim(preg_replace('#^http://#', 'https://', (string) config('app.url')), '/');
  $billUrl = $base.'/public/invoice/'.$invoice->id;
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $items = $invoice->items->filter(fn ($i) => (float) $i->amount != 0);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light only">
<title>{{ \App\Mail\InvoiceEmail::subjectFor($invoice) }}</title>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#EDE8E8;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
  @if ($isPaid) Payment received: KES {{ number_format($figure, 2) }}. This bill is fully paid. @else Your bill of KES {{ number_format($figure, 2) }} is ready. Pay securely online. @endif
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8E8;">
<tr><td align="center" style="padding:32px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:552px;background:#FFFFFF;border-radius:6px;overflow:hidden;">

    <tr><td style="background:#1A1214;padding:28px 32px 34px;color:#FFFFFF;font-family:{!! $sans !!};">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td align="left" valign="middle"><img src="{{ $logoSrc }}" alt="scharge" width="104" style="display:block;width:104px;height:auto;border:0;"></td>
          <td align="right" valign="middle"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:{{ $isPaid ? '#F3FFD0' : '#DC2626' }};color:{{ $isPaid ? '#3B5200' : '#FFFFFF' }};font-size:12px;font-weight:600;letter-spacing:.04em;">{{ $isPaid ? 'Paid in full' : 'Bill ready' }}</span></td>
        </tr>
      </table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:22px;line-height:1.3;font-weight:700;letter-spacing:-0.02em;color:#FFFFFF;">{{ $isPaid ? 'Thank you, '.$first : 'Hi '.$first.', your bill is ready' }}</h1>
      @if ($property || $unit)
        <div style="margin-top:6px;font-size:14px;color:#CDBFC2;">{{ collect([$property?->name, $unit?->unit_number ? 'Unit '.$unit->unit_number : null])->filter()->implode(' · ') }}</div>
      @endif
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;border-top:1px solid #3A2E31;">
        <tr><td style="padding-top:18px;font-family:{!! $sans !!};">
          <div style="font-size:13px;color:#CDBFC2;">{{ $isPaid ? 'Amount paid' : 'Amount due' }}</div>
          <div style="margin-top:6px;font-family:{!! $display !!};font-size:36px;line-height:1.1;font-weight:700;letter-spacing:-0.01em;color:#FFFFFF;"><span style="font-size:16px;color:#FCA5A5;">KES</span> {{ number_format($figure, 2) }}</div>
          <div style="margin-top:6px;font-size:14px;color:#E7DDDF;">Invoice #{{ $invoice->invoice_number ?? $invoice->id }}{{ $month ? ' · '.$month : '' }}</div>
        </td></tr>
      </table>
    </td></tr>

    <tr><td style="padding:30px 32px 34px;font-family:{!! $sans !!};color:#1A1214;">
      <p style="margin:0;font-size:15px;line-height:1.65;">
        @if ($isPaid)
          We've received your payment and this bill is now fully paid. Keep this email as your receipt.
        @else
          {{ $company?->name ?? 'Your property manager' }} has sent you this bill. You can pay it securely online with M-PESA or Airtel Money.
        @endif
      </p>

      @if (! $isPaid && $payUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:12px;background:#DC2626;">
          <a href="{{ $payUrl }}" style="display:inline-block;padding:14px 26px;font-family:{!! $sans !!};font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">Pay KES {{ number_format($figure, 2) }}</a>
        </td></tr></table>
      @endif

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px;font-size:14px;">
        @foreach ($items as $item)
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #EDE6E7;color:#5C4A4E;">{{ $item->description ?: ucfirst(str_replace('_', ' ', (string) $item->item_type)) }}</td>
            <td align="right" style="padding:10px 0 10px 12px;border-bottom:1px solid #EDE6E7;font-weight:600;white-space:nowrap;">KES {{ number_format($item->amount, 2) }}</td>
          </tr>
        @endforeach
        <tr>
          <td style="padding:12px 0;color:#5C4A4E;">{{ $isPaid ? 'Total paid' : 'Balance due' }}</td>
          <td align="right" style="padding:12px 0 12px 12px;font-family:{!! $display !!};font-size:16px;font-weight:700;white-space:nowrap;">KES {{ number_format($isPaid ? $invoice->total_amount : $due, 2) }}</td>
        </tr>
        @if ($isPaid)
          <tr><td style="padding:10px 0;border-top:1px solid #EDE6E7;color:#5C4A4E;">Paid</td><td align="right" style="padding:10px 0;border-top:1px solid #EDE6E7;font-weight:600;">{{ $now->format('j M Y, H:i') }} EAT</td></tr>
          @if ($reference)
            <tr><td style="padding:10px 0;border-top:1px solid #EDE6E7;color:#5C4A4E;">Transaction ID</td><td align="right" style="padding:10px 0;border-top:1px solid #EDE6E7;font-weight:600;font-family:monospace;font-size:13px;">{{ $reference }}</td></tr>
          @endif
        @endif
      </table>

      <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#5C4A4E;">
        <a href="{{ $billUrl }}" style="color:#991B1B;font-weight:600;">View this bill online</a>
        @if ($company?->email) · Questions? Reply to this email to reach {{ $company->name }}. @endif
      </p>
    </td></tr>

    <tr><td style="background:#1A1214;padding:22px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.55;color:#CDBFC2;">
      <strong style="color:#FFFFFF;">scharge</strong> · Sharet Africa · Nairobi, Kenya<br>
      You're receiving this because you rent {{ $unit?->unit_number ? 'unit '.$unit->unit_number : 'a unit' }}{{ $property ? ' at '.$property->name : '' }}.<br>
      Sent with scharge · Property management, simplified. <a href="{{ $base }}" style="color:#FCA5A5;">{{ preg_replace('#^https?://#', '', $base) }}</a>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
