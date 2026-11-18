{{-- CRM cold email (App\Modules\CRM\Mail\CrmOutreachEmail); $body is rich text (EmailBody), plain text still works., "Ink" direction: account-manager emails (design decision 9 Nov 2026).
     $body is the account manager's own (editable) text; the offers list comes from the segment playbook. Table layout + inline styles. --}}
@php
  $base = 'https://scharge.sharet.africa';
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $offers = array_values(array_filter($book['offers'] ?? [], fn ($o) => ! str_starts_with($o, 'Pricing')));
  $offers = array_slice($offers, 0, 4);
  $demoHref = $company ? \App\Modules\CRM\Support\Playbook::demoUrl($company).(! empty($track) ? '?t='.$track : '') : $base;
  $plain = \App\Modules\CRM\Support\EmailBody::toText($body);
  $paragraphs = preg_split("/\n{2,}/", $plain);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light only">
<title>{{ $subjectLine }}</title>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#EDE8E8;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $paragraphs[1] ?? $paragraphs[0] ?? ''), 140) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8E8;">
<tr><td align="center" style="padding:32px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:552px;background:#FFFFFF;border-radius:6px;overflow:hidden;">

    <tr><td style="background:#1A1214;padding:28px 32px 30px;color:#FFFFFF;font-family:{!! $sans !!};">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td align="left" valign="middle"><img src="{{ $logoSrc }}" alt="scharge" width="104" style="display:block;width:104px;height:auto;border:0;"></td>
        <td align="right" valign="middle"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;letter-spacing:.04em;">{{ $book['label'] ?? 'scharge' }}</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:21px;line-height:1.35;font-weight:700;letter-spacing:-0.02em;color:#FFFFFF;">{{ $subjectLine }}</h1>
      <div style="margin-top:8px;font-size:14px;color:#CDBFC2;">From {{ $manager->name }}, account manager at scharge</div>
    </td></tr>

    <tr><td style="padding:30px 32px 32px;font-family:{!! $sans !!};color:#1A1214;">
      {!! \App\Modules\CRM\Support\EmailBody::forEmail($body) !!}

      @if ($offers)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:10px;font-size:14px;">
          <tr><td colspan="2" style="padding:0 0 6px;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#5C4A4E;">What we'd set up for you</td></tr>
          @foreach ($offers as $offer)
            <tr>
              <td width="22" valign="top" style="padding:9px 0;border-bottom:1px solid #EDE6E7;color:#DC2626;font-weight:600;">✓</td>
              <td style="padding:9px 0;border-bottom:1px solid #EDE6E7;">{{ $offer }}</td>
            </tr>
          @endforeach
          <tr><td colspan="2" style="padding:10px 0 0;font-size:13px;color:#5C4A4E;">Priced per unit per month, from KES 40.</td></tr>
        </table>
      @endif

      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px;"><tr>
        <td style="border-radius:12px;background:#DC2626;">
          <a href="{{ $demoHref }}" style="display:inline-block;padding:14px 26px;font-family:{!! $sans !!};font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">Book your demo</a>
        </td>
        <td style="padding-left:16px;font-family:{!! $sans !!};font-size:13px;color:#5C4A4E;">2 minutes: your properties<br>and a time that suits you</td>
      </tr></table>
    </td></tr>

    <tr><td style="background:#1A1214;padding:22px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.55;color:#CDBFC2;">
      <strong style="color:#FFFFFF;">scharge</strong> · Sharet Africa · Nairobi, Kenya<br>
      You're receiving this because this address is published as the business contact for {{ $companyName }}. {{ $optOut }}<br>
      <a href="{{ $base }}" style="color:#FCA5A5;">scharge.sharet.africa</a>
    </td></tr>
  </table>
</td></tr>
</table>
@if (! empty($track))<img src="{{ $base }}/e/{{ $track }}.gif" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">@endif
</body>
</html>
