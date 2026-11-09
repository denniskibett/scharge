{{-- Email code (KYC) in the "Ink" direction from the email canvas (B · dark header carries the key figure).
     Table layout and inline styles for Gmail/Outlook. $logo: embedded image (cid) or URL. --}}
@php
  $first = \Illuminate\Support\Str::of($user->name ?? '')->before(' ')->trim()->value() ?: 'there';
  $now = now()->timezone('Africa/Nairobi');
  $logoSrc = $logo ?? (isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png'));
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Your scharge code</title>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#EDE8E8;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Your code is {{ $code }}. It works for {{ $minutes }} minutes.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8E8;">
<tr><td align="center" style="padding:32px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:552px;background:#FFFFFF;border-radius:6px;overflow:hidden;">

    {{-- Dark header: logo, pill, the code --}}
    <tr><td style="background:#1A1214;padding:28px 32px 34px;color:#FFFFFF;font-family:{!! $sans !!};">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td align="left" valign="middle"><img src="{{ $logoSrc }}" alt="scharge" width="104" style="display:block;width:104px;height:auto;border:0;"></td>
          <td align="right" valign="middle"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;letter-spacing:.04em;">Confirm your email</span></td>
        </tr>
      </table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:22px;line-height:1.3;font-weight:700;letter-spacing:-0.02em;color:#FFFFFF;">Your sign-in code</h1>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;border-top:1px solid #3A2E31;">
        <tr><td style="padding-top:18px;font-family:{!! $sans !!};">
          <div style="font-size:13px;color:#CDBFC2;">Your code</div>
          <div style="margin-top:6px;font-family:{!! $display !!};font-size:40px;line-height:1.1;font-weight:700;letter-spacing:8px;color:#FFFFFF;">{{ $code }}</div>
          <div style="margin-top:6px;font-size:14px;color:#E7DDDF;">Works for {{ $minutes }} minutes · {{ $user->email }}</div>
        </td></tr>
      </table>
    </td></tr>

    {{-- Body --}}
    <tr><td style="padding:30px 32px 34px;font-family:{!! $sans !!};color:#1A1214;">
      <p style="margin:0;font-size:15px;line-height:1.65;">Hi {{ $first }}, enter this code on scharge to confirm this is your email. We check it once, so your bills, receipts and invites always reach the right inbox.</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px;font-size:14px;">
        @foreach ([['Account', $user->email], ['Requested', $now->format('j M Y, H:i').' EAT'], ['Expires', $now->copy()->addMinutes($minutes)->format('H:i').' EAT']] as [$k, $v])
          <tr>
            <td style="padding:10px 0;border-bottom:1px solid #EDE6E7;color:#5C4A4E;">{{ $k }}</td>
            <td align="right" style="padding:10px 0;border-bottom:1px solid #EDE6E7;font-weight:600;">{{ $v }}</td>
          </tr>
        @endforeach
      </table>
      <p style="margin:26px 0 0;font-size:15px;line-height:1.65;">Didn't try to sign in? Ignore this email. Without the code nobody can use your account.</p>
    </td></tr>

    {{-- Dark footer --}}
    <tr><td style="background:#1A1214;padding:22px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.55;color:#CDBFC2;">
      <strong style="color:#FFFFFF;">scharge</strong> · Sharet Africa · Nairobi, Kenya<br>
      You're receiving this because someone signed in to scharge with this address.<br>
      Sent with scharge · Property management, simplified. <a href="{{ config('app.url') }}" style="color:#FCA5A5;">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
