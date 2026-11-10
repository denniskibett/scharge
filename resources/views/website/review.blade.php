{{-- scharge staff › Website approvals (WebsiteReviewController). --}}
@extends('layouts.app')
@section('title', 'Website approvals')

@section('content')
@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
@endphp
<div class="space-y-6">
  <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Website approvals</h1>
  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
  @forelse ($pages as $p)
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="font-medium text-gray-900 dark:text-white">{{ $p->company?->name }} · {{ $p->edit('title') }} <span class="font-mono text-xs text-gray-500">/{{ $p->slug }}</span></p>
          <p class="text-xs text-gray-500">{{ $p->isLive() ? 'Changes to a live page' : 'New page' }} · sent {{ $p->submitted_at?->timezone('Africa/Nairobi')->format('j M, H:i') }}</p></div>
        <a href="{{ route('website.preview', [$p, 'company' => $p->company_id]) }}" target="_blank" rel="noopener" class="{{ $outline }}">Preview</a>
      </div>
      <form method="POST" action="{{ route('website.review.decide', $p) }}" class="mt-3 flex flex-wrap items-end gap-2">
        @csrf
        <input name="note" maxlength="500" placeholder="Note (needed to send back)" class="h-10 min-w-56 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
        <button name="decision" value="approve" class="{{ $primary }}">Approve</button>
        <button name="decision" value="return" class="{{ $outline }}">Send back</button>
      </form>
    </div>
  @empty
    <p class="text-sm text-gray-500">Nothing waiting.</p>
  @endforelse
</div>
@endsection
