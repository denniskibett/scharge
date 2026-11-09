@extends('layouts.app')
@section('title', 'Building claims')

@section('content')
@include('partials.card.property-claims')
<div>
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Building claims</h1>
    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Management companies asking to manage a building already on scharge (often one a BnB host registered first). Approving moves the building's books to them; host units stay with their hosts and are billed service charge and water.</p>
  </div>

  @if (session('success'))
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif

  @php
    $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
  @endphp
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Claims</h3>
    </div>
    <div class="w-full overflow-x-auto">
    <table class="data-table w-full table-auto">
      <thead>
        <tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Building</th><th class="{{ $th }}">Company asking</th><th class="{{ $th }}">Their note</th><th class="{{ $th }}">Status</th><th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Action</th></tr>
      </thead>
      <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
        @forelse ($claims as $c)
          <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
            <td class="p-4 whitespace-nowrap"><span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $c->property?->name }}</span><p class="text-xs text-gray-500 dark:text-gray-400">{{ $c->property?->location ?: 'No location' }} · {{ $c->property?->claim_status === 'unclaimed' ? 'not managed yet' : 'managed by another company' }}</p></td>
            <td class="p-4 whitespace-nowrap"><span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $c->company?->name }}</span><p class="text-xs text-gray-500 dark:text-gray-400">{{ $c->requester?->name }} · {{ $c->requester?->phone ?: $c->requester?->email }} · {{ $c->created_at->diffForHumans() }}</p></td>
            <td class="max-w-xs p-4 text-sm text-gray-500 dark:text-gray-400">{{ $c->message ?: '—' }}</td>
            <td class="p-4 whitespace-nowrap"><span @class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
              'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' => $c->status === 'pending', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $c->status === 'approved', 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' => $c->status === 'rejected'])>{{ ucfirst($c->status) }}</span>
              @if ($c->review_note)<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $c->review_note }}</p>@endif</td>
            <td class="p-4 whitespace-nowrap text-right">
              @if ($c->status === 'pending')
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                  </button>
                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                    <form method="POST" action="{{ route('property-claims.approve', $c) }}">@csrf
                      <button class="{{ $menuItem }}" onclick="return confirm('Approve? {{ addslashes($c->company?->name) }} will manage {{ addslashes($c->property?->name) }}.')">Approve</button></form>
                    <form method="POST" action="{{ route('property-claims.reject', $c) }}">@csrf
                      <button class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Reject</button></form>
                  </div>
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="p-4 text-center">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No claims yet</h3>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>
</div>
@endsection
