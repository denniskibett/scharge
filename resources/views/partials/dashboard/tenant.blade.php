{{-- resources/views/partials/dashboard/tenant.blade.php --}}
@extends('layouts.app')

@section('title', 'Tenant Dashboard')

@section('content')
<div x-data="tenantDashboard()" x-init="init()">
    <div class="container-fluid px-4 py-4">
        
        <!-- Welcome Card -->
        <div class="row mb-6">
            <div class="col-12">
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 p-6 shadow-lg">
                    <div class="absolute inset-0 opacity-10">
                        <svg class="absolute -right-20 -top-20 h-64 w-64 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    
                    <div class="relative">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                        <img src="{{ Auth::user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->name) . '&background=ffffff&color=0D9488' }}" 
                                             alt="avatar" class="h-14 w-14 rounded-full">
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-white">Welcome back, {{ Auth::user()->first_name ?: Auth::user()->name }}!</h2>
                                        <p class="text-emerald-100 mt-1" x-text="currentDate"></p>
                                        <div class="mt-2 flex items-center gap-2 text-emerald-100 text-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            <span>Property: <strong>{{ $roleData['activeTenancy']['unit_number'] ?? 'N/A' }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <div class="text-right">
                                    <p class="text-sm text-emerald-100">Your Role</p>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm">
                                        Tenant
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6 mb-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Outstanding Balance</span>
                        <h4 class="mt-2 text-title-sm font-bold text-red-600 dark:text-red-400">
                            KES {{ number_format($outstandingBalance ?? 0, 2) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 dark:bg-red-500/15">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Paid</span>
                        <h4 class="mt-2 text-title-sm font-bold text-green-600 dark:text-green-400">
                            KES {{ number_format($totalPaid ?? 0, 2) }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Invoices</span>
                        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                            {{ collect($roleData['invoices'] ?? [])->count() }}
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Water Consumption</span>
                        <h4 class="mt-2 text-title-sm font-bold text-cyan-600 dark:text-cyan-400">
                            {{ number_format($roleData['waterInfo']['consumption'] ?? 0, 2) }} m³
                        </h4>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.card.card-dashboard', ['cardData' => array_merge($stats, ['user_role' => 'tenant'])])

        <!-- Wallet Summary Card - Add this -->
        <div class="mb-6">
            @include('partials.card.card-wallet-summary', ['walletData' => $walletData])

        </div>

        <!-- Tabs -->
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeTab = 'invoices'" :class="activeTab === 'invoices' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            My Invoices ({{ collect($roleData['invoices'] ?? [])->count() }})
                        </button>
                        <button @click="activeTab = 'payments'" :class="activeTab === 'payments' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Payment History ({{ collect($roleData['payments'] ?? [])->count() }})
                        </button>
                        <button @click="activeTab = 'maintenance'" :class="activeTab === 'maintenance' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Maintenance ({{ collect($roleData['maintenanceRequests'] ?? [])->count() }})
                        </button>
                        <button @click="activeTab = 'water'" :class="activeTab === 'water' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Water Usage
                        </button>
                        <button @click="activeTab = 'access'" :class="activeTab === 'access' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Access Logs ({{ collect($roleData['accessLogs'] ?? [])->count() }})
                        </button>
                    </div>
                </div>
                
                <div class="p-5">
                    <!-- Invoices Tab -->
                    <div x-show="activeTab === 'invoices'">
                        @include('partials.table.table-invoices', [
                            'mappedInvoices' => collect($roleData['invoices'] ?? []),
                            'mappedActiveTenancies' => collect()
                        ])
                    </div>
                    
                    <!-- Payments Tab -->
                    <div x-show="activeTab === 'payments'">
                        @include('partials.table.table-payments', [
                            'payments' => $roleData['payments'] ?? [], 
                            'showActions' => false, 
                            'showTenant' => false
                        ])
                    </div>
                    
                    <!-- Maintenance Tab -->
                    <div x-show="activeTab === 'maintenance'">
                        @include('partials.table.table-maintenance', [
                            'requests' => $roleData['maintenanceRequests'] ?? []
                        ])
                    </div>
                    
                    <!-- Water Usage Tab -->
                    <div x-show="activeTab === 'water'">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Current Water Reading</h3>
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center pb-2 border-b border-gray-200 dark:border-gray-700">
                                        <span class="text-gray-600 dark:text-gray-400">Previous Reading</span>
                                        <span class="font-medium text-gray-800 dark:text-white/90">{{ number_format($roleData['waterInfo']['previous_reading'] ?? 0, 2) }} m³</span>
                                    </div>
                                    <div class="flex justify-between items-center pb-2 border-b border-gray-200 dark:border-gray-700">
                                        <span class="text-gray-600 dark:text-gray-400">Current Reading</span>
                                        <span class="font-medium text-gray-800 dark:text-white/90">{{ number_format($roleData['waterInfo']['current_reading'] ?? 0, 2) }} m³</span>
                                    </div>
                                    <div class="flex justify-between items-center pb-2 border-b border-gray-200 dark:border-gray-700">
                                        <span class="text-gray-600 dark:text-gray-400">Consumption</span>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">{{ number_format($roleData['waterInfo']['consumption'] ?? 0, 2) }} m³</span>
                                    </div>
                                    <div class="flex justify-between items-center pb-2 border-b border-gray-200 dark:border-gray-700">
                                        <span class="text-gray-600 dark:text-gray-400">Rate</span>
                                        <span class="font-medium text-gray-800 dark:text-white/90">KES {{ number_format($roleData['waterInfo']['rate'] ?? 0, 2) }} / m³</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600 dark:text-gray-400">Billing Type</span>
                                        <span class="inline-flex px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                            {{ ucfirst($roleData['waterInfo']['billing_type'] ?? 'consumption') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">Water Charge Calculation</h3>
                                @if(($roleData['waterInfo']['billing_type'] ?? 'consumption') === 'flat')
                                    <div class="text-center py-8">
                                        <p class="text-gray-600 dark:text-gray-400 mb-2">Flat Rate Billing</p>
                                        <p class="text-2xl font-bold text-brand-600">KES {{ number_format($roleData['waterInfo']['rate'] ?? 0, 2) }}</p>
                                        <p class="text-sm text-gray-500 mt-2">Fixed monthly water charge</p>
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <p class="text-gray-600 dark:text-gray-400 mb-2">Estimated Water Charge</p>
                                        <p class="text-2xl font-bold text-brand-600">KES {{ number_format(($roleData['waterInfo']['consumption'] ?? 0) * ($roleData['waterInfo']['rate'] ?? 0), 2) }}</p>
                                        <p class="text-sm text-gray-500 mt-2">{{ number_format($roleData['waterInfo']['consumption'] ?? 0, 2) }} m³ × KES {{ number_format($roleData['waterInfo']['rate'] ?? 0, 2) }}</p>
                                    </div>
                                @endif
                                @php
                                    $lastReadingDate = $roleData['waterInfo']['last_reading_date'] ?? null;
                                @endphp
                                <p class="text-xs text-gray-500 text-center mt-4">
                                    Last reading date: 
                                    @if($lastReadingDate)
                                        {{ \Carbon\Carbon::parse($lastReadingDate)->format('M d, Y') }}
                                    @else
                                        Not available
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Access Logs Tab -->
                    <div x-show="activeTab === 'access'">
                        @include('partials.table.table-security', [
                            'logs' => $roleData['accessLogs'] ?? [],
                            'showActions' => false
                        ])
                    </div>
                </div>
            </div>
        </div>

        <!-- New Maintenance Request Button (Floating) -->
        <div class="fixed bottom-6 right-6 z-50">
            <button @click="openMaintenanceModal()" class="bg-brand-500 hover:bg-brand-600 text-white rounded-full p-4 shadow-lg transition-all hover:scale-105">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
function tenantDashboard() {
    return {
        activeTab: 'invoices',
        currentDate: new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        
        init() {
            console.log('Tenant Dashboard loaded');
            console.log('Access Logs count:', {{ collect($roleData['accessLogs'] ?? [])->count() }});
        },
        
        openMaintenanceModal() {
            if (window.maintenanceModal) {
                window.maintenanceModal.openModal();
            } else {
                alert('Please refresh the page to submit a maintenance request.');
            }
        }
    };
}
</script>
@endsection

<!-- Include Maintenance Create Modal for Tenants -->
@include('partials.modal.maintenance-create-modal', [
    'units' => $units ?? [],
    'estates' => $estates ?? [],
    'currentUnit' => $currentUnit ?? null
])