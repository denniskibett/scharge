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
  @if (session('success'))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ session('error') }}</div>
  @endif
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
            <a href="{{ route('billing.invoice', $i['id']) }}" data-no-spa class="font-medium text-gray-800 hover:text-brand-600 hover:underline dark:text-white/90">{{ \App\Support\InvoiceNo::of($i['id']) }}</a>
            <p class="text-xs {{ $late ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">Due {{ \Illuminate\Support\Carbon::parse($i['due_date'])->format('j M Y') }}{{ $late ? ' · overdue' : '' }}</p>
          </div>
          <p class="font-semibold text-gray-800 dark:text-white/90">{{ $money($i['amount'], $i['currency']) }}</p>
          @if ($online)
            @php $fromWallet = $wallet && $i['currency'] === 'KES' && $wallet['available'] >= (float) $i['amount']; @endphp
            <div class="flex w-full flex-wrap items-center justify-end gap-2" x-data="{ phone: false }">
              <form method="POST" action="{{ route('billing.pay.sharet', $i['id']) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <input x-show="phone" style="display: none" type="tel" name="phone" value="{{ $phone }}" placeholder="M-Pesa number" class="h-9 w-40 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <button type="button" x-show="!phone" @click="phone = true" class="text-xs text-gray-500 hover:underline dark:text-gray-400">Pay from another phone?</button>
                <button class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Pay with Sharet Pay</button>
              </form>
              @if ($fromWallet)
                <form method="POST" action="{{ route('billing.pay.wallet', $i['id']) }}" onsubmit="return confirm('Pay {{ $money($i['amount'], $i['currency']) }} from your scharge wallet? It comes off your next payout.')">
                  @csrf
                  <button class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]">Pay from wallet</button>
                </form>
              @endif
            </div>
          @endif
        </div>
      @empty
        <p class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">Nothing to pay. Thank you.</p>
      @endforelse
    </div>

    @if ($status['unpaid'])
      <div class="{{ $card }} text-sm text-gray-700 dark:text-gray-300">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">How to pay</h3>
        @if ($online)
          <p class="mt-2"><span class="font-medium">Sharet Pay</span> opens a secure page where you pay by M-Pesa (a prompt on your phone) or from your Sharet balance. The bill turns paid and access comes back within a minute.</p>
          <p class="mt-2"><span class="font-medium">Pay from wallet</span> uses rent collected through scharge that has not been paid out yet, after scharge's fee. Nothing extra is charged; the amount comes off your next payout and the rest is paid out as usual.</p>
        @elseif ($paybill)
          <p class="mt-2">M-Pesa › Lipa na M-Pesa › Pay Bill. Business number <span class="font-semibold">{{ $paybill }}</span>, account <span class="font-semibold">{{ $accountHint }}</span>.</p>
        @else
          <p class="mt-2">Online payment for scharge bills is not set up yet. Contact scharge for payment details.</p>
        @endif
        <p class="mt-2">Questions or a payment already made? Email <a class="text-brand-600 hover:underline dark:text-brand-400" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@if ($contactPhone) or call <a class="text-brand-600 hover:underline dark:text-brand-400" href="tel:{{ $contactPhone }}">{{ $contactPhone }}</a>@endif.</p>
      </div>
    @endif

    @if ($online && $wallet)
      <div class="{{ $card }}">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">scharge wallet</h3>
          <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $money($wallet['available']) }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">available</span></p>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          {{ collect(['Rent collected, not yet paid out '.$money($wallet['gross']),
            $wallet['fee'] > 0 ? 'scharge fee ('.rtrim(rtrim(number_format($wallet['fee_percent'], 2), '0'), '.').'%) '.$money($wallet['fee']) : null,
            $wallet['debits'] > 0 ? 'bills paid from wallet '.$money($wallet['debits']) : null])->filter()->implode(' · ') }}.
          What is left is paid out at 08:00, 12:00 and 16:00 to the M-Pesa number, paybill, till or bank set in Integrations › Rent collection & payouts.
        </p>
        @if ($ledger)
          <div class="mt-4 divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-800 dark:border-gray-800">
            @foreach ($ledger as $e)
              <div class="flex justify-between gap-3 py-2 text-sm">
                <span class="text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Carbon::parse($e['at'])->format('j M Y') }} · {{ $e['label'] }}@if ($e['detail'])<span class="text-xs text-gray-500 dark:text-gray-400"> · {{ $e['detail'] }}</span>@endif</span>
                <span class="whitespace-nowrap font-medium {{ ! $e['wallet'] ? 'text-gray-500 dark:text-gray-400' : ($e['direction'] === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-800 dark:text-white/90') }}">{{ $e['direction'] === 'in' ? '+' : '−' }}{{ $money($e['amount']) }}</span>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    @endif

    @if ($paid->isNotEmpty())
      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-gray-800"><h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent payments</h3></div>
        @foreach ($paid as $p)
          <div class="flex justify-between border-b border-gray-100 px-5 py-2.5 text-sm last:border-0 dark:border-gray-800">
            <span class="text-gray-700 dark:text-gray-300">{{ \App\Support\InvoiceNo::of($p->id) }} · {{ $p->paid_at ? \Illuminate\Support\Carbon::parse($p->paid_at)->format('j M Y') : '' }}</span>
            <span class="font-medium text-gray-800 dark:text-white/90">{{ $money($p->amount, $p->currency) }}</span>
          </div>
        @endforeach
      </div>
    @endif
  @endif
</div>
@endsection
