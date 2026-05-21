@extends('layouts.app')

@section('title', Auth::user()->dashboard_title)

@section('content')
<div x-data="dashboard()" x-init="init()" x-cloak>
    <div class="container-fluid px-4 py-4">

        <!-- Welcome Card -->
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
                            <div class="flex items-center gap-3">
                                <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white">Welcome back, <span x-text="userName"></span>!</h2>
                                    <p class="text-brand-100 mt-1" x-text="currentDate"></p>
                                    <p class="text-brand-50 text-sm mt-2" x-text="welcomeMessage"></p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm text-brand-100">Your Role</p>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm" x-text="userRole"></span>
                                </div>
                                <img src="{{ Auth::user()->avatar_url }}" alt="avatar" class="h-14 w-14 rounded-full border-2 border-white shadow-lg">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="mt-6 mb-6">
            @include('partials.card.card-dashboard', [
                'stats' => $stats ?? [],
                'outstandingBalance' => $outstandingBalance ?? 0,
                'totalPaid' => $totalPaid ?? 0
            ])
        </div>

        <!-- Role-Based Dashboard Content with Tab Cards -->
        
        <!-- ADMIN / SUPER ADMIN DASHBOARD -->
        @auth
        @if(auth()->user()->hasAnyRole(['super_admin', 'admin']))
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeAdminTab = 'invoices'" :class="activeAdminTab === 'invoices' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Recent Invoices
                        </button>
                        <button @click="activeAdminTab = 'payments'" :class="activeAdminTab === 'payments' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                            </svg>
                            Recent Payments
                        </button>
                        <button @click="activeAdminTab = 'readings'" :class="activeAdminTab === 'readings' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Water Readings
                        </button>
                    </div>
                </div>
                
                <!-- Tab Content -->
                <div class="p-5">
                    <div x-show="activeAdminTab === 'invoices'">
                        @include('partials.table.table-invoices', [
                            'mappedInvoices' => collect($roleData['recentInvoices'] ?? []),
                            'mappedActiveTenancies' => $mappedActiveTenancies ?? collect()
                        ])
                    </div>
                    <div x-show="activeAdminTab === 'payments'">
                        @include('partials.table.table-payments', [
                            'payments' => $roleData['recentPayments'] ?? [], 
                            'showActions' => true, 
                            'showTenant' => true
                        ])
                    </div>
                    <div x-show="activeAdminTab === 'readings'">
                        @include('partials.table.table-readings', [
                            'readings' => $roleData['recentReadings'] ?? [], 
                            'showActions' => true, 
                            'showConsumption' => true
                        ])
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth

        <!-- PROPERTY MANAGER DASHBOARD -->
        @auth
        @if(auth()->user()->hasRole('property_manager'))
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activePMTab = 'readings'" :class="activePMTab === 'readings' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Water Readings
                        </button>
                        <button @click="activePMTab = 'vacant'" :class="activePMTab === 'vacant' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Long Term Vacant
                        </button>
                        <button @click="activePMTab = 'maintenance'" :class="activePMTab === 'maintenance' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Maintenance
                        </button>
                        
                    </div>
                </div>
                <div class="p-5">
                    <div x-show="activePMTab === 'readings'">
                        @include('partials.table.table-readings', [
                            'readings' => $roleData['recentReadings'] ?? [], 
                            'showActions' => true, 
                            'showConsumption' => true
                        ])
                    </div>
                    <div x-show="activePMTab === 'vacant'">
                        @include('partials.table.table-units', [
                            'units' => $roleData['longTermVacant'] ?? []
                        ])
                    </div>
                    <div x-show="activePMTab === 'maintenance'">
                        @include('partials.table.table-maintenance', [
                            'requests' => $roleData['maintenanceRequests'] ?? []
                        ])
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth

        <!-- ACCOUNTANT DASHBOARD -->
        @auth
        @if(auth()->user()->hasRole('accountant'))
        <div class="mt-6 text-gray-800 dark:text-white">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeAccTab = 'financials'" :class="activeAccTab === 'financials' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Tenancy Financials
                        </button>
                        <button @click="activeAccTab = 'overdue'" :class="activeAccTab === 'overdue' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Overdue Invoices
                        </button>
                        <button @click="activeAccTab = 'transactions'" :class="activeAccTab === 'transactions' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Recent Transactions
                        </button>
                    </div>
                </div>
                <div class="p-5">
                    <!-- Tenancy Financials Tab -->
                    <div x-show="activeAccTab === 'financials'">
                        <div class="overflow-x-auto">
                            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unit</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Month</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Water Bill (KES)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service Charge (KES)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Due (KES)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    @forelse($roleData['tenancyFinancials'] ?? [] as $financial)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $financial->unit_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $financial->tenant_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $financial->phone_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $financial->reading_month }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($financial->water_bill, 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($financial->service_charge, 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($financial->total_due, 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <button @click="generateInvoiceForTenancy({{ $financial->tenancy_id }})" 
                                                    :disabled="generatingTenancy === {{ $financial->tenancy_id }}"
                                                    class="text-brand-600 hover:text-brand-900 dark:text-brand-400">
                                                <span x-show="generatingTenancy !== {{ $financial->tenancy_id }}">Generate Invoice</span>
                                                <span x-show="generatingTenancy === {{ $financial->tenancy_id }}">Generating...</span>
                                            </button>
                                            @if($financial->invoice_id)
                                                <a href="{{ route('invoices.show', $financial->invoice_id) }}" class="ml-2 text-gray-600 hover:text-gray-900 dark:text-gray-400">View</a>
                                            @else
                                                <span class="ml-2 text-gray-400 italic text-xs">No invoice</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">No active tenancies found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination links (only once) -->
                        @if(method_exists($roleData['tenancyFinancials'] ?? [], 'links'))
                            <div class="mt-4">
                                {{ $roleData['tenancyFinancials']->links() }}
                            </div>
                        @endif
                    </div>

                    <!-- Overdue Invoices Tab -->
                    <div x-show="activeAccTab === 'overdue'">
                        @include('partials.table.table-invoices', [
                            'mappedInvoices' => collect($roleData['overdueInvoices'] ?? []),
                            'mappedActiveTenancies' => $mappedActiveTenancies ?? collect()
                        ])
                    </div>

                    <!-- Recent Transactions Tab -->
                    <div x-show="activeAccTab === 'transactions'">
                        @include('partials.table.table-payments', [
                            'payments' => $roleData['recentTransactions'] ?? [], 
                            'showActions' => true, 
                            'showTenant' => true
                        ])
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth

        <!-- TENANT DASHBOARD -->
        @auth
        @if(auth()->user()->hasRole('tenant'))
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeTenantTab = 'invoices'" :class="activeTenantTab === 'invoices' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            My Invoices
                        </button>
                        <button @click="activeTenantTab = 'payments'" :class="activeTenantTab === 'payments' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Payment History
                        </button>
                        <button @click="activeTenantTab = 'maintenance'" :class="activeTenantTab === 'maintenance' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Maintenance
                        </button>
                        <button @click="activeTenantTab = 'security'" :class="activeTenantTab === 'security' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Access Logs
                        </button>
                    </div>
                </div>
                <div class="p-5">
                    <div x-show="activeTenantTab === 'invoices'">
                        @include('partials.table.table-invoices', [
                            'mappedInvoices' => collect($roleData['invoices'] ?? []),
                            'mappedActiveTenancies' => $mappedActiveTenancies ?? collect()
                        ])
                    </div>
                    <div x-show="activeTenantTab === 'payments'">
                        @include('partials.table.table-payments', [
                            'payments' => $roleData['payments'] ?? [], 
                            'showActions' => false, 
                            'showTenant' => false
                        ])
                    </div>
                    <div x-show="activeTenantTab === 'maintenance'">
                        @include('partials.table.table-maintenance', [
                            'requests' => $roleData['maintenanceRequests'] ?? []
                        ])
                    </div>
                    <div x-show="activeTenantTab === 'security'">
                        @include('partials.table.table-security', [
                            'logs' => $roleData['accessLogs'] ?? []
                        ])
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth

        <!-- METER READER DASHBOARD -->
@auth
@if(auth()->user()->hasRole('meter_reader'))

<!-- Month Selector -->
<div class="mb-4 flex items-center gap-3">
    <label for="monthSelect" class="text-sm font-medium text-gray-700 dark:text-gray-300">Select Month:</label>
    <input type="month" id="monthSelect" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" 
           value="{{ $roleData['selectedMonth'] ?? now()->format('Y-m') }}" 
           onchange="window.location.href = '{{ route('dashboard') }}?month=' + this.value">
</div>

<!-- DEBUG SECTION - Remove after fixing -->
<div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 rounded">
    <h3 class="font-bold">Debug: Meter Reader Data</h3>
    <p>Units Needing Reading Count: {{ $roleData['unitsNeedingReading']->count() }}</p>
    <p>Reading History Count: {{ $roleData['readingHistory']->count() }}</p>
    <details>
        <summary>First Pending Reading (Raw)</summary>
        <pre class="text-xs">{{ json_encode($roleData['unitsNeedingReading']->first(), JSON_PRETTY_PRINT) }}</pre>
    </details>
    <details>
        <summary>First History Reading (Raw)</summary>
        <pre class="text-xs">{{ json_encode($roleData['readingHistory']->first(), JSON_PRETTY_PRINT) }}</pre>
    </details>
</div>

<div class="mt-6">
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
            <div class="flex flex-wrap gap-2">
                <button 
                    @click="activeMeterTab = 'pending'" 
                    :class="activeMeterTab === 'pending' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                    class="px-4 py-2 text-sm font-medium transition-colors">
                    Pending Readings ({{ $roleData['unitsNeedingReading']->count() ?? 0 }})
                </button>
                <button 
                    @click="activeMeterTab = 'history'" 
                    :class="activeMeterTab === 'history' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" 
                    class="px-4 py-2 text-sm font-medium transition-colors">
                    Reading History ({{ $roleData['readingHistory']->count() ?? 0 }})
                </button>
            </div>
        </div>
        <div class="p-5">
            <!-- PENDING READINGS TAB -->
            <div x-show="activeMeterTab === 'pending'" x-cloak>
                @if(($roleData['unitsNeedingReading'] ?? collect())->count() > 0)
                    @include('partials.table.table-readings', [
                        'readings' => $roleData['unitsNeedingReading'] ?? [], 
                        'showActions' => true, 
                        'showConsumption' => true,
                        'units' => $roleData['units'] ?? []
                    ])
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No units need reading for the selected month.
                    </div>
                @endif
            </div>
            
            <!-- READING HISTORY TAB -->
            <div x-show="activeMeterTab === 'history'" x-cloak>
                @if(($roleData['readingHistory'] ?? collect())->count() > 0)
                    @include('partials.table.table-readings', [
                        'readings' => $roleData['readingHistory'] ?? [], 
                        'showActions' => false, 
                        'showConsumption' => true,
                        'units' => $roleData['units'] ?? []
                    ])
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No reading history found for the selected month.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Add x-cloak styles to prevent flash of unstyled content -->
<style>
    [x-cloak] { display: none !important; }
</style>
@endif
@endauth
                    <!-- READING HISTORY TAB -->
                    <div x-show="activeMeterTab === 'history'" x-cloak>
                        @if(($roleData['readingHistory'] ?? collect())->count() > 0)
                            @include('partials.table.table-readings', [
                                'readings' => $roleData['readingHistory'] ?? [], 
                                'showActions' => false, 
                                'showConsumption' => true,
                                'units' => $roleData['units'] ?? []
                            ])
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                No reading history found. Record your first reading to see history here.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Add x-cloak styles to prevent flash of unstyled content -->
        <style>
            [x-cloak] { display: none !important; }
        </style>
        @endif
        @endauth

        <!-- MAINTENANCE DASHBOARD -->
        @auth
        @if(auth()->user()->hasRole('maintenance'))
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeMaintTab = 'open'" :class="activeMaintTab === 'open' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Open Requests
                        </button>
                        <button @click="activeMaintTab = 'in_progress'" :class="activeMaintTab === 'in_progress' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            In Progress
                        </button>
                        <button @click="activeMaintTab = 'completed'" :class="activeMaintTab === 'completed' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Completed
                        </button>
                    </div>
                </div>
                <div class="p-5">
                    <div x-show="activeMaintTab === 'open'">
                        @include('partials.table.table-maintenance', [
                            'requests' => $roleData['openRequests'] ?? []
                        ])
                    </div>
                    <div x-show="activeMaintTab === 'in_progress'">
                        @include('partials.table.table-maintenance', [
                            'requests' => $roleData['inProgressRequests'] ?? []
                        ])
                    </div>
                    <div x-show="activeMaintTab === 'completed'">
                        @include('partials.table.table-maintenance', [
                            'requests' => $roleData['completedRequests'] ?? []
                        ])
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth

        <!-- SECURITY DASHBOARD -->
        @auth
        @if(auth()->user()->hasRole('security'))
        <div class="mt-6">
            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
                    <div class="flex flex-wrap gap-2">
                        <button @click="activeSecurityTab = 'pending'" :class="activeSecurityTab === 'pending' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Pending Approval
                        </button>
                        <button @click="activeSecurityTab = 'today'" :class="activeSecurityTab === 'today' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            Today's Visits
                        </button>
                        <button @click="activeSecurityTab = 'all'" :class="activeSecurityTab === 'all' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                            All Logs
                        </button>
                    </div>
                </div>
                <div class="p-5">
                    <div x-show="activeSecurityTab === 'pending'">
                        @include('partials.table.table-security', [
                            'logs' => $roleData['pendingLogs'] ?? []
                        ])
                    </div>
                    <div x-show="activeSecurityTab === 'today'">
                        @include('partials.table.table-security', [
                            'logs' => $roleData['todayLogs'] ?? []
                        ])
                    </div>
                    <div x-show="activeSecurityTab === 'all'">
                        @include('partials.table.table-security', [
                            'logs' => $roleData['accessLogs'] ?? []
                        ])
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endauth
    </div>
</div>

<script>
function dashboard() {
    return {
        roleData: @json($roleData ?? []),
        stats: @json($stats ?? []),
        userName: '{{ Auth::user()->first_name ?: Auth::user()->name }}',
        userRole: '{{ Auth::user()->role->name ?? "User" }}',
        dashboardTitle: '{{ Auth::user()->dashboard_title ?? 'Dashboard' }}',
        currentDate: new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        welcomeMessage: '',
        
        // Tab states for different roles
        activeAdminTab: 'invoices',
        activePMTab: 'readings',
        activeAccTab: 'financials',
        activeTenantTab: 'invoices',
        activeMeterTab: 'pending',  
        activeMaintTab: 'open',
        activeSecurityTab: 'pending',
        
        // For invoice generation loading state
        generatingTenancy: null,
        
        init() {
            this.setWelcomeMessage();
            
            console.log('========== DASHBOARD DEBUG ==========');
            console.log('Meter Reader - activeMeterTab:', this.activeMeterTab);
            console.log('Units needing reading count:', this.roleData.unitsNeedingReading?.length);
            console.log('Reading history count:', this.roleData.readingHistory?.length);
            
            if (this.activeMeterTab === 'pending') {
                console.log('Pending tab is active - should show units needing reading');
            }
            
            if (this.roleData.unitsNeedingReading && this.roleData.unitsNeedingReading.length > 0) {
                console.log('First pending reading sample:', this.roleData.unitsNeedingReading[0]);
            }
        },
        
        setWelcomeMessage() {
            const hour = new Date().getHours();
            if (hour < 12) {
                this.welcomeMessage = 'Good morning! Here\'s your property management overview.';
            } else if (hour < 18) {
                this.welcomeMessage = 'Good afternoon! Here\'s your property management overview.';
            } else {
                this.welcomeMessage = 'Good evening! Here\'s your property management overview.';
            }
        },
        
        async generateInvoiceForTenancy(tenancyId) {
            this.generatingTenancy = tenancyId;
            try {
                const response = await fetch('{{ route("invoices.generate.single") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ tenancy_id: tenancyId })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Invoice generated successfully!');
                    location.reload();
                } else {
                    alert(result.message || 'Failed to generate invoice');
                }
            } catch (error) {
                console.error('Error generating invoice:', error);
                alert('An error occurred while generating the invoice');
            } finally {
                this.generatingTenancy = null;
            }
        }
    };
}

// Global functions
function editReading(unitId) {
    window.location.href = '{{ route("water.readings.bulk.form") }}';
}

function viewPayment(paymentId) {
    window.location.href = `/payments/${paymentId}`;
}

function deletePayment(paymentId) {
    if (confirm('Are you sure you want to delete this payment?')) {
        fetch(`/payments/${paymentId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) location.reload();
          });
    }
}

function updateRequestStatus(requestId) {
    window.location.href = `/maintenance/${requestId}/edit`;
}

function viewRequest(requestId) {
    window.location.href = `/maintenance/${requestId}`;
}

function viewLog(logId) {
    window.location.href = `/security/logs/${logId}`;
}
</script>
@endsection