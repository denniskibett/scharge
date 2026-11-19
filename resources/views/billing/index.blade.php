{{-- Billing (BillingController): scharge's bills for this company. Open while the company is locked for an unpaid bill. --}}
@extends('layouts.app')
@section('title', 'Billing')
@section('page_title', 'Billing')

@php
  $money = fn ($amount, $cur = 'KES') => $cur.' '.number_format((float) $amount, 0);
  $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
@endphp

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
  @if ($staff)
    <div class="{{ $card }} text-sm text-gray-600 dark:text-gray-300">You work for scharge, so no company bill applies to you. Company bills are on Admin › Companies.</div>
  @elseif (! $payer)
    {{-- Not the owner or accountant: no amounts. --}}
    <div class="{{ $card }} text-center">
      <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full {{ $locked ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' }}">
        <x-icon :name="$locked ? 'lock-closed' : 'check-circle'" class="h-6 w-6" />
      </div>
      @if ($locked)
        <h2 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white/90">Your company's access is paused</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Contact your admin to restore it.</p>
      @else
        <h2 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white/90">Your company's account is in good standing</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Bills are handled by your admin and accountant.</p>
      @endif
      @if ($admins->isNotEmpty())
        <div class="mt-5 inline-flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
          @foreach ($admins as $a)
            <span><span class="font-medium">{{ $a->name }}</span>@if ($a->email) · <a class="text-brand-600 hover:underline dark:text-brand-400" href="mailto:{{ $a->email }}">{{ $a->email }}</a>@endif @if ($a->phone) · <a class="text-brand-600 hover:underline dark:text-brand-400" href="tel:{{ $a->phone }}">{{ $a->phone }}</a>@endif</span>
          @endforeach
        </div>
      @endif
    </div>
  @else
    @if ($locked)
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        <p class="font-semibold">Your company's scharge access is paused.</p>
        <p class="mt-1">A scharge bill is past its due date. Everyone in your company is locked out until it is paid; tenants can still pay their rent. Access comes back within a minute of payment being recorded.</p>
      </div>
    @elseif ($status['grace_until'] ?? null)
      <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
        scharge has given you until {{ \Illuminate\Support\Carbon::parse($status['grace_until'])->format('j M Y') }} to pay.
      </div>
    @endif

    <div class="grid grid-cols-2 gap-3">
      @include('partials.card.stat', ['card' => ['label' => 'Amount due', 'value' => $money($status['total_due'], $status['currency']), 'icon' => 'banknotes', 'tone' => $status['overdue'] ? 'red' : 'brand',
        'hint' => count($status['unpaid']).' unpaid '.\Illuminate\Support\Str::plural('bill', count($status['unpaid']))]])
      @include('partials.card.stat', ['card' => ['label' => 'Overdue', 'value' => count($status['overdue']), 'icon' => 'clock', 'tone' => $status['overdue'] ? 'amber' : 'green',
        'hint' => $status['overdue'] ? 'past the due date' : 'nothing overdue']])
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Unpaid bills</h3></div>
      @forelse ($status['unpaid'] as $i)
        @php $late = \Illuminate\Support\Carbon::parse($i['due_date'])->lt(now()->startOfDay()); @endphp
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3 text-sm last:border-0 dark:border-gray-800">
          <div>
            <p class="font-medium text-gray-800 dark:text-white/90">{{ $i['invoice_number'] }}</p>
            <p class="text-xs {{ $late ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">Due {{ \Illuminate\Support\Carbon::parse($i['due_date'])->format('j M Y') }}{{ $late ? ' · overdue' : '' }}</p>
          </div>
          <p class="font-semibold text-gray-800 dark:text-white/90">{{ $money($i['amount'], $i['currency']) }}</p>
        </div>
      @empty
        <p class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">Nothing to pay. Thank you.</p>
      @endforelse
    </div>

    @if ($status['unpaid'])
      <div class="{{ $card }} text-sm text-gray-700 dark:text-gray-300">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">How to pay</h3>
        @if ($paybill)
          <p class="mt-2">M-Pesa › Lipa na M-Pesa › Pay Bill. Business number <span class="font-semibold">{{ $paybill }}</span>, account <span class="font-semibold">{{ $accountHint }}</span>.</p>
        @else
          <p class="mt-2">Online payment for scharge bills is not set up yet. Contact scharge for payment details.</p>
        @endif
        <p class="mt-2">Questions or a payment already made? Email <a class="text-brand-600 hover:underline dark:text-brand-400" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@if ($contactPhone) or call <a class="text-brand-600 hover:underline dark:text-brand-400" href="tel:{{ $contactPhone }}">{{ $contactPhone }}</a>@endif.</p>
      </div>
    @endif

    @if ($paid->isNotEmpty())
      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent payments</h3></div>
        @foreach ($paid as $p)
          <div class="flex justify-between border-b border-gray-100 px-5 py-2.5 text-sm last:border-0 dark:border-gray-800">
            <span class="text-gray-700 dark:text-gray-300">{{ $p->invoice_number }} · {{ $p->paid_at ? \Illuminate\Support\Carbon::parse($p->paid_at)->format('j M Y') : '' }}</span>
            <span class="font-medium text-gray-800 dark:text-white/90">{{ $money($p->amount, $p->currency) }}</span>
          </div>
        @endforeach
      </div>
    @endif
  @endif
</div>
@endsection
