{{-- Bookings for short-stay units (BookingController). One calendar per unit; channel sync once switched on. --}}
@extends('layouts.app')
@section('title', 'Bookings')

@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $sourceTone = ['direct' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300', 'airbnb' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
    'booking_com' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'vrbo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'];
  $today = now('Africa/Nairobi')->toDateString();
@endphp

@section('content')
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Bookings</h1>
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">One calendar per short-stay unit. scharge refuses a booking on nights already taken, so a unit is never booked twice. {{ $tonight }} {{ $tonight === 1 ? 'unit is' : 'units are' }} occupied tonight.</p>
    </div>
  </div>

  @if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif
  @if (session('error') || $errors->any())
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ session('error') ?? $errors->first() }}</div>
  @endif

  @if ($units->isEmpty())
    <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
      No short-stay units yet. Set a unit's stay type to BnB, short stay or mixed on the <a href="{{ route('units.index') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Units</a> page to take bookings for it.
    </div>
  @else
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="space-y-4 xl:col-span-2">
        <div class="flex flex-wrap gap-2 text-sm">
          <a href="{{ route('bookings.index') }}" class="{{ $unitId ? $outline : $primary }}">All units</a>
          @foreach ($units as $u)<a href="{{ route('bookings.index', ['unit' => $u->id]) }}" class="{{ $unitId === $u->id ? $primary : $outline }}">{{ $u->unit_number }}</a>@endforeach
        </div>
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <table class="w-full text-sm">
            <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <th class="p-3">Stay</th><th class="p-3">Unit</th><th class="p-3">Guest</th><th class="hidden p-3 text-right md:table-cell">KES</th><th class="p-3">From</th><th class="p-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              @forelse ($bookings as $b)
                <tr class="align-top">
                  <td class="whitespace-nowrap p-3 text-gray-800 dark:text-white/90">{{ $b->check_in->format('D j M') }} → {{ $b->check_out->format('j M') }}
                    <span class="block text-xs text-gray-500">{{ $b->nights }} {{ $b->nights === 1 ? 'night' : 'nights' }}{{ $b->check_in->toDateString() <= $today ? ' · staying now' : '' }}</span></td>
                  <td class="p-3 text-gray-700 dark:text-gray-300">{{ $b->unit?->unit_number }}<span class="block text-xs text-gray-500">{{ $b->unit?->property?->name }}</span></td>
                  <td class="p-3 text-gray-700 dark:text-gray-300">{{ $b->guest_name ?: '–' }}<span class="block text-xs text-gray-500">{{ $b->guest_phone }}{{ $b->guests ? ' · '.$b->guests.' guests' : '' }}</span></td>
                  <td class="hidden p-3 text-right text-gray-800 md:table-cell dark:text-white/90">{{ $b->amount !== null ? number_format($b->amount) : '–' }}</td>
                  <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $sourceTone[$b->source] ?? $sourceTone['direct'] }}">{{ \App\Models\Booking::SOURCES[$b->source] ?? $b->source }}</span></td>
                  <td class="p-3 text-right">@if ($b->source === 'direct')
                    <form method="POST" action="{{ route('bookings.cancel', $b) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Cancel {{ $b->guest_name }}'s stay in {{ $b->unit?->unit_number }}?">@csrf<button class="text-xs text-red-600 hover:underline dark:text-red-400">Cancel</button></form>
                  @endif</td>
                </tr>
              @empty
                <tr><td colspan="6" class="p-6 text-center text-gray-500">No upcoming bookings.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        {{ $bookings->links() }}
      </div>

      <div class="space-y-6">
        <form method="POST" action="{{ route('bookings.store') }}" class="space-y-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          @csrf
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">New booking</h2>
          <label class="block text-sm text-gray-700 dark:text-gray-300">Unit
            <select name="unit_id" required class="{{ $field }} mt-1">@foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) old('unit_id', $unitId) === $u->id)>{{ $u->unit_number }}{{ $u->property ? ', '.$u->property->name : '' }}</option>@endforeach</select></label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm text-gray-700 dark:text-gray-300">Check-in<input type="date" name="check_in" required value="{{ old('check_in') }}" class="{{ $field }} mt-1"></label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">Check-out<input type="date" name="check_out" required value="{{ old('check_out') }}" class="{{ $field }} mt-1"></label>
          </div>
          <label class="block text-sm text-gray-700 dark:text-gray-300">Guest name<input name="guest_name" required maxlength="120" value="{{ old('guest_name') }}" class="{{ $field }} mt-1"></label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm text-gray-700 dark:text-gray-300">Phone<input name="guest_phone" maxlength="40" value="{{ old('guest_phone') }}" class="{{ $field }} mt-1"></label>
            <label class="block text-sm text-gray-700 dark:text-gray-300">Guests<input type="number" name="guests" min="1" max="50" value="{{ old('guests') }}" class="{{ $field }} mt-1"></label>
          </div>
          <label class="block text-sm text-gray-700 dark:text-gray-300">Amount (KES)<input type="number" name="amount" min="0" step="1" value="{{ old('amount') }}" class="{{ $field }} mt-1"></label>
          <button class="{{ $primary }} w-full">Book</button>
        </form>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Airbnb & Booking.com</h2>
            @unless ($syncOn)<span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Coming soon</span>@endunless
          </div>
          @if (! $syncOn)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Soon: stays booked on Airbnb or Booking.com will appear here, and nights booked here will be blocked there. Until then, add channel stays here by hand so the calendar stays complete.</p>
          @else
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">For each unit: paste the channel's <strong>export calendar</strong> link here, and paste scharge's link into the channel's <strong>import calendar</strong>. Calendars sync every 30 minutes.</p>
            <div class="mt-4 space-y-5">
              @foreach ($units->when($unitId, fn ($c) => $c->where('id', $unitId)) as $u)
                <div class="border-t border-gray-100 pt-4 first:border-0 first:pt-0 dark:border-gray-800">
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $u->unit_number }}{{ $u->property ? ', '.$u->property->name : '' }}</p>
                  @foreach (\App\Models\UnitChannel::CHANNELS as $key => $label)
                    @php $ch = ($channels[$u->id] ?? collect())->firstWhere('channel', $key); @endphp
                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                      <span class="font-medium">{{ $label }}</span>
                      @if ($ch)
                        · {{ $ch->last_status === 'ok' ? 'synced '.$ch->last_synced_at?->diffForHumans() : 'not synced' }}@if ($ch->last_error)<span class="block text-red-600 dark:text-red-400">{{ $ch->last_error }}</span>@endif
                        <span class="mt-1 block break-all">Paste into {{ $label }}: <code>{{ \App\Services\Bookings\ChannelSync::exportUrl($u->id, $key) }}</code></span>
                        <form method="POST" action="{{ route('bookings.channels.remove', $ch) }}" class="mt-1">@csrf<button class="text-red-600 hover:underline dark:text-red-400">Disconnect</button></form>
                      @else
                        <form method="POST" action="{{ route('bookings.channels.save', $u) }}" class="mt-1 flex gap-2">
                          @csrf<input type="hidden" name="channel" value="{{ $key }}">
                          <input name="import_url" required placeholder="https://… calendar link from {{ $label }}" class="{{ $field }} h-9 text-xs">
                          <button class="{{ $outline }} py-1.5 text-xs">Connect</button>
                        </form>
                      @endif
                    </div>
                  @endforeach
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>
    </div>
  @endif
</div>
@endsection
