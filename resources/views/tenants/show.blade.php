@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tenant Details</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">View and manage tenant information, invoices, and payments</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tenants.edit', $tenant) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Tenant
                </a>
                <a href="{{ route('tenants.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Tenants
                </a>
            </div>
        </div>
    </div>

    <!-- Main Tenant Card with Full Width -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-6">
            <!-- Tenant Header -->
            <div class="flex items-start justify-between mb-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center">
                        <span class="text-purple-600 font-medium text-xl">
                            {{ strtoupper(substr($tenant->user->name ?? 'T', 0, 1)) }}
                        </span>
                    </div>
                    
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $tenant->user->name ?? 'N/A' }}</h2>
                        <div class="flex flex-wrap gap-4 mt-2 text-sm text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span>{{ $tenant->user->email ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span>{{ $tenant->user->phone ?? 'N/A' }}</span>
                            </div>
                            @if($tenant->id_number)
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                                <span>ID: {{ $tenant->id_number }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Status Badge -->
                @if($activeTenancy)
                <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                    <svg class="mr-1 h-2 w-2" fill="currentColor" viewBox="0 0 8 8">
                        <circle cx="4" cy="4" r="3" />
                    </svg>
                    Active Tenant
                </span>
                @else
                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    <svg class="mr-1 h-2 w-2" fill="currentColor" viewBox="0 0 8 8">
                        <circle cx="4" cy="4" r="3" />
                    </svg>
                    Without Unit
                </span>
                @endif
            </div>

            <!-- Current Tenancy Details -->
            @if($activeTenancy)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Unit</p>
                            <a href="{{ route('units.show', $activeTenancy->unit_id) }}" 
                               class="text-lg font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                {{ $activeTenancy->unit->unit_number ?? 'N/A' }}
                            </a>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $activeTenancy->unit->estate->name ?? '' }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Move-in Date</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $activeTenancy->move_in_date_formatted }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($activeTenancy->move_in_date)->diffForHumans(now(), true) }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-purple-100 dark:bg-purple-800 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Paid</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">Ksh {{ number_format($totalPaid, 2) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Lifetime payments</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Balance</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">Ksh {{ number_format($totalBalance, 2) }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Pending payments</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Tenancy Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tenancies</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalTenancies }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Tenancies</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $activeTenancies }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Past Tenancies</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $pastTenancies }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Estates Lived In</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $estates->count() }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                @if($activeTenancy)
                <form action="{{ route('tenancies.create', $activeTenancy) }}" method="POST" onsubmit="return confirm('Are you sure you want to end this tenancy?')">
                    @csrf
                    @method('PUT')
                    <button type="submit" 
                            class="inline-flex items-center gap-2 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-2.5 text-sm font-medium text-yellow-800 shadow-theme-xs transition hover:bg-yellow-100 dark:border-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-300 dark:hover:bg-yellow-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        End Tenancy
                    </button>
                </form>
                @else
                <a href="{{ route('tenancies.create', ['tenant_id' => $tenant->id]) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Assign New Unit
                </a>
                @endif
                
                <button onclick="document.getElementById('create-invoice-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Create Invoice
                </button>
                
                @if($activeTenancy)
                <button onclick="document.getElementById('make-payment-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Make Payment
                </button>
                @endif
                
                <form action="{{ route('tenants.destroy', $tenant) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this tenant? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-800 shadow-theme-xs transition hover:bg-red-100 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Tenant
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Service Charge & Utilities Cards -->
    @if($activeTenancy)
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <!-- Tenancy Period -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tenancy Period</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ \Carbon\Carbon::parse($activeTenancy->move_in_date)->diffForHumans(now(), true) }}
                    </p>
                </div>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Started {{ $activeTenancy->move_in_date_formatted }}
            </div>
        </div>
        
        <!-- Power -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Power</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Ksh 1,500</p>
                </div>
            </div>
            <div class="text-xs text-green-500 dark:text-green-400">
                <span class="inline-flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Paid
                </span>
            </div>
        </div>
        
        <!-- Water -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Water</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Ksh 800</p>
                </div>
            </div>
            <div class="text-xs text-red-500 dark:text-red-400">
                <span class="inline-flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Pending
                </span>
            </div>
        </div>
        
        <!-- Security -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Security</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Ksh 1,200</p>
                </div>
            </div>
            <div class="text-xs text-green-500 dark:text-green-400">
                <span class="inline-flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Paid
                </span>
            </div>
        </div>
        
        <!-- Garbage -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Garbage</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Ksh 500</p>
                </div>
            </div>
            <div class="text-xs text-yellow-500 dark:text-yellow-400">
                <span class="inline-flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    Partial
                </span>
            </div>
        </div>
    </div>
    @endif

    <!-- Tabs for Invoices, Occupied Units, etc. -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <!-- Tabs Header -->
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex -mb-px">
                <button @click="activeTab = 'invoices'" :class="activeTab === 'invoices' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex-1 py-4 px-6 text-center border-b-2 font-medium text-sm">
                    Invoices & Payments
                </button>
                <button @click="activeTab = 'units'" :class="activeTab === 'units' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex-1 py-4 px-6 text-center border-b-2 font-medium text-sm">
                    Occupied Units
                </button>
                <button @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="flex-1 py-4 px-6 text-center border-b-2 font-medium text-sm">
                    Tenant Details
                </button>
            </nav>
        </div>

        <!-- Tabs Content -->
        <div x-data="{ activeTab: 'invoices' }" class="p-6">
            <!-- Invoices Tab -->
            <div x-show="activeTab === 'invoices'" x-cloak>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Invoice & Payment History</h3>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Total Balance: <span class="font-bold text-red-600 dark:text-red-400">Ksh {{ number_format($totalBalance, 2) }}</span>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Month</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($invoices as $invoice)
                            @php
                                $totalPaidOnInvoice = $invoice->payments->sum('amount');
                                $balance = $invoice->total_amount - $totalPaidOnInvoice;
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    #{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    Ksh {{ number_format($invoice->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    Ksh {{ number_format($totalPaidOnInvoice, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    Ksh {{ number_format($balance, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 
                                           ($invoice->status === 'partial' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : 
                                           'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300') }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <button onclick="showInvoiceDetails({{ $invoice->id }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No invoices found for this tenant.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Payments Table (Collapsible under each invoice) -->
                <div class="mt-6">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Payments</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaction ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment Month</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Message</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($payments as $payment)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($payment->payment_datetime)->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600 dark:text-green-400">
                                        Ksh {{ number_format($payment->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($payment->payment_method) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $payment->transaction_id ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $payment->payment_month }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                        {{ $payment->transaction_message ?? 'N/A' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No payments found for this tenant.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Occupied Units Tab -->
            <div x-show="activeTab === 'units'" x-cloak>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Occupied Units History</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-in Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-out Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($tenant->tenancies as $tenancy)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                            <span class="text-blue-600 font-medium">U</span>
                                        </div>
                                        <div>
                                            <a href="{{ route('units.show', $tenancy->unit_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                                {{ $tenancy->unit->unit_number ?? 'N/A' }}
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $tenancy->unit->estate->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($tenancy->move_in_date)->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date)->format('M d, Y') : 'Current' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($tenancy->move_out_date)
                                    {{ \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(\Carbon\Carbon::parse($tenancy->move_out_date), true) }}
                                    @else
                                    {{ \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(now(), true) }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $tenancy->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 
                                           'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ ucfirst($tenancy->status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No occupied units found for this tenant.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tenant Details Tab -->
            <div x-show="activeTab === 'details'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Personal Information</h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</p>
                                <p class="text-lg text-gray-900 dark:text-white">{{ $tenant->user->name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Email Address</p>
                                <p class="text-lg text-gray-900 dark:text-white">{{ $tenant->user->email ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</p>
                                <p class="text-lg text-gray-900 dark:text-white">{{ $tenant->user->phone ?? 'N/A' }}</p>
                            </div>
                            @if($tenant->user->phone2)
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Secondary Phone</p>
                                <p class="text-lg text-gray-900 dark:text-white">{{ $tenant->user->phone2 }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Additional Information</h4>
                        <div class="space-y-4">
                            @if($tenant->id_number)
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">ID Number</p>
                                <p class="text-lg text-gray-900 dark:text-white">{{ $tenant->id_number }}</p>
                            </div>
                            @endif
                            
                            @if($tenant->emergency_contact)
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Emergency Contact</p>
                                <p class="text-lg text-gray-900 dark:text-white">{{ $tenant->emergency_contact }}</p>
                            </div>
                            @endif
                            
                            @if($tenant->notes)
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</p>
                                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $tenant->notes }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Invoice Modal -->
<div id="create-invoice-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="document.getElementById('create-invoice-modal').classList.add('hidden')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
            <form action="{{ route('tenants.store.invoice', $tenant) }}" method="POST">
                @csrf
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Create New Invoice</h3>
                            <div class="mt-2">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Billing Month</label>
                                        <input type="month" name="billing_month" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ now()->format('Y-m') }}">
                                    </div>
                                    
                                    <div id="invoice-items">
                                        <div class="invoice-item grid grid-cols-3 gap-2 mb-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                                <input type="text" name="items[0][description]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g., Rent">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                                                <input type="number" name="items[0][amount]" required step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                                                <select name="items[0][item_type]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                    <option value="rent">Rent</option>
                                                    <option value="power">Power</option>
                                                    <option value="water">Water</option>
                                                    <option value="security">Security</option>
                                                    <option value="garbage">Garbage</option>
                                                    <option value="internet">Internet</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" onclick="addInvoiceItem()" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        + Add another item
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Create Invoice
                    </button>
                    <button type="button" onclick="document.getElementById('create-invoice-modal').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Make Payment Modal -->
<div id="make-payment-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="document.getElementById('make-payment-modal').classList.add('hidden')"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800">
            <form action="{{ route('tenants.store.payment', $tenant) }}" method="POST">
                @csrf
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Record Payment</h3>
                            <div class="mt-2">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Invoice</label>
                                        <select name="invoice_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select Invoice</option>
                                            @foreach($invoices->where('status', '!=', 'paid') as $invoice)
                                            <option value="{{ $invoice->id }}">
                                                Invoice #{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::parse($invoice->billing_month)->format('M Y') }} - Ksh {{ number_format($invoice->total_amount, 2) }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                                        <input type="number" name="amount" required step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                                        <select name="payment_method" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="mpesa">M-Pesa</option>
                                            <option value="bank">Bank Transfer</option>
                                            <option value="cash">Cash</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Transaction ID</label>
                                        <input type="text" name="transaction_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Optional">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Transaction Message</label>
                                        <textarea name="transaction_message" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Copy and paste M-Pesa message here"></textarea>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paid To</label>
                                            <input type="text" name="paid_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Receiver name">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payer Name</label>
                                            <input type="text" name="payer_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Sender name">
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Date & Time</label>
                                            <input type="datetime-local" name="payment_datetime" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ now()->format('Y-m-d\TH:i') }}">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Month</label>
                                            <input type="month" name="payment_month" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ now()->format('Y-m') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Record Payment
                    </button>
                    <button type="button" onclick="document.getElementById('make-payment-modal').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let invoiceItemCount = 1;

function addInvoiceItem() {
    const container = document.getElementById('invoice-items');
    const newItem = document.createElement('div');
    newItem.className = 'invoice-item grid grid-cols-3 gap-2 mb-2';
    newItem.innerHTML = `
        <div>
            <input type="text" name="items[${invoiceItemCount}][description]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Description">
        </div>
        <div>
            <input type="number" name="items[${invoiceItemCount}][amount]" required step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Amount">
        </div>
        <div class="flex items-center gap-2">
            <select name="items[${invoiceItemCount}][item_type]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="rent">Rent</option>
                <option value="power">Power</option>
                <option value="water">Water</option>
                <option value="security">Security</option>
                <option value="garbage">Garbage</option>
                <option value="internet">Internet</option>
                <option value="other">Other</option>
            </select>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-red-600 hover:text-red-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>
    `;
    container.appendChild(newItem);
    invoiceItemCount++;
}

function showInvoiceDetails(invoiceId) {
    // Implement invoice details modal or redirect to invoice page
    window.location.href = `/invoices/${invoiceId}`;
}
</script>
@endsection