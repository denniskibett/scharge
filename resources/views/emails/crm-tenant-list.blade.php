{{-- A customer uploaded their tenant list for us to load (Mail\TenantListUploaded). "Ink" direction like crm-task-invite. --}}
@php
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $company = $task->company;
  $rows = array_filter([
    'Company' => $company?->name,
    'Uploaded by' => trim($by->name.' · '.($by->phone ?: $company?->phone), ' ·'),
    'Email' => $by->email,
    'File' => $task->detail('file_name'),
    'Call them by' => $task->due_at?->timezone('Africa/Nairobi')->format('D j M, H:i').' (Nairobi time)',
  ]);
  $steps = [
    'Open the attached file. If it is a photo or a PDF, type it into the Excel template first.',
    'Go to Properties > Import in their account, upload the sheet and check the preview: every tenant has a name, a phone, a house number and a rent.',
    'Confirm the import, then call them: read back the number of tenants and agree the day the first bills go out.',
    'Close this task in the CRM with what you loaded.',
  ];
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
        <td align="right"><span style="display:inline-block;padding:6px 12px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:12px;font-weight:600;">Tenant list to load</span></td>
      </tr></table>
      <h1 style="margin:22px 0 0;font-family:{!! $display !!};font-size:21px;line-height:1.35;font-weight:700;color:#FFFFFF;">{{ $company?->name }} sent their tenant list</h1>
      <div style="margin-top:8px;font-size:14px;color:#CDBFC2;">They are waiting for us to load it. The file is attached.</div>
    </td></tr>
    <tr><td style="padding:28px 32px 32px;font-family:{!! $sans !!};color:#1A1214;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
        @foreach ($rows as $label => $value)
          <tr><td style="padding:9px 0;border-bottom:1px solid #EDE6E7;color:#5C4A4E;width:34%;">{{ $label }}</td><td style="padding:9px 0 9px 12px;border-bottom:1px solid #EDE6E7;font-weight:600;">{{ $value }}</td></tr>
        @endforeach
      </table>
      <h2 style="margin:24px 0 8px;font-family:{!! $display !!};font-size:15px;font-weight:700;">What to do</h2>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;line-height:1.55;">
        @foreach ($steps as $i => $step)
          <tr><td valign="top" style="padding:6px 10px 6px 0;width:26px;"><span style="display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;border-radius:999px;background:#1A1214;color:#FFFFFF;font-size:12px;font-weight:600;">{{ $i + 1 }}</span></td><td style="padding:6px 0;">{{ $step }}</td></tr>
        @endforeach
      </table>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:12px;background:#DC2626;">
        <a href="{{ url('/crm/companies/'.$task->company_id) }}" style="display:inline-block;padding:14px 26px;font-size:15px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:12px;">Open in the CRM</a>
      </td></tr></table>
      <p style="margin:18px 0 0;font-size:13px;color:#5C4A4E;">The file is also on the task: <a href="{{ url('/crm/follow-ups/'.$task->id.'/file') }}" style="color:#DC2626;">download it</a>.</p>
    </td></tr>
    <tr><td style="background:#1A1214;padding:20px 32px;font-family:{!! $sans !!};font-size:12px;color:#CDBFC2;"><strong style="color:#FFFFFF;">scharge</strong> CRM · onboarding</td></tr>
  </table>
</td></tr></table>
</body></html>
