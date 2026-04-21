@extends('layouts.app')

@section('content')
<!-- Include modal partials -->
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')

<div class="container mx-auto px-4 py-6" x-data="estateShow()" x-init="init()">
    <!-- Estate Info Card -->
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 pb-6 pt-6 dark:border-gray-800 dark:bg-white/[0.03] mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90 mb-2">{{ $estate->name }}</h1>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-gray-600 dark:text-gray-400">{{ $estate->location ?: 'No location specified' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-gray-600 dark:text-gray-400">{{ $totalUnits }} Units</span>
                    </div>
                </div>
            </div>
            
            <!-- Edit Estate Button -->
            <button 
                @click="openEditModal()"
                class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                </svg>
                Edit Estate
            </button>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Units</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            {{ $totalUnits }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Occupied Units</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            {{ $occupiedCount }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Vacant Units</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            {{ $vacantCount }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Monthly Rent Potential</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                            KES {{ number_format($monthlyRentPotential, 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Utility Charges Info Card -->
        <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-3">Default Utility Charges for this Estate</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Water Rate:</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">KES {{ number_format($estate->water_rate ?? 0, 2) }} / unit</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Service Charge:</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">KES {{ number_format($estate->service_charge ?? 0, 2) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Garbage Charge:</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">KES {{ number_format($estate->garbage_charge ?? 0, 2) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Security Charge:</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">KES {{ number_format($estate->security_charge ?? 0, 2) }}</span>
                </div>
            </div>
            @php
                $totalMonthlyPotential = ($estate->water_rate ?? 0) + ($estate->service_charge ?? 0) + ($estate->garbage_charge ?? 0) + ($estate->security_charge ?? 0);
            @endphp
            <div class="mt-3 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Monthly Charges per Unit (excluding rent):</span>
                    <span class="text-lg font-bold text-blue-600 dark:text-blue-400">KES {{ number_format($totalMonthlyPotential, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Units Table Component -->
    @include('partials.table.table-units', [
        'unitsData' => $unitsData,
        'estate' => $estate,
        'totalUnits' => $totalUnits,
        'occupiedCount' => $occupiedCount,
        'vacantCount' => $vacantCount
    ])
</div>

<!-- Edit Estate Slideover Modal -->
<div x-data="editEstateModal()" x-init="init()">
    <!-- Backdrop -->
    <template x-if="isOpen">
        <div 
            @click="closeModal()"
            class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
    </template>

    <!-- Modal Content - Slides from Right -->
    <div x-show="isOpen" 
         x-transition:enter="transition transform ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         x-cloak
         class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white dark:bg-gray-900 shadow-2xl z-99999 overflow-y-auto">
        <div class="p-6 lg:p-8">
            <!-- close btn -->
            <button
                @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11"
            >
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <form @submit.prevent="submitForm()">
                <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">
                    Edit Estate
                </h4>

                <!-- Form Errors -->
                <template x-if="formErrors.length > 0">
                    <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc pl-5">
                            <template x-for="error in formErrors" :key="error">
                                <li x-text="error"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5">
                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Estate Name *
                        </label>
                        <input
                            type="text"
                            x-model="form.name"
                            placeholder="Enter estate name"
                            required
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Location
                        </label>
                        <input
                            type="text"
                            x-model="form.location"
                            placeholder="Enter estate location"
                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                        />
                    </div>

                    <!-- Utility Charges Section -->
                    <div class="col-span-1 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                        <h5 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-4">Default Utility Charges</h5>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">These charges will be applied to all new units in this estate by default</p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Water Rate (per unit)
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.water_rate"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cost per water unit consumed (e.g., 150.00 per unit)</p>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Service Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.service_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Garbage Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.garbage_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>

                    <div class="col-span-1">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Default Security Charge
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 dark:text-gray-400">KES</span>
                            </div>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                x-model="form.security_charge"
                                placeholder="0.00"
                                class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pl-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button
                        @click="closeModal()"
                        type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex justify-center w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto"
                    >
                        <span x-show="!loading">Update Estate</span>
                        <span x-show="loading">Updating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function estateShow() {
    return {
        estateId: {{ $estate->id }},
        
        init() {
            console.log('Estate show page loaded');
        },
        
        openEditModal() {
            if (window.editEstateModal) {
                const estateData = {
                    id: {{ $estate->id }},
                    name: '{{ addslashes($estate->name) }}',
                    location: '{{ addslashes($estate->location) }}',
                    water_rate: {{ $estate->water_rate ?? 0 }},
                    service_charge: {{ $estate->service_charge ?? 0 }},
                    garbage_charge: {{ $estate->garbage_charge ?? 0 }},
                    security_charge: {{ $estate->security_charge ?? 0 }}
                };
                window.editEstateModal.openModal(estateData);
            }
        }
    };
}

function editEstateModal() {
    return {
        isOpen: false,
        estate: null,
        form: {
            name: '',
            location: '',
            water_rate: '',
            service_charge: '',
            garbage_charge: '',
            security_charge: ''
        },
        formErrors: [],
        loading: false,
        
        init() {
            window.editEstateModal = this;
        },
        
        openModal(estate) {
            this.estate = estate;
            this.form = {
                name: estate.name,
                location: estate.location || '',
                water_rate: estate.water_rate || '',
                service_charge: estate.service_charge || '',
                garbage_charge: estate.garbage_charge || '',
                security_charge: estate.security_charge || ''
            };
            this.formErrors = [];
            this.loading = false;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.isOpen = false;
            this.estate = null;
            this.formErrors = [];
            this.loading = false;
            document.body.style.overflow = '';
        },
        
        async submitForm() {
            this.loading = true;
            this.formErrors = [];
            
            try {
                const response = await fetch(`/estates/${this.estate.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.closeModal();
                    if (window.successModal) {
                        window.successModal.simple('Estate Updated', `Estate "${this.form.name}" has been updated successfully!`);
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat();
                        if (window.errorModal) {
                            window.errorModal.show('Update Failed', 'Please correct the following errors:', errorMessages);
                        } else {
                            this.formErrors = errorMessages;
                        }
                    } else {
                        if (window.errorModal) {
                            window.errorModal.show('Update Failed', data.message || 'Failed to update estate');
                        } else {
                            this.formErrors = [data.message || 'Failed to update estate'];
                        }
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                if (window.errorModal) {
                    window.errorModal.show('Error', 'An unexpected error occurred. Please try again.');
                } else {
                    this.formErrors = ['An unexpected error occurred. Please try again.'];
                }
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.z-99999 {
    z-index: 99999;
}
</style>
@endsection