{{-- Sandbox API key + partner pack ("Ink" direction); the PDF is attached. --}}
@php
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $mono = "'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace";
  $base = url('/api/v1');
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
        <td align="right"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;">{{ $ref }}</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:21px;line-height:1.35;font-weight:700;color:#FFFFFF;">Your sandbox key is ready</h1>
      <div style="margin-top:8px;font-size:14px;color:#CDBFC2;">Plus white label and partner terms for {{ $company->name }}</div>
    </td></tr>
    <tr><td style="padding:28px 32px 8px;font-family:{!! $sans !!};color:#1A1214;font-size:15px;line-height:1.65;">
      <p style="margin:0 0 14px;">Hello {{ $firstName ?: 'there' }},</p>
      <p style="margin:0 0 14px;">Thank you for asking to connect scharge to your accounting or CRM, and sorry this took longer than the one working day we promised. Here is your sandbox key. It reads a sample property company (3 properties, 34 units, tenants, three months of bills, M-Pesa payments and meter readings), through the same API and fields as a live account.</p>
    </td></tr>
    <tr><td style="padding:0 32px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F7F3F3;border-left:3px solid #DC2626;">
        <tr><td style="padding:14px 16px;font-family:{!! $sans !!};font-size:13px;color:#5C4A4E;line-height:1.6;">
          Sandbox key (shown only in this email, keep it safe)<br>
          <span style="font-family:{!! $mono !!};font-size:13px;color:#1A1214;word-break:break-all;">{{ $key }}</span><br><br>
          Try it:<br>
          <span style="font-family:{!! $mono !!};font-size:12px;color:#1A1214;word-break:break-all;">curl -H "Authorization: Bearer {{ $key }}" {{ $base }}/invoices?limit=5</span>
        </td></tr>
      </table>
    </td></tr>
    <tr><td style="padding:22px 32px 32px;font-family:{!! $sans !!};color:#1A1214;font-size:15px;line-height:1.65;">
      <p style="margin:0 0 14px;">The attached PDF covers the rest:</p>
      <ul style="margin:0 0 14px;padding-left:20px;">
        <li><strong>The endpoints</strong> for invoices, payments, units, tenants and readings, and how to sync them to your books.</li>
        <li><strong>White label:</strong> scharge under your name, logo, colours, domain and email sender, live in about 4 weeks.</li>
        <li><strong>Setup fee</strong> of KES 50,000, with three ways to pay, one of them nothing upfront if you bring 100 units in 3 months.</li>
        <li><strong>Your revenue share:</strong> 20% to 30% of what your clients pay us, every month they stay.</li>
      </ul>
      <p style="margin:0 0 14px;">Developer guide: <a href="{{ url('/developers') }}" style="color:#DC2626;">{{ url('/developers') }}</a></p>
      <p style="margin:0;">Reply to this email with the system you are connecting and a good time to talk, and we will set up a call{{ $manager?->phone ? ', or call '.$manager->phone : '' }}.</p>
      <p style="margin:18px 0 0;">{{ $manager?->name ?? 'The scharge team' }}<br><span style="color:#5C4A4E;font-size:13px;">scharge · Sharet Africa</span></p>
    </td></tr>
    <tr><td style="background:#1A1214;padding:22px 32px;font-family:{!! $sans !!};font-size:12px;line-height:1.55;color:#CDBFC2;">
      <strong style="color:#FFFFFF;">scharge</strong> · Sharet Africa · Nairobi, Kenya<br>
      You received this because you asked for sandbox API keys at scharge.sharet.africa/api. <a href="https://scharge.sharet.africa" style="color:#FCA5A5;">scharge.sharet.africa</a>
    </td></tr>
  </table>
</td></tr></table>
</body></html>
