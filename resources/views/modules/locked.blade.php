{{-- A paid module this company does not have (App\Support\Modules::deny). Friendly, with who to contact. --}}
@extends('layouts.app')
@section('title', $label)
@section('page_title', $label)

@section('content')
<div class="mx-auto max-w-2xl">
  <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center sm:p-10 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
    </div>
    <h2 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $label }} isn't on your plan</h2>
    @if ($blurb)
      <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $blurb }}</p>
    @endif
    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
      To add it, contact {{ $contact['name'] }}. Everything else in your account keeps working as before.
    </p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
      <a href="mailto:{{ $contact['email'] }}?subject={{ rawurlencode('Add '.$label.' to our scharge plan') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Ask to add {{ $label }}</a>
      @if ($contact['phone'])
        <a href="tel:{{ $contact['phone'] }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Call {{ $contact['phone'] }}</a>
      @endif
      <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Back to dashboard</a>
    </div>
  </div>
</div>
@endsection
