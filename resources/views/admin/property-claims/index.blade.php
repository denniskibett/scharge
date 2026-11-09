@extends('layouts.app')
@section('title', 'Building claims')

@section('content')
<div class="p-4 md:p-6">
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Building claims</h1>
    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Management companies asking to manage a building already on scharge (often one a BnB host registered first). Approving moves the building's books to them; host units stay with their hosts and are billed service charge and water.</p>
  </div>

  @if (session('success'))
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif

  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <table class="min-w-full text-left text-sm">
      <thead class="border-b border-gray-100 bg-gray-50 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
        <tr><th class="px-5 py-3">Building</th><th class="px-5 py-3">Company asking</th><th class="px-5 py-3">Their note</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($claims as $c)
          <tr class="text-gray-700 dark:text-gray-300">
            <td class="px-5 py-3"><p class="font-medium text-gray-900 dark:text-white">{{ $c->property?->name }}</p><p class="text-xs text-gray-500">{{ $c->property?->location ?: 'No location' }} · {{ $c->property?->claim_status === 'unclaimed' ? 'not managed yet' : 'managed by another company' }}</p></td>
            <td class="px-5 py-3"><p>{{ $c->company?->name }}</p><p class="text-xs text-gray-500">{{ $c->requester?->name }} · {{ $c->requester?->phone ?: $c->requester?->email }} · {{ $c->created_at->diffForHumans() }}</p></td>
            <td class="max-w-xs px-5 py-3 text-xs">{{ $c->message ?: '—' }}</td>
            <td class="px-5 py-3"><span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium',
              'bg-amber-50 text-amber-700' => $c->status === 'pending', 'bg-emerald-50 text-emerald-700' => $c->status === 'approved', 'bg-gray-100 text-gray-600' => $c->status === 'rejected'])>{{ ucfirst($c->status) }}</span>
              @if ($c->review_note)<p class="mt-1 text-xs text-gray-500">{{ $c->review_note }}</p>@endif</td>
            <td class="whitespace-nowrap px-5 py-3 text-right">
              @if ($c->status === 'pending')
                <form method="POST" action="{{ route('property-claims.approve', $c) }}" class="inline">@csrf
                  <button class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800" onclick="return confirm('Approve? {{ addslashes($c->company?->name) }} will manage {{ addslashes($c->property?->name) }}.')">Approve</button></form>
                <form method="POST" action="{{ route('property-claims.reject', $c) }}" class="ml-2 inline">@csrf
                  <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Reject</button></form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">No claims yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
