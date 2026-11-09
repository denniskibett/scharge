{{-- Sharet security kit: this site's security report. Data: App\SecurityKit\SecurityReportController. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Security · {{ config('app.name') }}</title>
<style>
  :root { --ink:#1A1214; --red:#E0362C; --bg:#F4F1F1; --card:#fff; --line:#ece7e7; --muted:#6b6265; --text:#1A1214; }
  @media (prefers-color-scheme: dark) { :root { --bg:#141012; --card:#1e191b; --line:#2e2729; --muted:#a59ca0; --text:#f3eeef; } }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }
  header { background:var(--ink); color:#fff; padding:20px 16px; }
  .wrap { max-width:1100px; margin:0 auto; padding:0 16px; }
  header .wrap { padding:0; }
  header small { letter-spacing:.08em; font-weight:700; font-size:12px; opacity:.8; }
  header h1 { margin:6px 0 2px; font-size:22px; }
  header p { margin:0; color:#c9bfc1; font-size:13px; }
  header a { color:#fff; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin:18px 0; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px; }
  .card .n { font-size:26px; font-weight:700; margin-top:4px; }
  .card .h { color:var(--muted); font-size:12px; }
  h2 { font-size:16px; margin:26px 0 10px; }
  table { width:100%; border-collapse:collapse; background:var(--card); border:1px solid var(--line); border-radius:14px; overflow:hidden; }
  th, td { text-align:left; padding:9px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { color:var(--muted); font-weight:600; font-size:12px; }
  .scroll { overflow-x:auto; }
  .pill { display:inline-block; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:700; text-transform:uppercase; color:#fff; }
  .critical { background:#B3261E; } .high { background:#D9480F; } .medium { background:#B58100; } .low { background:#7a7f87; } .ok { background:#2B8A3E; }
  .block { background:#B3261E; } .watch { background:#B58100; }
  .fix { color:var(--muted); font-size:12px; margin-top:3px; }
  .muted { color:var(--muted); }
  code { font-size:12px; }
  .bars { display:flex; align-items:flex-end; gap:3px; height:90px; }
  .bars div { flex:1; display:flex; flex-direction:column-reverse; }
</style>
</head>
<body>
<header><div class="wrap">
  <small>SHARET · SECURITY</small>
  <h1>{{ config('app.name') }} security</h1>
  <p>Checked daily by the server's defense agent, plus every login, sign-up, password and code attempt on this site. <a href="{{ url('/') }}">Back to the site</a></p>
</div></header>
<main class="wrap">

  @if ($report)
    @php $s = $report['summary'] ?? []; @endphp
    <div class="grid">
      @foreach (['critical' => 'Fix today', 'high' => 'Fix this week', 'medium' => 'Plan it', 'low' => 'Nice to have'] as $k => $hint)
        <div class="card"><div class="h" style="text-transform:capitalize">{{ $k }}</div><div class="n" style="color:{{ ['critical' => '#B3261E', 'high' => '#D9480F', 'medium' => '#B58100', 'low' => '#7a7f87'][$k] }}">{{ $s[$k] ?? 0 }}</div><div class="h">{{ $hint }}</div></div>
      @endforeach
      <div class="card"><div class="h">Last checked</div><div class="n" style="font-size:20px">{{ \Illuminate\Support\Carbon::parse($report['generated_at'])->timezone('Africa/Nairobi')->format('D j M, H:i') }}</div><div class="h">Nairobi time</div></div>
    </div>

    @if (count($history) > 1)
      @php $max = max(1, max(array_map(fn ($h) => ($h['summary']['critical'] ?? 0) + ($h['summary']['high'] ?? 0) + ($h['summary']['medium'] ?? 0), $history))); @endphp
      <div class="card"><div class="h" style="margin-bottom:8px">Problems per day (critical, high, medium)</div>
        <div class="bars">@foreach ($history as $h)<div title="{{ substr($h['at'], 0, 10) }}">@foreach (['critical' => '#B3261E', 'high' => '#D9480F', 'medium' => '#E5B53A'] as $k => $c)<span style="display:block;height:{{ round(86 * ($h['summary'][$k] ?? 0) / $max) }}px;background:{{ $c }}"></span>@endforeach</div>@endforeach</div>
      </div>
    @endif

    @if (! empty($report['domains']))
      <h2>Domains</h2>
      <div class="scroll"><table>
        <tr><th>Name</th><th>Certificate</th><th>HTTP</th><th>SPF</th><th>DKIM</th><th>DMARC</th></tr>
        @foreach ($report['domains'] as $name => $d)
          @php $t = $report['sites'][$name]['tls'] ?? null; $hs = $report['sites'][$name]['http']['status'] ?? null; @endphp
          <tr>
            <td><b>{{ $name }}</b>@if (empty($d['a'])) <span class="muted">does not resolve</span>@endif</td>
            <td>@if ($t){{ ($t['verified'] ?? false) ? ($t['days_left'].' days') : (($t['days_left'] ?? 0) < 0 ? 'expired' : 'not trusted') }} <span class="muted">{{ $t['issuer'] ?? '' }}</span>@else <span class="muted">—</span>@endif</td>
            <td>{{ $hs ?? '—' }}</td>
            <td>{!! array_key_exists('spf', $d) ? ($d['spf'] ? '✓' : '<b style="color:#B3261E">✗</b>') : '<span class="muted">—</span>' !!}</td>
            <td>{!! isset($d['dkim']) ? ($d['dkim'] === 'ok' ? '✓' : '<b style="color:#B3261E">'.e($d['dkim']).'</b>') : '<span class="muted">—</span>' !!}</td>
            <td>{!! array_key_exists('dmarc', $d) ? ($d['dmarc'] ? '✓' : '<b style="color:#B3261E">✗</b>') : '<span class="muted">—</span>' !!}</td>
          </tr>
        @endforeach
      </table></div>
    @endif

    <h2>What to fix</h2>
    <div class="scroll"><table>
      @forelse ($report['findings'] ?? [] as $f)
        <tr><td style="width:90px"><span class="pill {{ $f['severity'] }}">{{ $f['severity'] }}</span></td>
          <td><b>{{ $f['scope'] }}</b> <span class="muted" style="font-size:11px;text-transform:uppercase">{{ $f['check'] }}</span><br>{{ $f['title'] }}
            @if (! empty($f['fix']))<div class="fix">Fix: {{ $f['fix'] }}</div>@endif</td></tr>
      @empty
        <tr><td>Nothing to fix. 🎉</td></tr>
      @endforelse
    </table></div>
  @else
    <div class="card" style="margin-top:18px">No server report for this site yet. The defense agent writes it to <code>{{ $file }}</code> every morning.</div>
  @endif

  <h2>Login, sign-up and code attempts (24 hours)</h2>
  <div class="grid" style="margin-top:0">
    <div class="card"><div class="h">Failed</div><div class="n">{{ $kyc['fail'] }}</div><div class="h">{{ $kyc['week_fail'] }} in 7 days</div></div>
    <div class="card"><div class="h">Succeeded</div><div class="n">{{ $kyc['ok'] }}</div><div class="h">logins, codes, resets</div></div>
    <div class="card"><div class="h">New accounts</div><div class="n">{{ $kyc['signups'] }}</div><div class="h">sign-ups</div></div>
    <div class="card"><div class="h">Refused</div><div class="n" style="color:{{ $kyc['blocked'] ? '#B3261E' : 'inherit' }}">{{ $kyc['blocked'] }}</div><div class="h">guessing detected</div></div>
  </div>

  @if (count($ips))
  <div class="scroll"><table>
    <tr><th>Network (IP)</th><th>Verdict</th><th>Failed</th><th>OK</th><th>Refused</th><th>Accounts tried</th><th>Last seen</th></tr>
    @foreach ($ips as $i)
      <tr><td><code>{{ $i['ip'] }}</code></td><td><span class="pill {{ $i['verdict'] }}">{{ $i['verdict'] }}</span></td><td>{{ $i['fail'] }}</td><td>{{ $i['ok'] }}</td><td>{{ $i['blocked'] }}</td><td>{{ $i['accounts'] }}</td><td class="muted">{{ \Illuminate\Support\Carbon::parse($i['last'])->diffForHumans() }}</td></tr>
    @endforeach
  </table></div>
  @endif

  @if (count($recent))
  <h2>Recent failed and refused attempts</h2>
  <div class="scroll"><table>
    <tr><th>When</th><th>What</th><th>Account</th><th>IP</th></tr>
    @foreach ($recent as $r)
      <tr><td class="muted">{{ \Illuminate\Support\Carbon::parse($r['at'])->timezone('Africa/Nairobi')->format('j M H:i') }}</td><td>{{ $r['event'] }}@if (! empty($r['reasons']))<div class="fix">{{ implode('; ', (array) $r['reasons']) }}</div>@endif</td><td>{{ $r['identity'] ?? '' }}</td><td><code>{{ $r['ip'] ?? '' }}</code></td></tr>
    @endforeach
  </table></div>
  @endif

  <p class="muted" style="margin:28px 0;font-size:12px">Attempt log: <code>storage/logs/kyc/attempts-YYYY-MM-DD.jsonl</code> (one JSON object per line; passwords and codes are never written). Refused when a network has {{ \App\SecurityKit\KycLog::limits()['ip_fail_per_hour'] }}+ failures an hour or tries {{ \App\SecurityKit\KycLog::limits()['ip_accounts_per_hour'] }}+ accounts, or one account has {{ \App\SecurityKit\KycLog::limits()['account_fail_per_day'] }}+ failures a day.</p>
</main>
</body>
</html>
