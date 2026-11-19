{{-- scharge staff › Sign-in locations (Admin\SignInController). docs/SIGN_IN_LOCATIONS.md --}}
@extends('layouts.app')
@section('title', 'Sign-in locations')

@section('content')
@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $card = 'rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
  $th = 'p-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400';
  $td = 'p-3 text-sm text-gray-800 dark:text-white/90';
  $q = fn (array $over) => array_filter(array_merge(['days' => $days, 'channel' => $channel, 'company' => $companyId], $over), fn ($v) => $v !== null && $v !== '');
  $channels = \App\Http\Controllers\Admin\SignInController::CHANNELS;
@endphp
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      @section('page_title')
Sign-in locations
@endsection
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Where people sign in from: the website, the mobile app and the reader app. Locations are approximate (city level) and come from the IP address. Kept 180 days.</p>
    </div>
    <form method="GET" class="flex flex-wrap gap-2">
      <select name="days" class="h-10 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="this.form.submit()" aria-label="Period">@foreach (\App\Http\Controllers\Admin\SignInController::RANGES as $d => $l)<option value="{{ $d }}" @selected($days === $d)>{{ $l }}</option>@endforeach</select>
      <select name="channel" class="h-10 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="this.form.submit()" aria-label="Channel"><option value="">All channels</option>@foreach ($channels as $k => $l)<option value="{{ $k }}" @selected($channel === $k)>{{ $l }}</option>@endforeach</select>
      <select name="company" class="h-10 max-w-56 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="this.form.submit()" aria-label="Company"><option value="">All companies</option>@foreach ($companyOptions as $c)<option value="{{ $c->id }}" @selected($companyId === (int) $c->id)>{{ $c->name }}</option>@endforeach</select>
    </form>
  </div>

  @unless ($geoReady)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">The city database is not on the server yet, so locations show as Unknown. It downloads monthly; run <code>php artisan geo:update</code> once to start now.</div>
  @endunless

  <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
    @foreach (['sign_ins' => 'Sign-ins', 'people' => 'People', 'countries' => 'Countries', 'failed' => 'Failed attempts', 'unknown' => 'Location unknown'] as $k => $l)
      <div class="{{ $card }} p-4"><p class="text-xs text-gray-500 dark:text-gray-400">{{ $l }}</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($totals[$k]) }}</p></div>
    @endforeach
  </div>
  <p class="text-sm text-gray-500 dark:text-gray-400">@foreach ($channels as $k => $l){{ $l }}: <strong class="text-gray-800 dark:text-white/90">{{ number_format($channelCounts[$k] ?? 0) }}</strong>@if (! $loop->last) · @endif @endforeach</p>

  <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Countries</h2>
      <table class="w-full"><thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Country</th><th class="{{ $th }} text-right">Sign-ins</th><th class="{{ $th }} text-right">People</th></tr></thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($countries as $r)<tr><td class="{{ $td }}">{{ $r->place }}</td><td class="{{ $td }} text-right">{{ number_format($r->n) }}</td><td class="{{ $td }} text-right">{{ number_format($r->people) }}</td></tr>@empty<tr><td colspan="3" class="{{ $td }} text-gray-500">No sign-ins in this period.</td></tr>@endforelse</tbody></table></div>
    <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Cities</h2>
      <table class="w-full"><thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">City</th><th class="{{ $th }} text-right">Sign-ins</th><th class="{{ $th }} text-right">People</th></tr></thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($cities as $r)<tr><td class="{{ $td }}">{{ $r->city }}<span class="block text-xs text-gray-500">{{ collect([$r->region, $r->country_code])->filter()->implode(', ') }}</span></td><td class="{{ $td }} text-right">{{ number_format($r->n) }}</td><td class="{{ $td }} text-right">{{ number_format($r->people) }}</td></tr>@empty<tr><td colspan="3" class="{{ $td }} text-gray-500">No city-level locations yet.</td></tr>@endforelse</tbody></table></div>
    <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Companies</h2>
      <table class="w-full"><thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Company</th><th class="{{ $th }} text-right">Sign-ins</th><th class="{{ $th }} text-right">People</th><th class="{{ $th }} text-right">Cities</th></tr></thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($companies as $r)<tr><td class="{{ $td }}">@if ($r->company_id)<a class="hover:underline" href="{{ route('sign-ins.index', $q(['company' => $r->company_id])) }}">{{ $r->name }}</a>@else{{ $r->name }}@endif</td><td class="{{ $td }} text-right">{{ number_format($r->n) }}</td><td class="{{ $td }} text-right">{{ number_format($r->people) }}</td><td class="{{ $td }} text-right">{{ number_format($r->cities) }}</td></tr>@empty<tr><td colspan="4" class="{{ $td }} text-gray-500">Nothing yet.</td></tr>@endforelse</tbody></table></div>
    <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Failed attempts by country</h2>
      <table class="w-full"><thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Country</th><th class="{{ $th }} text-right">Attempts</th><th class="{{ $th }} text-right">IP addresses</th></tr></thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($failedByPlace as $r)<tr><td class="{{ $td }}">{{ $r->place }}</td><td class="{{ $td }} text-right">{{ number_format($r->n) }}</td><td class="{{ $td }} text-right">{{ number_format($r->ips) }}</td></tr>@empty<tr><td colspan="3" class="{{ $td }} text-gray-500">No failed attempts.</td></tr>@endforelse</tbody></table></div>
  </div>

  <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">Latest</h2>
    <table class="w-full"><thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">When</th><th class="{{ $th }}">Who</th><th class="{{ $th }}">Where</th><th class="{{ $th }} hidden md:table-cell">How</th><th class="{{ $th }}">Result</th></tr></thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($recent as $r)
      <tr><td class="{{ $td }} whitespace-nowrap">{{ \Carbon\Carbon::parse($r->created_at)->timezone('Africa/Nairobi')->format('j M, H:i') }}</td>
        <td class="{{ $td }}">{{ $r->user_name ?? 'Unknown account' }}<span class="block text-xs text-gray-500">{{ collect([$r->role, $r->company_name])->filter()->implode(' · ') }}</span></td>
        <td class="{{ $td }}">{{ collect([$r->city, $r->country ?? $r->country_code])->filter()->implode(', ') ?: 'Unknown' }}<span class="block font-mono text-xs text-gray-500">{{ $r->ip }}</span></td>
        <td class="{{ $td }} hidden md:table-cell">{{ $channels[$r->channel] ?? $r->channel }}<span class="block text-xs text-gray-500">{{ collect([$r->method, $r->device, $r->browser])->filter()->implode(' · ') }}</span></td>
        <td class="{{ $td }}">@if ($r->success)<span class="text-emerald-700 dark:text-emerald-300">Signed in</span>@else<span class="text-red-700 dark:text-red-300">Failed</span>@endif</td></tr>
    @empty<tr><td colspan="5" class="{{ $td }} text-gray-500">No sign-ins in this period.</td></tr>@endforelse</tbody></table></div>

  <p class="text-xs text-gray-500">IP geolocation by <a class="underline" href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a> (CC BY 4.0).</p>
</div>
@endsection
