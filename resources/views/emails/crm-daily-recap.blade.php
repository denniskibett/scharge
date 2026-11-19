{{-- CRM daily recap (crm:daily-recap): what happened on the last working day and who to call today. --}}
@php
  $logoSrc = isset($message) ? $message->embed(public_path('images/email/scharge-logo-white.png')) : asset('images/email/scharge-logo-white.png');
  $sans = "'DM Sans','Helvetica Neue',Helvetica,Arial,sans-serif";
  $display = "'Unbounded','Arial Black',Arial,sans-serif";
  $h2 = 'margin:26px 0 10px;font-size:13px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#7A6A6D;';
  $cell = 'padding:7px 0;border-bottom:1px solid #F1ECEC;font-size:14px;';
  $num = $cell.'text-align:right;font-weight:600;';
  $pct = fn ($n) => $funnel['sent'] ? ' <span style="color:#9A8C8F;font-weight:400;">'.round($n / $funnel['sent'] * 100).'%</span>' : '';
  $url = rtrim(config('app.url'), '/');
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"></head>
<body style="margin:0;padding:0;background:#EDE8E8;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8E8;"><tr><td align="center" style="padding:32px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#FFFFFF;border-radius:6px;overflow:hidden;">
    <tr><td style="background:#1A1214;padding:26px 32px 28px;color:#FFFFFF;font-family:{!! $sans !!};">
      <img src="{{ $logoSrc }}" alt="scharge" width="104" style="display:block;width:104px;height:auto;border:0;">
      <h1 style="margin:20px 0 0;font-family:{!! $display !!};font-size:20px;line-height:1.35;font-weight:700;color:#FFFFFF;">{{ $headline }}</h1>
      <div style="margin-top:6px;font-size:14px;color:#CDBFC2;">{{ $window }}</div>
    </td></tr>
    <tr><td style="padding:8px 32px 30px;font-family:{!! $sans !!};color:#1A1214;line-height:1.55;">

      @if (! empty($todo))
        <div style="{{ $h2 }}">Today</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          @foreach ($todo as [$label, $n, $link])
            <tr><td style="{{ $cell }}"><a href="{{ $link }}" style="color:#1A1214;text-decoration:none;">{{ $label }}</a></td><td style="{{ $num }}{{ $n ? 'color:#DC2626;' : '' }}">{{ $n }}</td></tr>
          @endforeach
        </table>
      @endif

      <div style="{{ $h2 }}">Cold emails sent</div>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        @foreach ([['Sent', $funnel['sent']], ['Delivered', $funnel['delivered']], ['Opened', $funnel['opened']], ['Clicked to the demo form', $funnel['clicked']], ['Replied', $funnel['replied']], ['Booked a demo', $funnel['booked']], ['Bounced (back on Unsent)', $funnel['bounced']]] as [$label, $n])
          <tr><td style="{{ $cell }}">{{ $label }}</td><td style="{{ $num }}">{{ number_format($n) }}{!! $label === 'Sent' ? '' : $pct($n) !!}</td></tr>
        @endforeach
      </table>
      <p style="margin:8px 0 0;font-size:12px;color:#9A8C8F;">Across all earlier emails in the same window: {{ $funnel['events']['opened'] }} opens, {{ $funnel['events']['clicked'] }} clicks, {{ $funnel['events']['replied'] }} replies.</p>

      @if (! empty($totals))
        <div style="{{ $h2 }}">Pipeline</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td style="{{ $cell }}">New prospects added</td><td style="{{ $num }}">{{ number_format($totals['new_prospects']) }}</td></tr>
          <tr><td style="{{ $cell }}">Replies received</td><td style="{{ $num }}">{{ $totals['replies'] }}</td></tr>
          <tr><td style="{{ $cell }}">Demo requests</td><td style="{{ $num }}">{{ $totals['demo_requests'] }}</td></tr>
          <tr><td style="{{ $cell }}">Stage moves by the team</td><td style="{{ $num }}">{{ collect($totals['stage_moves'])->map(fn ($n, $s) => $s.' '.$n)->join(', ') ?: '0' }}</td></tr>
          <tr><td style="{{ $cell }}">Ready for the next sends</td><td style="{{ $num }}">{{ collect($totals['ready'])->map(fn ($n, $t) => $t.' '.$n)->join(', ') }}</td></tr>
        </table>
      @endif

      @if (! empty($people))
        <div style="{{ $h2 }}">Account managers</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
          <tr style="color:#7A6A6D;"><td style="padding:4px 0;">Name</td><td style="padding:4px 0;text-align:right;">Signed in</td><td style="padding:4px 0;text-align:right;">Calls</td><td style="padding:4px 0;text-align:right;">WhatsApp</td><td style="padding:4px 0;text-align:right;">Answers</td><td style="padding:4px 0;text-align:right;">Stage moves</td><td style="padding:4px 0;text-align:right;">Waiting</td><td style="padding:4px 0;text-align:right;">Overdue</td></tr>
          @foreach ($people as $p)
            <tr><td style="{{ $cell }}">{{ $p['user']->name }}</td><td style="{{ $num }}">{{ $p['logged_in'] ? 'yes' : 'no' }}</td><td style="{{ $num }}">{{ $p['calls'] }}</td><td style="{{ $num }}">{{ $p['whatsapp'] }}</td><td style="{{ $num }}">{{ $p['answered'] }}</td><td style="{{ $num }}">{{ $p['stages'] }}</td><td style="{{ $num }}{{ $p['waiting'] ? 'color:#DC2626;' : '' }}">{{ $p['waiting'] }}</td><td style="{{ $num }}{{ $p['overdue'] ? 'color:#DC2626;' : '' }}">{{ $p['overdue'] }}</td></tr>
          @endforeach
        </table>
      @endif

      @if (count($hot))
        <div style="{{ $h2 }}">Call first: opened, no answer yet</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          @foreach ($hot as $e)
            <tr><td style="{{ $cell }}"><a href="{{ $url }}/crm/companies/{{ $e->company_id }}" style="color:#1A1214;font-weight:600;text-decoration:none;">{{ $e->company->name }}</a><br><span style="font-size:12px;color:#7A6A6D;">{{ $e->company->phone ?: 'no phone' }}{{ ! empty($people) || ! isset($me) ? ' · '.($e->company->owner?->name ?? 'no owner') : '' }}</span></td>
              <td style="{{ $num }}font-size:12px;font-weight:400;color:#7A6A6D;">{{ $e->detail('clicked_at') ? 'clicked · ' : '' }}{{ max(1, (int) $e->detail('opens')) }} open{{ (int) $e->detail('opens') > 1 ? 's' : '' }}</td></tr>
          @endforeach
        </table>
      @endif

      <p style="margin:26px 0 0;"><a href="{{ $url }}/crm/team" style="display:inline-block;padding:11px 18px;border-radius:6px;background:#DC2626;color:#FFFFFF;font-size:14px;font-weight:600;text-decoration:none;">Open CRM › Team</a></p>
    </td></tr>
    <tr><td style="background:#1A1214;padding:18px 32px;font-family:{!! $sans !!};font-size:12px;color:#CDBFC2;">scharge CRM · sent every weekday at 07:30 Nairobi time</td></tr>
  </table>
</td></tr></table>
</body></html>
