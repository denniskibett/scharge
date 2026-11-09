@extends('layouts.app')

@section('title', 'Daily Visitor Report')

@section('content')
<div class="flex flex-col gap-5">
    <!-- Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Daily Visitor Report
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="formatDate('{{ $date ?? now()->toDateString() }}')"></span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('security.index') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Dashboard
                </a>
                <button onclick="window.print()" 
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Visitors</p>
            <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $stats['total'] ?? 0 }}</h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Checked In</p>
            <h4 class="text-2xl font-semibold text-green-600 dark:text-green-400">{{ $stats['checked_in'] ?? 0 }}</h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Checked Out</p>
            <h4 class="text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ $stats['checked_out'] ?? 0 }}</h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
            <h4 class="text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ $stats['pending'] ?? 0 }}</h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Currently Inside</p>
            <h4 class="text-2xl font-semibold text-purple-600 dark:text-purple-400">{{ $stats['checked_in'] ?? 0 }}</h4>
        </div>
    </div>

    <!-- Purpose Breakdown -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Purpose Breakdown</h4>
        </div>
        <div class="pt-4">
            @if(!empty($stats['by_purpose']))
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @foreach($stats['by_purpose'] as $purpose => $count)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ ucfirst($purpose) }}</p>
                            <p class="text-lg font-semibold text-brand-600 dark:text-brand-400">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No data available</p>
            @endif
        </div>
    </div>

    <!-- Visitors Table -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" action="{{ route('security.reports.daily') }}" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Visitor Log</h4>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <input type="date" id="date" name="date" value="{{ $date ?? now()->toDateString() }}" aria-label="Report date" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            </div>
        </form>
        <div>
            <div class="w-full overflow-x-auto">
                <table class="data-table w-full table-auto">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Visitor</th>
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Unit</th>
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Purpose</th>
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Check-In</th>
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Check-Out</th>
                            <th class="p-4 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($visitors ?? [] as $visitor)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="p-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                            <span class="text-sm font-medium text-purple-600">
                                                {{ $visitor->visitor_name_snapshot ? strtoupper(substr($visitor->visitor_name_snapshot, 0, 2)) : '??' }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {{ $visitor->visitor_name_snapshot ?? $visitor->visitor->name ?? 'Unknown' }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $visitor->visitor_phone_snapshot ?? $visitor->visitor->phone ?? '' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 whitespace-nowrap text-sm text-gray-800 dark:text-white/90">
                                    {{ $visitor->unit->unit_number ?? 'N/A' }}
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ ucfirst($visitor->purpose ?? $visitor->access_type ?? 'Guest') }}
                                    </span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $visitor->access_time ? $visitor->access_time->format('M d, Y H:i') : 'N/A' }}
                                </td>
                                <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $visitor->exit_time ? $visitor->exit_time->format('M d, Y H:i') : '-' }}
                                </td>
                                <td class="p-4 whitespace-nowrap text-center">
                                    @if($visitor->status === 'approved' && !$visitor->exit_time)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Inside</span>
                                    @elseif($visitor->status === 'approved' && $visitor->exit_time)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Completed</span>
                                    @elseif($visitor->status === 'pending')
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Pending</span>
                                    @elseif($visitor->status === 'denied')
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Denied</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">{{ ucfirst($visitor->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No visitors recorded on this date</h3>
  </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }
</script>
@endpush
@endsection