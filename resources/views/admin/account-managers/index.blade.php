@extends('layouts.app')

@section('title', 'Account Managers')

@section('content')
<div class="flex flex-col gap-4 p-4">
    <!-- Header -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Account Managers</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Manage account managers and view their performance</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.account-managers.create') }}" 
                   class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white hover:bg-brand-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Manager
                </a>
                <button onclick="window.location.reload()" 
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="mt-3 rounded-xl border border-success-200 bg-success-50 p-3 dark:border-success-500/10 dark:bg-success-500/10">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-xs text-success-700 dark:text-success-300">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mt-3 rounded-xl border border-error-200 bg-error-50 p-3 dark:border-error-500/10 dark:bg-error-500/10">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-error-600 dark:text-error-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span class="text-xs text-error-700 dark:text-error-300">{{ session('error') }}</span>
                </div>
            </div>
        @endif
    </div>

    <!-- Compact Statistics Cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Managers</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $stats->total_managers ?? 0 }}</p>
            <p class="text-[10px] text-green-600 dark:text-green-400">{{ $stats->active_managers ?? 0 }} active</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Companies</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $stats->total_companies ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Estates</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $stats->total_estates ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Units</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $stats->total_units ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Occupied</p>
            <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $stats->total_occupied_units ?? 0 }}</p>
            <p class="text-[10px] text-gray-500 dark:text-gray-400">{{ $stats->occupancy_rate ?? 0 }}% rate</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Vacant</p>
            <p class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $stats->total_unoccupied_units ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Tenants</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $stats->total_tenants ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Revenue</p>
            <p class="text-sm font-semibold text-yellow-600 dark:text-yellow-400">
                KES {{ number_format(($stats->total_revenue ?? 0) / 1000, 1) }}K
            </p>
        </div>
    </div>

    <!-- Account Managers Table -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Manager</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Company</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Estates</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Units</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Occupied</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Vacant</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tenants</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Revenue</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($accountManagers as $manager)
                        @php
                            $summary = $manager->getCompanySummary($manager->company_id);
                            $occupancy = $manager->getUnitOccupancyStats($manager->company_id);
                            $occupiedUnits = 0;
                            $unoccupiedUnits = 0;
                            $totalUnits = 0;
                            foreach ($occupancy as $estate) {
                                $totalUnits += $estate->total_units ?? 0;
                                $occupiedUnits += $estate->occupied_units ?? 0;
                                $unoccupiedUnits += $estate->unoccupied_units ?? 0;
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <!-- Manager -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                        <span class="text-xs font-semibold">
                                            {{ $manager->user->name ? strtoupper(substr($manager->user->name, 0, 2)) : 'AM' }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                            {{ $manager->user->name ?? 'N/A' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $manager->title ?? 'Account Manager' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Company -->
                            <td class="px-4 py-3">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $manager->company->name ?? 'No Company' }}
                                </span>
                            </td>

                            <!-- Estates -->
                            <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                {{ $summary->total_estates ?? 0 }}
                            </td>

                            <!-- Units -->
                            <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                {{ $totalUnits }}
                            </td>

                            <!-- Occupied -->
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                    {{ $occupiedUnits }}
                                </span>
                            </td>

                            <!-- Vacant -->
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-medium text-red-600 dark:text-red-400">
                                    {{ $unoccupiedUnits }}
                                </span>
                            </td>

                            <!-- Tenants -->
                            <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-300">
                                {{ $summary->total_tenants ?? 0 }}
                            </td>

                            <!-- Revenue -->
                            <td class="px-4 py-3 text-right">
                                <span class="text-sm font-medium text-yellow-600 dark:text-yellow-400">
                                    KES {{ number_format(($summary->total_revenue ?? 0) / 1000, 1) }}K
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if($manager->is_active)
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        <span class="text-xs text-green-600 dark:text-green-400">Active</span>
                                    @else
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        <span class="text-xs text-red-600 dark:text-red-400">Inactive</span>
                                    @endif
                                    @if($manager->is_primary)
                                        <span class="ml-1 inline-block px-1.5 py-0.5 text-[8px] font-medium bg-yellow-100 text-yellow-800 rounded dark:bg-yellow-500/20 dark:text-yellow-400">
                                            Primary
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.account-managers.show', $manager->id) }}" 
                                       class="p-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.account-managers.edit', $manager->id) }}" 
                                       class="p-1 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <form action="{{ route('admin.account-managers.destroy', $manager->id) }}" 
                                          method="POST" 
                                          class="inline"
                                          onsubmit="return confirm('Delete this account manager?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" 
                                                title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                <p class="text-sm font-medium">No Account Managers Found</p>
                                <p class="text-xs mt-1">Get started by creating your first account manager.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection