@extends('layouts.app')

@section('content')
@php
    $pill = ['pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200', 'sending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'sent' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'];
    $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $td = 'p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400';
@endphp
<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $campaign->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Created {{ $campaign->created_at?->format('j M Y, H:i') }}@if($campaign->creator) by {{ $campaign->creator->name }}@endif
                @if($campaign->template) · Template: {{ $campaign->template->name }}@endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $pill[$campaign->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">{{ ucfirst($campaign->status) }}</span>
            <a href="{{ route('sms.campaigns.export', $campaign->id) }}" class="shadow-theme-xs flex h-11 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Export CSV</a>
            <a href="{{ route('sms.campaigns.index') }}" class="shadow-theme-xs flex h-11 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([['Recipients', $stats['total'], ''], ['Sent or delivered', $stats['sent'], 'text-green-600 dark:text-green-400'], ['Failed', $stats['failed'], 'text-red-600 dark:text-red-400'], ['Waiting', $stats['pending'], 'text-amber-600']] as [$label, $n, $tone])
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <h4 class="stat-num mt-1 {{ $tone }}">{{ number_format($n) }}</h4>
            </div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Recipients</h3>
        </div>
        <div class="custom-scrollbar overflow-x-auto">
            <table class="w-full table-auto">
                <thead><tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="{{ $th }}">Phone</th><th class="{{ $th }}">Tenant</th><th class="{{ $th }}">Status</th><th class="{{ $th }}">Sent</th><th class="{{ $th }}">Message</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($recipients as $r)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="{{ $td }} font-medium text-gray-800 dark:text-white/90">{{ $r->phone_number }}</td>
                            <td class="{{ $td }}">{{ $r->tenant?->user?->name ?? '—' }}</td>
                            <td class="p-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $pill[$r->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">{{ ucfirst($r->status ?? 'pending') }}</span>
                                @if ($r->error_message)<p class="mt-1 max-w-xs truncate text-xs text-red-600" title="{{ $r->error_message }}">{{ $r->error_message }}</p>@endif
                            </td>
                            <td class="{{ $td }}">{{ $r->sent_at ? \Illuminate\Support\Carbon::parse($r->sent_at)->format('j M, H:i') : '—' }}</td>
                            <td class="p-4 text-sm text-gray-500 dark:text-gray-400"><span class="line-clamp-2 max-w-md">{{ $r->message }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">No recipients in this campaign yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.table.pager', ['paginator' => $recipients])
    </div>
</div>
@endsection
