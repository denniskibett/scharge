{{-- Renter demand heatmaps (DemandController). docs/RENTER_DEMAND.md --}}
@extends('layouts.app')
@section('title', 'Renter demand')

@section('content')
@php
  $card = 'rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
  $th = 'p-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400';
  $td = 'p-2 text-sm text-gray-800 dark:text-white/90';
  $sel = 'h-10 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      @section('page_title')
Renter demand
@endsection
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Where people look for homes on scharge's Open listings, and at what budget. Searches, SMS alerts and viewing requests (a viewing request counts 5, an alert 3, a search 1). Anonymous.@unless ($isStaff) Free for every landlord on scharge.@endunless</p>
    </div>
    <form method="GET" class="flex flex-wrap gap-2">
      <select name="kind" class="{{ $sel }}" onchange="this.form.submit()" aria-label="Kind">@foreach (\App\Http\Controllers\DemandController::KINDS as $k)<option @selected($kind === $k)>{{ $k }}</option>@endforeach</select>
      <select name="days" class="{{ $sel }}" onchange="this.form.submit()" aria-label="Period">@foreach ([7, 30, 90, 180] as $d)<option value="{{ $d }}" @selected($days === $d)>{{ $d }} days</option>@endforeach</select>
    </form>
  </div>

  @unless ($isStaff)
    @if ($vacantUnlisted || ! $listed)
      <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
        @if (! $listed)You have no homes on Open listings right now.@endif
        @if ($vacantUnlisted) {{ $vacantUnlisted }} vacant {{ $vacantUnlisted === 1 ? 'unit has' : 'units have' }} no rent amount, so renters cannot see {{ $vacantUnlisted === 1 ? 'it' : 'them' }}.@endif
        Set a rent amount on <a class="font-medium underline" href="{{ route('units.index') }}">Units</a> and vacant units appear on Open listings for free, with viewing requests coming to your Enquiries.
      </div>
    @endif
  @endunless

  @if (! $ready || ! $r)
    <p class="text-sm text-gray-500">Collecting data: come back in a few days.</p>
  @else
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
      @foreach (['searches' => 'Searches', 'alerts' => 'SMS alerts set', 'viewings' => 'Viewing requests', 'people' => 'People', 'unmet_share' => 'Searches with no match'] as $k => $l)
        <div class="{{ $card }} p-4"><p class="text-xs text-gray-500">{{ $l }}</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($r['totals'][$k]) }}{{ $k === 'unmet_share' ? '%' : '' }}</p></div>
      @endforeach
    </div>

    <div class="{{ $card }} overflow-x-auto p-4">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Heatmap: area × budget</h2>
      <p class="mb-3 text-xs text-gray-500">Darker = more demand. {{ $kind === 'Short stays' ? 'KES per night.' : 'KES per month.' }}@unless ($isStaff) Your areas are marked ●.@endunless</p>
      @if (! $r['areas'])
        <p class="text-sm text-gray-500">No searches in this period yet.</p>
      @else
        <table class="w-full border-separate border-spacing-1">
          <thead><tr><th class="{{ $th }}">Area</th>@foreach ($r['bands'] as $b)<th class="{{ $th }} text-center">{{ $b }}</th>@endforeach</tr></thead>
          <tbody>@foreach ($r['areas'] as $a)
            <tr><td class="{{ $td }} whitespace-nowrap">{{ $a }}@if (in_array($a, $mine, true)) <span class="text-brand-600 dark:text-brand-400" title="You have property here">●</span>@endif</td>
              @foreach ($r['bands'] as $b)
                @php $v = $r['grid'][$a][$b] ?? 0; $alpha = $v ? 0.12 + 0.88 * ($v / $r['max']) : 0; @endphp
                <td class="rounded-md p-2 text-center text-xs {{ $alpha > 0.55 ? 'text-white' : 'text-gray-700 dark:text-gray-300' }}" style="background: {{ $v ? 'rgba(70,95,255,'.round($alpha, 2).')' : 'transparent' }}" title="{{ $a }}, {{ $b }}: {{ $v }}">{{ $v ?: '' }}</td>
              @endforeach</tr>
          @endforeach</tbody>
        </table>
      @endif
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Looked for, nothing found</h2>
        <p class="px-4 text-xs text-gray-500">Areas where searches matched no listing: homes people want that nobody offers yet.</p>
        <table class="w-full"><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($r['unmet'] as $area => $n)<tr><td class="{{ $td }} px-4">{{ $area }}@if (in_array($area, $mine, true)) ●@endif</td><td class="{{ $td }} px-4 text-right">{{ number_format($n) }} searches</td></tr>@empty<tr><td class="{{ $td }} px-4 text-gray-500">None in this period.</td></tr>@endforelse</tbody></table></div>
      <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Demand against homes on offer</h2>
        <p class="px-4 text-xs text-gray-500">Highest demand per listing first: where a new listing gets most attention.</p>
        <table class="w-full"><thead><tr><th class="{{ $th }} px-4">Area</th><th class="{{ $th }} text-right">Demand</th><th class="{{ $th }} px-4 text-right">Listings</th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($r['versus'] as $x)<tr><td class="{{ $td }} px-4">{{ $x['area'] }}@if (in_array($x['area'], $mine, true)) ●@endif</td><td class="{{ $td }} text-right">{{ number_format($x['demand']) }}</td><td class="{{ $td }} px-4 text-right">{{ $x['listings'] }}</td></tr>@empty<tr><td colspan="3" class="{{ $td }} px-4 text-gray-500">No data yet.</td></tr>@endforelse</tbody></table></div>
      <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Where searchers are</h2>
        <p class="px-4 text-xs text-gray-500">Approximate city of the person searching.</p>
        <table class="w-full"><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($r['cities'] as $city => $n)<tr><td class="{{ $td }} px-4">{{ $city }}</td><td class="{{ $td }} px-4 text-right">{{ number_format($n) }}</td></tr>@empty<tr><td class="{{ $td }} px-4 text-gray-500">No data yet.</td></tr>@endforelse</tbody></table></div>
    </div>
  @endif
</div>
@endsection
