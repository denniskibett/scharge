@extends('layouts.app')

@section('title', 'Visitor Trends Report')

@section('content')
<div class="flex flex-col gap-5" x-data="trendsReport()" x-init="init()">
    <!-- Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Visitor Trends Report
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $period ?? 30 }} day analysis
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Visitors</p>
            <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $dailyTrends->sum('total') ?? 0 }}</h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Avg Daily Visitors</p>
            <h4 class="text-2xl font-semibold text-brand-600 dark:text-brand-400">
                {{ $dailyTrends->count() > 0 ? round($dailyTrends->sum('total') / $dailyTrends->count(), 1) : 0 }}
            </h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Peak Day</p>
            <h4 class="text-2xl font-semibold text-yellow-600 dark:text-yellow-400">
                @php
                    $peakDay = $dailyTrends->sortByDesc('total')->first();
                @endphp
                {{ $peakDay ? \Carbon\Carbon::parse($peakDay->date)->format('M d') : 'N/A' }}
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $peakDay ? $peakDay->total : 0 }})
                </span>
            </h4>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Top Purpose</p>
            <h4 class="text-2xl font-semibold text-purple-600 dark:text-purple-400">
                @php
                    $topPurpose = $purposeBreakdown->sortByDesc('count')->first();
                @endphp
                {{ $topPurpose ? ucfirst($topPurpose->purpose) : 'N/A' }}
            </h4>
        </div>
    </div>

    <!-- Daily Trends Chart (Table View) -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" action="{{ route('security.reports.trends') }}" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daily Visitor Trends</h4>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Last {{ $period ?? 30 }} days</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @include('partials.table.select', ['name' => 'period', 'aria' => 'Period (days)', 'selected' => $period ?? 30, 'min' => 'min-w-[120px]',
                    'options' => [7 => '7 days', 14 => '14 days', 30 => '30 days', 60 => '60 days', 90 => '90 days']])
            </div>
        </form>
        <div>
            <div class="w-full overflow-x-auto">
                <table class="data-table w-full table-auto">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Date</th>
                            <th class="p-4 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Total</th>
                            <th class="p-4 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Active</th>
                            <th class="p-4 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Completed</th>
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($dailyTrends as $trend)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ \Carbon\Carbon::parse($trend->date)->format('M d, Y') }}
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($trend->date)->format('l') }}
                                    </span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-center text-sm text-gray-800 dark:text-white/90">
                                    <span class="font-semibold">{{ $trend->total }}</span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-center text-sm text-green-600 dark:text-green-400">
                                    {{ $trend->active ?? 0 }}
                                </td>
                                <td class="p-4 whitespace-nowrap text-center text-sm text-blue-600 dark:text-blue-400">
                                    {{ $trend->completed ?? 0 }}
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                            <div class="h-2 bg-brand-500 rounded-full" 
                                                 style="width: {{ $trend->total > 0 ? min(($trend->total / $dailyTrends->max('total')) * 100, 100) : 0 }}%">
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $trend->total > 0 ? round(($trend->total / $dailyTrends->max('total')) * 100) : 0 }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No data available for this period</h3>
  </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Purpose & Access Type Breakdown -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <!-- Purpose Breakdown -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
                <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Purpose Breakdown</h4>
            </div>
            <div class="pt-4">
                @if($purposeBreakdown->count() > 0)
                    <div class="space-y-2">
                        @foreach($purposeBreakdown as $purpose)
                            @php
                                $total = $purposeBreakdown->sum('count');
                                $percentage = $total > 0 ? round(($purpose->count / $total) * 100, 1) : 0;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($purpose->purpose ?? 'Other') }}</span>
                                    <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $purpose->count }}</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-2 bg-brand-500 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No data available</p>
                @endif
            </div>
        </div>

        <!-- Access Type Breakdown -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
                <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Access Type Breakdown</h4>
            </div>
            <div class="pt-4">
                @if($accessTypeBreakdown->count() > 0)
                    <div class="space-y-2">
                        @foreach($accessTypeBreakdown as $accessType)
                            @php
                                $total = $accessTypeBreakdown->sum('count');
                                $percentage = $total > 0 ? round(($accessType->count / $total) * 100, 1) : 0;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ ucfirst($accessType->access_type ?? 'Other') }}</span>
                                    <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $accessType->count }}</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-2 bg-brand-500 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No data available</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Top Visitors -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Top Frequent Visitors</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Visitors with the most visits</p>
        </div>
        <div>
            <div class="w-full overflow-x-auto">
                <table class="data-table w-full table-auto">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Visitor</th>
                            <th class="p-4 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Visits</th>
                            <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($topVisitors ?? [] as $visitor)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $visitor->visitor_name_snapshot ?? 'Unknown' }}
                                </td>
                                <td class="p-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {{ $visitor->visit_count }}
                                    </span>
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <div class="w-32 h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                        @php
                                            $maxVisits = $topVisitors->max('visit_count') ?? 1;
                                            $width = $maxVisits > 0 ? ($visitor->visit_count / $maxVisits) * 100 : 0;
                                        @endphp
                                        <div class="h-2 bg-brand-500 rounded-full" style="width: {{ $width }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No frequent visitors data available</h3>
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
    function trendsReport() {
        return {
            init() {
                // Any initialization logic
            }
        }
    }
</script>
@endpush
@endsection