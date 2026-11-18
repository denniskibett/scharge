{{-- Account-manager notice for a demo-form submission ("Ink" direction). --}}
@php
  use App\Modules\CRM\Services\DemoRequestService as Demo;
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $when = \Illuminate\Support\Carbon::parse($demo->detail('contact.date'))->format('D j M').', '.$demo->detail('contact.window');
  $rows = array_filter([
    'Contact' => trim(($demo->contact?->name ?? '').' · '.($demo->contact?->phone ?? ''), ' ·'),
    'Email' => $demo->contact?->email,
    'Who' => Demo::ROLES[$demo->detail('role')] ?? null,
    'Call them' => $when.' by '.strtolower((string) $demo->detail('contact.channel')),
    'Properties' => count($demo->detail('properties', [])).' · '.number_format((int) $demo->detail('units')).' units',
    'Bills today' => $demo->detail('billing_now'),
    'Wants to see' => collect($demo->detail('interests', []))->map(fn ($i) => Demo::INTERESTS[$i] ?? $i)->join(', '),
  ]);
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only">
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"></head>
<body style="margin:0;padding:0;background:#EDE8E8;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8E8;"><tr><td align="center" style="padding:32px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:552px;background:#FFFFFF;border-radius:6px;overflow:hidden;">
    <tr><td style="background:#1A1214;padding:28px 32px 30px;color:#FFFFFF;font-family:{!! $sans !!};">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td><img src="{{ $logoSrc }}" alt="scharge" width="104" style="display:block;width:104px;height:auto;border:0;"></td>
        <td align="right"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;">Demo booked · {{ $demo->detail('ref') }}</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:21px;line-height:1.35;font-weight:700;color:#FFFFFF;">{{ $demo->company?->name }} wants a demo</h1>
      <div style="margin-top:8px;font-size:14px;color:#CDBFC2;">Contact them {{ $when }}</div>
    </td></tr>
    <tr><td style="padding:28px 32px 32px;font-family:{!! $sans !!};color:#1A1214;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        @foreach ($rows as $label => $value)
          <tr><td style="padding:9px 0;border-bottom:1px solid #EDE6E7;color:#5C4A4E;width:34%;">{{ $label }}</td><td style="padding:9px 0 9px 12px;border-bottom:1px solid #EDE6E7;font-weight:600;">{{ $value }}</td></tr>
        @endforeach
      </table>
      @if ($demo->notes)<p style="margin:18px 0 0;font-size:14px;line-height:1.6;">“{{ $demo->notes }}”</p>@endif
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:12px;background:#DC2626;">
        <a href="{{ route('crm.companies.show', $demo->company_id) }}" style="display:inline-block;padding:14px 26px;font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">Open in the CRM</a>
      </td></tr></table>
      <p style="margin:18px 0 0;font-size:13px;color:#5C4A4E;">The full submission is attached as a PDF and saved on the company's thread.</p>
    </td></tr>
    <tr><td style="background:#1A1214;padding:20px 32px;font-family:{!! $sans !!};font-size:12px;color:#CDBFC2;"><strong style="color:#FFFFFF;">scharge</strong> CRM · internal notice</td></tr>
  </table>
</td></tr></table>
</body></html>
