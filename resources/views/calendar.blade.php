@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
<main class="p-4 md:p-6">
  <div x-data="{ pageName: `Calendar`}">
    @include('partials.breadcrumb')
  </div>

  {{-- What this person can see; untick to hide a kind of event. --}}
  <div class="mb-4 flex flex-wrap items-center gap-2" x-data="{ off: [] }"
       x-init="$watch('off', v => window.schargeCalendarTypes && window.schargeCalendarTypes(v))">
    @php
      $dot = ['Warning' => 'bg-amber-500', 'Success' => 'bg-emerald-500', 'Primary' => 'bg-brand-500', 'Danger' => 'bg-red-500'];
    @endphp
    @foreach ($types as $key => [$label, $level])
      <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
             :class="off.includes('{{ $key }}') && 'opacity-50'">
        <input type="checkbox" class="sr-only" :checked="!off.includes('{{ $key }}')"
               @change="off = $event.target.checked ? off.filter(t => t !== '{{ $key }}') : [...off, '{{ $key }}']">
        <span class="h-2.5 w-2.5 rounded-full {{ $dot[$level] }}"></span> {{ $label }}
      </label>
    @endforeach
    @if ($types->isEmpty())
      <p class="text-sm text-gray-500 dark:text-gray-400">Nothing is scheduled for your role yet.</p>
    @endif
  </div>

  <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div id="calendar" class="min-h-screen" data-feed="{{ route('calendar.events') }}" data-types="{{ $types->keys()->join(',') }}"></div>
  </div>
</main>
@endsection
