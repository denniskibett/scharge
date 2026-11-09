<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your scharge code</title></head>
<body style="margin:0;background:#f6f7f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#101828;">
  <div style="display:none;max-height:0;overflow:hidden;">Your code is {{ $code }}. It works for {{ $minutes }} minutes.</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #eaecf0;border-radius:16px;">
        <tr><td style="padding:28px 28px 8px;font-size:20px;font-weight:700;color:#d92d20;">scharge</td></tr>
        <tr><td style="padding:8px 28px 0;font-size:16px;line-height:24px;">Hi {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}, here is the code to confirm your email:</td></tr>
        <tr><td style="padding:20px 28px;">
          <div style="font-size:34px;font-weight:700;letter-spacing:10px;text-align:center;background:#f9fafb;border:1px solid #eaecf0;border-radius:12px;padding:16px 0;">{{ $code }}</div>
        </td></tr>
        <tr><td style="padding:0 28px 24px;font-size:14px;line-height:22px;color:#475467;">It works for {{ $minutes }} minutes. If you did not try to sign in to scharge, ignore this email: nobody can use your account without the code.</td></tr>
        <tr><td style="padding:16px 28px;border-top:1px solid #eaecf0;font-size:12px;color:#98a2b3;">scharge · property management by Sharet Africa · {{ config('app.url') }}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
