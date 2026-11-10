{{-- Settings › Data-processing agreement (AgreementController). --}}
@extends('layouts.app')
@section('title', 'Data-processing agreement')

@section('content')
@php
  $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<div class="space-y-6">
  <a href="{{ route('brand.edit', $q) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">‹ White label & data</a>
  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ $errors->first() }}</div>@endif
  <article class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 text-sm leading-relaxed text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 [&_h1]:mb-3 [&_h1]:text-xl [&_h1]:font-semibold [&_h1]:text-gray-900 dark:[&_h1]:text-white [&_h2]:mt-5 [&_h2]:mb-2 [&_h2]:font-semibold [&_h2]:text-gray-900 dark:[&_h2]:text-white [&_p]:mb-2 [&_blockquote]:my-3 [&_blockquote]:rounded-lg [&_blockquote]:bg-amber-50 [&_blockquote]:p-3 [&_blockquote]:text-amber-800">
    {!! $html !!}
  </article>
  @if ($accepted)
    <p class="max-w-3xl rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
      Accepted by {{ $accepted->accepted_name }} ({{ $accepted->accepted_title }}) on {{ $accepted->accepted_at->timezone('Africa/Nairobi')->format('j M Y, H:i') }}, version {{ $accepted->version }}.</p>
  @elseif (! $q)
    <form method="POST" action="{{ route('dpa.accept') }}" class="max-w-3xl space-y-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      @csrf
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Your full name<input name="name" required maxlength="160" value="{{ old('name', auth()->user()->name) }}" class="{{ $field }} mt-1.5"></label>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Your role<input name="title" required maxlength="120" placeholder="Director" value="{{ old('title') }}" class="{{ $field }} mt-1.5"></label>
      </div>
      <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="agree" value="1" class="mt-1"> I am authorised to sign for {{ $company->name }} and accept this agreement.</label>
      <button class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Accept</button>
    </form>
  @else
    <p class="text-sm text-gray-500">Not accepted yet. The company's admin accepts it from their own login.</p>
  @endif
</div>
@endsection
