{{-- Website analytics › Sharet growth hub. Data: Admin\GrowthController@index via App\Services\Sharet\GrowthHub. --}}
@extends('layouts.app')
@section('title', 'Growth · Sharet hub')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'p-3 text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $td = 'p-3 text-sm';
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%';
    $t = $site['totals'] ?? [];
    $tone = ['red' => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-400', 'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400', 'green' => 'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-400'];
@endphp
<div>
  <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <div class="mb-3 flex gap-1 text-sm font-medium">
        <a href="{{ route('site-analytics.index', ['days' => $days]) }}" class="rounded-md px-3 py-1.5 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Our website report</a>
        <span class="rounded-md bg-gray-900 px-3 py-1.5 text-white dark:bg-white dark:text-gray-900" aria-current="page">Sharet growth hub</span>
      </div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Growth</h1>
      <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">scharge.sharet.africa as the Sharet Africa growth hub sees it: visits, conversions and channels from the Sharet tracker, plus the hub’s daily SEO check. Read with scharge’s own Sharet API key. Numbers refresh every 5 minutes.</p>
    </div>
    <div class="flex shrink-0 rounded-lg border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]">
      @foreach ($ranges as $value => $label)
        <a href="?days={{ $value }}" @class(['whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium', 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $days === $value, 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $days !== $value])>{{ $label }}</a>
      @endforeach
    </div>
  </div>

  @if (! in_array($data['state'], ['ok', 'empty'], true))
    <div class="{{ $card }} mb-6 text-sm text-gray-700 dark:text-gray-300" role="status" data-state="{{ $data['state'] }}">
      <p class="font-medium text-gray-900 dark:text-white">{{ match ($data['state']) { 'not_connected' => 'Not connected to Sharet', 'unauthorized' => 'Sharet key not accepted', 'forbidden' => 'No access to scharge’s growth data', 'timeout' => 'Sharet did not answer', 'unavailable' => 'Growth hub unavailable', default => 'Growth numbers unavailable' } }}</p>
      <p class="mt-1">{{ $data['message'] }}</p>
    </div>
  @else
    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400" data-state="{{ $data['state'] }}">
      {{ \Illuminate\Support\Carbon::parse($data['from'])->format('j M') }} – {{ \Illuminate\Support\Carbon::parse($data['to'])->format('j M Y') }}
      · Sharet {{ $data['environment'] === 'live' ? 'live' : 'sandbox' }} key
      @if ($data['account']) · acts as {{ $data['account'] }}@if ($data['account'] !== $expectedId) <span class="font-medium text-amber-600">(the hub expects {{ $expectedId }})</span>@endif @endif
      @if (! empty($site['last_data_at'])) · last visit recorded {{ \Illuminate\Support\Carbon::parse($site['last_data_at'])->diffForHumans() }}@endif
    </p>

    @if ($data['state'] === 'empty')
      <div class="{{ $card }} mb-6 text-sm text-gray-700 dark:text-gray-300">
        <p class="font-medium text-gray-900 dark:text-white">No visits recorded yet for this period.</p>
        <p class="mt-1">The Sharet tracker runs on the public website in production. Numbers appear here after the first visits; the SEO check below runs daily either way.</p>
      </div>
    @endif

    {{-- Overview --}}
    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
      @foreach ([['Visits', number_format($t['visits']), 'One visitor, one day'], ['Page views', number_format($t['page_views']), ''], ['Conversions', number_format($t['conversions']), 'Forms sent'],
          ['Conversion rate', $pct($t['conversion_rate']), 'Conversions per visit'], ['Organic search', $pct($t['organic_share']), 'Share of visits'], ['Paid', $pct($t['paid_share']), 'Share of visits']] as [$label, $value, $hint])
        <div class="{{ $card }}"><p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>@if ($hint)<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>@endif</div>
      @endforeach
    </div>

    {{-- Daily chart --}}
    @php($daily = collect($site['daily']))
    @if ($daily->isNotEmpty())
      @php($max = max(1, $daily->max('page_views')))
      <div class="{{ $card }} mb-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-semibold text-gray-900 dark:text-white">Per day</h2>
          <p class="text-xs text-gray-500"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#1A1214;opacity:.75"></span> page views &nbsp; <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#FCA5A5"></span> visits &nbsp; <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#DC2626"></span> conversions</p>
        </div>
        <div role="img" aria-label="Per day: {{ $daily->sum('page_views') }} page views, {{ $daily->sum('visits') }} visits, {{ $daily->sum('conversions') }} conversions" style="display:flex;align-items:flex-end;gap:2px;height:180px;border-bottom:1px solid rgba(127,127,127,.25)">
          @foreach ($daily as $p)
            <div title="{{ \Illuminate\Support\Carbon::parse($p['date'])->format('j M') }}: {{ $p['page_views'] ?? 0 }} views, {{ $p['visits'] ?? 0 }} visits, {{ $p['conversions'] ?? 0 }} conversions" style="flex:1 1 0;min-width:0;height:100%;position:relative;display:flex;align-items:flex-end;justify-content:center">
              <div style="width:100%;height:{{ round(($p['page_views'] ?? 0) / $max * 100, 1) }}%;background:#1A1214;opacity:.75;border-radius:3px 3px 0 0"></div>
              <div style="position:absolute;bottom:0;width:55%;height:{{ round(($p['visits'] ?? 0) / $max * 100, 1) }}%;background:#FCA5A5;border-radius:3px 3px 0 0"></div>
              @if (! empty($p['conversions']))<span style="position:absolute;bottom:calc({{ round(($p['page_views'] ?? 0) / $max * 100, 1) }}% + 4px);width:8px;height:8px;border-radius:50%;background:#DC2626"></span>@endif
            </div>
          @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-gray-500"><span>{{ \Illuminate\Support\Carbon::parse($daily->first()['date'])->format('j M') }}</span><span>{{ \Illuminate\Support\Carbon::parse($daily->last()['date'])->format('j M') }}</span></div>
      </div>
    @endif

    <div class="mb-6 grid gap-6 xl:grid-cols-3">
      <div class="{{ $card }} overflow-x-auto">
        <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Conversions</h2>
        <table class="w-full table-auto text-left"><tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-600 dark:text-gray-400">
          @forelse ($site['conversions'] as $c)<tr><td class="{{ $td }}">{{ $c['label'] ?? $c['name'] }}</td><td class="{{ $td }} text-right font-medium text-gray-900 dark:text-white">{{ number_format($c['count'] ?? 0) }}</td></tr>
          @empty<tr><td class="{{ $td }}">None yet.</td></tr>@endforelse
        </tbody></table>
      </div>
      <div class="{{ $card }} overflow-x-auto xl:col-span-2">
        <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Channels</h2>
        <table class="w-full table-auto text-left">
          <thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Channel</th><th class="{{ $th }} text-right">Visits</th><th class="{{ $th }} text-right">Share</th><th class="{{ $th }} text-right">Conversions</th><th class="{{ $th }} text-right">Rate</th></tr></thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-600 dark:text-gray-400">
            @forelse ($site['channels'] as $c)<tr><td class="{{ $td }} font-medium text-gray-800 dark:text-white/90">{{ $c['label'] ?? $c['channel'] }}</td><td class="{{ $td }} text-right">{{ number_format($c['visits'] ?? 0) }}</td><td class="{{ $td }} text-right">{{ $pct($c['share'] ?? null) }}</td><td class="{{ $td }} text-right">{{ number_format($c['conversions'] ?? 0) }}</td><td class="{{ $td }} text-right">{{ $pct($c['conversion_rate'] ?? null) }}</td></tr>
            @empty<tr><td class="{{ $td }}" colspan="5">No visits yet.</td></tr>@endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-2">
      <div class="{{ $card }} overflow-x-auto">
        <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Sources and campaigns</h2>
        <table class="w-full table-auto text-left">
          <thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Source / medium</th><th class="{{ $th }}">Campaign</th><th class="{{ $th }} text-right">Visits</th><th class="{{ $th }} text-right">Conv.</th><th class="{{ $th }} text-right">Rate</th></tr></thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-600 dark:text-gray-400">
            @forelse ($site['sources'] as $s)<tr><td class="{{ $td }} font-medium text-gray-800 dark:text-white/90">{{ $s['source'] ?? '(direct)' }}@if (! empty($s['medium'])) / {{ $s['medium'] }}@endif</td><td class="{{ $td }}">{{ $s['campaign'] ?? '—' }}</td><td class="{{ $td }} text-right">{{ number_format($s['visits'] ?? 0) }}</td><td class="{{ $td }} text-right">{{ number_format($s['conversions'] ?? 0) }}</td><td class="{{ $td }} text-right">{{ $pct($s['conversion_rate'] ?? null) }}</td></tr>
            @empty<tr><td class="{{ $td }}" colspan="5">No sources yet.</td></tr>@endforelse
          </tbody>
        </table>
      </div>
      <div class="{{ $card }} overflow-x-auto">
        <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Landing pages</h2>
        <table class="w-full table-auto text-left">
          <thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">First page of the visit</th><th class="{{ $th }} text-right">Visits</th><th class="{{ $th }} text-right">Conv.</th><th class="{{ $th }} text-right">Rate</th></tr></thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-600 dark:text-gray-400">
            @forelse ($site['landing_pages'] as $l)<tr><td class="{{ $td }} font-medium text-gray-800 dark:text-white/90">{{ $l['path'] ?? '/' }}</td><td class="{{ $td }} text-right">{{ number_format($l['visits'] ?? 0) }}</td><td class="{{ $td }} text-right">{{ number_format($l['conversions'] ?? 0) }}</td><td class="{{ $td }} text-right">{{ $pct($l['conversion_rate'] ?? null) }}</td></tr>
            @empty<tr><td class="{{ $td }}" colspan="4">No landing pages yet.</td></tr>@endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- SEO --}}
    @php($seo = $site['seo'])
    <div class="{{ $card }} mb-6">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold text-gray-900 dark:text-white">SEO check</h2>
        <p class="text-xs text-gray-500">{{ $seo['checked_at'] ? 'Checked '.\Illuminate\Support\Carbon::parse($seo['checked_at'])->diffForHumans() : 'Not checked yet' }}@if ($seo['sitemap_urls'] !== null) · {{ $seo['sitemap_urls'] }} URLs in sitemap.xml @endif</p>
      </div>
      <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['red' => 'to fix', 'amber' => 'to improve', 'green' => 'fine'] as $level => $word)
          <span class="rounded-full px-3 py-1 font-medium {{ $tone[$level] }}">{{ (int) ($seo['counts'][$level] ?? 0) }} {{ $word }}</span>
        @endforeach
      </div>
      <h3 class="mb-2 text-sm font-medium text-gray-900 dark:text-white">Fix first</h3>
      <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
        @forelse (array_slice($seo['fix_first'], 0, 10) as $f)
          <li class="flex gap-2"><span class="mt-0.5 shrink-0 rounded px-1.5 text-xs font-medium {{ $tone[$f['level'] ?? 'amber'] ?? $tone['amber'] }}">{{ $f['level'] ?? 'amber' }}</span><span><span class="text-gray-800 dark:text-white/90">{{ $f['text'] ?? '' }}</span> <span class="break-all text-xs text-gray-500">{{ $f['url'] ?? '' }}</span></span></li>
        @empty
          <li>Nothing flagged.</li>
        @endforelse
      </ul>
    </div>
  @endif

  {{-- Ad center: always available (copy and links do not depend on the hub) --}}
  <div class="{{ $card }} mb-6">
    <h2 class="mb-1 font-semibold text-gray-900 dark:text-white">Ad center</h2>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">What to push on scharge.sharet.africa, ready copy, and tagged links so campaigns show up in both reports.</p>

    <h3 class="mb-2 text-sm font-medium text-gray-900 dark:text-white">What to push</h3>
    <div class="mb-6 text-sm text-gray-700 dark:text-gray-300" data-advice="{{ $advice['enough'] ? 'ready' : 'not-enough' }}">
      @if (! $advice['enough'])
        <p>Not enough data yet: {{ number_format($advice['visits']) }} visits and {{ number_format($advice['conversions']) }} conversions in this period. Recommendations start at 100 visits and 10 conversions. Until then, send traffic to the landing pages written for Kenyan searches (property management software, rent collection on M-Pesa, landlord app).</p>
      @else
        <ul class="list-disc space-y-1 pl-5">
          <li>Best landing page: @if ($advice['best_page'])<b>{{ $advice['best_page']['path'] }}</b>, {{ $pct($advice['best_page']['conversion_rate']) }} of {{ number_format($advice['best_page']['visits']) }} visits convert. Point ads here.@else no page has 20+ visits with a conversion yet.@endif</li>
          <li>Best source: @if ($advice['best_source'])<b>{{ $advice['best_source']['source'] ?? '(direct)' }}{{ ! empty($advice['best_source']['medium']) ? ' / '.$advice['best_source']['medium'] : '' }}{{ ! empty($advice['best_source']['campaign']) ? ' ('.$advice['best_source']['campaign'].')' : '' }}</b>, {{ $pct($advice['best_source']['conversion_rate']) }}. Put more budget here.@else no source has 20+ visits with a conversion yet.@endif</li>
        </ul>
      @endif
      @if ($advice['leaks'])
        <p class="mt-3">Traffic but no conversions (add a clearer call to action or a form):</p>
        <ul class="list-disc pl-5">@foreach ($advice['leaks'] as $l)<li>{{ $l['path'] }}: {{ number_format($l['visits']) }} visits</li>@endforeach</ul>
      @endif
    </div>

    <h3 class="mb-2 text-sm font-medium text-gray-900 dark:text-white">Ready ad copy</h3>
    <div class="mb-6 grid gap-4 md:grid-cols-2">
      @foreach ($adCopy as $channel => $parts)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
          <p class="mb-2 font-medium text-gray-900 dark:text-white">{{ $channel }}</p>
          @foreach ($parts as $label => $lines)
            <p class="mt-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <ul class="mt-1 space-y-1 text-sm text-gray-700 dark:text-gray-300">@foreach ($lines as $line)<li>{{ $line }}</li>@endforeach</ul>
          @endforeach
        </div>
      @endforeach
    </div>

    <h3 class="mb-2 text-sm font-medium text-gray-900 dark:text-white">UTM link builder</h3>
    <form class="grid gap-3 md:grid-cols-4" id="utm-builder" onsubmit="return false">
      <label class="text-sm text-gray-600 dark:text-gray-400 md:col-span-4">Page
        <select name="url" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
          @foreach ($urls as $label => $u)<option value="{{ $u }}">{{ $label }} · {{ $u }}</option>@endforeach
        </select>
      </label>
      <label class="text-sm text-gray-600 dark:text-gray-400">Source<input name="source" value="facebook" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"></label>
      <label class="text-sm text-gray-600 dark:text-gray-400">Medium<input name="medium" value="paid_social" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"></label>
      <label class="text-sm text-gray-600 dark:text-gray-400">Campaign<input name="campaign" value="landlords-{{ now()->format('Y-m') }}" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"></label>
      <label class="text-sm text-gray-600 dark:text-gray-400">Content (optional)<input name="content" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"></label>
      <div class="flex gap-2 md:col-span-4">
        <input id="utm-out" readonly aria-label="Tagged link" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm dark:border-gray-700 dark:bg-white/5 dark:text-white">
        <button type="button" id="utm-copy" class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white dark:bg-white dark:text-gray-900">Copy</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var f = document.getElementById('utm-builder'), out = document.getElementById('utm-out');
  function slug(v) { return (v || '').trim().toLowerCase().replace(/[^a-z0-9_.-]+/g, '-').replace(/^-+|-+$/g, ''); }
  function build() {
    var u = new URL(f.url.value), p = { utm_source: f.source.value, utm_medium: f.medium.value, utm_campaign: f.campaign.value, utm_content: f.content.value };
    Object.keys(p).forEach(function (k) { var v = slug(p[k]); if (v) u.searchParams.set(k, v); });
    out.value = u.toString();
  }
  f.addEventListener('input', build); f.addEventListener('change', build); build();
  document.getElementById('utm-copy').addEventListener('click', function () {
    out.select();
    (navigator.clipboard ? navigator.clipboard.writeText(out.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
    this.textContent = 'Copied'; var b = this; setTimeout(function () { b.textContent = 'Copy'; }, 1500);
  });
})();
</script>
@endsection
