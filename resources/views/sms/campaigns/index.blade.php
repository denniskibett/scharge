{{-- resources/views/sms/campaigns/index.blade.php --}}
@extends('layouts.app')

@section('title', 'SMS Campaigns')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-black dark:text-white">SMS Campaigns</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage your water bill SMS campaigns</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Back to SMS Manager -->
            <a href="{{ route('sms.broadcast') }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm font-medium text-black hover:bg-gray-100 dark:border-strokedark dark:text-white dark:hover:bg-gray-800">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to SMS
            </a>
            <!-- Campaign History -->
            <a href="{{ route('sms.campaigns.history') }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm font-medium text-black hover:bg-gray-100 dark:border-strokedark dark:text-white dark:hover:bg-gray-800">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Campaign History
            </a>
            <!-- New Campaign -->
            <a href="{{ route('sms.campaigns.create') }}" class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Campaign
            </a>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
    <div class="mb-4 rounded-sm border-l-4 border-success bg-success/10 p-4 text-success">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 rounded-sm border-l-4 border-danger bg-danger/10 p-4 text-danger">
        {{ session('error') }}
    </div>
    @endif

    <!-- Filters -->
    <div class="mb-6 rounded-sm border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
        <form method="GET" action="{{ route('sms.campaigns.index') }}" class="flex flex-wrap items-center gap-4">
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
            <a href="{{ route('sms.campaigns.index') }}" class="rounded-md border border-stroke px-4 py-1.5 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">Clear</a>
        </form>
    </div>

    <!-- Campaigns Table -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estate</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Recipients</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stroke dark:divide-strokedark">
                    @forelse($campaigns as $campaign)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-4 py-3 text-sm font-medium text-black dark:text-white">
                            <a href="{{ route('sms.campaigns.show', $campaign) }}" class="hover:text-primary">{{ $campaign->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $campaign->estate->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $campaign->billing_month }}</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-400">
                            <span class="inline-flex rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                {{ $campaign->total_recipients }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm">
                            @php
                                $statusColors = [
                                    'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-700 dark:text-blue-300',
                                    'sending' => 'bg-purple-100 text-purple-700 dark:bg-purple-700 dark:text-purple-300',
                                    'completed' => 'bg-green-100 text-green-700 dark:bg-green-700 dark:text-green-300',
                                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-700 dark:text-red-300',
                                    'cancelled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$campaign->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $campaign->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('sms.campaigns.show', $campaign) }}" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="View">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @if($campaign->status == 'draft')
                                    <a href="{{ route('sms.campaigns.edit', $campaign) }}" class="rounded-md p-1.5 text-yellow-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('sms.campaigns.destroy', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Delete this campaign?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-md p-1.5 text-red-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Delete">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                                @if(in_array($campaign->status, ['draft', 'scheduled']))
                                    <form action="{{ route('sms.campaigns.send', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Send this campaign now?')">
                                        @csrf
                                        <button type="submit" class="rounded-md p-1.5 text-green-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Send">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('sms.campaigns.duplicate', $campaign) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded-md p-1.5 text-blue-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Duplicate">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </form>
                                <a href="{{ route('sms.campaigns.export', $campaign) }}" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="Export CSV">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="mt-2">No campaigns found.</p>
                            <a href="{{ route('sms.campaigns.create') }}" class="mt-2 inline-block text-primary hover:underline">Create your first campaign</a>
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