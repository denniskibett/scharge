{{-- resources/views/sms/campaigns/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Campaign Details')

@section('content')
<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- Alerts -->
    @if(session('success'))
    <div class="mb-4 rounded-sm border-l-4 border-success bg-success/10 p-4 text-success">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <strong>Success!</strong> {{ session('success') }}
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 rounded-sm border-l-4 border-danger bg-danger/10 p-4 text-danger">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <strong>Error!</strong> {{ session('error') }}
        </div>
    </div>
    @endif

    <!-- Header -->
    <div class="mb-6 rounded-sm border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-black dark:text-white">{{ $campaign->name }}</h1>
                @php
                    $statusColors = [
                        'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                        'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-700 dark:text-blue-300',
                        'queued' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-700 dark:text-yellow-300',
                        'sending' => 'bg-purple-100 text-purple-700 dark:bg-purple-700 dark:text-purple-300',
                        'completed' => 'bg-green-100 text-green-700 dark:bg-green-700 dark:text-green-300',
                        'failed' => 'bg-red-100 text-red-700 dark:bg-red-700 dark:text-red-300',
                        'cancelled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                    ];
                @endphp
                <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$campaign->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ ucfirst($campaign->status) }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Created: {{ $campaign->created_at->format('M d, Y H:i') }}
                </span>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('sms.campaigns.index') }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                
                @if($campaign->status == 'draft')
                    <a href="{{ route('sms.campaigns.edit', $campaign) }}" class="inline-flex items-center rounded-md bg-warning px-4 py-2 text-sm text-white hover:bg-warning/90">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                    <form action="{{ route('sms.campaigns.destroy', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Delete this campaign? This action cannot be undone!')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center rounded-md bg-danger px-4 py-2 text-sm text-white hover:bg-danger/90">
                            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>
                    </form>
                @endif

                <!-- SEND BUTTON -->
                @if(in_array($campaign->status, ['draft', 'scheduled']))
                    <form action="{{ route('sms.campaigns.send', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Send this campaign now? This will send SMS to all ' + {{ $stats['total'] }} + ' recipients.')">
                        @csrf
                        <button type="submit" id="sendBtn" class="inline-flex items-center rounded-md bg-success px-4 py-2 text-sm text-white hover:bg-success/90">
                            <svg id="sendIcon" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            <span id="sendText">Send</span>
                        </button>
                    </form>
                @endif

                @if($campaign->status == 'scheduled' || $campaign->status == 'queued')
                    <form action="{{ route('sms.campaigns.cancel', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this campaign?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md bg-danger px-4 py-2 text-sm text-white hover:bg-danger/90">
                            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Cancel
                        </button>
                    </form>
                @endif

                @if($campaign->status == 'completed' && $stats['failed'] > 0)
                    <form action="{{ route('sms.campaigns.resend-failed', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Resend failed messages?')">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md bg-warning px-4 py-2 text-sm text-white hover:bg-warning/90">
                            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Resend Failed
                        </button>
                    </form>
                @endif

                <form action="{{ route('sms.campaigns.duplicate', $campaign) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm text-white hover:bg-primary/90">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Duplicate
                    </button>
                </form>

                <a href="{{ route('sms.campaigns.export', $campaign) }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-5">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                <p class="mt-0.5 font-medium text-black dark:text-white">{{ ucfirst($campaign->status) }}</p>
            </div>
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Estate</span>
                <p class="mt-0.5 font-medium text-black dark:text-white">{{ $campaign->estate->name ?? 'N/A' }}</p>
            </div>
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Billing Month</span>
                <p class="mt-0.5 font-medium text-black dark:text-white">{{ $campaign->billing_month }}</p>
            </div>
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Cost</span>
                <p class="mt-0.5 font-medium text-black dark:text-white">
                    KES {{ number_format($campaign->estimated_cost, 2) }}
                    <span class="text-sm text-gray-500 font-normal">Actual: KES {{ number_format($campaign->actual_cost ?? 0, 2) }}</span>
                </p>
            </div>
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">Total</span>
                <p class="mt-0.5 font-medium text-black dark:text-white">{{ $stats['total'] }}</p>
            </div>
        </div>
    </div>

    <!-- Progress -->
    @if($stats['total'] > 0)
    <div class="mb-6 rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-black dark:text-white">Delivery Progress</span>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $stats['delivered'] }} / {{ $stats['total'] }} ({{ $stats['success_rate'] }}%)
            </span>
        </div>
        <div class="relative mt-3 h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="h-2.5 rounded-full bg-success transition-all" style="width: {{ $stats['success_rate'] }}%;"></div>
        </div>
        <div class="mt-3 flex flex-wrap gap-5 text-sm">
            <span class="flex items-center gap-2 text-success">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-success"></span>
                {{ $stats['delivered'] }} Delivered
            </span>
            <span class="flex items-center gap-2 text-danger">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-danger"></span>
                {{ $stats['failed'] }} Failed
            </span>
            <span class="flex items-center gap-2 text-warning">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-warning"></span>
                {{ $stats['pending'] ?? 0 }} Pending
            </span>
        </div>
    </div>
    @endif

    <!-- Stats Cards -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total</span>
            <h4 class="mt-1 text-2xl font-bold text-black dark:text-white">{{ $stats['total'] }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Delivered</span>
            <h4 class="mt-1 text-2xl font-bold text-success">{{ $stats['delivered'] }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Failed</span>
            <h4 class="mt-1 text-2xl font-bold text-danger">{{ $stats['failed'] }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Success Rate</span>
            <h4 class="mt-1 text-2xl font-bold text-primary">{{ $stats['success_rate'] }}%</h4>
        </div>
    </div>

    <!-- Three Columns -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Campaign Information -->
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">Campaign Information</h5>
            </div>
            <div class="p-6">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Campaign Name</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Type</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">Water Bill Campaign</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Estate</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->estate->name ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Billing Month</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->billing_month }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Created By</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->createdBy->name ?? 'Super Admin' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-sm font-medium">
                            <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$campaign->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Timeline -->
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">Timeline</h5>
            </div>
            <div class="p-6">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Scheduled</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->scheduled_at ? $campaign->scheduled_at->format('M d, Y H:i') : 'Not scheduled' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Sent</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->sent_at ? $campaign->sent_at->format('M d, Y H:i') : 'Not sent' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Completed</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">{{ $campaign->completed_at ? $campaign->completed_at->format('M d, Y H:i') : 'Not completed' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Cost Summary -->
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
                <h5 class="text-base font-semibold text-black dark:text-white">Cost Summary</h5>
            </div>
            <div class="p-6">
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Estimated Cost</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">KES {{ number_format($campaign->estimated_cost, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Actual Cost</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">KES {{ number_format($campaign->actual_cost ?? 0, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Cost Per SMS</dt>
                        <dd class="text-sm font-medium text-black dark:text-white">KES {{ number_format($campaign->cost_per_sms ?? 0, 4) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- Message Preview -->
    <div class="mb-6 rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="border-b border-stroke px-6 py-4 dark:border-strokedark">
            <h5 class="text-base font-semibold text-black dark:text-white">Message Preview</h5>
        </div>
        <div class="p-6">
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                <div class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">
                    {{ $campaign->message }}
                </div>
            </div>
            @if(strpos($campaign->message, '{{') !== false)
                <div class="mt-3 text-xs text-gray-500">
                    <strong>Placeholders:</strong>
                    @php
                        preg_match_all('/{{([^}]*)}}/', $campaign->message, $matches);
                        $placeholders = array_unique($matches[1] ?? []);
                    @endphp
                    @foreach($placeholders as $placeholder)
                        <span class="inline-block rounded bg-blue-100 px-2 py-0.5 text-xs font-mono dark:bg-blue-900">{{ $placeholder }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Recipients Table -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="flex flex-wrap items-center justify-between border-b border-stroke px-6 py-4 dark:border-strokedark">
            <h5 class="text-base font-semibold text-black dark:text-white">
                Recipients
                <span class="ml-2 rounded-full bg-primary/10 px-3 py-0.5 text-sm text-primary">{{ $stats['total'] }}</span>
            </h5>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm text-success">● {{ $stats['delivered'] }}</span>
                <span class="text-sm text-danger">● {{ $stats['failed'] }}</span>
                <span class="text-sm text-warning">● {{ $stats['pending'] ?? 0 }}</span>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="border-b border-stroke dark:border-strokedark">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failure Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipients as $index => $recipient)
                        <tr class="border-b border-stroke last:border-0 dark:border-strokedark hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-black dark:text-white">{{ $recipient->unit_number ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $recipient->tenant_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $recipient->phone }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-black dark:text-white">KES {{ number_format($recipient->water_bill ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $recipientColors = [
                                        'delivered' => 'bg-success/10 text-success',
                                        'sent' => 'bg-blue-100 text-blue-700 dark:bg-blue-700 dark:text-blue-300',
                                        'queued' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-700 dark:text-yellow-300',
                                        'failed' => 'bg-danger/10 text-danger',
                                        'pending' => 'bg-warning/10 text-warning',
                                    ];
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $recipientColors[$recipient->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($recipient->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ $recipient->sent_at ? $recipient->sent_at->format('M d, H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ $recipient->failure_reason ?? '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No recipients found for this campaign.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($recipients, 'links'))
            <div class="mt-4">
                {{ $recipients->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sendBtn = document.getElementById('sendBtn');
    const sendText = document.getElementById('sendText');
    const sendIcon = document.getElementById('sendIcon');

    if (sendBtn) {
        sendBtn.addEventListener('click', function(e) {
            // Show loading state immediately
            sendBtn.disabled = true;
            sendBtn.classList.add('opacity-75', 'cursor-not-allowed');
            sendText.textContent = 'Sending...';
            sendIcon.innerHTML = `
                <svg class="mr-1.5 h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            `;
        });
    }

    // Check if there's a success message and reset button state
    const successMessage = document.querySelector('.text-success');
    const errorMessage = document.querySelector('.text-danger');
    
    if (successMessage || errorMessage) {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            sendText.textContent = 'Send';
            sendIcon.innerHTML = `
                <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            `;
        }
    }
});
</script>
@endsection