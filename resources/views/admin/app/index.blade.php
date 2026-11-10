{{-- scharge staff › App health (Admin\AppHealthController). docs/APP_DATA.md --}}
@extends('layouts.app')
@section('title', 'App health')

@section('content')
@php
  $card = 'rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
  $th = 'p-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400';
  $td = 'p-3 text-sm text-gray-800 dark:text-white/90';
@endphp
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">App health</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Phones that used the scharge app in the last 30 days, which versions they run, and what crashes.</p>
  </div>
  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="{{ $card }} p-4"><p class="text-xs text-gray-500">Phones (30 days)</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($phones) }}</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs text-gray-500">Can get push</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($withPush) }} <span class="text-sm font-normal text-gray-500">{{ $phones ? round($withPush * 100 / $phones) : 0 }}%</span></p></div>
    <div class="{{ $card }} p-4"><p class="text-xs text-gray-500">Push sending</p><p class="mt-1 text-lg font-semibold {{ $pushOn ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">{{ $pushOn ? 'On' : 'Off: add Firebase in Integrations' }}</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs text-gray-500">Oldest version allowed</p><p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $minVersion ?: 'Any' }}</p></div>
  </div>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @foreach (['App versions' => $versions, 'Android / iOS versions' => $os, 'Phone models' => $models] as $title => $rows)
      <div class="{{ $card }} overflow-x-auto"><h2 class="px-4 pt-4 text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
        <table class="w-full"><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($rows as $r)<tr><td class="{{ $td }}">{{ $r->v }}</td><td class="{{ $td }} text-right">{{ number_format($r->n) }}</td></tr>@empty<tr><td class="{{ $td }} text-gray-500">Nothing yet.</td></tr>@endforelse</tbody></table></div>
    @endforeach
  </div>
  <div class="{{ $card }} overflow-x-auto">
    <div class="flex items-center justify-between px-4 pt-4"><h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $showResolved ? 'Fixed errors' : 'Crashes and errors' }}</h2>
      <a href="{{ route('app-health.index', $showResolved ? [] : ['resolved' => 1]) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">{{ $showResolved ? 'Show open' : 'Show fixed' }}</a></div>
    <table class="w-full"><thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Error</th><th class="{{ $th }} text-right">Times</th><th class="{{ $th }} hidden md:table-cell">Last seen</th><th class="{{ $th }}"></th></tr></thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($errors as $e)
      <tr class="align-top"><td class="{{ $td }}">{{ $e->message }}<span class="block text-xs text-gray-500">{{ collect([$e->screen, $e->app_version ? 'app '.$e->app_version : null, $e->os_version, $e->device_model])->filter()->implode(' · ') }}</span>
        @if ($e->stack)<details class="mt-1"><summary class="cursor-pointer text-xs text-brand-600 dark:text-brand-400">Stack</summary><pre class="mt-1 max-h-64 overflow-auto whitespace-pre-wrap text-xs text-gray-600 dark:text-gray-400">{{ $e->stack }}</pre></details>@endif</td>
        <td class="{{ $td }} text-right">{{ number_format($e->count) }}</td>
        <td class="{{ $td }} hidden whitespace-nowrap md:table-cell">{{ \Carbon\Carbon::parse($e->last_seen_at)->timezone('Africa/Nairobi')->format('j M, H:i') }}</td>
        <td class="{{ $td }} text-right">@unless ($showResolved)<form method="POST" action="{{ route('app-health.resolve', $e->id) }}">@csrf<button class="text-xs text-brand-600 hover:underline dark:text-brand-400">Mark fixed</button></form>@endunless</td></tr>
    @empty<tr><td colspan="4" class="{{ $td }} text-gray-500">No errors reported.</td></tr>@endforelse</tbody></table>
  </div>
</div>
@endsection
