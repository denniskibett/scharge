<!-- Card Dashboard Component - Role-Aware Metric Cards -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6">
    
    <!-- ========== TOTAL UNITS CARD ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'property_manager']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
            <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4ZM6 4H18V20H6V4ZM8 6H16V8H8V6ZM8 10H12V12H8V10Z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ number_format($stats['total_units'] ?? 0) }}
            </h4>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="text-success-600 dark:text-success-500">✓ {{ number_format($stats['occupied_units'] ?? 0) }} Occupied</span>
                <span class="text-warning-600 dark:text-warning-500">🏠 {{ number_format($stats['vacant_units'] ?? 0) }} Vacant</span>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== TOTAL TENANTS CARD ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'property_manager', 'accountant']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
            <svg class="fill-blue-500 dark:fill-blue-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12ZM12 14C8.68629 14 6 16.6863 6 20H18C18 16.6863 15.3137 14 12 14Z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ number_format($stats['total_tenants'] ?? 0) }}
            </h4>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="text-success-600 dark:text-success-500">✓ {{ number_format($stats['active_tenancies'] ?? 0) }} Active</span>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== REVENUE CARD ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'accountant']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-success-50 dark:bg-success-500/15">
            <svg class="fill-success-500 dark:fill-success-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.5 15h-3v-2h3v2zm0-4h-3V7h3v6z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                KES {{ number_format($stats['total_revenue'] ?? 0, 2) }}
            </h4>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="text-gray-500">Net: KES {{ number_format($stats['net_income'] ?? 0, 2) }}</span>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== INVOICES CARD ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'accountant']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-warning-50 dark:bg-warning-500/15">
            <svg class="fill-warning-500 dark:fill-warning-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4H20V20H4V4ZM6 6V18H18V6H6ZM8 8H16V10H8V8ZM8 12H12V14H8V12Z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Invoices</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ number_format($stats['total_invoices'] ?? 0) }}
            </h4>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="text-danger-600 dark:text-danger-500">⚠️ {{ number_format($stats['unpaid_invoices'] ?? 0) }} Unpaid</span>
                <span class="text-success-600 dark:text-success-500">✓ {{ number_format($stats['paid_invoices'] ?? 0) }} Paid</span>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== OCCUPANCY RATE CARD ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'property_manager']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-info-50 dark:bg-info-500/15">
            <svg class="fill-info-500 dark:fill-info-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M3 3H21V21H3V3ZM5 5V19H19V5H5ZM7 7H17V9H7V7ZM7 11H12V13H7V11Z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Occupancy Rate</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ number_format($stats['occupancy_rate'] ?? 0, 1) }}%
            </h4>
            <div class="mt-2">
                <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $stats['occupancy_rate'] ?? 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== WATER READINGS CARD ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'property_manager', 'meter_reader']))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
            <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Water Consumption</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ number_format($stats['total_consumption'] ?? 0, 0) }} m³
            </h4>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span class="text-cyan-600 dark:text-cyan-500">💧 This month: {{ number_format($stats['monthly_consumption'] ?? 0, 0) }} m³</span>
            </div>
        </div>
    </div>
    @endif
    @endauth

<!-- ========== TENANT WATER READING CARD ========== -->
@auth
@if(auth()->user()->hasRole('tenant'))
<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
        <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
        </svg>
    </div>
    <div class="mt-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Current Reading</span>
            <div class="px-2 py-0.5 rounded-lg bg-cyan-50 dark:bg-cyan-500/10">
                <span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">{{ Carbon\Carbon::now()->format('F Y') }}</span>
            </div>
        </div>
        <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
            {{ number_format($stats['tenant_consumption'] ?? 0, 2) }}
            <span class="text-sm font-normal text-gray-500">m³</span>
        </h4>
        
        {{-- <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Previous Reading</p>
                <p class="mt-1 text-base font-semibold text-gray-800 dark:text-white">
                    {{ number_format($stats['tenant_previous_reading'] ?? 0, 2) }}
                    <span class="text-xs font-normal text-gray-400">m³</span>
                </p>
            </div>
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Current Reading</p>
                <p class="mt-1 text-base font-semibold text-gray-800 dark:text-white">
                    {{ number_format($stats['tenant_current_reading'] ?? 0, 2) }}
                    <span class="text-xs font-normal text-gray-400">m³</span>
                </p>
            </div>
        </div> --}}
        
        <div class="mt-4">
            @php
                $consumptionPercent = min(100, (($stats['tenant_consumption'] ?? 0) / max(1, $stats['tenant_previous_reading'] ?? 1)) * 100);
            @endphp
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                <span>Usage vs Previous</span>
                <span class="text-cyan-600 dark:text-cyan-400">{{ number_format($consumptionPercent, 1) }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-cyan-500" style="width: {{ $consumptionPercent }}%"></div>
            </div>
        </div>
        
        @if(($stats['tenant_consumption'] ?? 0) > 50)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
            <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <span>High water usage this month. Consider conservation measures.</span>
        </div>
        @endif
    </div>
</div>
@endif
@endauth
    <!-- ========== METER READER WATER READING CARD ========== -->
    @auth
    @if(auth()->user()->hasRole('meter_reader'))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
            <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Pending Readings</span>
            <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                {{ number_format($stats['pending_readings_count'] ?? 0) }}
            </h4>
            <div class="mt-3">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-500 dark:text-gray-400">Units needing reading</span>
                    <span class="font-semibold text-cyan-600 dark:text-cyan-400">{{ number_format($stats['units_needing_reading'] ?? 0) }}</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                    <div class="bg-cyan-500 h-1.5 rounded-full" style="width: {{ min(100, ($stats['units_needing_reading'] ?? 0) / max(1, $stats['total_units'] ?? 1) * 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== OUTSTANDING BALANCE CARD (Tenant Only) ========== -->
    @auth
    @if(auth()->user()->hasRole('tenant'))
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-danger-50 dark:bg-danger-500/15">
            <svg class="fill-danger-500 dark:fill-danger-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Outstanding Balance</span>
            <h4 class="mt-2 text-title-sm font-bold text-danger-600 dark:text-danger-500">
                KES {{ number_format($outstandingBalance ?? 0, 2) }}
            </h4>
        </div>
    </div>

    <!-- ========== TOTAL PAID CARD (Tenant Only) ========== -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-success-50 dark:bg-success-500/15">
            <svg class="fill-success-500 dark:fill-success-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
            </svg>
        </div>
        <div class="mt-5">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Paid</span>
            <h4 class="mt-2 text-title-sm font-bold text-success-600 dark:text-success-500">
                KES {{ number_format($totalPaid ?? 0, 2) }}
            </h4>
        </div>
    </div>
    @endif
    @endauth
</div>