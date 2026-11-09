@extends('layouts.app')

@section('title', 'Security Dashboard')

@section('content')
<div class="flex flex-col gap-5">
    <!-- Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Security Dashboard
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage visitor check-in and check-out
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('security.quick-entry') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-green-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Quick Entry
                </a>
                <a href="{{ route('security.full-entry') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-blue-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Full Entry
                </a>
                <a href="{{ route('security.logs.index') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-600 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    All Logs
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @php
            $currentlyInside = $roleData['accessLogs']->where('status', 'approved')->whereNull('exit_time')->count();
            $todayVisitors = $roleData['accessLogs']->where('access_time', '>=', Carbon\Carbon::today())->count();
            $pendingApprovals = $roleData['pendingLogs']->count();
            $todayCheckIns = $roleData['accessLogs']->where('access_time', '>=', Carbon\Carbon::today())->where('status', 'approved')->count();
            $todayCheckOuts = $roleData['accessLogs']->where('exit_time', '>=', Carbon\Carbon::today())->count();
        @endphp
        
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Currently Inside</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $currentlyInside }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 dark:bg-green-500/15">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Today's Visitors</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $todayVisitors }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/15">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending Approvals</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $pendingApprovals }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-50 dark:bg-yellow-500/15">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Today's Check-Ins</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $todayCheckIns }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-500/15">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Today's Check-Outs</p>
                    <h4 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $todayCheckOuts }}</h4>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/15">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Table (filters in its header) -->
    @php $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'; @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" action="{{ route('security.index') }}" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Security Logs</h4>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Date from" title="Date from" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Date to" title="Date to" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                @include('partials.table.select', ['name' => 'status', 'aria' => 'Status', 'selected' => request('status'),
                    'options' => ['' => 'All Status', 'approved' => 'Checked In', 'completed' => 'Checked Out', 'pending' => 'Pending']])
                @if (request('date_from') || request('date_to') || request('status'))
                    <a href="{{ route('security.index') }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear
                    </a>
                @endif
                <a href="{{ route('security.logs.index') }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">View all</a>
            </div>
        </form>

        <div class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Date & Time</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Unit</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Visitor Name</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Phone</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Vehicle</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                        <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($roleData['accessLogs'] as $log)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $log->access_time ? $log->access_time->format('M d, Y H:i') : 'N/A' }}
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $log->unit->unit_number ?? 'N/A' }}
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $log->visitor_name_snapshot ?? 'N/A' }}</span>
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $log->visitor_phone_snapshot ?? 'N/A' }}
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($log->vehicle_type || $log->vehicle_model)
                                    <div>{{ $log->vehicle_type ?? '' }} {{ $log->vehicle_model ?? '' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log->vehicle_color ?? '' }} {{ $log->vehicle_registration_snapshot ?? '' }}</div>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                @if($log->status === 'approved' && !$log->exit_time)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Checked In</span>
                                @elseif($log->status === 'completed' || $log->exit_time)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Checked Out</span>
                                @elseif($log->status === 'pending')
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Pending</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">{{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                            <td class="p-4 whitespace-nowrap text-right">
                                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions"><svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg></button>
                                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                    <button type="button" onclick="viewLog({{ $log->id }})" @click="open = false" class="{{ $menuItem }}">View details</button>
                                    @if($log->status === 'approved' && !$log->exit_time)
                                      <button type="button" onclick="checkOut({{ $log->id }})" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Check out</button>
                                    @endif
                                  </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No security logs found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><a href="{{ route('security.quick-entry') }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">Create your first entry →</a></p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Log Modal -->
<div id="logModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px]" onclick="closeModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-800">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Log Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="logDetails" class="mt-4 space-y-3">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<script>
function checkOut(logId) {
    if (!confirm('Check out this visitor?')) return;
    
    fetch('/security/api/check-out', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ visitor_id: logId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Visitor checked out successfully!');
            (window.schargeRefresh || (() => location.reload()))();
        } else {
            alert('' + (data.message || 'Failed to check out visitor'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to check out visitor');
    });
}

function viewLog(logId) {
    fetch(`/security/logs/${logId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const log = data.log;
                const details = document.getElementById('logDetails');
                details.innerHTML = `
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500">Visitor</label>
                            <p class="text-sm font-medium text-gray-800">${log.person_name || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Phone</label>
                            <p class="text-sm text-gray-600">${log.visitor_phone || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Unit</label>
                            <p class="text-sm text-gray-600">${log.unit_number || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Purpose</label>
                            <p class="text-sm text-gray-600">${log.purpose || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Check In</label>
                            <p class="text-sm text-gray-600">${log.datetime_formatted || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Status</label>
                            <p class="text-sm font-medium ${log.status === 'approved' ? 'text-green-600' : 'text-gray-600'}">${log.status_label || log.status}</p>
                        </div>
                        ${log.vehicle ? `
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-500">Vehicle</label>
                            <p class="text-sm text-gray-600">${log.vehicle}</p>
                        </div>
                        ` : ''}
                        ${log.vehicle_type ? `
                        <div>
                            <label class="text-xs font-medium text-gray-500">Vehicle Type</label>
                            <p class="text-sm text-gray-600">${log.vehicle_type}</p>
                        </div>
                        ` : ''}
                        ${log.vehicle_model ? `
                        <div>
                            <label class="text-xs font-medium text-gray-500">Vehicle Model</label>
                            <p class="text-sm text-gray-600">${log.vehicle_model}</p>
                        </div>
                        ` : ''}
                        ${log.vehicle_color ? `
                        <div>
                            <label class="text-xs font-medium text-gray-500">Vehicle Color</label>
                            <p class="text-sm text-gray-600">${log.vehicle_color}</p>
                        </div>
                        ` : ''}
                        ${log.notes ? `
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-500">Notes</label>
                            <p class="text-sm text-gray-600">${log.notes}</p>
                        </div>
                        ` : ''}
                    </div>
                `;
                document.getElementById('logModal').classList.remove('hidden');
            }
        })
        .catch(error => console.error('Error:', error));
}

function closeModal() {
    document.getElementById('logModal').classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
@endsection