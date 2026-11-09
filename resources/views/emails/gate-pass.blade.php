{{-- Gate pass emails (App\Mail\GatePassEmail), "Ink" direction like the bill emails. Table layout + inline styles.
     $kind: 'pass' (guest: code + check-out link) | 'arrived' / 'left' (host: their guest checked in / out). --}}
@php
  $isGuest = $kind === 'pass';
  $first = \Illuminate\Support\Str::of($pass->guest_name)->before(' ')->trim()->value() ?: 'there';
  $place = $pass->placeLabel();
  $company = $pass->company;
  $base = rtrim(preg_replace('#^http://#', 'https://', (string) config('app.url')), '/');
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $at = ($kind === 'left' ? $pass->checked_out_at : $pass->checked_in_at)?->timezone('Africa/Nairobi');
  $pill = match ($kind) { 'arrived' => ['Checked in', '#F3FFD0', '#3B5200'], 'left' => ['Checked out', '#E0F2FE', '#075985'], default => ['Gate pass', '#DC2626', '#FFFFFF'] };
  $method = fn ($m) => match ($m) { 'self' => 'by the guest, from their phone', 'host' => 'by the host', default => 'at the gate' };
  $rows = array_filter([
    'Guest' => $pass->guest_name.($pass->guests_count > 1 ? ' + '.($pass->guests_count - 1) : ''),
    'Where' => $place,
    'Valid' => $pass->windowLabel(),
    'Car' => $pass->vehicle_plate ? trim($pass->vehicle_plate.' '.($pass->vehicle_desc ? '· '.$pass->vehicle_desc : '')) : null,
    'Checked in' => ! $isGuest && $pass->checked_in_at ? $pass->checked_in_at->timezone('Africa/Nairobi')->format('j M, H:i').' '.$method($pass->check_in_method) : null,
    'Checked out' => $kind === 'left' && $pass->checked_out_at ? $pass->checked_out_at->timezone('Africa/Nairobi')->format('j M, H:i').' '.$method($pass->check_out_method) : null,
  ]);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Gate pass</title>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#EDE8E8;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
  @if ($isGuest) Your gate code is {{ $pass->code }}. Show it to the guard at {{ $place }}. @else {{ $pass->guest_name }} {{ $kind === 'arrived' ? 'checked in' : 'checked out' }}{{ $at ? ' at '.$at->format('H:i') : '' }}. @endif
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8E8;">
<tr><td align="center" style="padding:32px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:552px;background:#FFFFFF;border-radius:6px;overflow:hidden;">

    <tr><td style="background:#1A1214;padding:28px 32px 34px;color:#FFFFFF;font-family:{!! $sans !!};">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td align="left" valign="middle"><img src="{{ $logoSrc }}" alt="scharge" width="104" style="display:block;width:104px;height:auto;border:0;"></td>
        <td align="right" valign="middle"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:{{ $pill[1] }};color:{{ $pill[2] }};font-size:12px;font-weight:600;letter-spacing:.04em;">{{ $pill[0] }}</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:22px;line-height:1.3;font-weight:700;letter-spacing:-0.02em;color:#FFFFFF;">
        @if ($isGuest) Hi {{ $first }}, here's your gate code @else {{ $pass->guest_name }} {{ $kind === 'arrived' ? 'has checked in' : 'has checked out' }} @endif
      </h1>
      <div style="margin-top:6px;font-size:14px;color:#CDBFC2;">{{ $place }}</div>
      @if ($isGuest)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;border-top:1px solid #3A2E31;"><tr><td style="padding-top:18px;font-family:{!! $sans !!};">
          <div style="font-size:13px;color:#CDBFC2;">Show this code to the guard</div>
          <div style="margin-top:6px;font-family:{!! $display !!};font-size:40px;line-height:1.1;font-weight:700;letter-spacing:.12em;color:#FFFFFF;">{{ $pass->code }}</div>
        </td></tr></table>
      @elseif ($at)
        <div style="margin-top:18px;font-family:{!! $display !!};font-size:28px;color:#FFFFFF;">{{ $at->format('H:i') }} <span style="font-family:{!! $sans !!};font-size:14px;color:#CDBFC2;">{{ $at->format('D j M') }}</span></div>
      @endif
    </td></tr>

    <tr><td style="padding:30px 32px 34px;font-family:{!! $sans !!};color:#1A1214;">
      <p style="margin:0;font-size:15px;line-height:1.65;">
        @if ($isGuest)
          {{ $company?->name ?? 'Your host' }} has registered your visit. At the gate, show this code (or the QR on your pass page) to the guard. When you leave for good, check out from your phone with the button below — no login needed.
        @else
          Your guest {{ $kind === 'arrived' ? 'arrived' : 'left' }}. You can see every check-in and check-out under Guests &amp; gate in scharge.
        @endif
      </p>
      @if ($isGuest)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:12px;background:#DC2626;">
          <a href="{{ $pass->publicUrl() }}" style="display:inline-block;padding:14px 26px;font-family:{!! $sans !!};font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">Open my pass · check out</a>
        </td></tr></table>
      @else
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:12px;background:#1A1214;">
          <a href="{{ $base }}/gate/passes/{{ $pass->id }}" style="display:inline-block;padding:14px 26px;font-family:{!! $sans !!};font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">View in scharge</a>
        </td></tr></table>
      @endif

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px;font-size:14px;">
        @foreach ($rows as $label => $value)
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #EDE6E7;color:#5C4A4E;">{{ $label }}</td>
            <td align="right" style="padding:10px 0 10px 12px;border-bottom:1px solid #EDE6E7;font-weight:600;">{{ $value }}</td>
          </tr>
        @endforeach
      </table>
    </td></tr>

    <tr><td style="background:#1A1214;padding:22px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.55;color:#CDBFC2;">
      <strong style="color:#FFFFFF;">scharge</strong> · Sharet Africa · Nairobi, Kenya<br>
      @if ($isGuest) You're receiving this because {{ $company?->name ?? 'your host' }} registered your visit. @else You're receiving this because you issued this gate pass. @endif<br>
      <a href="{{ $base }}" style="color:#FCA5A5;">{{ preg_replace('#^https?://#', '', $base) }}</a>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
