@extends('layouts.app')

@section('title', 'Water Meter Readings')

@section('content')
<div x-data="waterIndex()" x-init="init()">
    <div class="container-fluid px-4 py-4">
        <!-- Header -->
        <div class="row mb-6">
            <div class="col-12">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-brand-500 to-brand-600 p-6 shadow-lg dark:from-brand-600 dark:to-brand-700">
                    <div class="absolute inset-0 opacity-10">
                        <svg class="absolute -right-20 -top-20 h-64 w-64 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    
                    <div class="relative flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex-1">
                            <h1 class="text-2xl font-bold text-white">Water Meter Readings</h1>
                            <p class="mt-1 text-brand-100">Track and manage all water consumption readings across estates</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <button @click="openCreateReadingModal()" class="inline-flex items-center gap-2 rounded-lg bg-white/20 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm transition hover:bg-white/30">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Record New Reading
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Readings</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="totalReadings">0</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Consumption</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">
                            <span x-text="formatNumber(totalConsumption)"></span> m³
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Charges</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">
                            KES <span x-text="formatNumber(totalCharges)"></span>
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Avg Reading</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">
                            <span x-text="formatNumber(averageConsumption)"></span> m³
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                        <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Water Readings Table -->
        @include('partials.table.table-readings', [
            'readings' => $groupedReadings ?? [],
            'showActions' => true,
            'showConsumption' => true,
            'units' => $units ?? []
        ])
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('waterIndex', () => ({
        readings: @json($groupedReadings ?? []),
        totalReadings: 0,
        totalConsumption: 0,
        totalCharges: 0,
        averageConsumption: 0,
        
        init() {
            this.calculateStats();
        },
        
        calculateStats() {
            this.totalReadings = this.readings.length;
            this.totalConsumption = this.readings.reduce((sum, r) => sum + (r.consumption || 0), 0);
            this.totalCharges = this.readings.reduce((sum, r) => sum + (r.charge || 0), 0);
            this.averageConsumption = this.totalReadings > 0 ? this.totalConsumption / this.totalReadings : 0;
        },
        
        formatNumber(value) {
            if (value === undefined || value === null) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        
        openCreateReadingModal(unitId = null) {
            if (window.openCreateReadingModal) {
                window.openCreateReadingModal(unitId);
            }
        }
    }));
});
</script>

<!-- ============================================ -->
<!-- THIS IS THE KEY FIX - Passing estates to modal -->
<!-- ============================================ -->
@include('partials.modal.modal-create-reading', ['estates' => $estates ?? []])
@endsection