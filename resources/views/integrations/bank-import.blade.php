{{-- Payments › Bank statement import: upload a CSV/Excel statement, confirm each matched deposit (docs/INTEGRATIONS.md). --}}
@extends('layouts.app')
@section('title', 'Bank statement import')
@section('page_title', 'Bank statement import')

@section('content')
@php
  $q = request()->filled('company') ? ['company' => request('company')] : [];
  $money = fn ($n) => 'KES '.number_format((float) $n);
  $btn = 'inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium';
@endphp

@if (session('status'))
  <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('status') }}</div>
@endif
@error('statement')
  <div class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">{{ $message }}</div>
@enderror

@if (! $installed)
  <div class="rounded-2xl border border-gray-200 bg-white p-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
    Bank import is not switched on for this server yet. scharge support needs to apply database/schema/2026_11_20_integrations_more.sql.
  </div>
@else
  <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @include('partials.card.stat', ['card' => ['label' => 'To confirm', 'value' => number_format($counts['suggested']->n ?? 0), 'icon' => 'check-circle', 'tone' => 'amber', 'hint' => $money($counts['suggested']->total ?? 0)]])
    @include('partials.card.stat', ['card' => ['label' => 'No match yet', 'value' => number_format($counts['new']->n ?? 0), 'icon' => 'magnifying-glass', 'tone' => 'sky', 'hint' => $money($counts['new']->total ?? 0)]])
    @include('partials.card.stat', ['card' => ['label' => 'Recorded', 'value' => number_format($counts['posted']->n ?? 0), 'icon' => 'banknotes', 'tone' => 'green', 'hint' => $money($counts['posted']->total ?? 0)]])
    @include('partials.card.stat', ['card' => ['label' => 'Not rent', 'value' => number_format($counts['ignored']->n ?? 0), 'icon' => 'x-circle', 'tone' => 'red', 'hint' => $money($counts['ignored']->total ?? 0)]])
  </div>

  <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 dark:border-gray-800">
      <nav class="flex gap-5 text-sm">
        @foreach (['open' => 'To do', 'suggested' => 'Matched', 'new' => 'No match', 'posted' => 'Recorded', 'ignored' => 'Not rent'] as $key => $label)
          <a href="{{ route('bank-import.index', $q + ['show' => $key]) }}"
             class="border-b-2 py-4 font-medium {{ $status === $key ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">{{ $label }}</a>
        @endforeach
      </nav>
      <form method="POST" action="{{ route('bank-import.store', $q) }}" enctype="multipart/form-data" class="flex items-center gap-2 py-3">
        @csrf
        <input type="file" name="statement" accept=".csv,.xlsx,text/csv" required class="max-w-[14rem] text-sm text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm dark:text-gray-300 dark:file:bg-white/10 dark:file:text-gray-200">
        <button class="{{ $btn }} bg-brand-500 text-white hover:bg-brand-600">Upload statement</button>
      </form>
    </div>

    <p class="px-5 pt-4 text-xs text-gray-500 dark:text-gray-400">
      Download the statement from your bank's internet banking as CSV or Excel. Only money in is read; a deposit already imported is skipped.
      Matches use the bill number, the unit number with the exact amount, the tenant's phone number, or their name with the exact amount.
    </p>

    <div class="overflow-x-auto p-5">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
          <tr><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Deposit</th><th class="py-2 pr-4 text-right">Amount</th><th class="py-2 pr-4">Bill</th><th class="py-2"></th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          @forelse ($lines as $l)
            @php $inv = $l->invoice_id ? ($invoices[$l->invoice_id] ?? null) : null; @endphp
            <tr class="align-top">
              <td class="py-3 pr-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($l->posted_on)->format('j M Y') }}</td>
              <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">
                <span class="block max-w-md break-words">{{ $l->description ?: '—' }}</span>
                @if ($l->reference)<span class="text-xs text-gray-400">Ref {{ $l->reference }}</span>@endif
              </td>
              <td class="py-3 pr-4 text-right font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ $money($l->amount) }}</td>
              <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">
                @if ($l->status === 'posted' && $inv)
                  {{ \App\Services\Connectors\Payloads::number($inv) }} · {{ $inv->tenancy?->tenant?->user?->name }}
                @elseif (in_array($l->status, ['suggested', 'new'], true))
                  <form id="post-{{ $l->id }}" method="POST" action="{{ route('bank-import.post', $q + ['line' => $l->id]) }}">
                    @csrf
                    <select name="invoice_id" required class="h-9 w-64 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900">
                      <option value="">Choose the bill…</option>
                      @foreach ($open as $o)
                        <option value="{{ $o->id }}" @selected($o->id === $l->invoice_id)>{{ $o->tenancy?->unit?->unit_number }} · {{ $o->tenancy?->tenant?->user?->name }} · {{ \App\Services\Connectors\Payloads::number($o) }} · owes {{ number_format($o->total_amount - $o->total_paid) }}</option>
                      @endforeach
                    </select>
                  </form>
                  @if ($l->match_reason)<span class="mt-1 block text-xs text-emerald-600 dark:text-emerald-400">Matched by {{ strtolower($l->match_reason) }}</span>@endif
                @else
                  <span class="text-gray-400">—</span>
                @endif
              </td>
              <td class="py-3 text-right whitespace-nowrap">
                @if (in_array($l->status, ['suggested', 'new'], true))
                  <button form="post-{{ $l->id }}" class="{{ $btn }} bg-gray-900 text-white hover:bg-gray-700 dark:bg-brand-500">Record</button>
                  <form method="POST" action="{{ route('bank-import.ignore', $q + ['line' => $l->id]) }}" class="inline">@csrf
                    <button class="{{ $btn }} text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Not rent</button></form>
                @elseif ($l->status === 'ignored')
                  <form method="POST" action="{{ route('bank-import.ignore', $q + ['line' => $l->id, 'undo' => 1]) }}" class="inline">@csrf
                    <button class="{{ $btn }} text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Undo</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-10 text-center text-gray-500 dark:text-gray-400">Nothing here. Upload a statement to start.</td></tr>
          @endforelse
        </tbody>
      </table>
      @if (method_exists($lines, 'links'))<div class="mt-4">{{ $lines->links() }}</div>@endif
    </div>
  </div>
@endif
@endsection
