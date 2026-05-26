<!-- Card Dashboard Component - Role-Aware Metric Cards -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6">
    
    <!-- ========== SUPER ADMIN / ADMIN CARDS (6 cards) ========== -->
    @auth
    @if(auth()->user()->hasAnyRole(['super_admin', 'admin']))
    
    <!-- Card 1: Total Units -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4ZM6 4H18V20H6V4ZM8 6H16V8H8V6ZM8 10H12V12H8V10Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span>Occupied: {{ number_format($stats['occupied_units'] ?? 0) }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                <span>Vacant: {{ number_format($stats['vacant_units'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Tenants -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_tenants'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                <svg class="fill-blue-500 dark:fill-blue-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12ZM12 14C8.68629 14 6 16.6863 6 20H18C18 16.6863 15.3137 14 12 14Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex items-center gap-1 text-xs">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span>Active Leases: {{ number_format($stats['active_tenancies'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Total Revenue -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    KES {{ number_format($stats['total_revenue'] ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                <svg class="fill-green-500 dark:fill-green-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Net Income: KES {{ number_format($stats['net_income'] ?? 0, 2) }}
        </div>
    </div>

    <!-- Card 4: Invoices -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Invoices</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_invoices'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/15">
                <svg class="fill-orange-500 dark:fill-orange-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4H20V20H4V4ZM6 6V18H18V6H6ZM8 8H16V10H8V8ZM8 12H12V14H8V12Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                <span>Unpaid: {{ number_format($stats['unpaid_invoices'] ?? 0) }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span>Paid: {{ number_format($stats['paid_invoices'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    <!-- Card 5: Water Consumption -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Water Consumption</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_consumption'] ?? 0, 0) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            This month: {{ number_format($stats['monthly_consumption'] ?? 0, 0) }} m³
        </div>
    </div>

    <!-- Card 6: Occupancy Rate -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Occupancy Rate</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['occupancy_rate'] ?? 0, 1) }}%
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/15">
                <svg class="fill-purple-500 dark:fill-purple-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M3 3H21V21H3V3ZM5 5V19H19V5H5ZM7 7H17V9H7V7ZM7 11H12V13H7V11Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $stats['occupancy_rate'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== PROPERTY MANAGER CARDS (5 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('property_manager'))
    
    <!-- Card 1: Total Units -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4ZM6 4H18V20H6V4Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span>Occupied: {{ number_format($stats['occupied_units'] ?? 0) }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                <span>Vacant: {{ number_format($stats['vacant_units'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Tenants -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_tenants'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                <svg class="fill-blue-500 dark:fill-blue-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12ZM12 14C8.68629 14 6 16.6863 6 20H18C18 16.6863 15.3137 14 12 14Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Active Leases: {{ number_format($stats['active_tenancies'] ?? 0) }}
        </div>
    </div>

    <!-- Card 3: Occupancy Rate -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Occupancy Rate</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['occupancy_rate'] ?? 0, 1) }}%
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                <svg class="fill-green-500 dark:fill-green-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M3 3H21V21H3V3ZM5 5V19H19V5H5ZM7 7H17V9H7V7ZM7 11H12V13H7V11Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-green-500" style="width: {{ $stats['occupancy_rate'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>

    <!-- Card 4: Water Consumption -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Water Consumption</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_consumption'] ?? 0, 0) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Units needing reading: {{ number_format($stats['units_needing_reading'] ?? 0) }}
        </div>
    </div>

    <!-- Card 5: Maintenance Requests -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Maintenance</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format(count($roleData['maintenanceRequests'] ?? [])) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/15">
                <svg class="fill-orange-500 dark:fill-orange-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20 8h-2.81c-.45-.8-1.07-1.5-1.82-2L15 4.56 17 2l3 3-2 3zm-10 0H6.81L5 6l2-3 3 3zM4 10h5v6H4v-6zm7 0h5v6h-5v-6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Open Requests
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== ACCOUNTANT CARDS (4 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('accountant'))
    
    <!-- Card 1: Total Revenue -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    KES {{ number_format($stats['total_revenue'] ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                <svg class="fill-green-500 dark:fill-green-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Net Income: KES {{ number_format($stats['net_income'] ?? 0, 2) }}
        </div>
    </div>

    <!-- Card 2: Total Invoices -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Invoices</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_invoices'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/15">
                <svg class="fill-orange-500 dark:fill-orange-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4H20V20H4V4ZM6 6V18H18V6H6ZM8 8H16V10H8V8ZM8 12H12V14H8V12Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                <span>Unpaid: {{ number_format($stats['unpaid_invoices'] ?? 0) }}</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                <span>Paid: {{ number_format($stats['paid_invoices'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    <!-- Card 3: Collection Rate -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Collection Rate</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['collection_rate'] ?? 0, 1) }}%
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                <svg class="fill-blue-500 dark:fill-blue-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M3 3H21V21H3V3ZM5 5V19H19V5H5ZM7 7H17V9H7V7ZM7 11H12V13H7V11Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $stats['collection_rate'] ?? 0 }}%"></div>
            </div>
        </div>
    </div>

    <!-- Card 4: Outstanding Amount -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Outstanding</span>
                <h4 class="mt-2 text-title-sm font-bold text-red-600 dark:text-red-500">
                    KES {{ number_format($stats['outstanding_invoices'] ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 dark:bg-red-500/15">
                <svg class="fill-red-500 dark:fill-red-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Requires attention
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== TENANT CARDS (4 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('tenant'))
    
    <!-- Card 1: Current Water Usage -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Water Usage</span>
                    <span class="px-2 py-0.5 rounded-lg text-xs font-medium bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400">{{ \Carbon\Carbon::now()->format('M Y') }}</span>
                </div>
                <h4 class="text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['tenant_consumption'] ?? 0, 2) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Previous</p>
                <p class="text-sm font-semibold">{{ number_format($stats['tenant_previous_reading'] ?? 0, 2) }} m³</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                <p class="text-xs text-gray-500">Current</p>
                <p class="text-sm font-semibold">{{ number_format($stats['tenant_current_reading'] ?? 0, 2) }} m³</p>
            </div>
        </div>
    </div>

    <!-- Card 2: Outstanding Balance -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Outstanding Balance</span>
                <h4 class="mt-2 text-title-sm font-bold text-red-600 dark:text-red-500">
                    KES {{ number_format($outstandingBalance ?? 0, 2) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 dark:bg-red-500/15">
                <svg class="fill-red-500 dark:fill-red-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Total Paid: KES {{ number_format($totalPaid ?? 0, 2) }}
        </div>
    </div>

    <!-- Card 3: Your Unit -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Your Unit</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ $stats['tenant_unit_number'] ?? 'N/A' }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4ZM6 4H18V20H6V4Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Billing: {{ ucfirst($stats['tenant_water_billing_type'] ?? 'consumption') }}
        </div>
    </div>

    <!-- Card 4: Maintenance Requests -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Maintenance</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format(count($roleData['maintenanceRequests'] ?? [])) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-500/15">
                <svg class="fill-orange-500 dark:fill-orange-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20 8h-2.81c-.45-.8-1.07-1.5-1.82-2L15 4.56 17 2l3 3-2 3zm-10 0H6.81L5 6l2-3 3 3zM4 10h5v6H4v-6zm7 0h5v6h-5v-6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Active requests
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== METER READER CARDS (4 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('meter_reader'))
    
    <!-- Card 1: Pending Readings -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Pending Readings</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['units_needing_reading'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm0 13c-2.33 0-4.31-1.46-5.11-3.5h10.22c-.8 2.04-2.78 3.5-5.11 3.5z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-3">
            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-cyan-500" style="width: {{ $stats['total_units'] > 0 ? (($stats['total_units'] - ($stats['units_needing_reading'] ?? 0)) / ($stats['total_units'] ?? 1) * 100) : 0 }}%"></div>
            </div>
            <div class="mt-2 text-xs text-gray-500">Completion Progress</div>
        </div>
    </div>

    <!-- Card 2: Total Readings -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Readings</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($roleData['readingHistory']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/15">
                <svg class="fill-purple-500 dark:fill-purple-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Historical records
        </div>
    </div>

    <!-- Card 3: Total Consumption -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Consumption</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_consumption'] ?? 0, 0) }} m³
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                <svg class="fill-green-500 dark:fill-green-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            All-time usage
        </div>
    </div>

    <!-- Card 4: Total Units -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Active units monitored
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== MAINTENANCE CARDS (4 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('maintenance'))
    
    <!-- Card 1: Open Requests -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Open Requests</span>
                <h4 class="mt-2 text-title-sm font-bold text-red-600 dark:text-red-500">
                    {{ number_format($roleData['openRequests']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 dark:bg-red-500/15">
                <svg class="fill-red-500 dark:fill-red-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Needs immediate attention
        </div>
    </div>

    <!-- Card 2: In Progress -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">In Progress</span>
                <h4 class="mt-2 text-title-sm font-bold text-yellow-600 dark:text-yellow-500">
                    {{ number_format($roleData['inProgressRequests']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50 dark:bg-yellow-500/15">
                <svg class="fill-yellow-500 dark:fill-yellow-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Currently being worked on
        </div>
    </div>

    <!-- Card 3: Completed (This Month) -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Completed</span>
                <h4 class="mt-2 text-title-sm font-bold text-green-600 dark:text-green-500">
                    {{ number_format($roleData['completedRequests']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                <svg class="fill-green-500 dark:fill-green-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Resolved this month
        </div>
    </div>

    <!-- Card 4: Total Requests -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Requests</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format(($roleData['openRequests']->count() ?? 0) + ($roleData['inProgressRequests']->count() ?? 0) + ($roleData['completedRequests']->count() ?? 0)) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            All time requests
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== SECURITY CARDS (4 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('security'))
    
    <!-- Card 1: Pending Approvals -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Pending Approvals</span>
                <h4 class="mt-2 text-title-sm font-bold text-yellow-600 dark:text-yellow-500">
                    {{ number_format($roleData['pendingLogs']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50 dark:bg-yellow-500/15">
                <svg class="fill-yellow-500 dark:fill-yellow-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM11 8h2v6h-2V8zm0 8h2v2h-2v-2z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Requiring verification
        </div>
    </div>

    <!-- Card 2: Today's Visits -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Today's Visits</span>
                <h4 class="mt-2 text-title-sm font-bold text-blue-600 dark:text-blue-500">
                    {{ number_format($roleData['todayLogs']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/15">
                <svg class="fill-blue-500 dark:fill-blue-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm4 12h-4v3h-2v-3H8v-2h4V9h2v4h4v2z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Scheduled for today
        </div>
    </div>

    <!-- Card 3: Total Logs -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Logs</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($roleData['accessLogs']->count() ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/15">
                <svg class="fill-purple-500 dark:fill-purple-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20 8h-2.81c-.45-.8-1.07-1.5-1.82-2L15 4.56 17 2l3 3-2 3zm-10 0H6.81L5 6l2-3 3 3zM4 10h5v6H4v-6zm7 0h5v6h-5v-6z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Total access records
        </div>
    </div>

    <!-- Card 4: Total Units -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Under security monitoring
        </div>
    </div>
    @endif
    @endauth

    <!-- ========== CLEANING STAFF CARDS (4 cards) ========== -->
    @auth
    @if(auth()->user()->hasRole('cleaning_staff'))
    
    <!-- Card 1: Pending Tasks -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Pending Tasks</span>
                <h4 class="mt-2 text-title-sm font-bold text-yellow-600 dark:text-yellow-500">
                    {{ number_format(count($roleData['cleaningTasks'] ?? [])) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-yellow-50 dark:bg-yellow-500/15">
                <svg class="fill-yellow-500 dark:fill-yellow-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Awaiting completion
        </div>
    </div>

    <!-- Card 2: Completed Tasks -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Completed</span>
                <h4 class="mt-2 text-title-sm font-bold text-green-600 dark:text-green-500">
                    {{ number_format(0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-500/15">
                <svg class="fill-green-500 dark:fill-green-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Tasks done this month
        </div>
    </div>

    <!-- Card 3: Total Units -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    {{ number_format($stats['total_units'] ?? 0) }}
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/15">
                <svg class="fill-brand-500 dark:fill-brand-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4C4 2.89543 4.89543 2 6 2H18C19.1046 2 20 2.89543 20 4V20C20 21.1046 19.1046 22 18 22H6C4.89543 22 4 21.1046 4 20V4Z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Units to maintain
        </div>
    </div>

    <!-- Card 4: Weekly Schedule -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Weekly Schedule</span>
                <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                    Active
                </h4>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                <svg class="fill-cyan-500 dark:fill-cyan-500" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM7 12h5v5H7v-5z" fill=""/>
                </svg>
            </div>
        </div>
        <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
            Regular cleaning schedule
        </div>
    </div>
    @endif
    @endauth

</div>