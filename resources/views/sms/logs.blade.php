@extends('layouts.app')

@section('title', 'SMS Logs')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">SMS Logs</h1>
        <p class="text-gray-500">View and filter all sent SMS messages.</p>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="{{ route('sms.logs') }}" id="filterForm" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full rounded-lg border border-gray-300 p-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full rounded-lg border border-gray-300 p-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border border-gray-300 p-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Delivered</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="text" name="phone" value="{{ request('phone') }}" placeholder="e.g., 2547..." class="w-full rounded-lg border border-gray-300 p-2 text-sm">
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-4">
            <button type="button" onclick="resetFilters()" class="rounded-lg bg-gray-500 px-4 py-2 text-sm text-white hover:bg-gray-600">
                Reset Filters
            </button>
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm text-white hover:bg-brand-600">
                Apply Filters
            </button>
        </div>
    </form>

    <!-- Logs Table -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="p-3 text-left">ID</th>
                        <th class="p-3 text-left">Phone</th>
                        <th class="p-3 text-left">Message</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">Provider ID</th>
                        <th class="p-3 text-left">Failure Reason</th>
                        <th class="p-3 text-left">Sent At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-t hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-3">{{ $log->id }}</td>
                        <td class="p-3">{{ $log->recipient_phone }}</td>
                        <td class="p-3">{{ Str::limit($log->message, 60) }}</td>
                        <td class="p-3">
                            @if($log->status == 'sent')
                                <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">delivered</span>
                            @elseif($log->status == 'pending')
                                <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">pending</span>
                            @elseif($log->status == 'failed')
                                <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">failed</span>
                            @elseif($log->status == 'delivered')
                                <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">delivered</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">{{ $log->status }}</span>
                            @endif
                        </td>
                        <td class="p-3">{{ $log->provider_message_id ?? '-' }}</td>
                        <td class="p-3">{{ Str::limit($log->failure_reason, 30) ?? '-' }}</td>
                        <td class="p-3">{{ $log->created_at ? $log->created_at->format('d-m-Y H:i') : '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-3 text-center text-gray-500">No SMS logs found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $logs->appends(request()->query())->links() }}
        </div>
    </div>
</div>

<script>
function resetFilters() {
    window.location.href = '{{ route('sms.logs') }}';
}
</script>
@endsection