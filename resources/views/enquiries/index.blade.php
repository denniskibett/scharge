{{-- Property › Enquiries (EnquiryController): renters' requests for this landlord's listings. --}}
@extends('layouts.app')
@section('title', 'Enquiries')

@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $stages = \App\Http\Controllers\EnquiryController::STAGES;
@endphp

@section('content')
<div class="space-y-6">
  <div>
    @section('page_title')
Enquiries
@endsection
    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">People who asked to view or book your vacant units on scharge's Open listings. You get an SMS for each new one; reply on WhatsApp and keep track here.</p>
  </div>

  @if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif

  @php
    $tab = fn ($on) => 'shrink-0 px-4 py-2 text-sm font-medium transition-colors '.($on
      ? 'border-b-2 border-brand-500 -mb-px text-brand-600 dark:text-brand-400'
      : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200');
  @endphp
  <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    {{-- Stages as a tab line inside the card --}}
    <nav class="flex gap-1 overflow-x-auto whitespace-nowrap border-b border-gray-200 px-3 pt-3 sm:px-5 dark:border-gray-800" aria-label="Enquiry stages" data-spa-tabs>
      <a href="{{ route('enquiries.index') }}" class="{{ $tab(! $stage) }}" @if (! $stage) aria-current="page" @endif>All <span class="ml-1 text-xs opacity-70">{{ $counts->sum() }}</span></a>
      @foreach ($stages as $k => $l)<a href="{{ route('enquiries.index', ['stage' => $k]) }}" class="{{ $tab($stage === $k) }}" @if ($stage === $k) aria-current="page" @endif>{{ $l }} <span class="ml-1 text-xs opacity-70">{{ $counts[$k] }}</span></a>@endforeach
    </nav>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
        <th class="p-3">When</th><th class="p-3">Who</th><th class="p-3">For</th><th class="p-3">Stage</th>
      </tr></thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($enquiries as $e)
          @php $phone = $e->contact?->phone; $wa = $phone ? \App\Services\WhatsApp::link($phone, 'Hi '.strtok((string) $e->contact?->name, ' ').', about your request for '.($e->details['title'] ?? 'the house').':') : null; @endphp
          <tr class="align-top">
            <td class="whitespace-nowrap p-3 text-gray-700 dark:text-gray-300">{{ $e->created_at?->timezone('Africa/Nairobi')->format('j M, H:i') }}</td>
            <td class="p-3 text-gray-800 dark:text-white/90">{{ $e->contact?->name ?? '–' }}
              <span class="block text-xs text-gray-500">{{ $phone }}@if ($wa) · <a href="{{ $wa }}" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:underline dark:text-brand-400">WhatsApp</a>@endif</span></td>
            <td class="p-3 text-gray-700 dark:text-gray-300">{{ $e->details['title'] ?? $e->title }}<span class="block text-xs text-gray-500">{{ $e->details['action'] ?? '' }}{{ ! empty($e->details['when']) ? ' · '.$e->details['when'] : '' }}</span>
              @if ($e->notes)<span class="mt-1 block whitespace-pre-line text-xs text-gray-500">{{ $e->notes }}</span>@endif</td>
            <td class="p-3">
              <form method="POST" action="{{ route('enquiries.update', $e) }}" class="flex flex-wrap gap-2">
                @csrf
                <select name="stage" class="{{ $field }}" aria-label="Stage">@foreach ($stages as $k => $l)<option value="{{ $k }}" @selected(($e->details['stage'] ?? 'new') === $k)>{{ $l }}</option>@endforeach</select>
                <input name="note" maxlength="500" placeholder="Note (optional)" class="{{ $field }} w-40">
                <button class="{{ $outline }}">Save</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="p-6 text-center text-gray-500">No enquiries yet. Switch a property on for Open listings (on its property page) and renters' requests for its vacant units land here.</td></tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>
  {{ $enquiries->links() }}
</div>
@endsection
