@extends('layouts.app')

@section('title', 'Security Logs')

@section('content')
<div class="flex flex-col gap-5 p-6">
    <!-- Header -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Security Logs
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    View all visitor check-ins and check-outs
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('security.index') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Dashboard
                </a>
                <a href="{{ route('security.quick-entry') }}" 
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 dark:bg-brand-600 dark:hover:bg-brand-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    New Entry
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <form method="GET" action="{{ route('security.logs.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date</label>
                <input type="date" name="date" value="{{ request('date') }}"
                       class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estate</label>
                <select name="estate_id" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">All Estates</option>
                    @foreach($estates as $estate)
                        <option value="{{ $estate->id }}" {{ request('estate_id') == $estate->id ? 'selected' : '' }}>
                            {{ $estate->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status" class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="all">All Status</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Checked In</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Checked Out</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                    Filter
                </button>
                <a href="{{ route('security.logs.index') }}" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        @if(isset($error))
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">
                {{ $error }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visitor</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-3 text-sm text-gray-800">
                                <div class="font-medium">{{ $log->visitor_name_snapshot ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $log->purpose ?? 'N/A' }}</div>
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-600">{{ $log->visitor_phone_snapshot ?? 'N/A' }}</td>
                            <td class="px-3 py-3 text-sm text-gray-600">
                                <div>{{ $log->unit->unit_number ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">{{ $log->estate->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-600">
                                @if($log->vehicle_type || $log->vehicle_model)
                                    <div>{{ $log->vehicle_type ?? '' }} {{ $log->vehicle_model ?? '' }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->vehicle_color ?? '' }} {{ $log->vehicle_registration_snapshot ?? '' }}</div>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-600">
                                {{ $log->access_time ? $log->access_time->format('M d, Y H:i') : 'N/A' }}
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-600">
                                {{ $log->exit_time ? $log->exit_time->format('M d, Y H:i') : '—' }}
                            </td>
                            <td class="px-3 py-3 text-sm">
                                @if($log->status === 'approved' && !$log->exit_time)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        Checked In
                                    </span>
                                @elseif($log->status === 'completed' || $log->exit_time)
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-400">
                                        Checked Out
                                    </span>
                                @elseif($log->status === 'pending')
                                    <span class="inline-flex rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm">
                                @if($log->status === 'approved' && !$log->exit_time)
                                    <button onclick="checkOut({{ $log->id }})" 
                                            class="inline-flex items-center rounded-lg bg-red-500 px-3 py-1 text-xs font-medium text-white hover:bg-red-600">
                                        Check Out
                                    </button>
                                @endif
                                <button onclick="viewLog({{ $log->id }})" 
                                        class="inline-flex items-center rounded-lg bg-blue-500 px-3 py-1 text-xs font-medium text-white hover:bg-blue-600">
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">
                                No security logs found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>

<!-- View Log Modal -->
<div id="logModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeModal()"></div>
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
            alert('✅ Visitor checked out successfully!');
            location.reload();
        } else {
            alert('❌ ' + (data.message || 'Failed to check out visitor'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to check out visitor');
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
                            <label class="text-xs font-medium text-gray-500">Vehicle Registration</label>
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

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
@endsection