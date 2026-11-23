{{-- "How to handle this request" for the account manager (Mail\DemoHandlingGuide, Support\DemoGuide). "Ink" direction like crm-demo-notice. --}}
@php
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $h2 = "margin:26px 0 10px;font-family:$display;font-size:14px;font-weight:700;color:#1A1214;";
  $li = 'margin:0 0 8px;';
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
        <td align="right"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;">Your guide · {{ $demo->detail('ref') }}</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:21px;line-height:1.35;font-weight:700;color:#FFFFFF;">How to handle {{ $demo->company?->name }}</h1>
      <div style="margin-top:8px;font-size:14px;color:#CDBFC2;">Contact them {{ $guide['when'] }} by {{ $guide['channel'] }}</div>
    </td></tr>
    <tr><td style="padding:6px 32px 32px;font-family:{!! $sans !!};color:#1A1214;font-size:14px;line-height:1.6;">
      <h2 style="{!! $h2 !!}">1. Before you call</h2>
      <ul style="margin:0;padding-left:18px;">@foreach ($guide['before'] as $line)<li style="{{ $li }}">{{ $line }}</li>@endforeach</ul>

      <h2 style="{!! $h2 !!}">2. What to show them</h2>
      <ul style="margin:0;padding-left:18px;">@foreach ($guide['show'] as $line)<li style="{{ $li }}">{{ $line }}</li>@endforeach</ul>

      @if ($guide['objections'])
        <h2 style="{!! $h2 !!}">3. If they say</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
          @foreach ($guide['objections'] as $said => $answer)
            <tr><td style="padding:9px 0;border-bottom:1px solid #EDE6E7;color:#5C4A4E;width:36%;vertical-align:top;">“{{ $said }}”</td><td style="padding:9px 0 9px 12px;border-bottom:1px solid #EDE6E7;">{{ $answer }}</td></tr>
          @endforeach
        </table>
      @endif

      <h2 style="{!! $h2 !!}">{{ $guide['objections'] ? 4 : 3 }}. When they say yes: onboard them</h2>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        @foreach ($guide['onboard'] as $n => $step)
          <tr><td style="padding:10px 0;border-bottom:1px solid #EDE6E7;width:26px;vertical-align:top;font-weight:600;color:#DC2626;">{{ $n + 1 }}</td>
            <td style="padding:10px 0;border-bottom:1px solid #EDE6E7;"><strong>@if ($step['url'])<a href="{{ $step['url'] }}" style="color:#1A1214;">{{ $step['title'] }}</a>@else{{ $step['title'] }}@endif</strong><br><span style="color:#5C4A4E;">{{ $step['text'] }}</span></td></tr>
        @endforeach
      </table>

      <h2 style="{!! $h2 !!}">{{ $guide['objections'] ? 5 : 4 }}. After every contact</h2>
      <ul style="margin:0;padding-left:18px;">@foreach ($guide['after'] as $line)<li style="{{ $li }}">{{ $line }}</li>@endforeach</ul>

      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:12px;background:#DC2626;">
        <a href="{{ route('crm.companies.show', $demo->company_id) }}" style="display:inline-block;padding:14px 26px;font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">Open them in the CRM</a>
      </td></tr></table>
      <p style="margin:18px 0 0;font-size:13px;color:#5C4A4E;">Their answers are in the “Demo request” email, with the PDF. The call is on your calendar.</p>
    </td></tr>
    <tr><td style="background:#1A1214;padding:20px 32px;font-family:{!! $sans !!};font-size:12px;color:#CDBFC2;"><strong style="color:#FFFFFF;">scharge</strong> CRM · internal guide</td></tr>
  </table>
</td></tr></table>
</body></html>
