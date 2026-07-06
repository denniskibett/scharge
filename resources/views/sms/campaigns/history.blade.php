{{-- resources/views/sms/campaigns/history.blade.php --}}
@extends('layouts.app')

@section('title', 'Campaign History')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-black dark:text-white">Campaign History</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">View all campaigns synced from KenyaSMS</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('sms.campaigns.index') }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Campaigns
            </a>
            <a href="{{ route('sms.sync-campaigns') }}" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm text-white hover:bg-primary/90" onclick="this.innerHTML='⏳ Syncing...'">
                <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Sync Now
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Campaigns</span>
            <h4 class="mt-1 text-2xl font-bold text-black dark:text-white">{{ number_format($summary->total_campaigns ?? 0) }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Recipients</span>
            <h4 class="mt-1 text-2xl font-bold text-black dark:text-white">{{ number_format($summary->total_recipients ?? 0) }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Sent</span>
            <h4 class="mt-1 text-2xl font-bold text-primary">{{ number_format($summary->total_sent ?? 0) }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Delivered</span>
            <h4 class="mt-1 text-2xl font-bold text-success">{{ number_format($summary->total_delivered ?? 0) }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Failed</span>
            <h4 class="mt-1 text-2xl font-bold text-danger">{{ number_format($summary->total_failed ?? 0) }}</h4>
        </div>
        <div class="rounded-sm border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Cost</span>
            <h4 class="mt-1 text-2xl font-bold text-black dark:text-white">KES {{ number_format($summary->total_cost ?? 0, 2) }}</h4>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-sm border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
        <form method="GET" action="{{ route('sms.campaigns.history') }}" class="flex flex-wrap items-center gap-4">
            <div>
                <select name="estate_id" class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
                    <option value="">All Estates</option>
                    @foreach($estates as $estate)
                        <option value="{{ $estate->id }}" {{ request('estate_id') == $estate->id ? 'selected' : '' }}>
                            {{ $estate->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="month" class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
                    <option value="">All Months</option>
                    @foreach($months as $month)
                        <option value="{{ $month }}" {{ request('month') == $month ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
                    <option value="">All Status</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="sending" {{ request('status') == 'sending' ? 'selected' : '' }}>Sending</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search campaigns..." class="rounded-md border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-boxdark">
            </div>
            <button type="submit" class="rounded-md bg-primary px-4 py-1.5 text-sm text-white hover:bg-primary/90">Filter</button>
            <a href="{{ route('sms.campaigns.history') }}" class="rounded-md border border-stroke px-4 py-1.5 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">Clear</a>
        </form>
    </div>

    <!-- Campaigns Table -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campaign</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estate</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Recipients</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Sent</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Delivered</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stroke dark:divide-strokedark">
                    @forelse($campaigns as $campaign)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $campaign->id }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-black dark:text-white">{{ $campaign->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $campaign->estate_name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {{ $campaign->month ? \Carbon\Carbon::createFromFormat('Y-m', $campaign->month)->format('M Y') : 'N/A' }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ $campaign->total_recipients }}</td>
                        <td class="px-4 py-3 text-center text-sm text-blue-600">{{ $campaign->sent_count }}</td>
                        <td class="px-4 py-3 text-center text-sm text-success">{{ $campaign->delivered_count }}</td>
                        <td class="px-4 py-3 text-center text-sm text-danger">{{ $campaign->failed_count }}</td>
                        <td class="px-4 py-3 text-center text-sm font-medium text-black dark:text-white">KES {{ number_format($campaign->actual_cost, 2) }}</td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $statusColors = [
                                    'completed' => 'bg-green-100 text-green-700 dark:bg-green-700 dark:text-green-300',
                                    'sending' => 'bg-purple-100 text-purple-700 dark:bg-purple-700 dark:text-purple-300',
                                    'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-700 dark:text-blue-300',
                                    'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-700 dark:text-red-300',
                                    'cancelled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$campaign->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ $campaign->sent_at ? \Carbon\Carbon::parse($campaign->sent_at)->format('M d, Y H:i') : '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="mt-2">No campaigns found.</p>
                            <a href="{{ route('sms.sync-campaigns') }}" class="mt-2 inline-block text-primary hover:underline">Sync campaigns from KenyaSMS</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($campaigns, 'links'))
        <div class="border-t border-stroke px-6 py-4 dark:border-strokedark">
            {{ $campaigns->links() }}
        </div>
        @endif
    </div>
</div>
@endsection