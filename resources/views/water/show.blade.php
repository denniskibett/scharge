@extends('layouts.app')

@section('title', 'Water Meter Reading Statement - Unit ' . ($unit->unit_number ?? ''))

@section('content')
<div x-data="waterStatement()" x-init="init()" @update-readings.window="loadReadings()">
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
                            <h1 class="text-2xl font-bold text-white">Water Meter Reading Statement</h1>
                            <div class="mt-2 flex flex-wrap gap-4 text-brand-100">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <span>Unit: <span class="font-semibold text-white" x-text="unitNumber"></span></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span>Estate: <span class="font-semibold text-white" x-text="estateName"></span></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Billing: <span class="font-semibold text-white" x-text="billingType"></span></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>Readings: <span class="font-semibold text-white" x-text="totalReadings"></span></span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex gap-2">
                            <a href="{{ route('water.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/20 px-4 py-2 text-sm font-medium text-white backdrop-blur-sm transition hover:bg-white/30">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Readings
                            </a>
                            <button @click="openAutoFillModal()" class="inline-flex items-center gap-2 rounded-lg bg-yellow-500/20 px-4 py-2 text-sm font-medium text-yellow-100 backdrop-blur-sm transition hover:bg-yellow-500/30">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Auto-Fill Missing
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <!-- Total Consumption -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Consumption</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">
                            <span x-text="formatNumber(totalConsumption)"></span> m³
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Charges -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Charges</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">
                            KES <span x-text="formatNumber(totalCharges)"></span>
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Average Monthly Consumption -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Avg Consumption</p>
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

            <!-- Number of Readings -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Readings</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800 dark:text-white/90">
                            <span x-text="totalReadings"></span>
                        </p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missing Months Warning -->
        <div x-show="missingMonths.length > 0" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-red-800 dark:text-red-300">Missing Readings Detected</p>
                    <p class="text-sm text-red-700 dark:text-red-400">
                        This unit has <strong x-text="missingMonths.length"></strong> month(s) with no readings: 
                        <span x-text="missingMonths.join(', ')"></span>
                    </p>
                    <button @click="openAutoFillModal()" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-red-800 hover:text-red-900 dark:text-red-300 dark:hover:text-red-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Auto-fill missing months
                    </button>
                </div>
            </div>
        </div>

        <!-- Initial Reading Note -->
        <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20" x-show="hasInitialReading">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Initial Reading Information</p>
                    <p class="text-sm text-blue-700 dark:text-blue-400">
                        The initial reading recorded on <strong x-text="initialReadingDate"></strong> was 
                        <strong x-text="formatNumber(initialReadingValue) + ' m³'"></strong>. 
                        This reading established the baseline and is excluded from consumption calculations.
                        Consumption calculations start from the second reading onwards.
                    </p>
                </div>
            </div>
        </div>

        <!-- Current Reading Info Card -->
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Current Reading</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Previous Reading</p>
                        <p class="text-xl font-bold text-gray-800 dark:text-white/90" x-text="formatNumber(previousReading) + ' m³'">0.00 m³</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Current Reading</p>
                        <p class="text-xl font-bold text-gray-800 dark:text-white/90" x-text="formatNumber(currentReading) + ' m³'">0.00 m³</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Current Consumption</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400" x-text="formatNumber(currentConsumption) + ' m³'">0.00 m³</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Current Charge</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">KES <span x-text="formatNumber(currentCharge)"></span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Water Readings History Table -->
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Reading History</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Complete water meter reading history for this unit</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button @click="openUpdateModal()" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Update Readings
                        </button>
                        <button @click="exportStatement()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export Statement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-500"></div>
                <span class="ml-3 text-gray-500">Loading readings...</span>
            </div>

            <!-- Table Content -->
            <div x-show="!loading" class="custom-scrollbar overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Reading Date</th>
                            <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Previous (m³)</th>
                            <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Current (m³)</th>
                            <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Consumption (m³)</th>
                            <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Rate (KES/m³)</th>
                            <th class="p-4 text-right text-xs font-medium text-gray-700 dark:text-gray-400">Charge (KES)</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Status</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-700 dark:text-gray-400">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        <template x-for="reading in readings" :key="reading.id">
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="p-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatDate(reading.reading_date)"></span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-right">
                                    <span class="text-sm text-gray-600 dark:text-gray-400" x-text="formatNumber(reading.previous_reading)"></span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-right">
                                    <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="formatNumber(reading.current_reading)"></span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-right">
                                    <span :class="getConsumptionClass(reading.consumption)" class="text-sm font-medium" x-text="formatNumber(reading.consumption)"></span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-right">
                                    <span class="text-sm text-gray-600 dark:text-gray-400" x-text="formatNumber(reading.rate_applied)"></span>
                                </td>
                                <td class="p-4 whitespace-nowrap text-right">
                                    <span class="text-sm font-medium text-blue-600 dark:text-blue-400">KES <span x-text="formatNumber(reading.charge)"></span></span>
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <span :class="getStatusBadgeClass(reading.consumption)" class="px-2 py-1 text-xs font-medium rounded-full">
                                        <span x-text="getStatusText(reading.consumption)"></span>
                                    </span>
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-400" x-text="reading.recorded_by_name || 'System'"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="readings.length === 0">
                            <td colspan="8" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                No water meter readings have been recorded for this unit yet.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot x-show="readings.length > 0" class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/50">
                        <tr>
                            <td colspan="3" class="p-4 text-right font-semibold text-gray-800 dark:text-white/90">Totals:</td>
                            <td class="p-4 text-right font-semibold text-gray-800 dark:text-white/90" x-text="formatNumber(totalConsumption)"></td>
                            <td class="p-4 text-right"></td>
                            <td class="p-4 text-right font-semibold text-blue-600 dark:text-blue-400">KES <span x-text="formatNumber(totalCharges)"></span></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Consumption Chart -->
        <div class="mt-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-show="readings.length > 0 && !loading">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Consumption Trend</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Monthly water consumption over time</p>
            </div>
            <div class="p-5">
                <canvas id="consumptionChart" class="w-full h-64"></canvas>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- UPDATE READINGS MODAL -->
    <!-- ============================================ -->
    <div x-show="updateModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="updateModalOpen = false"></div>
        
        <!-- Modal -->
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900 z-10">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Update Water Readings</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Edit readings for <span x-text="unitNumber"></span></p>
                    </div>
                    <button @click="updateModalOpen = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="p-4">
                    <div class="mb-4 bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">
                            <svg class="inline w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <strong>Warning:</strong> Updating readings will recalculate all consumption and charges. 
                            This will affect existing invoices. Use with caution.
                        </p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Previous Reading</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Current Reading</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Consumption</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Charge</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <template x-for="(reading, index) in editableReadings" :key="reading.id">
                                    <tr>
                                        <td class="px-4 py-2 text-sm font-medium" x-text="formatDate(reading.reading_date)"></td>
                                        <td class="px-4 py-2 text-sm text-gray-500 text-right" x-text="formatNumber(reading.previous_reading)"></td>
                                        <td class="px-4 py-2 text-right">
                                            <input type="number" step="0.01" 
                                                x-model="reading.current_reading"
                                                @input="recalculateReading(index)"
                                                :class="{'border-red-500': reading.current_reading < reading.previous_reading}"
                                                class="w-32 px-2 py-1 text-sm border border-gray-300 rounded focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 text-right">
                                        </td>
                                        <td class="px-4 py-2 text-sm text-green-600 text-right" x-text="formatNumber(reading.consumption)"></td>
                                        <td class="px-4 py-2 text-sm text-blue-600 text-right" x-text="formatNumber(reading.charge)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4 flex justify-end gap-2">
                        <button @click="autoFillMissingFromModal()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-yellow-700 bg-yellow-100 rounded-lg hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:hover:bg-yellow-900/50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Auto-Fill Missing
                        </button>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-800 sticky bottom-0 bg-white dark:bg-gray-900">
                    <button @click="updateModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button @click="saveUpdatedReadings()" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- AUTO-FILL MODAL -->
    <!-- ============================================ -->
    <div x-show="autoFillModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="autoFillModalOpen = false"></div>
        
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Auto-Fill Missing Months</h3>
                    <button @click="autoFillModalOpen = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        This will create readings for all missing months from the selected start month to the current month.
                        Each missing month will use the same reading value as the last available reading.
                    </p>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Month</label>
                        <input type="month" x-model="autoFillStartMonth" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                    </div>
                    
                    <div class="mb-4 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg">
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            This will create <strong x-text="missingMonths.length"></strong> reading(s) for:
                            <span x-text="missingMonths.join(', ')"></span>
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-800">
                    <button @click="autoFillModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button @click="executeAutoFill()" class="px-4 py-2 text-sm font-medium text-white bg-yellow-500 rounded-lg hover:bg-yellow-600">
                        Auto-Fill
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('waterStatement', () => ({
        // Unit data
        unitId: {{ $unit->id }},
        unitNumber: '{{ $unit->unit_number }}',
        estateName: '{{ $unit->estate->name ?? 'N/A' }}',
        billingType: '{{ ucfirst($unit->water_billing_type ?? 'consumption') }}',
        billingTypeRaw: '{{ $unit->water_billing_type ?? 'consumption' }}',
        
        // Current readings
        previousReading: {{ (float) ($unit->previous_water_reading ?? 0) }},
        currentReading: {{ (float) ($unit->current_water_reading ?? 0) }},
        currentRate: {{ (float) ($unit->custom_water_rate ?? $unit->estate->water_rate ?? 50) }},
        flatRate: {{ (float) ($unit->water_charge ?? 0) }},
        
        // Initial reading info from PHP
        hasInitialReading: {{ isset($stats['has_initial_reading']) && $stats['has_initial_reading'] ? 'true' : 'false' }},
        initialReadingDate: '{{ $stats['initial_reading']['reading_date'] ?? '' }}',
        initialReadingValue: {{ $stats['initial_reading']['current_reading'] ?? 0 }},
        
        // State
        readings: [],
        loading: false,
        totalConsumption: 0,
        totalCharges: 0,
        averageConsumption: 0,
        totalReadings: 0,
        chart: null,
        missingMonths: [],
        
        // Modal states
        updateModalOpen: false,
        autoFillModalOpen: false,
        editableReadings: [],
        autoFillStartMonth: '',
        
        // Computed properties
        get currentConsumption() {
            if (this.billingTypeRaw === 'flat') return 0;
            return Math.max(0, this.currentReading - this.previousReading);
        },
        
        get currentCharge() {
            if (this.billingTypeRaw === 'flat') {
                return this.flatRate;
            }
            return Math.max(0, this.currentConsumption * this.currentRate);
        },
        
        async init() {
            // Set auto-fill start month to one month before first reading or 6 months ago
            const today = new Date();
            const defaultStart = new Date(today);
            defaultStart.setMonth(defaultStart.getMonth() - 6);
            this.autoFillStartMonth = defaultStart.toISOString().slice(0, 7);
            
            await this.loadReadings();
        },
        
        async loadReadings() {
            this.loading = true;
            try {
                const response = await fetch(`/water/unit-history/${this.unitId}`);
                const data = await response.json();
                
                if (data.success) {
                    this.readings = data.history || [];
                    this.totalConsumption = data.stats.total_consumption || 0;
                    this.totalCharges = data.stats.total_charges || 0;
                    this.averageConsumption = data.stats.average_consumption || 0;
                    this.totalReadings = data.stats.total_readings || 0;
                    
                    // Update unit info from response
                    if (data.unit) {
                        this.unitNumber = data.unit.unit_number;
                        this.estateName = data.unit.estate_name;
                    }
                    
                    // Update initial reading info if available
                    if (data.initial_reading) {
                        this.hasInitialReading = true;
                        this.initialReadingDate = data.initial_reading.reading_date;
                        this.initialReadingValue = data.initial_reading.current_reading;
                    }
                    
                    // Calculate missing months
                    this.calculateMissingMonths();
                    
                    // Create chart after readings are loaded
                    this.$nextTick(() => this.createChart());
                } else {
                    console.error('API returned error:', data);
                }
            } catch (error) {
                console.error('Error loading readings:', error);
                alert('Error loading water readings: ' + error.message);
            } finally {
                this.loading = false;
            }
        },
        
        calculateMissingMonths() {
            if (this.readings.length === 0) {
                this.missingMonths = [];
                return;
            }
            
            // Get all reading months
            const readingMonths = this.readings.map(r => r.reading_date.slice(0, 7));
            
            // Get first and last reading months
            const firstReading = this.readings[this.readings.length - 1];
            const lastReading = this.readings[0];
            
            if (!firstReading || !lastReading) {
                this.missingMonths = [];
                return;
            }
            
            const start = new Date(firstReading.reading_date + '-01');
            const end = new Date(lastReading.reading_date + '-01');
            
            // Include initial reading month if exists
            let startMonth = start;
            if (this.hasInitialReading && this.initialReadingDate) {
                const initialDate = new Date(this.initialReadingDate);
                if (initialDate < start) {
                    startMonth = initialDate;
                }
            }
            
            const missing = [];
            const current = new Date(startMonth);
            
            while (current <= end) {
                const month = current.toISOString().slice(0, 7);
                if (!readingMonths.includes(month)) {
                    missing.push(month);
                }
                current.setMonth(current.getMonth() + 1);
            }
            
            this.missingMonths = missing;
        },
        
        createChart() {
            const canvas = document.getElementById('consumptionChart');
            if (!canvas) return;
            
            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }
            
            // Prepare data (oldest to newest for chart)
            const reversedReadings = [...this.readings].reverse();
            const labels = reversedReadings.map(r => this.formatDate(r.reading_date));
            const consumptionData = reversedReadings.map(r => r.consumption);
            
            const ctx = canvas.getContext('2d');
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Water Consumption (m³)',
                        data: consumptionData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Consumption: ${context.parsed.y.toFixed(2)} m³`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Consumption (m³)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Reading Date'
                            }
                        }
                    }
                }
            });
        },
        
        // ============================================
        // UPDATE MODAL FUNCTIONS
        // ============================================
        openUpdateModal() {
            this.editableReadings = this.readings.map(r => ({
                ...r,
                original_current: r.current_reading,
                original_charge: r.charge
            }));
            this.updateModalOpen = true;
        },
        
        recalculateReading(index) {
            const reading = this.editableReadings[index];
            if (!reading) return;
            
            // Get previous reading (from previous month or 0)
            let prevReading = 0;
            if (index < this.editableReadings.length - 1) {
                // Previous reading is from the next older reading
                prevReading = this.editableReadings[index + 1].current_reading;
            } else {
                // Last (oldest) reading - use the unit's previous reading
                prevReading = this.previousReading || 0;
            }
            
            reading.previous_reading = prevReading;
            
            // Calculate consumption
            const consumption = reading.current_reading - prevReading;
            reading.consumption = Math.max(0, consumption);
            
            // Calculate charge
            if (this.billingTypeRaw === 'flat') {
                reading.charge = this.flatRate;
            } else {
                reading.charge = reading.consumption * this.currentRate;
            }
            
            // Update subsequent readings
            for (let i = index - 1; i >= 0; i--) {
                const nextReading = this.editableReadings[i];
                if (nextReading) {
                    const prevForNext = i < this.editableReadings.length - 1 
                        ? this.editableReadings[i + 1].current_reading 
                        : 0;
                    nextReading.previous_reading = prevForNext;
                    const cons = nextReading.current_reading - prevForNext;
                    nextReading.consumption = Math.max(0, cons);
                    if (this.billingTypeRaw !== 'flat') {
                        nextReading.charge = nextReading.consumption * this.currentRate;
                    }
                }
            }
        },
        
        async saveUpdatedReadings() {
            // Validate all readings
            for (const reading of this.editableReadings) {
                if (reading.current_reading < reading.previous_reading) {
                    alert(`Reading for ${this.formatDate(reading.reading_date)} cannot be less than previous reading.`);
                    return;
                }
            }
            
            // Prepare data for API
            const readingsData = this.editableReadings.map(r => ({
                id: r.id,
                current_reading: r.current_reading,
                reading_date: r.reading_date,
                previous_reading: r.previous_reading,
                consumption: r.consumption,
                charge: r.charge
            }));
            
            // Show confirmation
            if (!confirm('This will update all readings and recalculate charges. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch(`/water/readings/bulk-matrix`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        readings: readingsData.map(r => ({
                            unit_id: this.unitId,
                            current_reading: r.current_reading,
                            reading_date: r.reading_date,
                            existing_reading_id: r.id
                        })),
                        notes: 'Bulk update via statement page'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.updateModalOpen = false;
                    showToast('Readings updated successfully!', 'success');
                    await this.loadReadings();
                    this.$dispatch('update-readings');
                } else {
                    showToast(data.message || 'Error updating readings', 'error');
                }
            } catch (error) {
                console.error('Error saving readings:', error);
                showToast('Error saving readings: ' + error.message, 'error');
            }
        },
        
        // ============================================
        // AUTO-FILL FUNCTIONS
        // ============================================
        openAutoFillModal() {
            this.autoFillModalOpen = true;
        },
        
        async executeAutoFill() {
            if (!this.autoFillStartMonth) {
                alert('Please select a start month.');
                return;
            }
            
            if (!confirm(`This will create readings for ${this.missingMonths.length} missing month(s). Continue?`)) {
                return;
            }
            
            try {
                const endMonth = new Date().toISOString().slice(0, 7);
                const response = await fetch(`/water/unit/${this.unitId}/auto-fill`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        start_month: this.autoFillStartMonth,
                        end_month: endMonth
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.autoFillModalOpen = false;
                    showToast(data.message, 'success');
                    await this.loadReadings();
                    this.$dispatch('update-readings');
                } else {
                    showToast(data.message || 'Error auto-filling', 'error');
                }
            } catch (error) {
                console.error('Error auto-filling:', error);
                showToast('Error auto-filling: ' + error.message, 'error');
            }
        },
        
        autoFillMissingFromModal() {
            this.autoFillModalOpen = true;
            this.updateModalOpen = false;
        },
        
        // ============================================
        // HELPER FUNCTIONS
        // ============================================
        getConsumptionStatus(consumption) {
            if (consumption > 30) return 'high';
            if (consumption > 5) return 'normal';
            return 'low';
        },
        
        getConsumptionClass(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'text-red-600 dark:text-red-400 font-semibold';
            if (status === 'normal') return 'text-yellow-600 dark:text-yellow-400';
            return 'text-green-600 dark:text-green-400';
        },
        
        getStatusBadgeClass(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            if (status === 'normal') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        },
        
        getStatusText(consumption) {
            const status = this.getConsumptionStatus(consumption);
            if (status === 'high') return 'High Usage';
            if (status === 'normal') return 'Normal Usage';
            return 'Low Usage';
        },
        
        formatNumber(value) {
            if (value === undefined || value === null) return '0.00';
            return parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        exportStatement() {
            // Create CSV export
            const headers = ['Reading Date', 'Previous (m³)', 'Current (m³)', 'Consumption (m³)', 'Rate (KES/m³)', 'Charge (KES)', 'Status'];
            const rows = this.readings.map(r => [
                this.formatDate(r.reading_date),
                this.formatNumber(r.previous_reading),
                this.formatNumber(r.current_reading),
                this.formatNumber(r.consumption),
                this.formatNumber(r.rate_applied),
                this.formatNumber(r.charge),
                this.getStatusText(r.consumption)
            ]);
            
            // Add summary rows
            rows.push(['', '', '', 'TOTAL:', '', this.formatNumber(this.totalCharges), '']);
            rows.push(['', '', '', 'AVERAGE:', '', this.formatNumber(this.averageConsumption), '']);
            
            const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `water-statement-${this.unitNumber}-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        },
        
        recordReading() {
            if (window.openCreateReadingModal) {
                window.openCreateReadingModal(this.unitId);
            }
        }
    }));
});

// Toast notification helper
function showToast(message, type = 'success') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification fixed bottom-4 right-4 z-[99999] px-4 py-2 rounded-lg shadow-lg text-white flex items-center gap-2 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    toast.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            ${type === 'success' 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>'}
        </svg>
        ${message}
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<style>
[x-cloak] { display: none !important; }
</style>
@endsection