@extends('layouts.app')
@section('title', 'Guide')

@section('content')
@if (session('success'))
  <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
@endif
@if (! empty($guide->state()['hidden_at']) && auth()->user()->hasAnyRole(['admin', 'property_manager']))
  <form method="POST" action="{{ route('guide.visibility') }}" class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    @csrf<input type="hidden" name="show" value="1">
    Hidden from your dashboard. <button class="font-medium text-brand-500 hover:text-brand-600">Show it there again</button>
  </form>
@endif
@include('partials.onboarding-guide', ['guide' => $guide, 'page' => true])
<p class="text-sm text-gray-500 dark:text-gray-400">Need a hand? Your account manager can set it up with you, or send us your tenant sheet and we will move it in for free. More answers in the <a href="{{ route('home.guides') }}" class="text-brand-500 hover:text-brand-600" target="_blank" rel="noopener" data-no-spa>guides</a>.</p>
@endsection
