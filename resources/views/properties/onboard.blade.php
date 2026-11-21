@extends('layouts.app')
@section('title', 'Import properties')

@section('content')
@php
  $th = 'p-3 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
  $td = 'p-3 text-sm text-gray-700 dark:text-gray-300 align-top';
  $card = 'overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
  $btn = 'bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition';
  $btn2 = 'inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300';
  $input = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $badge = fn ($a) => match ($a) {
      'created' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      'matched', 'linked' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      'claim', 'review' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
      'error' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
  };
  $words = ['created' => 'New', 'matched' => 'Already on scharge', 'linked' => 'Linked to you', 'claim' => 'Claim sent', 'review' => 'Check match', 'error' => 'Error', 'skipped' => 'Skipped'];
  $shown = $done ?? $report;
@endphp

<div class="mb-6">
  @section('page_title')
Import properties and units
@endsection
  <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">One row per unit, with the building's details repeated on each row. Every building and unit is checked against scharge first: one already here is matched (blank details filled, nothing overwritten) and linked to you in your role, never copied. You see the result before anything is saved.</p>
</div>

@if ($errors->any())
  <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ $errors->first() }}</div>
@endif
@if ($done)
  <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">Imported for {{ $done['company']['name'] }}. The results are below.</div>
@endif

@if (! $report && ! $done)
<div class="grid gap-6 lg:grid-cols-5">
  <div class="{{ $card }} p-5 lg:col-span-2">
    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">1. Fill the template</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open it in Excel or Google Sheets and replace the two grey example rows: one row per unit, with the tenant living there now and what they owe, if any. Upload it as it is; no need to save as CSV.</p>
    <div class="mt-4 flex flex-wrap gap-3">
      <a href="{{ route('properties.onboard.template.xlsx') }}" class="{{ $btn }}">Download template (Excel)</a>
      <a href="{{ route('properties.onboard.template') }}" class="{{ $btn2 }}">CSV</a>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Clients can fill it before onboarding too: <a href="{{ route('onboarding.template.public') }}" class="text-brand-500 hover:underline">{{ route('onboarding.template.public') }}</a></p>

    <h3 class="mt-8 text-lg font-semibold text-gray-800 dark:text-white/90">2. Upload it</h3>
    <form method="POST" action="{{ route('properties.onboard.preview') }}" enctype="multipart/form-data" class="mt-3 space-y-4">
      @csrf
      @if ($companies)
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Company
          <select name="company_id" required class="{{ $input }} mt-1.5">
            <option value="">Choose…</option>
            @foreach ($companies as $c)<option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>@endforeach
          </select>
        </label>
      @endif
      <fieldset>
        <legend class="text-sm font-medium text-gray-700 dark:text-gray-400">Your role in these buildings</legend>
        @foreach ($roles as $value => $label)
          <label class="mt-2 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="radio" name="role" value="{{ $value }}" class="mt-1" @checked(old('role', auth()->user()->hasRole('bnb_host') ? 'bnb_host' : 'manager') === $value)> {{ $label }}
          </label>
        @endforeach
      </fieldset>
      <input type="file" name="file" accept=".xlsx,.xls,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required class="block w-full text-sm text-gray-700 dark:text-gray-300">
      <button class="{{ $btn }}">Check the file</button>
      <p class="text-xs text-gray-500 dark:text-gray-400">Excel (.xlsx, .xls) or CSV, up to 5 MB. Checking saves nothing, and tenants get no message.</p>
    </form>
  </div>

  <div class="{{ $card }} lg:col-span-3">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700"><h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Columns</h3></div>
    <div class="w-full overflow-x-auto">
      <table class="w-full table-auto">
        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
          @foreach ($columns as $col => $help)
            <tr><td class="{{ $td }} whitespace-nowrap font-mono text-xs">{{ $col }}</td><td class="{{ $td }}">{{ $help }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

@if ($shown)
  @php $c = $shown['counts']; @endphp
  <div class="mb-5 {{ $card }} p-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $shown['commit'] ? 'Import done' : 'Preview: nothing saved yet' }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">@if (! empty($upload['name'])){{ $upload['name'] }} · @endif{{ $shown['company']['name'] }} as {{ str_replace('_', ' ', $shown['role']) }} · {{ $c['rows'] }} rows</p>
      </div>
      <div class="flex gap-3">
        @if (! $shown['commit'] && empty($shown['fatal']))
          <form method="POST" action="{{ route('properties.onboard.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $upload['token'] }}"><button class="{{ $btn }}">Import {{ $c['units_created'] + $c['units_matched'] + $c['units_linked'] }} units</button></form>
        @endif
        <form method="POST" action="{{ route('properties.onboard.cancel') }}">@csrf<button class="{{ $btn2 }}">{{ $shown['commit'] ? 'Import another file' : 'Cancel' }}</button></form>
      </div>
    </div>
    @if (! empty($shown['fatal']))<p class="mt-3 text-sm text-red-600">{{ $shown['fatal'] }}</p>@endif
    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
      @foreach (['properties_created' => 'New buildings', 'properties_matched' => 'Buildings matched', 'properties_review' => 'To check', 'claims' => 'Claims',
                 'units_created' => 'New units', 'units_matched' => 'Units matched', 'units_linked' => 'Units linked', 'units_skipped' => 'Units skipped',
                 'tenants_created' => 'New tenants', 'tenants_skipped' => 'Tenants skipped', 'balances_added' => 'Balances carried over',
                 'meters_created' => 'New meters', 'fields_filled' => 'Blanks filled', 'errors' => 'Rows with errors'] as $k => $label)
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="stat-num text-lg font-semibold text-gray-800 dark:text-white/90">{{ number_format($c[$k] ?? 0) }}</dd></div>
      @endforeach
    </dl>
    @if (($c['balances_total'] ?? 0) > 0)<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Balances carried over: <span class="stat-num font-semibold">KES {{ number_format($c['balances_total'], 2) }}</span>, each as one unpaid bill on the tenant's account.</p>@endif
    @if (! empty($shown['unknown_columns']))<p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Ignored columns: {{ implode(', ', $shown['unknown_columns']) }}</p>@endif
  </div>

  <div class="mb-5 {{ $card }}">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700"><h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Buildings</h3></div>
    <div class="w-full overflow-x-auto">
      <table class="w-full table-auto">
        <thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Building in the file</th><th class="{{ $th }}">Result</th><th class="{{ $th }}">On scharge</th><th class="{{ $th }}">Notes</th></tr></thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
          @foreach ($shown['properties'] as $p)
            <tr>
              <td class="{{ $td }}"><span class="font-medium text-gray-800 dark:text-white/90">{{ $p['name'] }}</span><p class="text-xs text-gray-500">{{ $p['location'] ?: 'No location' }}{{ $p['lr_number'] ? ' · LR '.$p['lr_number'] : '' }}</p></td>
              <td class="{{ $td }} whitespace-nowrap"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $badge($p['action']) }}">{{ $words[$p['action']] ?? $p['action'] }}</span></td>
              <td class="{{ $td }}">
                @if ($p['id'] && $p['action'] !== 'created')#{{ $p['id'] }}{{ $p['score'] && $p['score'] < 100 ? ' ('.$p['score'].'% match)' : '' }}{{ $p['managed_by'] ? ' · managed by '.$p['managed_by'] : '' }}@endif
                @foreach ($p['candidates'] as $m)<p class="text-xs">#{{ $m['id'] }} {{ $m['name'] }} · {{ $m['location'] ?: 'no location' }} · {{ $m['units'] }} units · {{ $m['score'] }}%</p>@endforeach
                @foreach ($p['crm'] as $m)<p class="text-xs">CRM #{{ $m['id'] }} {{ $m['name'] }} ({{ str_replace('_', ' ', $m['kind'] ?? '') }}){{ $m['metres'] !== null ? ' · '.$m['metres'].' m away' : '' }}</p>@endforeach
              </td>
              <td class="{{ $td }} text-xs">{{ implode(' ', $p['notes']) ?: $p['error'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="{{ $card }}">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700"><h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Units</h3></div>
    <div class="w-full overflow-x-auto">
      <table class="w-full table-auto">
        <thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Line</th><th class="{{ $th }}">Building</th><th class="{{ $th }}">Unit</th><th class="{{ $th }}">Result</th><th class="{{ $th }}">Filled</th><th class="{{ $th }}">Kept (scharge differs from the file)</th><th class="{{ $th }}">Notes</th></tr></thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
          @foreach ($shown['rows'] as $r)
            <tr>
              <td class="{{ $td }}">{{ $r['line'] }}</td>
              <td class="{{ $td }}">{{ $r['property'] }}</td>
              <td class="{{ $td }} whitespace-nowrap">{{ $r['unit'] }}</td>
              <td class="{{ $td }} whitespace-nowrap"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $badge($r['unit_action']) }}">{{ $words[$r['unit_action']] ?? $r['unit_action'] }}</span></td>
              <td class="{{ $td }} text-xs">{{ implode(', ', array_merge($r['unit_action'] === 'created' ? ['new unit'] : [], array_map(fn ($f) => str_replace('_', ' ', $f), $r['filled']))) }}</td>
              <td class="{{ $td }} text-xs">@foreach ($r['kept'] as $k)<p>{{ str_replace('_', ' ', $k['field']) }}: {{ $k['existing'] }} (file: {{ $k['file'] }})</p>@endforeach</td>
              <td class="{{ $td }} text-xs @if ($r['errors']) text-red-600 @endif">{{ implode('; ', array_merge($r['errors'], $r['notes'])) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
