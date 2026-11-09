{{-- Reports & analytics. Data: ReportController@index → App\Services\Reports\PortfolioReport. Charts: resources/js/components/charts/reports.js --}}
@extends('layouts.app')
@section('title', 'Reports & analytics')

@section('content')
@php
  $k = $data['kpis'];
  $kes = fn ($v) => 'KES '.number_format((float) $v);
  $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
  $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
  $query = array_filter(['period' => $period, 'company' => $companyId, 'property' => $propertyId, 'from' => request('from'), 'to' => request('to')]);
@endphp

<div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Reports &amp; analytics</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
      {{ $scopeLabel }} · {{ \Carbon\Carbon::parse($data['from'])->format('j M Y') }} – {{ \Carbon\Carbon::parse($data['to'])->format('j M Y') }}.
      Billing is by billing month, collections by payment date; arrears and occupancy are as of today.
    </p>
  </div>

  <form method="GET" action="{{ route('reports.index') }}" x-data="{ period: @js($period) }" class="flex flex-wrap items-end gap-2">
    @if ($companies->isNotEmpty())
      <label class="text-xs text-gray-500">Company
        <select name="company" onchange="this.form.requestSubmit()" class="mt-1 block h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
          <option value="">All</option>
          @foreach ($companies as $c)<option value="{{ $c->id }}" @selected($companyId === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
      </label>
    @endif
    <label class="text-xs text-gray-500">Property
      <select name="property" onchange="this.form.requestSubmit()" class="mt-1 block h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
        <option value="">All properties</option>
        @foreach ($properties as $p)<option value="{{ $p->id }}" @selected($propertyId === $p->id)>{{ $p->name }}</option>@endforeach
      </select>
    </label>
    <label class="text-xs text-gray-500">Period
      <select name="period" x-model="period" @change="period !== 'custom' && $el.form.requestSubmit()" class="mt-1 block h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
        @foreach ($periods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
      </select>
    </label>
    <template x-if="period === 'custom'">
      <div class="flex items-end gap-2">
        <label class="text-xs text-gray-500">From<input type="date" name="from" value="{{ $data['from'] }}" class="mt-1 block h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></label>
        <label class="text-xs text-gray-500">To<input type="date" name="to" value="{{ $data['to'] }}" class="mt-1 block h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></label>
        <button class="h-10 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white dark:bg-brand-500">Apply</button>
      </div>
    </template>
    <a href="{{ route('reports.export', $query) }}" data-no-spa class="inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
      Export CSV
    </a>
  </form>
</div>

{{-- Headline numbers --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
  @php $tiles = [
    ['Billed', $kes($k['billed']), number_format($k['invoices']).' invoices'],
    ['Collected', $kes($k['collected']), 'Money received in the period'],
    ['Collection rate', $pct($k['collection_rate']), 'Of this period’s bills, paid so far'],
    ['Arrears now', $kes($k['arrears']), 'Unpaid balance on all bills'],
    ['Occupancy', $pct($k['occupancy']), number_format($k['occupied']).' of '.number_format($k['units']).' units let'],
    ['Expenses', $kes($k['expenses']), 'Recorded in the period'],
    ['Net', $kes($k['net']), 'Collected minus expenses'],
    ['Water', number_format($k['water_units']).' units', $kes($k['water_billed']).' charged on readings'],
  ]; @endphp
  @foreach ($tiles as [$label, $value, $hint])
    <div class="{{ $card }}">
      <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
      <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    </div>
  @endforeach
</div>

{{-- Charts --}}
<div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
  <div class="{{ $card }} xl:col-span-2">
    <div class="mb-2 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Billed vs collected</h3>
      <span class="text-xs text-gray-500">By month, KES</span>
    </div>
    <div id="report-monthly" class="-ml-2 min-h-[300px]"></div>
  </div>
  <div class="{{ $card }}">
    <h3 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">How tenants paid</h3>
    <div id="report-methods" class="min-h-[300px]"></div>
  </div>
  <div class="{{ $card }} xl:col-span-2">
    <h3 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">Occupancy by property</h3>
    <div id="report-occupancy" class="-ml-2 min-h-[280px]"></div>
  </div>
  <div class="{{ $card }}">
    <h3 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">Arrears by age</h3>
    <div id="report-aging" class="min-h-[280px]"></div>
  </div>
</div>

{{-- Tables --}}
<div class="mt-6 grid grid-cols-1 gap-4 2xl:grid-cols-5">
  <div class="{{ $card }} 2xl:col-span-3">
    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Property performance</h3>
    <div class="overflow-x-auto">
      <table class="data-table w-full">
        <thead><tr><th>Property</th><th class="!text-right">Units</th><th class="!text-right">Occupancy</th><th class="!text-right">Rent roll / month</th><th class="!text-right">Billed</th><th class="!text-right">Collected</th><th class="!text-right">Rate</th><th class="!text-right">Arrears now</th></tr></thead>
        <tbody>
          @forelse ($data['properties'] as $p)
            <tr>
              <td><a href="{{ route('reports.index', array_merge($query, ['property' => $p['id']])) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $p['name'] }}</a>
                <span class="block text-xs text-gray-500">{{ $p['location'] }}@if ($companyId === null && $companies->isNotEmpty()) · {{ $p['company'] }}@endif</span></td>
              <td class="text-right">{{ number_format($p['units']) }}</td>
              <td class="text-right">{{ $pct($p['occupancy']) }}</td>
              <td class="text-right">{{ number_format($p['rent_roll']) }}</td>
              <td class="text-right">{{ number_format($p['billed']) }}</td>
              <td class="text-right">{{ number_format($p['collected']) }}</td>
              <td class="text-right">{{ $pct($p['collection_rate']) }}</td>
              <td class="text-right {{ $p['arrears'] > 0 ? 'font-medium text-red-600 dark:text-red-400' : '' }}">{{ number_format($p['arrears']) }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="py-6 text-center text-gray-500">No properties yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="{{ $card }} 2xl:col-span-2">
    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Owing the most</h3>
    <div class="overflow-x-auto">
      <table class="data-table w-full">
        <thead><tr><th>Tenant</th><th>Unit</th><th class="!text-right">Balance</th><th class="!text-right">Since</th></tr></thead>
        <tbody>
          @forelse ($data['top_arrears'] as $r)
            <tr>
              <td><a href="{{ url('/tenancies/'.$r['tenancy_id']) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $r['tenant'] ?: 'Tenancy #'.$r['tenancy_id'] }}</a>
                <span class="block text-xs text-gray-500">{{ $r['phone'] }}@if ($r['tenancy_status'] === 'ended') · moved out @endif</span></td>
              <td>{{ $r['unit'] }}<span class="block text-xs text-gray-500">{{ $r['property'] }}</span></td>
              <td class="text-right font-medium text-red-600 dark:text-red-400">{{ number_format($r['balance']) }}</td>
              <td class="text-right text-gray-500">{{ \Carbon\Carbon::parse($r['oldest'])->format('M Y') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="py-6 text-center text-gray-500">Nobody owes anything. 🎉</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@if (count($data['expense_categories']))
  <div class="{{ $card }} mt-6">
    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Expenses by category</h3>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
      @foreach ($data['expense_categories'] as $e)
        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5"><span class="text-gray-600 dark:text-gray-300">{{ $e['category'] }}</span><span class="font-medium text-gray-900 dark:text-white">{{ number_format($e['total']) }}</span></div>
      @endforeach
    </div>
  </div>
@endif

@php $chartData = ['monthly' => $data['monthly'], 'methods' => $data['methods'], 'aging' => $data['aging'], 'properties' => $data['properties']]; @endphp
<script type="application/json" id="reports-data">@json($chartData)</script>
@endsection
