@extends('layouts.app')

@section('title', 'Account Managers')

@section('content')
<div class="flex flex-col gap-4">
    @if(session('success'))
        <div class="rounded-xl border border-success-200 bg-success-50 p-3 dark:border-success-500/10 dark:bg-success-500/10">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-xs text-success-700 dark:text-success-300">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-error-200 bg-error-50 p-3 dark:border-error-500/10 dark:bg-error-500/10">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-error-600 dark:text-error-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span class="text-xs text-error-700 dark:text-error-300">{{ session('error') }}</span>
            </div>
        </div>
    @endif

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
            <p class="text-[10px] text-gray-500 dark:text-gray-400">Properties</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $stats->total_properties ?? 0 }}</p>
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
    @php
        $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Account Managers</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Manage account managers and view their performance</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <button type="button" onclick="(window.schargeRefresh || (() => location.reload()))()"
                        class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Refresh
                </button>
                <a href="{{ route('admin.account-managers.create') }}"
                   class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Add Manager
                </a>
            </div>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Manager</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Company</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Properties</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Units</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Occupied</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Vacant</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Tenants</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Revenue</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                        <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($accountManagers as $manager)
                        @php
                            $summary = $manager->getCompanySummary($manager->company_id);
                            $occupancy = $manager->getUnitOccupancyStats($manager->company_id);
                            $occupiedUnits = 0;
                            $unoccupiedUnits = 0;
                            $totalUnits = 0;
                            foreach ($occupancy as $property) {
                                $totalUnits += $property->total_units ?? 0;
                                $occupiedUnits += $property->occupied_units ?? 0;
                                $unoccupiedUnits += $property->unoccupied_units ?? 0;
                            }
                        @endphp
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                        <span class="text-sm font-medium text-purple-600">{{ $manager->user->name ? strtoupper(substr($manager->user->name, 0, 2)) : 'AM' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $manager->user->name ?? 'N/A' }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $manager->title ?? 'Account Manager' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $manager->company->name ?? 'No Company' }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $summary->total_properties ?? 0 }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $totalUnits }}</td>
                            <td class="p-4 whitespace-nowrap text-sm font-medium text-green-600 dark:text-green-400">{{ $occupiedUnits }}</td>
                            <td class="p-4 whitespace-nowrap text-sm font-medium text-red-600 dark:text-red-400">{{ $unoccupiedUnits }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $summary->total_tenants ?? 0 }}</td>
                            <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format(($summary->total_revenue ?? 0) / 1000, 1) }}K</td>
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    @if($manager->is_active)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                                    @endif
                                    @if($manager->is_primary)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Primary</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap text-right">
                                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                    <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
                                        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
                                    </button>
                                    <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                        <a href="{{ route('admin.account-managers.show', $manager->id) }}" class="{{ $menuItem }}">View</a>
                                        <a href="{{ route('admin.account-managers.edit', $manager->id) }}" class="{{ $menuItem }}">Edit</a>
                                        <form action="{{ route('admin.account-managers.destroy', $manager->id) }}" method="POST" onsubmit="return confirm('Delete this account manager?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-4 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No account managers found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first account manager.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
