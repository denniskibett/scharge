{{-- Money › Rent collection (RentCollectionController). Paybill payments to match, bill/reminder messages, paused tenants. --}}
@extends('layouts.app')
@section('title', 'Rent collection')

@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $tenancyLabel = fn ($t) => trim(($t?->tenant?->user?->name ?? 'Tenant').' · '.($t?->unit?->unit_number ?? '').($t?->unit?->property?->name ? ', '.$t->unit->property->name : ''));
  $statusTone = ['applied' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'credited' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'unmatched' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'ignored' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
    'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'added' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'failed' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300', 'no_phone' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    'sending' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'];
  $kinds = ['bill' => 'Bill', 'reminder' => 'Reminder', 'link' => 'Bill link', 'penalty' => 'Late penalty'];
@endphp

@section('content')
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Rent collection</h1>
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
        @if ($settings)
          Automatic bills on day {{ $settings->invoiceDay }}, due on day {{ $settings->dueDay }}.
          {{ $settings->reminderDays ? 'Reminders '.implode(', ', $settings->reminderDays).' days late.' : 'No reminders.' }}
          Late penalty: {{ $settings->penaltyType === 'off' ? 'off' : ($settings->penaltyType === 'percent' ? rtrim(rtrim(number_format($settings->penaltyAmount, 2), '0'), '.').'% after '.$settings->penaltyAfterDays.' days' : 'KES '.number_format($settings->penaltyAmount).' after '.$settings->penaltyAfterDays.' days') }}.
        @else
          Rent automation is off. Switch it on in Integrations › Rent automation to bill and remind tenants automatically.
        @endif
        <a href="{{ route('integrations.index') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Settings</a>
      </p>
    </div>
    @if ($company)
      <form method="POST" action="{{ route('rent.resend-links') }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Send a fresh bill link by SMS to every tenant who owes ({{ $counts['owing'] ?? 0 }} unpaid {{ ($counts['owing'] ?? 0) === 1 ? 'bill' : 'bills' }})?">
        @csrf
        <button class="{{ $primary }}" type="submit">Send fresh bill links</button>
      </form>
    @endif
  </div>

  @if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif
  @if (session('error') || $errors->any())
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ session('error') ?? $errors->first() }}</div>
  @endif

  <nav class="flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
    @foreach (['paybill' => ['Paybill payments', $counts['unmatched'], 'to match'], 'messages' => ['Messages', $counts['failed'], 'failed'], 'paused' => ['Paused tenants', $counts['paused'], null], 'attempts' => ['Payment attempts', null, null]] as $key => [$label, $count, $hint])
      <a href="{{ route('rent.index', ['tab' => $key]) }}" @class(['-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium', 'border-brand-500 text-brand-600 dark:text-brand-400' => $tab === $key, 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key])>
        {{ $label }}@if ($count) <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $count }}{{ $hint ? ' '.$hint : '' }}</span>@endif
      </a>
    @endforeach
  </nav>

  @if ($tab === 'paybill')
    <p class="text-sm text-gray-500 dark:text-gray-400">Rent paid straight to your paybill. scharge matches the account number to a unit (e.g. <strong>A1</strong>), a bill number (INV-123) or the tenant's phone, and pays off the oldest bills first. Pick the tenant for the ones it could not match.</p>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
          <th class="p-3">Paid</th><th class="p-3">From</th><th class="p-3">Account no.</th><th class="p-3 text-right">KES</th><th class="hidden p-3 md:table-cell">Receipt</th><th class="p-3">Status</th><th class="p-3"></th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          @forelse ($paybill as $p)
            <tr class="align-top">
              <td class="whitespace-nowrap p-3 text-gray-700 dark:text-gray-300">{{ $p->paid_at?->timezone('Africa/Nairobi')->format('j M, H:i') }}</td>
              <td class="p-3 text-gray-800 dark:text-white/90">{{ $p->payer_name ?: '–' }}<span class="block text-xs text-gray-500 md:hidden">{{ $p->trans_id }}</span></td>
              <td class="p-3 font-mono text-gray-800 dark:text-white/90">{{ $p->bill_ref ?: '–' }}</td>
              <td class="p-3 text-right font-medium text-gray-900 dark:text-white">{{ number_format($p->amount) }}</td>
              <td class="hidden p-3 font-mono text-xs text-gray-500 md:table-cell">{{ $p->trans_id }}</td>
              <td class="p-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusTone[$p->status] ?? '' }}">{{ \App\Models\PaybillPayment::STATUSES[$p->status] ?? $p->status }}</span>
                @if ($p->tenancy)<span class="mt-1 block text-xs text-gray-500">{{ $tenancyLabel($p->tenancy) }}{{ $p->matched_by ? ' · by '.$p->matched_by : '' }}</span>@endif
                @if ($p->note)<span class="mt-1 block text-xs text-gray-500">{{ $p->note }}</span>@endif
              </td>
              <td class="p-3">
                @if ($p->status === 'unmatched' && $tenancies->isNotEmpty())
                  <form method="POST" action="{{ route('rent.paybill.assign', $p) }}" class="flex flex-wrap gap-2">
                    @csrf
                    <select name="tenancy_id" required class="{{ $field }} max-w-56" aria-label="Tenant for {{ $p->trans_id }}">
                      <option value="">Choose tenant…</option>
                      @foreach ($tenancies as $t)<option value="{{ $t->id }}">{{ $tenancyLabel($t) }}</option>@endforeach
                    </select>
                    <button class="{{ $primary }}">Record</button>
                  </form>
                  <form method="POST" action="{{ route('rent.paybill.ignore', $p) }}" class="mt-2" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Mark KES {{ number_format($p->amount) }} from {{ $p->payer_name ?: 'this payer' }} as not rent?">
                    @csrf
                    <button class="text-xs text-gray-500 hover:underline">Not rent</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="p-6 text-center text-gray-500">No paybill payments yet. They appear here once your paybill is connected (Integrations › M-Pesa Daraja › Test).</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $paybill->links() }}
  @elseif ($tab === 'attempts')
    @php
      $L = \App\Services\Payments\PaymentAttempts::LABELS;
      $st = $attempts['by_status'] ?? collect();
      $pressed = $st->sum('n');
      $paid = (int) ($st['paid']->n ?? 0);
    @endphp
    <p class="text-sm text-gray-500 dark:text-gray-400">Every time a tenant pressed Pay in the last 30 days, on the website or in the app, and how it ended. Use it to see why rent is not being paid.</p>
    @if (! $attempts)
      <p class="text-sm text-gray-500">Not set up yet.</p>
    @else
      <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500">Pressed Pay</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($pressed) }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500">Paid</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($paid) }} <span class="text-sm font-normal text-gray-500">{{ $pressed ? round($paid * 100 / $pressed) : 0 }}%</span></p></div>
        @foreach (['app' => 'From the app', 'web' => 'From the website'] as $ch => $l)
          @php $c = $attempts['by_channel'][$ch] ?? null; @endphp
          <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-xs text-gray-500">{{ $l }}</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($c->n ?? 0) }} <span class="text-sm font-normal text-gray-500">{{ $c && $c->n ? round($c->paid * 100 / $c->n) : 0 }}% paid</span></p></div>
        @endforeach
      </div>
      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="w-full text-sm"><thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400"><th class="p-3">How it ended</th><th class="p-3 text-right">Times</th><th class="p-3 text-right">KES</th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@foreach ($L as $k => $l)@if (isset($st[$k]))<tr><td class="p-3 text-gray-800 dark:text-white/90">{{ $l }}</td><td class="p-3 text-right">{{ number_format($st[$k]->n) }}</td><td class="p-3 text-right">{{ number_format($st[$k]->amount) }}</td></tr>@endif @endforeach</tbody></table>
      </div>
      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="w-full text-sm"><thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400"><th class="p-3">When</th><th class="p-3">Bill</th><th class="p-3 text-right">KES</th><th class="p-3">From</th><th class="p-3">Result</th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse ($attempts['recent'] as $a)<tr>
          <td class="p-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($a->created_at)->timezone('Africa/Nairobi')->format('j M, H:i') }}</td>
          <td class="p-3 text-gray-700 dark:text-gray-300">#{{ $a->invoice_id }}</td><td class="p-3 text-right">{{ number_format($a->amount) }}</td>
          <td class="p-3 text-gray-600 dark:text-gray-400">{{ $a->channel === 'app' ? 'App' : 'Website' }}{{ $a->method ? ' · '.($a->method === 'stk' ? 'M-Pesa prompt' : 'Sharet') : '' }}</td>
          <td class="p-3">{{ $L[$a->status] ?? $a->status }}@if ($a->reason && $a->status !== 'paid')<span class="block text-xs text-gray-500">{{ $a->reason }}</span>@endif</td></tr>
        @empty<tr><td colspan="5" class="p-6 text-center text-gray-500">No Pay presses in the last 30 days.</td></tr>@endforelse</tbody></table>
      </div>
    @endif
  @elseif ($tab === 'messages')
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <a href="{{ route('rent.index', ['tab' => 'messages']) }}" class="{{ request('kind') || request('failed') ? $outline : $primary }}">All</a>
      @foreach ($kinds as $k => $label)<a href="{{ route('rent.index', ['tab' => 'messages', 'kind' => $k]) }}" class="{{ request('kind') === $k ? $primary : $outline }}">{{ $label }}s</a>@endforeach
      <a href="{{ route('rent.index', ['tab' => 'messages', 'failed' => 1]) }}" class="{{ request('failed') ? $primary : $outline }}">Not sent</a>
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
          <th class="p-3">When</th><th class="p-3">Tenant</th><th class="p-3">What</th><th class="hidden p-3 lg:table-cell">Message</th><th class="p-3">Status</th><th class="p-3"></th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          @forelse ($messages as $m)
            <tr class="align-top">
              <td class="whitespace-nowrap p-3 text-gray-700 dark:text-gray-300">{{ $m->created_at?->timezone('Africa/Nairobi')->format('j M, H:i') }}</td>
              <td class="p-3 text-gray-800 dark:text-white/90">{{ $tenancyLabel($m->tenancy) }}<span class="block text-xs text-gray-500">{{ $m->phone }}</span></td>
              <td class="p-3 text-gray-700 dark:text-gray-300">{{ $kinds[$m->kind] ?? $m->kind }}{{ $m->kind === 'reminder' ? ' ('.$m->stage.' days)' : '' }}<span class="block text-xs text-gray-500">KES {{ number_format($m->balance) }}</span></td>
              <td class="hidden max-w-md p-3 text-xs text-gray-500 lg:table-cell">{{ $m->message }}</td>
              <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusTone[$m->status] ?? '' }}">{{ ['sent' => 'Sent', 'failed' => 'Not sent', 'no_phone' => 'No phone', 'added' => 'Added to bill', 'sending' => 'Sending'][$m->status] ?? $m->status }}</span>
                @if ($m->error)<span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $m->error }}</span>@endif</td>
              <td class="p-3">@if (in_array($m->status, ['failed', 'no_phone'], true))
                <form method="POST" action="{{ route('rent.messages.retry', $m) }}">@csrf<button class="{{ $outline }}">Send again</button></form>
              @endif</td>
            </tr>
          @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-500">No messages yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $messages->links() }}
  @else
    <p class="text-sm text-gray-500 dark:text-gray-400">A paused tenant gets no reminders and no late penalties: for an agreed payment plan, or while something is sorted out. Bills still go out.</p>
    @if ($tenancies->isNotEmpty())
      <form method="POST" action="{{ route('rent.pause') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        <label class="text-sm text-gray-700 dark:text-gray-300">Tenant<br>
          <select name="tenancy_id" required class="{{ $field }} mt-1 w-64 max-w-full">
            <option value="">Choose tenant…</option>
            @foreach ($tenancies as $t)<option value="{{ $t->id }}">{{ $tenancyLabel($t) }}</option>@endforeach
          </select></label>
        <label class="text-sm text-gray-700 dark:text-gray-300">Until (optional)<br><input type="date" name="until" min="{{ now('Africa/Nairobi')->toDateString() }}" class="{{ $field }} mt-1"></label>
        <label class="min-w-48 flex-1 text-sm text-gray-700 dark:text-gray-300">Reason<br><input name="reason" maxlength="255" placeholder="e.g. Paying KES 5,000 a week" class="{{ $field }} mt-1 w-full"></label>
        <button class="{{ $primary }}">Pause reminders</button>
      </form>
    @endif
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400"><th class="p-3">Tenant</th><th class="p-3">Until</th><th class="p-3">Reason</th><th class="p-3"></th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          @forelse ($holds as $h)
            <tr>
              <td class="p-3 text-gray-800 dark:text-white/90">{{ $tenancyLabel($h->tenancy) }}</td>
              <td class="p-3 text-gray-700 dark:text-gray-300">{{ $h->until ? $h->until->format('j M Y') : 'Until switched back on' }}{{ $h->until && $h->until->isPast() && ! $h->until->isToday() ? ' (ended)' : '' }}</td>
              <td class="p-3 text-gray-500">{{ $h->reason ?: '–' }}</td>
              <td class="p-3 text-right"><form method="POST" action="{{ route('rent.resume', $h) }}">@csrf<button class="{{ $outline }}">Turn reminders back on</button></form></td>
            </tr>
          @empty
            <tr><td colspan="4" class="p-6 text-center text-gray-500">No tenant is paused.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
