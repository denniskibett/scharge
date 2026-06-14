{{-- resources/views/partials/card/card-dashboard.blade.php --}}
@props(['cardData' => []])

@php
    $roleName = $cardData['user_role'] ?? 'guest';
@endphp

<div class="grid grid-cols-1 gap-4 md:gap-6 mb-6"
     style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">      
    {{-- ===== REUSABLE CARD COMPONENTS ===== --}}
    
    {{-- Total Units Card (Used by: admin, property_manager) --}}
    @if(in_array($roleName, ['admin', 'property_manager']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Units</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span class="text-gray-500">Occupied:</span> 
                 <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-600 dark:bg-green-500/15 dark:text-green-500">
                    {{ number_format($cardData['occupied_units'] ?? 0) }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                <span class="text-gray-500">Vacant: </span>
                <span class="rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-600 dark:bg-yellow-500/15 dark:text-yellow-500">
                    {{ number_format($cardData['vacant_units'] ?? 0) }}
                </span>
            </div>
        </div>
    </div>
    @endif

    {{-- Total Tenants Card (Used by: admin, property_manager) --}}
    @if(in_array($roleName, ['admin', 'property_manager']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_tenants'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-600 dark:bg-green-500/15 dark:text-green-500">
                {{ number_format($cardData['active_tenancies'] ?? 0) }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">active leases</span>
        </div>
    </div>
    @endif

    {{-- Total Revenue Card (Used by: admin, accountant) --}}
    @if(in_array($roleName, ['admin', 'accountant']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    KES {{ number_format($cardData['total_revenue'] ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Net Income
                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-600 dark:bg-green-500/15 dark:text-green-500">
                    KES {{ number_format($cardData['net_income'] ?? 0, 2) }}
                </span>
            </span>
        </div>
    </div>
    @endif

    {{-- Invoices Card (Used by: admin, accountant) --}}
    @if(in_array($roleName, ['admin', 'accountant']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Invoices</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_invoices'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                <span class="text-gray-500">Unpaid: </span>
                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600 dark:bg-red-500/15 dark:text-red-500">
                    {{ number_format($cardData['unpaid_invoices'] ?? 0) }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span class="text-gray-500">Paid: </span>
                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-600 dark:bg-green-500/15 dark:text-green-500">
                    {{ number_format($cardData['paid_invoices'] ?? 0) }}
                </span>
            </div>
        </div>
    </div>
    @endif

    {{-- Water Consumption Card (Used by: admin, property_manager) --}}
    @if(in_array($roleName, ['admin', 'property_manager']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Water Consumption</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_consumption'] ?? 0, 0) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900">
                <svg class="h-6 w-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">
                This month: {{ number_format($cardData['monthly_consumption'] ?? 0, 0) }} m³
                @if($roleName === 'property_manager')
                    | Units needing reading: {{ number_format($cardData['units_needing_reading'] ?? 0) }}
                @endif
            </span>
        </div>
    </div>
    @endif

    {{-- Occupancy Rate Card (Used by: admin, property_manager) --}}
    @if(in_array($roleName, ['admin', 'property_manager']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Occupancy Rate</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['occupancy_rate'] ?? 0, 1) }}%
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-purple-600" style="width: {{ $cardData['occupancy_rate'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Collection Rate Card (Used by: accountant only) --}}
    @if($roleName === 'accountant')
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Collection Rate</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['collection_rate'] ?? 0, 1) }}%
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-blue-600" style="width: {{ $cardData['collection_rate'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Outstanding Amount Card (Used by: accountant only) --}}
    @if($roleName === 'accountant')
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Outstanding</p>
                <h4 class="mt-2 text-2xl font-bold text-red-600 dark:text-red-500">
                    KES {{ number_format($cardData['outstanding_amount'] ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600 dark:bg-red-500/15 dark:text-red-500">
                Requires attention
            </span>
        </div>
    </div>
    @endif

    {{-- Maintenance Requests Card (Used by: property_manager only) --}}
    @if($roleName === 'property_manager')
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Maintenance</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['open_maintenance'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 8h-2.81c-.45-.8-1.07-1.5-1.82-2L15 4.56 17 2l3 3-2 3zm-10 0H6.81L5 6l2-3 3 3zM4 10h5v6H4v-6zm7 0h5v6h-5v-6z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Open Requests</span>
        </div>
    </div>
    @endif

    {{-- ===== TENANT SPECIFIC CARDS ===== --}}
    @if($roleName === 'tenant')
    <!-- Current Water Usage -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Water Usage</p>
                    <span class="rounded-full bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-500">
                        {{ \Carbon\Carbon::now()->format('M Y') }}
                    </span>
                </div>
                <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['tenant_consumption'] ?? 0, 2) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900">
                <svg class="h-6 w-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Previous</p>
                <p class="text-sm font-semibold">{{ number_format($cardData['tenant_previous_reading'] ?? 0, 2) }} m³</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Current</p>
                <p class="text-sm font-semibold">{{ number_format($cardData['tenant_current_reading'] ?? 0, 2) }} m³</p>
            </div>
        </div>
    </div>

    <!-- Outstanding Balance -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Outstanding Balance</p>
                <h4 class="mt-2 text-2xl font-bold text-red-600 dark:text-red-500">
                    KES {{ number_format($cardData['outstanding_balance'] ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Total Paid: KES {{ number_format($cardData['total_paid'] ?? 0, 2) }}</span>
        </div>
    </div>

    <!-- Your Unit -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Your Unit</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ $cardData['tenant_unit_number'] ?? 'N/A' }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600 dark:bg-blue-500/15 dark:text-blue-500">
                {{ ucfirst($cardData['tenant_water_billing_type'] ?? 'consumption') }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">billing type</span>
        </div>
    </div>

    <!-- Maintenance Requests -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Maintenance</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['tenant_maintenance_count'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 8h-2.81c-.45-.8-1.07-1.5-1.82-2L15 4.56 17 2l3 3-2 3zm-10 0H6.81L5 6l2-3 3 3zM4 10h5v6H4v-6zm7 0h5v6h-5v-6z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Active requests</span>
        </div>
    </div>
    @endif

    {{-- ===== METER READER CARDS ===== --}}
    @if($roleName === 'meter_reader')
    @php
        $totalUnits = $cardData['total_units'] ?? 0;
        $pendingUnits = $cardData['units_needing_reading'] ?? 0;
        $completedUnits = $totalUnits - $pendingUnits;
        $progressPercent = $totalUnits > 0 ? ($completedUnits / $totalUnits) * 100 : 0;
    @endphp

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Readings</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['units_needing_reading'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900">
                <svg class="h-6 w-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-cyan-600" style="width: {{ $progressPercent }}%"></div>
            </div>
            <div class="mt-2 text-xs text-gray-500">Completion Progress</div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Readings</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_readings_count'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Historical records</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Consumption</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_consumption'] ?? 0, 0) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">All-time usage</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Units</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Active units monitored</span>
        </div>
    </div>
    @endif

    {{-- ===== MAINTENANCE STAFF CARDS ===== --}}
    @if($roleName === 'maintenance')
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Open Requests</p>
                <h4 class="mt-2 text-2xl font-bold text-red-600 dark:text-red-500">
                    {{ number_format($cardData['maintenance_open'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Needs immediate attention</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">In Progress</p>
                <h4 class="mt-2 text-2xl font-bold text-yellow-600 dark:text-yellow-500">
                    {{ number_format($cardData['maintenance_in_progress'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Currently being worked on</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Completed</p>
                <h4 class="mt-2 text-2xl font-bold text-green-600 dark:text-green-500">
                    {{ number_format($cardData['maintenance_completed'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Resolved this month</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Requests</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['maintenance_total'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">All time requests</span>
        </div>
    </div>
    @endif

    {{-- ===== SECURITY CARDS ===== --}}
    @if($roleName === 'security')
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Approvals</p>
                <h4 class="mt-2 text-2xl font-bold text-yellow-600 dark:text-yellow-500">
                    {{ number_format($cardData['security_pending'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Requiring verification</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Today's Visits</p>
                <h4 class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-500">
                    {{ number_format($cardData['security_today'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Scheduled for today</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Logs</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['security_total_logs'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Total access records</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Units</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Under security monitoring</span>
        </div>
    </div>
    @endif

    {{-- ===== CLEANING STAFF CARDS ===== --}}
    @if($roleName === 'cleaning_staff')
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending Tasks</p>
                <h4 class="mt-2 text-2xl font-bold text-yellow-600 dark:text-yellow-500">
                    {{ number_format($cardData['cleaning_pending'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Awaiting completion</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Completed</p>
                <h4 class="mt-2 text-2xl font-bold text-green-600 dark:text-green-500">
                    {{ number_format($cardData['cleaning_completed'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Tasks done this month</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Units</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($cardData['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Units to maintain</span>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Weekly Schedule</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">Active</h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900">
                <svg class="h-6 w-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Regular cleaning schedule</span>
        </div>
    </div>
    @endif

    {{-- ===== DEFAULT FALLBACK ===== --}}
    @if(!in_array($roleName, ['admin', 'property_manager', 'accountant', 'tenant', 'meter_reader', 'maintenance', 'security', 'cleaning_staff']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Welcome</p>
                <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ ucfirst($roleName) }}</h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Welcome to your dashboard</span>
        </div>
    </div>
    @endif

</div>