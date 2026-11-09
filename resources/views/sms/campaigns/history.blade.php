{{-- resources/views/sms/campaigns/history.blade.php --}}
@extends('layouts.app')

@section('title', 'Campaign History')

@section('content')
<div>
    
    <!-- Header -->
    <div class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Campaign History</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">View all campaigns synced from KenyaSMS</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('sms.campaigns.index') }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Campaigns
            </a>
            <a href="{{ route('sms.sync-campaigns') }}" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition" onclick="this.innerHTML='⏳ Syncing...'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Sync Now
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Campaigns</span>
            <h4 class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary->total_campaigns ?? 0) }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Recipients</span>
            <h4 class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($summary->total_recipients ?? 0) }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Sent</span>
            <h4 class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ number_format($summary->total_sent ?? 0) }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Delivered</span>
            <h4 class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ number_format($summary->total_delivered ?? 0) }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Failed</span>
            <h4 class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ number_format($summary->total_failed ?? 0) }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Cost</span>
            <h4 class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">KES {{ number_format($summary->total_cost ?? 0, 2) }}</h4>
        </div>
    </div>

    <!-- Campaigns Table (filters in its header) -->
    @php
        $histMonths = ['' => 'All Months'];
        foreach ($months as $m) { $histMonths[$m] = \Carbon\Carbon::createFromFormat('Y-m', $m)->format('F Y'); }
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" action="{{ route('sms.campaigns.history') }}" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Campaigns</h3>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @include('partials.table.select', ['name' => 'property_id', 'aria' => 'Property', 'selected' => request('property_id'),
                    'options' => ['' => 'All Properties'] + collect($properties)->pluck('name', 'id')->all()])
                @include('partials.table.select', ['name' => 'month', 'aria' => 'Month', 'selected' => request('month'), 'options' => $histMonths])
                @include('partials.table.select', ['name' => 'status', 'aria' => 'Status', 'selected' => request('status'), 'min' => 'min-w-[120px]',
                    'options' => ['' => 'All Status', 'draft' => 'Draft', 'scheduled' => 'Scheduled', 'sending' => 'Sending', 'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled']])
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
                    <input type="search" name="search" size="14" value="{{ request('search') }}" placeholder="Search campaigns..." aria-label="Search campaigns" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[160px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                </div>
                @if (request('property_id') || request('month') || request('status') || request('search'))
                    <a href="{{ route('sms.campaigns.history') }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Clear</a>
                @endif
            </div>
        </form>

        <div class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">#</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Campaign</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Property</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Month</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Recipients</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Sent</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Delivered</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Failed</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Cost</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Sent At</th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($campaigns as $campaign)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $campaign->id }}</td>
                        <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">{{ $campaign->name }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $campaign->property_name ?? 'N/A' }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $campaign->month ? \Carbon\Carbon::createFromFormat('Y-m', $campaign->month)->format('M Y') : 'N/A' }}
                        </td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $campaign->total_recipients }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-blue-600 dark:text-blue-400">{{ $campaign->sent_count }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">{{ $campaign->delivered_count }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-red-600 dark:text-red-400">{{ $campaign->failed_count }}</td>
                        <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">KES {{ number_format($campaign->actual_cost, 2) }}</td>
                        <td class="p-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'sending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$campaign->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $campaign->sent_at ? \Carbon\Carbon::parse($campaign->sent_at)->format('M d, Y H:i') : '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No campaigns found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><a href="{{ route('sms.sync-campaigns') }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">Sync campaigns from KenyaSMS</a></p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($campaigns, 'lastPage'))
            @include('partials.table.pager', ['paginator' => $campaigns->appends(request()->query())])
        @elseif(method_exists($campaigns, 'links'))
            <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">{{ $campaigns->links() }}</div>
        @endif
    </div>
</div>
@endsection