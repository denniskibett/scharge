@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Tenancy Details</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">View and manage tenancy information</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('tenancies.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Tenancies
                </a>
                <a href="{{ route('tenancies.edit', $tenancy) }}" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                    </svg>
                    Edit Tenancy
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tenancy Information Card -->
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tenancy Information</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-6 flex items-center gap-4">
                                <div class="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center">
                                    @php
                                        $tenantInitial = optional(optional($tenancy->tenant)->user)->name ? 
                                            substr(optional($tenancy->tenant->user)->name, 0, 1) : 'T';
                                    @endphp
                                    <span class="text-purple-600 font-bold text-xl">{{ strtoupper($tenantInitial) }}</span>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                        {{ optional(optional($tenancy->tenant)->user)->name ?? 'Unknown Tenant' }}
                                    </h2>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-1
                                        {{ $tenancy->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }}">
                                        {{ ucfirst($tenancy->status) }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Tenant Contact</label>
                                    @if(optional(optional($tenancy->tenant)->user)->phone)
                                        <p class="text-gray-800 dark:text-white/90 font-medium">{{ optional($tenancy->tenant->user)->phone }}</p>
                                    @endif
                                    @if(optional(optional($tenancy->tenant)->user)->phone2)
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ optional($tenancy->tenant->user)->phone2 }}</p>
                                    @endif
                                    @if(optional(optional($tenancy->tenant)->user)->email)
                                        <p class="text-sm text-blue-600 dark:text-blue-400">{{ optional($tenancy->tenant->user)->email }}</p>
                                    @endif
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Duration</label>
                                    <p class="text-gray-800 dark:text-white/90 font-medium">{{ $duration ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Unit</label>
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        @php
                                            $unitInitial = optional($tenancy->unit)->unit_number ? 
                                                substr($tenancy->unit->unit_number, 0, 1) : 'U';
                                        @endphp
                                        <span class="text-blue-600 font-medium text-sm">{{ strtoupper($unitInitial) }}</span>
                                    </div>
                                    @if($tenancy->unit)
                                        <a href="{{ route('units.show', $tenancy->unit) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-medium">
                                            {{ $tenancy->unit->unit_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-800 dark:text-white/90 font-medium">No Unit</span>
                                    @endif
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Estate</label>
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                                        @php
                                            $estateInitial = optional(optional($tenancy->unit)->estate)->name ? 
                                                substr($tenancy->unit->estate->name, 0, 1) : 'E';
                                        @endphp
                                        <span class="text-purple-600 font-medium text-sm">{{ strtoupper($estateInitial) }}</span>
                                    </div>
                                    @if($tenancy->unit && $tenancy->unit->estate)
                                        <a href="{{ route('estates.show', $tenancy->unit->estate) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-medium">
                                            {{ $tenancy->unit->estate->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-800 dark:text-white/90 font-medium">No Estate</span>
                                    @endif
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Move-in Date</label>
                                <p class="text-gray-800 dark:text-white/90 font-medium">
                                    {{ $tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date)->format('M d, Y') : 'N/A' }}
                                </p>
                            </div>
                            
                            @if($tenancy->move_out_date)
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Move-out Date</label>
                                <p class="text-gray-800 dark:text-white/90 font-medium">
                                    {{ \Carbon\Carbon::parse($tenancy->move_out_date)->format('M d, Y') }}
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unit Tenancy History Card -->
            {{-- @include('partials.table.table-tenancy', ['tenancies' => $unitTenancyHistory]) --}}
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Unit Tenancy History</h3>
                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $unitTenancyHistory->count() ?? 0 }} {{ Str::plural('tenancy', $unitTenancyHistory->count() ?? 0) }}
                        </span>
                    </div>
                </div>
                
                <div class="w-full overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-in Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-out Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($unitTenancyHistory ?? [] as $unitTenant)
                                @php
                                    $moveIn = \Carbon\Carbon::parse($unitTenant->move_in_date);
                                    $moveOut = $unitTenant->move_out_date ? \Carbon\Carbon::parse($unitTenant->move_out_date) : null;
                                    $tenancyDuration = $moveOut ? $moveIn->diffForHumans($moveOut, true) : $moveIn->diffForHumans(now(), true);
                                    $tenantName = optional(optional($unitTenant->tenant)->user)->name ?? 'Unknown Tenant';
                                    $tenantInitial = $tenantName[0] ?? 'U';
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ $unitTenant->id === $tenancy->id ? 'bg-blue-50 dark:bg-blue-900/10' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full {{ $unitTenant->id === $tenancy->id ? 'bg-blue-100' : 'bg-purple-100' }} flex items-center justify-center">
                                                <span class="{{ $unitTenant->id === $tenancy->id ? 'text-blue-600' : 'text-purple-600' }} font-medium">
                                                    {{ strtoupper($tenantInitial) }}
                                                </span>
                                            </div>
                                            <div>
                                                <a href="{{ route('tenancies.show', $unitTenant) }}" class="font-medium {{ $unitTenant->id === $tenancy->id ? 'text-blue-600' : 'text-gray-800 dark:text-white/90' }} text-sm hover:text-blue-600">
                                                    {{ $tenantName }}
                                                    @if($unitTenant->id === $tenancy->id)
                                                        <span class="ml-2 text-xs text-blue-500">(Current)</span>
                                                    @endif
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $moveIn->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $moveOut ? $moveOut->format('M d, Y') : '-' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $tenancyDuration }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $unitTenant->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }}">
                                            {{ ucfirst($unitTenant->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tenancy history</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            This is the first tenancy for this unit.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar - Stats & Actions -->
        <div class="lg:col-span-1">
            <!-- Stats Card -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tenancy Statistics</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Days Occupied</span>
                            <span class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                @php
                                    $moveIn = $tenancy->move_in_date ? \Carbon\Carbon::parse($tenancy->move_in_date) : null;
                                    $moveOut = $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date) : null;
                                    $daysOccupied = $moveIn ? $moveIn->diffInDays($moveOut ?? now()) : 0;
                                @endphp
                                {{ $daysOccupied }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Monthly Rent</span>
                            <span class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                {{ SystemHelper::currencySymbol() }} {{ optional($tenancy->unit)->rent_amount ? number_format($tenancy->unit->rent_amount, 2) : '0.00' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Total Paid (Est.)</span>
                            <span class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                @php
                                    $monthlyRent = optional($tenancy->unit)->rent_amount ?? 0;
                                    $monthsOccupied = $moveIn ? ceil($moveIn->diffInMonths($moveOut ?? now())) : 0;
                                    $totalEstimated = $monthlyRent * $monthsOccupied;
                                @endphp
                                {{ SystemHelper::currencySymbol() }} {{ number_format($totalEstimated, 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Quick Actions</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @if($tenancy->unit)
                        <a href="{{ route('units.show', $tenancy->unit) }}" 
                           class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            View Unit
                        </a>
                        @endif

                        @if($tenancy->status === 'active')
                            <a href="{{ route('tenancies.edit', $tenancy) }}" 
                               class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Set Move-out Date
                            </a>
                        @endif

                        <a href="{{ route('tenancies.edit', $tenancy) }}" 
                           class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                            Edit Tenancy Details
                        </a>

                        <form action="{{ route('tenancies.destroy', $tenancy) }}" method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this tenancy? This will set the unit status to vacant.');"
                              class="w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-medium text-red-600 shadow-theme-xs transition hover:bg-red-50 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                End Tenancy
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .shadow-theme-xs {
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    [x-cloak] { display: none !important; }
</style>
@endpush