{{-- Website analytics report. Data: Admin\SiteAnalyticsController@index (own database, no third party). --}}
@extends('layouts.app')
@section('title', 'Website analytics')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'px-3 py-2 font-medium';
    $td = 'px-3 py-2';
@endphp
<div class="p-4 md:p-6">
  <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Website analytics</h1>
      <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">What visitors to the scharge website read, where they came from and which forms they sent. Stored in our own database: no cookies, no IP addresses, no third parties, and visitors who ask not to be tracked are skipped. Signed-in users are not counted. Tag links you share with <code>?utm_source=…&amp;utm_medium=…&amp;utm_campaign=…</code> (docs/MARKETING_DISTRIBUTION.md).</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('site-analytics.heatmap', ['days' => $days]) }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Click heatmap</a>
      <div class="flex rounded-lg border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]">
        @foreach ($ranges as $value => $label)
          <a href="?days={{ $value }}" @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $days === $value, 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $days !== $value])>{{ $label }}</a>
        @endforeach
      </div>
    </div>
  </div>

  @if (! $ready)
    <div class="{{ $card }} text-sm text-gray-700 dark:text-gray-300">
      <p class="font-medium text-gray-900 dark:text-white">Analytics tables are not set up yet.</p>
      <p class="mt-1">Apply <code>database/schema/2026_11_09_site_analytics.sql</code> to the database (e.g. <code>mysql scha_main &lt; database/schema/2026_11_09_site_analytics.sql</code>). The website starts recording visits as soon as the tables exist, within ten minutes.</p>
    </div>
  @else
  @php
      $kpis = [
          ['Visitors', number_format($kpi['visitors']), 'Counted per day, no cookies'],
          ['Page views', number_format($kpi['views']), $kpi['per_visitor'].' per visitor'],
          ['Form conversions', number_format($kpi['conversions']), $kpi['conv_rate'].'% of visitors'],
          ['Time on page', $kpi['avg_time'], 'Average, 1s–30m'],
          ['Bounce rate', $kpi['bounce'].'%', 'Left after one page'],
          ['Scroll depth', $kpi['scroll'].'%', 'How far down people read'],
      ];
      $max = max(1, $series->max('views'));
  @endphp

  <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    @foreach ($kpis as [$label, $value, $hint])
      <div class="{{ $card }}">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
      </div>
    @endforeach
  </div>

  {{-- Views per day (per week for 12 months): bars = page views, red dots = conversions --}}
  <div class="{{ $card }} mb-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h2 class="font-semibold text-gray-900 dark:text-white">{{ $days > 90 ? 'Views per week' : 'Views per day' }}</h2>
      <p class="text-xs text-gray-500"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#1A1214;opacity:.75"></span> page views &nbsp; <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#FCA5A5"></span> visitors &nbsp; <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#DC2626"></span> conversions</p>
    </div>
    <div role="img" aria-label="Page views per {{ $days > 90 ? 'week' : 'day' }}: {{ $series->sum('views') }} views, {{ $series->sum('conversions') }} conversions in total" style="display:flex;align-items:flex-end;gap:2px;height:200px;border-bottom:1px solid rgba(127,127,127,.25)">
      @foreach ($series as $p)
        <div title="{{ \Illuminate\Support\Carbon::parse($p['date'])->format('j M') }}: {{ $p['views'] }} views, {{ $p['visitors'] }} visitors, {{ $p['conversions'] }} conversions" style="flex:1 1 0;min-width:0;height:100%;position:relative;display:flex;align-items:flex-end;justify-content:center">
          <div style="width:100%;height:{{ round($p['views'] / $max * 100, 1) }}%;background:#1A1214;opacity:.75;border-radius:3px 3px 0 0"></div>
          <div style="position:absolute;bottom:0;width:55%;height:{{ round($p['visitors'] / $max * 100, 1) }}%;background:#FCA5A5;border-radius:3px 3px 0 0"></div>
          @if ($p['conversions'])<span style="position:absolute;bottom:calc({{ round($p['views'] / $max * 100, 1) }}% + 4px);width:8px;height:8px;border-radius:50%;background:#DC2626"></span>@endif
        </div>
      @endforeach
    </div>
    <div class="mt-1 flex justify-between text-xs text-gray-500"><span>{{ \Illuminate\Support\Carbon::parse($series->first()['date'])->format('j M') }}</span><span>{{ \Illuminate\Support\Carbon::parse($series->last()['date'])->format('j M') }}</span></div>
  </div>

  <div class="mb-6 grid gap-6 xl:grid-cols-3">
    <div class="{{ $card }} overflow-x-auto xl:col-span-2">
      <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Top pages</h2>
      <table class="data-table min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500"><tr><th class="{{ $th }}">Page</th><th class="{{ $th }} text-right">Views</th><th class="{{ $th }} text-right">Visitors</th><th class="{{ $th }} text-right">Time</th><th class="{{ $th }} text-right">Scroll</th><th class="{{ $th }}"></th></tr></thead>
        <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
          @forelse ($pages as $p)
            <tr><td class="{{ $td }} font-medium text-gray-900 dark:text-white">{{ $p->path }}</td><td class="{{ $td }} text-right">{{ number_format($p->views) }}</td><td class="{{ $td }} text-right">{{ number_format($p->visitors) }}</td><td class="{{ $td }} text-right">{{ $duration($p->avg_ms) }}</td><td class="{{ $td }} text-right">{{ $p->scroll ? round($p->scroll).'%' : '—' }}</td>
              <td class="{{ $td }} text-right"><a class="text-xs text-red-600 hover:underline" href="{{ route('site-analytics.heatmap', ['path' => $p->path, 'days' => $days]) }}">Heatmap</a></td></tr>
          @empty
            <tr><td colspan="6" class="{{ $td }} text-gray-500">No visits yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="{{ $card }}">
      <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Form conversions</h2>
      <ul class="space-y-2 text-sm">
        @forelse ($forms as $f)
          <li class="flex justify-between gap-3 text-gray-700 dark:text-gray-300"><span>{{ $f['label'] }}</span><b class="text-gray-900 dark:text-white">{{ number_format($f['n']) }}</b></li>
        @empty
          <li class="text-gray-500">No forms sent yet. Counted: {{ implode(', ', array_values($formLabels)) }}.</li>
        @endforelse
      </ul>
      <h3 class="mb-2 mt-5 text-sm font-semibold text-gray-900 dark:text-white">Contact clicks</h3>
      <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
        @foreach (['phone_click' => 'Phone links', 'whatsapp_click' => 'WhatsApp links', 'email_click' => 'Email links', 'outbound' => 'Links to other sites'] as $k => $label)
          <li class="flex justify-between"><span>{{ $label }}</span><span>{{ number_format($contacts[$k] ?? 0) }}</span></li>
        @endforeach
      </ul>
    </div>
  </div>

  <div class="mb-6 grid gap-6 xl:grid-cols-2">
    <div class="{{ $card }} overflow-x-auto">
      <h2 class="font-semibold text-gray-900 dark:text-white">Where visitors come from</h2>
      <p class="mb-3 text-xs text-gray-500">utm_source when the link was tagged, otherwise the referring site. Conversions use the visitor's first source this session.</p>
      <table class="data-table min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500"><tr><th class="{{ $th }}">Source</th><th class="{{ $th }} text-right">Views</th><th class="{{ $th }} text-right">Visitors</th><th class="{{ $th }} text-right">Conversions</th></tr></thead>
        <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
          @forelse ($sources as $s)
            <tr><td class="{{ $td }}">{{ $s->source }}</td><td class="{{ $td }} text-right">{{ number_format($s->views) }}</td><td class="{{ $td }} text-right">{{ number_format($s->visitors) }}</td><td class="{{ $td }} text-right font-medium">{{ $s->conversions }}</td></tr>
          @empty
            <tr><td colspan="4" class="{{ $td }} text-gray-500">No visits yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="{{ $card }} overflow-x-auto">
      <h2 class="font-semibold text-gray-900 dark:text-white">UTM campaigns</h2>
      <p class="mb-3 text-xs text-gray-500">Links tagged with utm_campaign, e.g. <code>?utm_source=facebook&amp;utm_medium=paid_social&amp;utm_campaign=landlords_nairobi_2026q4</code>.</p>
      <table class="data-table min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500"><tr><th class="{{ $th }}">Campaign</th><th class="{{ $th }}">Source / medium</th><th class="{{ $th }} text-right">Visitors</th><th class="{{ $th }} text-right">Conv.</th><th class="{{ $th }} text-right">Rate</th></tr></thead>
        <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
          @forelse ($campaigns as $c)
            <tr><td class="{{ $td }} font-medium text-gray-900 dark:text-white">{{ $c->campaign }}</td><td class="{{ $td }} text-xs">{{ $c->source ?: '—' }} / {{ $c->medium ?: '—' }}</td><td class="{{ $td }} text-right">{{ number_format($c->visitors) }}</td><td class="{{ $td }} text-right">{{ $c->conversions }}</td><td class="{{ $td }} text-right">{{ $c->visitors ? round($c->conversions / $c->visitors * 100, 1) : 0 }}%</td></tr>
          @empty
            <tr><td colspan="5" class="{{ $td }} text-gray-500">No tagged campaign visits yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mb-6 grid gap-6 xl:grid-cols-2">
    <div class="{{ $card }} overflow-x-auto">
      <h2 class="font-semibold text-gray-900 dark:text-white">Conversions by form and source</h2>
      <p class="mb-3 text-xs text-gray-500">Which channel brought each sign-up. Leads themselves are in Sales CRM › Requests / Waitlist.</p>
      <table class="data-table min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500"><tr><th class="{{ $th }}">Form</th><th class="{{ $th }}">Source</th><th class="{{ $th }}">Campaign</th><th class="{{ $th }} text-right">Sent</th></tr></thead>
        <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
          @forelse ($formSources as $r)
            <tr><td class="{{ $td }}">{{ $formLabels[$r->label] ?? $r->label }}</td><td class="{{ $td }}">{{ $r->source ?: 'direct' }}</td><td class="{{ $td }} text-xs">{{ $r->campaign ?: '—' }}</td><td class="{{ $td }} text-right font-medium">{{ $r->n }}</td></tr>
          @empty
            <tr><td colspan="4" class="{{ $td }} text-gray-500">No conversions yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="{{ $card }} overflow-x-auto">
      <h2 class="font-semibold text-gray-900 dark:text-white">Listing views</h2>
      <p class="mb-3 text-xs text-gray-500">Listings opened on Open listings or their own page. {{ number_format($listingRequests) }} viewing / booking requests sent in this period.</p>
      <table class="data-table min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500"><tr><th class="{{ $th }}">Listing</th><th class="{{ $th }} text-right">Opened</th><th class="{{ $th }} text-right">Visitors</th></tr></thead>
        <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
          @forelse ($listings as $l)
            <tr><td class="{{ $td }}">{{ $l->label }}</td><td class="{{ $td }} text-right">{{ number_format($l->views) }}</td><td class="{{ $td }} text-right">{{ number_format($l->visitors) }}</td></tr>
          @empty
            <tr><td colspan="3" class="{{ $td }} text-gray-500">No listings opened yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([['Devices', $devices], ['Browsers', $browsers], ['Countries', $countries]] as [$title, $rows])
      <div class="{{ $card }}">
        <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
        <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
          @forelse ($rows as $r)
            <li class="flex justify-between"><span>{{ ucfirst($r->label) }}</span><span>{{ number_format($r->views) }}</span></li>
          @empty
            <li class="text-gray-500">{{ $title === 'Countries' ? 'Shown when the site runs behind Cloudflare.' : 'No visits yet.' }}</li>
          @endforelse
        </ul>
      </div>
    @endforeach
    <div class="{{ $card }}">
      <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Most clicked</h2>
      <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
        @forelse ($clicks as $c)
          <li class="flex justify-between gap-3"><span class="truncate" title="{{ $c->path }}">{{ $c->target }}</span><span>{{ number_format($c->clicks) }}</span></li>
        @empty
          <li class="text-gray-500">No clicks yet.</li>
        @endforelse
      </ul>
    </div>
  </div>
  @endif
</div>
@endsection
