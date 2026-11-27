{{-- The prospect's copy of their demo request ("Ink" direction); the PDF is attached. --}}
@php
  use App\Modules\CRM\Services\DemoRequestService as Demo;
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $when = \Illuminate\Support\Carbon::parse($demo->detail('contact.date'))->format('l j F').', '.$demo->detail('contact.window');
  $channel = $demo->detail('contact.channel') === 'WhatsApp' ? 'WhatsApp' : lcfirst((string) $demo->detail('contact.channel'));
  $am = $demo->assignee;
  $first = \Illuminate\Support\Str::before((string) $demo->contact?->name, ' ') ?: 'there';
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
        <td align="right"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;">{{ $demo->detail('ref') }}</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:21px;line-height:1.35;font-weight:700;color:#FFFFFF;">Your demo is booked</h1>
      <div style="margin-top:8px;font-size:14px;color:#CDBFC2;">{{ $when }} by {{ $channel }}</div>
    </td></tr>
    <tr><td style="padding:28px 32px 32px;font-family:{!! $sans !!};color:#1A1214;font-size:15px;line-height:1.65;">
      <p style="margin:0 0 14px;">Hello {{ $first }},</p>
      <p style="margin:0 0 14px;">Thank you for telling us about your properties. A copy of everything you sent is attached as a PDF; keep it with your reference <strong>{{ $demo->detail('ref') }}</strong>.</p>
      <p style="margin:0 0 14px;">{{ $am?->name ?? 'Your account manager' }} will contact you on {{ $when }} by {{ $channel }}{{ $demo->detail('units') ? ' to walk through how scharge would run your '.number_format((int) $demo->detail('units')).' units' : '' }}.</p>
      <p style="margin:0 0 18px;">If anything changes, just reply to this email{{ $am?->phone ? ' or call '.$am->phone : '' }}.</p>
      @if ($demo->company && ! $demo->company->profile('setup.started_at'))
      <p style="margin:0 0 12px;"><strong>Don't want to wait for the demo?</strong> Create your scharge account now. We set it up from what you sent: you add your units, we do the rest. Nothing is billed until you decide to go ahead.</p>
      <p style="margin:0;"><a href="{{ \App\Modules\CRM\Services\DemoOnboarding::startUrl($demo->company) }}" style="display:inline-block;background:#DC2626;color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 20px;border-radius:999px;">Create my scharge account</a></p>
      @endif
    </td></tr>
    <tr><td style="background:#1A1214;padding:22px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.55;color:#CDBFC2;">
      <strong style="color:#FFFFFF;">scharge</strong> · Sharet Africa · Nairobi, Kenya<br>
      You received this because you booked a demo at scharge.sharet.africa. <a href="https://scharge.sharet.africa" style="color:#FCA5A5;">scharge.sharet.africa</a>
    </td></tr>
  </table>
</td></tr></table>
</body></html>
