@extends('layouts.app')

@section('title', 'Visitor Trends Report')

@section('content')
<div class="flex flex-col gap-5 p-6" x-data="trendsReport()" x-init="init()">
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

    <!-- Period Filter -->
    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" action="{{ route('security.reports.trends') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="period" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Period (Days)</label>
                <select id="period" name="period" 
                        class="mt-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                    <option value="7" {{ ($period ?? 30) == 7 ? 'selected' : '' }}>7 days</option>
                    <option value="14" {{ ($period ?? 30) == 14 ? 'selected' : '' }}>14 days</option>
                    <option value="30" {{ ($period ?? 30) == 30 ? 'selected' : '' }}>30 days</option>
                    <option value="60" {{ ($period ?? 30) == 60 ? 'selected' : '' }}>60 days</option>
                    <option value="90" {{ ($period ?? 30) == 90 ? 'selected' : '' }}>90 days</option>
                </select>
            </div>
            <button type="submit" 
                    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                View Report
            </button>
        </form>
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
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Daily Visitor Trends</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Last {{ $period ?? 30 }} days</p>
        </div>
        <div class="pt-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Active</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Completed</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyTrends as $trend)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                                    {{ \Carbon\Carbon::parse($trend->date)->format('M d, Y') }}
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($trend->date)->format('l') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-800 dark:text-white">
                                    <span class="font-semibold">{{ $trend->total }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-green-600 dark:text-green-400">
                                    {{ $trend->active ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-center text-blue-600 dark:text-blue-400">
                                    {{ $trend->completed ?? 0 }}
                                </td>
                                <td class="px-4 py-3">
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
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <p>No data available for this period</p>
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
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">Top Frequent Visitors</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Visitors with the most visits</p>
        </div>
        <div class="pt-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visitor</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Visits</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topVisitors ?? [] as $visitor)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                                    {{ $visitor->visitor_name_snapshot ?? 'Unknown' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                        {{ $visitor->visit_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
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
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <p>No frequent visitors data available</p>
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