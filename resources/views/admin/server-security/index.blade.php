{{-- Setup › Server security. Data: Admin\ServerSecurityController@index, from the VPS defense agent (ops/defense-agent). --}}
@extends('layouts.app')
@section('title', 'Server security')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'px-3 py-2 font-medium';
    $td = 'px-3 py-2';
    $pill = [
        'critical' => 'bg-red-600 text-white', 'high' => 'bg-orange-500 text-white', 'medium' => 'bg-amber-400 text-gray-900',
        'low' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200', 'ok' => 'bg-emerald-600 text-white',
    ];
    $bar = ['critical' => '#DC2626', 'high' => '#F97316', 'medium' => '#FBBF24', 'low' => '#D1D5DB'];
    $yes = fn ($v) => $v === null ? '<span class="text-gray-400">—</span>' : ($v ? '<span class="text-emerald-600">✓</span>' : '<span class="font-semibold text-red-600">✗</span>');
@endphp
<div>
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Server security</h1>
    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">Every domain and site on the VPS, checked daily by the defense agent: certificates, SPF / DKIM / DMARC, uptime, security headers, exposed files, Laravel settings, package vulnerabilities, SSH and firewall, mail, updates and file changes. A digest is emailed every morning. How it works: <code>ops/defense-agent/README.md</code>.</p>
  </div>

  @if (! $report)
    <div class="{{ $card }} text-sm text-gray-700 dark:text-gray-300">
      <p class="font-medium text-gray-900 dark:text-white">No report yet.</p>
      <p class="mt-1">The agent publishes <code>latest.json</code> to <code>{{ $dir }}</code> after each run. Run it on the server with <code>systemctl start defense-agent.service</code>, or set <code>SERVER_SECURITY_DIR</code> to where the reports are.</p>
    </div>
  @else
  @php $s = $report['summary']; @endphp

  @if ($stale)
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">The last report is from {{ \Carbon\Carbon::parse($report['generated_at'])->diffForHumans() }}. Check the timer: <code>systemctl status defense-agent.timer</code>.</div>
  @endif

  <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    @foreach (['critical' => 'Fix today', 'high' => 'Fix this week', 'medium' => 'Plan it', 'low' => 'Nice to have'] as $k => $hint)
      <a href="?severity={{ $k }}#findings" class="{{ $card }} block hover:border-gray-400">
        <p class="text-sm capitalize text-gray-500 dark:text-gray-400">{{ $k }}</p>
        <p class="mt-2 text-2xl font-semibold" style="color: {{ $k === 'low' ? '#6B7280' : $bar[$k] }}">{{ $s[$k] ?? 0 }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
      </a>
    @endforeach
    <div class="{{ $card }}">
      <p class="text-sm text-gray-500 dark:text-gray-400">Domains &amp; sites</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $rows->count() }}</p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $rows->where('worst', 'ok')->count() }} with nothing to fix</p>
    </div>
    <div class="{{ $card }}">
      <p class="text-sm text-gray-500 dark:text-gray-400">Last checked</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($report['generated_at'])->timezone('Africa/Nairobi')->format('D H:i') }}</p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">took {{ round($report['took_seconds'] ?? 0) }} s · {{ $report['server'] }}</p>
    </div>
  </div>

  {{-- Trend: stacked critical / high / medium per run --}}
  @if ($history->count() > 1)
  @php $max = max(1, $history->max(fn ($h) => ($h['summary']['critical'] ?? 0) + ($h['summary']['high'] ?? 0) + ($h['summary']['medium'] ?? 0))); @endphp
  <div class="{{ $card }} mb-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h2 class="font-semibold text-gray-900 dark:text-white">Trend (last {{ $history->count() }} days)</h2>
      <p class="text-xs text-gray-500">@foreach (['critical', 'high', 'medium'] as $k)<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $bar[$k] }}"></span> {{ $k }} &nbsp; @endforeach</p>
    </div>
    <div class="flex h-36 items-end gap-1">
      @foreach ($history as $h)
        <div class="flex flex-1 flex-col-reverse" title="{{ substr($h['at'], 0, 10) }}: {{ $h['summary']['critical'] ?? 0 }} critical, {{ $h['summary']['high'] ?? 0 }} high, {{ $h['summary']['medium'] ?? 0 }} medium">
          @foreach (['critical', 'high', 'medium'] as $k)
            <div style="height: {{ round(130 * ($h['summary'][$k] ?? 0) / $max) }}px; background: {{ $bar[$k] }}"></div>
          @endforeach
        </div>
      @endforeach
    </div>
    <div class="mt-1 flex justify-between text-xs text-gray-400"><span>{{ substr($history->first()['at'], 0, 10) }}</span><span>{{ substr($history->last()['at'], 0, 10) }}</span></div>
  </div>
  @endif

  {{-- Server --}}
  @if ($server)
  <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
    @foreach ([
        ['Disk used', ($server['disk_used_pct'] ?? '?').'%', 'of /'],
        ['Failed SSH logins', number_format($server['ssh_failed_24h'] ?? 0), 'last 24 h'],
        ['IPs blocked', ($server['csf_permanent_blocks'] ?? 0).' / '.($server['csf_limit'] ?? '?'), 'CSF, + '.($server['csf_temp_blocks'] ?? 0).' temporary'],
        ['Mail sent', number_format($server['mail_sent_24h'] ?? 0), ($server['mail_bounced_24h'] ?? 0).' bounced, '.($server['mail_deferred_24h'] ?? 0).' deferred'],
        ['Security updates', $server['updates_security'] ?? 0, ($server['updates_total'] ?? 0).' updates in all'.(($server['reboot_required'] ?? false) ? ' · reboot needed' : '')],
        ['Files watched', number_format($report['files']['files'] ?? 0), ($report['files']['changed'] ?? 0).' changed, '.($report['files']['added'] ?? 0).' new since last run'],
    ] as [$label, $value, $hint])
      <div class="{{ $card }}">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
        <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
      </div>
    @endforeach
  </div>
  @endif

  {{-- Domains --}}
  <div class="{{ $card }} mb-6">
    <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Domains and sites</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-800"><tr>
          <th class="{{ $th }}">Name</th><th class="{{ $th }}">Status</th><th class="{{ $th }}">Problems</th>
          <th class="{{ $th }}">Certificate</th><th class="{{ $th }}">HTTP</th><th class="{{ $th }}">SPF</th><th class="{{ $th }}">DKIM</th><th class="{{ $th }}">DMARC</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($rows as $r)
          <tr @class(['opacity-60' => $r['resolves'] === false])>
            <td class="{{ $td }} font-medium text-gray-900 dark:text-white"><a href="?scope={{ urlencode($r['name']) }}#findings" class="hover:underline">{{ $r['name'] }}</a>
              @if ($r['resolves'] === false)<span class="ml-1 text-xs text-gray-500">does not resolve</span>@endif
              @if ($r['mail_host'])<span class="ml-1 text-xs text-gray-500">mail host</span>@endif</td>
            <td class="{{ $td }}"><span class="rounded-full px-2 py-0.5 text-xs font-semibold uppercase {{ $pill[$r['worst']] }}">{{ $r['worst'] }}</span></td>
            <td class="{{ $td }} text-xs text-gray-600 dark:text-gray-400">@foreach (['critical', 'high', 'medium', 'low'] as $k)@if ($r['counts'][$k] ?? 0)<span class="mr-2">{{ $r['counts'][$k] }} {{ $k }}</span>@endif @endforeach</td>
            <td class="{{ $td }} text-xs">
              @if ($r['tls_days'] !== null)
                <span @class(['font-semibold text-red-600' => $r['tls_days'] < 7 || ! $r['tls_ok'], 'text-amber-600' => $r['tls_days'] >= 7 && $r['tls_days'] < 21, 'text-gray-700 dark:text-gray-300' => $r['tls_days'] >= 21 && $r['tls_ok']])>{{ $r['tls_ok'] ? $r['tls_days'].' days' : ($r['tls_days'] < 0 ? 'expired' : 'not trusted') }}</span>
                <span class="text-gray-400">{{ $r['issuer'] }}</span>
              @else <span class="text-gray-400">—</span> @endif
            </td>
            <td class="{{ $td }} text-xs">{{ $r['http'] ?? '—' }}</td>
            <td class="{{ $td }}">{!! $yes($r['spf']) !!}</td>
            <td class="{{ $td }} text-xs">{!! $r['dkim'] === null ? '<span class="text-gray-400">—</span>' : ($r['dkim'] === 'ok' ? '<span class="text-emerald-600">✓</span>' : '<span class="font-semibold text-red-600">'.e($r['dkim']).'</span>') !!}</td>
            <td class="{{ $td }}">{!! $yes($r['dmarc']) !!}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Findings, most severe first --}}
  <div id="findings" class="{{ $card }}">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h2 class="font-semibold text-gray-900 dark:text-white">What to fix @if ($scope) · {{ $scope }} @endif @if ($severity) · {{ $severity }} @endif</h2>
      @if ($scope || $severity)<a href="{{ url()->current() }}#findings" class="text-sm text-gray-500 hover:underline">Show all</a>@endif
    </div>
    <table class="w-full text-left text-sm">
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
      @forelse ($findings as $f)
        <tr>
          <td class="{{ $td }} w-24 align-top"><span class="rounded-full px-2 py-0.5 text-xs font-semibold uppercase {{ $pill[$f['severity']] }}">{{ $f['severity'] }}</span></td>
          <td class="{{ $td }} align-top">
            <p class="text-gray-900 dark:text-white"><span class="font-medium">{{ $f['scope'] }}</span> · <span class="text-xs uppercase text-gray-400">{{ $f['check'] }}</span></p>
            <p class="text-gray-700 dark:text-gray-300">{{ $f['title'] }}</p>
            @if ($f['fix'])<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Fix: {{ $f['fix'] }}</p>@endif
            @if (is_array($f['detail'] ?? null) && $f['detail'])
              <details class="mt-1 text-xs text-gray-500"><summary class="cursor-pointer">{{ count($f['detail']) }} files</summary><pre class="mt-1 whitespace-pre-wrap">{{ implode("\n", $f['detail']) }}</pre></details>
            @endif
          </td>
        </tr>
      @empty
        <tr><td class="{{ $td }} text-gray-500">Nothing to fix here.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection
