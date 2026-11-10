{{-- scharge staff › KYC reviews (KycReviewController). --}}
@extends('layouts.app')
@section('title', 'KYC reviews')

@section('content')
@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
  $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6';
  $tone = ['ok' => 'text-emerald-700 dark:text-emerald-300', 'warn' => 'text-amber-700 dark:text-amber-300', 'fail' => 'text-red-700 dark:text-red-300'];
  $statusTone = ['draft' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300', 'submitted' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'verified' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'rejected' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'];
@endphp
<div class="space-y-6">
  <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">KYC reviews</h1>
  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  <div class="flex flex-wrap gap-2 text-sm">
    @foreach (\App\Models\CompanyKyc::STATUSES as $k => $l)<a href="{{ route('kyc.review.index', ['status' => $k]) }}" class="{{ $status === $k ? $primary : $outline }}">{{ $l }} <span class="ml-1 opacity-70">{{ $counts[$k] }}</span></a>@endforeach
  </div>
  <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <table class="w-full text-sm">
      <thead><tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400"><th class="p-3">Company</th><th class="p-3">Sent</th><th class="p-3">Checks</th><th class="p-3"></th></tr></thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($items as $k)
          @php $fails = collect($k->checks)->where('level', 'fail')->count(); $warns = collect($k->checks)->where('level', 'warn')->count(); @endphp
          <tr>
            <td class="p-3 text-gray-800 dark:text-white/90">{{ $k->company?->name }}<span class="block text-xs text-gray-500">{{ $k->owner_name }} · {{ $k->kra_pin }}</span></td>
            <td class="p-3 text-gray-600 dark:text-gray-400">{{ $k->submitted_at?->timezone('Africa/Nairobi')->format('j M, H:i') ?? '–' }}</td>
            <td class="p-3"><span class="{{ $fails ? $tone['fail'] : ($warns ? $tone['warn'] : $tone['ok']) }}">{{ $fails ? $fails.' failed' : '' }}{{ $fails && $warns ? ', ' : '' }}{{ $warns ? $warns.' to look at' : '' }}{{ ! $fails && ! $warns ? 'All passed' : '' }}</span></td>
            <td class="p-3 text-right"><a href="{{ route('kyc.review.show', $k) }}" class="{{ $outline }}">Review</a></td>
          </tr>
        @empty
          <tr><td colspan="4" class="p-6 text-center text-gray-500">Nothing here.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $items->links() }}
</div>
@endsection
