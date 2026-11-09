@extends('layouts.app')
@section('title', 'Gate pass '.$pass->code)

@php
  $tz = 'Africa/Nairobi';
  $btn = 'inline-flex h-12 items-center justify-center rounded-lg px-5 text-sm font-medium';
  $method = fn ($m) => ['self' => 'guest\'s phone', 'host' => 'host', 'guard' => 'gate'][$m] ?? $m;
  [$state] = $pass->stateLabel();
@endphp

@section('content')
<div class="flex max-w-4xl flex-col gap-5">
  <a href="{{ route('gate.index') }}" class="text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400">← Guests & gate</a>
  @include('gate._flash')

  <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    {{-- The guard's card --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-5 lg:col-span-2 dark:border-gray-800 dark:bg-gray-900">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="text-xs text-gray-500 dark:text-gray-400">{{ $pass->kindLabel() }} · code <span class="font-mono">{{ $pass->code }}</span></div>
          <h1 class="mt-1 truncate text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $pass->guest_name }}@if ($pass->guests_count > 1)<span class="text-gray-400"> +{{ $pass->guests_count - 1 }}</span>@endif</h1>
          <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $pass->placeLabel() }}</div>
        </div>
        @include('gate._pill', ['pass' => $pass])
      </div>

      @if ($place?->is_blocked)
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">Blocked for this unit. Do not let in; call the host.</div>
      @endif

      <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Valid</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $pass->windowLabel() }}</dd></div>
        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Car</dt><dd class="mt-0.5 font-mono font-medium text-gray-800 dark:text-white/90">{{ $pass->vehicle_plate ?? '–' }}</dd>@if ($pass->vehicle_desc)<dd class="text-xs text-gray-500">{{ $pass->vehicle_desc }}</dd>@endif</div>
        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Phone</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90"><a href="tel:{{ $pass->guest_phone }}">{{ $pass->guest_phone }}</a></dd></div>
        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Host</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $pass->company?->name ?? '–' }}</dd>@if ($pass->creator)<dd class="text-xs text-gray-500">{{ $pass->creator->name }}@if ($pass->creator->phone) · <a href="tel:{{ $pass->creator->phone }}">{{ $pass->creator->phone }}</a>@endif</dd>@endif</div>
        <div><dt class="text-xs text-gray-500 dark:text-gray-400">Visits here</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $place?->visit_count ?? 0 }}@if (($place?->visit_count ?? 0) > 1) <span class="text-xs font-normal text-brand-500">returning</span>@endif</dd></div>
        @if ($pass->notes)<div class="col-span-2"><dt class="text-xs text-gray-500 dark:text-gray-400">Note</dt><dd class="mt-0.5 text-gray-800 dark:text-white/90">{{ $pass->notes }}</dd></div>@endif
      </dl>

      @if ($canManage && $pass->isOpen())
        <form method="POST" action="{{ route('gate.passes.act', $pass) }}" class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:flex-wrap sm:items-center dark:border-gray-800">
          @csrf
          @if (! $pass->is_inside && ! $pass->isExpired() && ! $place?->is_blocked)
            <input name="vehicle_plate" value="{{ $pass->vehicle_plate }}" placeholder="Plate (if by car)" maxlength="20" class="h-12 rounded-lg border border-gray-300 bg-transparent px-4 font-mono text-sm uppercase text-gray-800 sm:w-64 dark:border-gray-700 dark:text-white/90">
            <button name="action" value="checkin" class="{{ $btn }} bg-green-600 text-white hover:bg-green-700">{{ $pass->status === 'checked_in' ? 'Back in' : 'Let in' }}</button>
          @endif
          @if ($pass->status === 'checked_in')
            @if ($pass->is_inside && $pass->kind === 'stay')
              <button name="action" value="stepout" class="{{ $btn }} border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Stepping out</button>
            @endif
            <button name="action" value="checkout" onclick="return confirm('Check {{ e($pass->guest_name) }} out for good?')" class="{{ $btn }} bg-gray-900 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900">Check out</button>
          @endif
          <div class="flex gap-3 ml-auto">
            <button name="action" value="resend" class="text-sm font-medium text-brand-500 hover:text-brand-600">Send code again</button>
            @if ($pass->status === 'issued')
              <button name="action" value="cancel" onclick="return confirm('Cancel this pass?')" class="text-sm font-medium text-red-600 hover:text-red-700">Cancel pass</button>
            @endif
          </div>
        </form>
      @elseif ($pass->isExpired())
        <p class="mt-6 border-t border-gray-100 pt-5 text-sm text-gray-500 dark:border-gray-800">This pass has expired. Ask the host for a new code.</p>
      @endif
    </section>

    {{-- Timeline --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Timeline</h2>
      <ol class="mt-4 flex flex-col gap-4 text-sm">
        <li>
          <div class="font-medium text-gray-800 dark:text-white/90">Code sent</div>
          <div class="text-xs text-gray-500 dark:text-gray-400">{{ $pass->created_at->timezone($tz)->format('j M H:i') }}{{ $pass->creator ? ' by '.$pass->creator->name : '' }}
            @if ($pass->sms_sent_at) · SMS ✓@endif @if ($pass->email_sent_at) · email ✓@endif</div>
        </li>
        @foreach ($pass->logs as $log)
          <li>
            <div class="font-medium text-gray-800 dark:text-white/90">In {{ $log->access_time->timezone($tz)->format('j M H:i') }}@if ($log->vehicle_registration_snapshot) <span class="font-mono text-xs text-gray-500">{{ $log->vehicle_registration_snapshot }}</span>@endif</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log->verifiedByUser?->name ?? ($loop->first && $pass->check_in_method === 'self' ? 'Guest\'s phone' : '') }}</div>
            @if ($log->exit_time)
              <div class="mt-1 font-medium text-gray-800 dark:text-white/90">Out {{ $log->exit_time->timezone($tz)->format('j M H:i') }}</div>
            @endif
          </li>
        @endforeach
        @if ($pass->checked_out_at)
          <li><div class="font-medium text-gray-800 dark:text-white/90">Checked out {{ $pass->checked_out_at->timezone($tz)->format('j M H:i') }}</div><div class="text-xs text-gray-500 dark:text-gray-400">by the {{ $method($pass->check_out_method) }}</div></li>
        @elseif ($pass->status === 'cancelled')
          <li class="font-medium text-gray-500">Cancelled</li>
        @endif
      </ol>

      @if ($others->isNotEmpty())
        <h2 class="mt-6 text-sm font-semibold text-gray-800 dark:text-white/90">Earlier visits here</h2>
        <ul class="mt-3 flex flex-col gap-2 text-sm">
          @foreach ($others as $o)
            <li class="flex items-center justify-between gap-2"><a href="{{ route('gate.passes.show', $o) }}" class="text-gray-700 hover:text-brand-500 dark:text-gray-300">{{ ($o->checked_in_at ?? $o->valid_from)?->timezone($tz)->format('j M Y') }}</a>@include('gate._pill', ['pass' => $o])</li>
          @endforeach
        </ul>
      @endif
      @if ($canManage)
        <a href="{{ route('gate.passes.create', ['again' => $pass->id]) }}" class="mt-6 inline-flex text-sm font-medium text-brand-500 hover:text-brand-600">New pass for {{ \Illuminate\Support\Str::before($pass->guest_name, ' ') }} →</a>
      @endif
    </section>
  </div>
</div>
@endsection
