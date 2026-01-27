@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Unit Details</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">View and manage unit information</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('units.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Units
                </a>
                <a href="{{ route('units.edit', $unit) }}" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                    </svg>
                    Edit Unit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Unit Information Card -->
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Unit Information</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-6 flex items-center gap-4">
                                <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span class="text-blue-600 font-bold text-xl">{{ $unit->unit_number[0] ?? 'U'  ?? 'U' }}</span>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $unit->unit_number }}</h2>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-1
                                        {{ $unit->status === 'occupied' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' }}">
                                        {{ ucfirst($unit->status) }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Unit Type</label>
                                    <p class="text-gray-800 dark:text-white/90 font-medium">{{ $unit->unit_type ?? 'Not specified' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Monthly Rent</label>
                                    <p class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                        KES {{ number_format($unit->rent_amount, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Estate</label>
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                                        <span class="text-purple-600 font-medium text-sm">{{ $unit->estate->name[0] ?? 'U'  ?? 'E' }}</span>
                                    </div>
                                    <a href="{{ route('estates.show', $unit->estate) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-medium">
                                        {{ $unit->estate->name }}
                                    </a>
                                </div>
                                @if($unit->estate->location)
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $unit->estate->location }}</p>
                                @endif
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Created</label>
                                <p class="text-gray-800 dark:text-white/90">{{ $unit->created_at->format('M d, Y') }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Last Updated</label>
                                <p class="text-gray-800 dark:text-white/90">{{ $unit->updated_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenants History Card -->
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tenancy History</h3>
                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $unit->tenancies->count() }} {{ Str::plural('tenancy', $unit->tenancies->count()) }}
                        </span>
                    </div>
                </div>
                
                <div class="w-full overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">tenancy</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-in Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Move-out Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($unit->tenancies as $tenancy)
                                @php
                                    $moveIn = \Carbon\Carbon::parse($tenancy->move_in_date);
                                    $moveOut = $tenancy->move_out_date ? \Carbon\Carbon::parse($tenancy->move_out_date) : null;
                                    $duration = $moveOut ? $moveIn->diffForHumans($moveOut, true) : $moveIn->diffForHumans(now(), true);
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                                                <span class="text-purple-600 font-medium">{{ $tenancy->tenant->user->name[0] ?? 'U'  ?? 'T' }}</span>
                                            </div>
                                            <div>
                                                <a href="{{ route('tenants.show', $tenancy->tenant) }}" class="font-medium text-gray-800 text-sm dark:text-white/90 hover:text-blue-600">
                                                    {{ $tenancy->tenant->user->name }}
                                                </a>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenancy->tenant->user->phone ?? 'N/A' }}</p>
                                            
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
                                            {{ $duration }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $tenancy->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }}">
                                            {{ ucfirst($tenancy->status) }}
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
                                            This unit has never been occupied.
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
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Unit Statistics</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Total Tenancies</span>
                            <span class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $unit->tenancies->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Active Tenancy</span>
                            <span class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                {{ $unit->tenancies->where('status', 'active')->count() }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Past Tenancies</span>
                            <span class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                {{ $unit->tenancies->where('status', 'ended')->count() }}
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
                        @if($unit->status === 'vacant')
                            <a href="{{ route('tenants.create', ['unit_id' => $unit->id]) }}" 
                               class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-blue-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Tenant
                            </a>
                        @else
                            <a href="{{ route('tenants.index', ['unit_id' => $unit->id]) }}" 
                               class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-green-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                View Current Tenant
                            </a>
                        @endif

                        <a href="{{ route('units.edit', $unit) }}" 
                           class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                            Edit Unit Details
                        </a>

                        <form action="{{ route('units.destroy', $unit) }}" method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this unit? This action cannot be undone.');"
                              class="w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-medium text-red-600 shadow-theme-xs transition hover:bg-red-50 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                Delete Unit
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notes Section (Optional) -->
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Notes</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        Add any notes about this unit here. You can track maintenance requests, special conditions, or other important information.
                    </p>
                    <button class="mt-4 w-full rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 hover:border-gray-400 dark:border-gray-700 dark:text-gray-400">
                        + Add Note
                    </button>
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