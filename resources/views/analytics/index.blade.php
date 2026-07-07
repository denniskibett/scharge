{{-- resources/views/analytics/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Analytics Dashboard')

@section('content')
<style>
    .stat-card {
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }
    .chart-container {
        position: relative;
        height: 280px;
        width: 100%;
    }
    .chart-container-sm {
        height: 220px;
        width: 100%;
    }
    .gradient-1 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .gradient-2 {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .gradient-3 {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .gradient-4 {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    /* Toggle button fix */
    .chart-toggle-btn {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .chart-toggle-btn:hover {
        background-color: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    .dark .chart-toggle-btn:hover {
        background-color: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
    }
</style>

<div class="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-black dark:text-white">📊 Analytics Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Comprehensive overview of your entire system</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('analytics') }}" class="flex flex-wrap items-center gap-2" id="filterForm">
                <input type="date" name="date_from" value="{{ $dateFrom }}" 
                       class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
                <span class="text-sm text-gray-500">to</span>
                <input type="date" name="date_to" value="{{ $dateTo }}" 
                       class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
                
                <select name="estate_id" class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
                    <option value="">All Estates</option>
                    @foreach($estates as $estate)
                        <option value="{{ $estate->id }}" {{ $estateFilter == $estate->id ? 'selected' : '' }}>
                            {{ $estate->name }}
                        </option>
                    @endforeach
                </select>
                
                <button type="submit" class="rounded-md bg-primary px-4 py-1.5 text-sm text-white hover:bg-primary/90">
                    Apply
                </button>
                <a href="{{ route('analytics') }}" class="rounded-md border border-stroke px-4 py-1.5 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                    Reset
                </a>
            </form>
            
            <button onclick="refreshData()" class="rounded-md border border-stroke px-4 py-1.5 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800" title="Refresh Data">
                <svg class="h-4 w-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
        <div class="rounded-lg bg-white p-8 dark:bg-boxdark">
            <div class="flex items-center gap-3">
                <svg class="h-8 w-8 animate-spin text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span class="text-lg font-medium text-black dark:text-white">Loading analytics data...</span>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- KPI CARDS -->
    <!-- ============================================ -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        
        <a href="{{ route('sms.campaigns.index') }}" class="stat-card rounded-lg overflow-hidden shadow-lg cursor-pointer block">
            <div class="gradient-1 p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium opacity-90">📱 SMS Sent</span>
                        <h4 class="mt-1 text-3xl font-bold">{{ number_format($smsSummary['total_sms']) }}</h4>
                        <span class="text-xs bg-white/20 px-2 py-1 rounded-full">{{ $smsSummary['delivery_rate'] }}% Delivery Rate</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs opacity-80">
                    <span>💰 KES {{ number_format($smsSummary['total_cost'], 2) }}</span>
                </div>
                <div class="mt-2 h-1 w-full bg-white/20 rounded-full overflow-hidden">
                    <div class="h-1 bg-white rounded-full" style="width: {{ $smsSummary['delivery_rate'] }}%;"></div>
                </div>
            </div>
        </a>

        <a href="{{ route('estates.index') }}" class="stat-card rounded-lg overflow-hidden shadow-lg cursor-pointer block">
            <div class="gradient-2 p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium opacity-90">🏢 Properties</span>
                        <h4 class="mt-1 text-3xl font-bold">{{ number_format($propertyStats['total_units']) }}</h4>
                        <span class="text-xs bg-white/20 px-2 py-1 rounded-full">{{ $propertyStats['occupancy_rate'] }}% Occupied</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs opacity-80">
                    <span>{{ number_format($propertyStats['total_estates']) }} Estates</span>
                    <span>•</span>
                    <span>{{ number_format($propertyStats['vacant_units']) }} Vacant</span>
                </div>
            </div>
        </a>

        <a href="{{ route('tenants.index') }}" class="stat-card rounded-lg overflow-hidden shadow-lg cursor-pointer block">
            <div class="gradient-3 p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium opacity-90">👤 Tenants</span>
                        <h4 class="mt-1 text-3xl font-bold">{{ number_format($tenantStats['total_tenants']) }}</h4>
                        <span class="text-xs bg-white/20 px-2 py-1 rounded-full">{{ number_format($tenantStats['active_tenants']) }} Active</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs opacity-80">
                    <span>{{ number_format($tenantStats['inactive_tenants']) }} Inactive</span>
                </div>
            </div>
        </a>

        <a href="{{ route('payments.index') }}" class="stat-card rounded-lg overflow-hidden shadow-lg cursor-pointer block">
            <div class="gradient-4 p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium opacity-90">💰 Revenue</span>
                        <h4 class="mt-1 text-3xl font-bold">KES {{ number_format($paymentSummary['total_amount'] ?? 0, 0) }}</h4>
                        <span class="text-xs bg-white/20 px-2 py-1 rounded-full">{{ $paymentSummary['collection_rate'] ?? 0 }}% Collected</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs opacity-80">
                    <span>● {{ number_format($paymentSummary['paid_invoices'] ?? 0) }} Paid</span>
                    <span>● {{ number_format($paymentSummary['unpaid_invoices'] ?? 0) }} Unpaid</span>
                </div>
            </div>
        </a>
    </div>

    <!-- ============================================ -->
    <!-- CHART ROW 1: LINE CHARTS -->
    <!-- ============================================ -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">📈 SMS Trends</h5>
                <button onclick="toggleChartType('sms')" class="chart-toggle-btn inline-flex items-center rounded-md border border-stroke px-3 py-1 text-xs font-medium text-black hover:border-primary hover:text-primary dark:border-strokedark dark:text-white">
                    <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Toggle View
                </button>
            </div>
            <div class="p-4">
                <div class="chart-container">
                    <canvas id="smsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">📈 Revenue Trends</h5>
                <button onclick="toggleChartType('revenue')" class="chart-toggle-btn inline-flex items-center rounded-md border border-stroke px-3 py-1 text-xs font-medium text-black hover:border-primary hover:text-primary dark:border-strokedark dark:text-white">
                    <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Toggle View
                </button>
            </div>
            <div class="p-4">
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CHART ROW 2: PIE CHARTS -->
    <!-- ============================================ -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">📊 SMS by Estate</h5>
            </div>
            <div class="p-4">
                <div class="chart-container-sm">
                    <canvas id="smsByEstateChart"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">🏢 Units by Type</h5>
            </div>
            <div class="p-4">
                <div class="chart-container-sm">
                    <canvas id="unitsByTypeChart"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">💳 Payment Status</h5>
            </div>
            <div class="p-4">
                <div class="chart-container-sm">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CHART ROW 3: BAR CHARTS -->
    <!-- ============================================ -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">🏢 Units by Estate</h5>
            </div>
            <div class="p-4">
                <div class="chart-container">
                    <canvas id="unitsByEstateChart"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">💰 Revenue by Estate</h5>
            </div>
            <div class="p-4">
                <div class="chart-container">
                    <canvas id="revenueByEstateChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- DETAILS ROW -->
    <!-- ============================================ -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">📊 Delivery Rate by Estate</h5>
            </div>
            <div class="p-6">
                @forelse($deliveryByEstate as $estate)
                <div class="mb-3 group hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded transition-colors">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-black dark:text-white">{{ $estate->estate_name ?? 'Unknown' }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $estate->delivery_rate ?? 0 }}%</span>
                    </div>
                    <div class="relative mt-1 h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 rounded-full bg-success transition-all" 
                             style="width: {{ $estate->delivery_rate ?? 0 }}%;"></div>
                    </div>
                    <div class="mt-0.5 flex justify-between text-xs text-gray-400">
                        <span>Sent: {{ $estate->total_sent ?? 0 }}</span>
                        <span>Delivered: {{ $estate->total_delivered ?? 0 }}</span>
                        <span>Failed: {{ $estate->total_failed ?? 0 }}</span>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500">No data available</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">📋 Recent Activity</h5>
            </div>
            <div class="p-6 max-h-80 overflow-y-auto">
                <div class="space-y-3">
                    @forelse($recentActivity as $activity)
                    <div class="flex items-start gap-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded transition-colors cursor-pointer">
                        <span class="text-xl">{{ $activity['icon'] }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-black dark:text-white">
                                    <a href="{{ $activity['link'] ?? '#' }}" class="hover:text-primary">
                                        {{ $activity['title'] }}
                                    </a>
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$activity['status']] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($activity['status']) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">{{ $activity['details'] }}</p>
                            <p class="text-xs text-gray-400">{{ $activity['time'] ? \Carbon\Carbon::parse($activity['time'])->diffForHumans() : '-' }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-gray-500 py-8">No recent activity</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let smsChartInstance = null;
let revenueChartInstance = null;
let smsByEstateChartInstance = null;
let unitsByTypeChartInstance = null;
let paymentStatusChartInstance = null;
let unitsByEstateChartInstance = null;
let revenueByEstateChartInstance = null;

let smsChartType = 'line';
let revenueChartType = 'line';

// Status colors for activity
const statusColors = {
    'completed': 'bg-success/10 text-success',
    'sending': 'bg-blue-100 text-blue-700',
    'draft': 'bg-gray-100 text-gray-700',
    'paid': 'bg-success/10 text-success',
    'unpaid': 'bg-danger/10 text-danger',
    'pending': 'bg-warning/10 text-warning',
    'partial': 'bg-yellow-100 text-yellow-700',
};

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
});

function initCharts() {
    // ============================================
    // SMS Chart - Line/Bar
    // ============================================
    const smsCtx = document.getElementById('smsChart').getContext('2d');
    const smsData = @json($smsByMonth);
    
    smsChartInstance = new Chart(smsCtx, {
        type: 'line',
        data: {
            labels: smsData.map(item => item.month),
            datasets: [
                {
                    label: 'Delivered',
                    data: smsData.map(item => item.delivered || 0),
                    backgroundColor: 'rgba(34, 197, 94, 0.2)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(34, 197, 94)',
                    pointRadius: 4,
                    pointHoverRadius: 7
                },
                {
                    label: 'Failed',
                    data: smsData.map(item => item.failed || 0),
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(239, 68, 68)',
                    pointRadius: 4,
                    pointHoverRadius: 7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: { size: 11, weight: '500' },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' SMS';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    ticks: { font: { size: 10 } },
                    grid: { display: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            onClick: function(event, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = this.data.labels[index];
                    window.location.href = '{{ route("sms.campaigns.index") }}?month=' + label;
                }
            }
        }
    });

    // ============================================
    // Revenue Chart - Line/Bar
    // ============================================
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = @json($paymentsByMonth);
    
    revenueChartInstance = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueData.map(item => item.month),
            datasets: [
                {
                    label: 'Revenue',
                    data: revenueData.map(item => item.paid || 0),
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgb(99, 102, 241)',
                    pointRadius: 4,
                    pointHoverRadius: 7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: { size: 11, weight: '500' },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return 'KES ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 10 },
                        callback: function(value) {
                            return 'KES ' + value.toLocaleString();
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    ticks: { font: { size: 10 } },
                    grid: { display: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            onClick: function(event, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = this.data.labels[index];
                    window.location.href = '{{ route("payments.index") }}?month=' + label;
                }
            }
        }
    });

    // ============================================
    // SMS by Estate - Pie Chart
    // ============================================
    const smsByEstateCtx = document.getElementById('smsByEstateChart').getContext('2d');
    const smsByEstateData = @json($smsByEstate);
    const pieColors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b', '#fa709a', '#a18cd1'];
    
    smsByEstateChartInstance = new Chart(smsByEstateCtx, {
        type: 'pie',
        data: {
            labels: smsByEstateData.map(item => item.estate_name || 'Unknown'),
            datasets: [{
                data: smsByEstateData.map(item => item.total || 0),
                backgroundColor: pieColors.slice(0, smsByEstateData.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 10,
                        font: { size: 10 },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // Units by Type - Pie Chart
    // ============================================
    const unitsByTypeCtx = document.getElementById('unitsByTypeChart').getContext('2d');
    const unitsByTypeData = @json($unitsByType);
    const typeColors = ['#43e97b', '#38f9d7', '#fa709a', '#fee140', '#4facfe', '#764ba2'];
    
    unitsByTypeChartInstance = new Chart(unitsByTypeCtx, {
        type: 'pie',
        data: {
            labels: unitsByTypeData.map(item => item.unit_type || 'Unknown'),
            datasets: [{
                data: unitsByTypeData.map(item => item.total || 0),
                backgroundColor: typeColors.slice(0, unitsByTypeData.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 10,
                        font: { size: 10 },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // Payment Status - Doughnut Chart
    // ============================================
    const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
    const paymentStatusData = @json($paymentStatusDistribution);
    const statusColors2 = ['#22c55e', '#ef4444', '#eab308', '#f59e0b'];
    const statusLabels = {
        'paid': 'Paid',
        'unpaid': 'Unpaid',
        'partial': 'Partial',
        'pending': 'Pending'
    };
    
    paymentStatusChartInstance = new Chart(paymentStatusCtx, {
        type: 'doughnut',
        data: {
            labels: paymentStatusData.map(item => statusLabels[item.status] || item.status),
            datasets: [{
                data: paymentStatusData.map(item => item.count || 0),
                backgroundColor: statusColors2.slice(0, paymentStatusData.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 10,
                        font: { size: 10 },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' invoices (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // Units by Estate - Bar Chart
    // ============================================
    const unitsByEstateCtx = document.getElementById('unitsByEstateChart').getContext('2d');
    const unitsByEstateData = @json($unitsByEstate);
    
    unitsByEstateChartInstance = new Chart(unitsByEstateCtx, {
        type: 'bar',
        data: {
            labels: unitsByEstateData.map(item => item.name || 'Unknown'),
            datasets: [{
                label: 'Units',
                data: unitsByEstateData.map(item => item.total || 0),
                backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a'],
                borderRadius: 4,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    ticks: { font: { size: 10 } },
                    grid: { display: false }
                }
            },
            onClick: function(event, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = this.data.labels[index];
                    window.location.href = '{{ route("estates.index") }}?search=' + encodeURIComponent(label);
                }
            }
        }
    });

    // ============================================
    // Revenue by Estate - Bar Chart
    // ============================================
    const revenueByEstateCtx = document.getElementById('revenueByEstateChart').getContext('2d');
    const revenueByEstateData = @json($paymentsByEstate);
    
    revenueByEstateChartInstance = new Chart(revenueByEstateCtx, {
        type: 'bar',
        data: {
            labels: revenueByEstateData.map(item => item.estate_name || 'Unknown'),
            datasets: [
                {
                    label: 'Total Revenue',
                    data: revenueByEstateData.map(item => item.total_amount || 0),
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderRadius: 4,
                    borderWidth: 0
                },
                {
                    label: 'Collected',
                    data: revenueByEstateData.map(item => item.paid_amount || 0),
                    backgroundColor: 'rgba(34, 197, 94, 0.7)',
                    borderRadius: 4,
                    borderWidth: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: { size: 11 },
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': KES ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 10 },
                        callback: function(value) {
                            return 'KES ' + value.toLocaleString();
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    ticks: { font: { size: 10 } },
                    grid: { display: false }
                }
            },
            onClick: function(event, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = this.data.labels[index];
                    window.location.href = '{{ route("payments.index") }}?estate=' + encodeURIComponent(label);
                }
            }
        }
    });
}

function toggleChartType(type) {
    if (type === 'sms') {
        smsChartType = smsChartType === 'line' ? 'bar' : 'line';
        smsChartInstance.destroy();
        const smsCtx = document.getElementById('smsChart').getContext('2d');
        const smsData = @json($smsByMonth);
        smsChartInstance = new Chart(smsCtx, {
            type: smsChartType,
            data: {
                labels: smsData.map(item => item.month),
                datasets: [
                    {
                        label: 'Delivered',
                        data: smsData.map(item => item.delivered || 0),
                        backgroundColor: smsChartType === 'bar' ? 'rgba(34, 197, 94, 0.7)' : 'rgba(34, 197, 94, 0.2)',
                        borderColor: 'rgb(34, 197, 94)',
                        borderWidth: smsChartType === 'bar' ? 0 : 3,
                        fill: smsChartType === 'line',
                        tension: smsChartType === 'line' ? 0.4 : 0,
                        pointRadius: smsChartType === 'line' ? 4 : 0
                    },
                    {
                        label: 'Failed',
                        data: smsData.map(item => item.failed || 0),
                        backgroundColor: smsChartType === 'bar' ? 'rgba(239, 68, 68, 0.7)' : 'rgba(239, 68, 68, 0.2)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: smsChartType === 'bar' ? 0 : 3,
                        fill: smsChartType === 'line',
                        tension: smsChartType === 'line' ? 0.4 : 0,
                        pointRadius: smsChartType === 'line' ? 4 : 0
                    }
                ]
            },
            options: smsChartInstance.options
        });
    } else {
        revenueChartType = revenueChartType === 'line' ? 'bar' : 'line';
        revenueChartInstance.destroy();
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = @json($paymentsByMonth);
        revenueChartInstance = new Chart(revenueCtx, {
            type: revenueChartType,
            data: {
                labels: revenueData.map(item => item.month),
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenueData.map(item => item.paid || 0),
                        backgroundColor: revenueChartType === 'bar' ? 'rgba(99, 102, 241, 0.7)' : 'rgba(99, 102, 241, 0.2)',
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: revenueChartType === 'bar' ? 0 : 3,
                        fill: revenueChartType === 'line',
                        tension: revenueChartType === 'line' ? 0.4 : 0,
                        pointRadius: revenueChartType === 'line' ? 4 : 0
                    }
                ]
            },
            options: revenueChartInstance.options
        });
    }
}

function refreshData() {
    document.getElementById('loadingOverlay').classList.remove('hidden');
    setTimeout(function() {
        location.reload();
    }, 500);
}
</script>
@endsection