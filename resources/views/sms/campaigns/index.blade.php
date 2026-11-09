@extends('layouts.app')

@section('content')
@php
    $campStatus = request('status', '');
    $campTab = fn ($v) => request()->fullUrlWithQuery(['status' => $v, 'page' => null]);
    $campTabs = [
        ['label' => 'All', 'url' => $campTab(null), 'active' => $campStatus === '' || $campStatus === 'all'],
        ['label' => 'Pending', 'url' => $campTab('pending'), 'active' => $campStatus === 'pending', 'tone' => 'amber'],
        ['label' => 'Sending', 'url' => $campTab('sending'), 'active' => $campStatus === 'sending', 'tone' => 'blue'],
        ['label' => 'Completed', 'url' => $campTab('completed'), 'active' => $campStatus === 'completed', 'tone' => 'green'],
        ['label' => 'Failed', 'url' => $campTab('failed'), 'active' => $campStatus === 'failed', 'tone' => 'red'],
    ];
    $campPill = ['pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200', 'sending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'];
    $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
@endphp
<div class="flex flex-col gap-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Total Campaigns</span>
            <h4 class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $campaigns->total() }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Completed</span>
            <h4 class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $campaigns->where('status', 'completed')->count() }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Pending / Sending</span>
            <h4 class="mt-1 text-2xl font-semibold text-amber-600">{{ $campaigns->whereIn('status', ['pending', 'sending'])->count() }}</h4>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <span class="text-sm text-gray-500 dark:text-gray-400">Failed</span>
            <h4 class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ $campaigns->where('status', 'failed')->count() }}</h4>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            @include('partials.table.tabs', ['tabs' => $campTabs])
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <a href="{{ route('sms.broadcast') }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <a href="{{ route('sms.campaigns.create') }}" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> New Campaign</a>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">#</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Name</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Description</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Recipients</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Sent</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Failed</th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Created</th>
                        <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($campaigns as $campaign)
                    <tr id="campaign-row-{{ $campaign->id }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $loop->iteration + ($campaigns->currentPage() - 1) * $campaigns->perPage() }}</td>
                        <td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">{{ $campaign->name }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $campaign->description ? Str::limit($campaign->description, 50) : '-' }}</td>
                        <td class="p-4 whitespace-nowrap"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $campPill[$campaign->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">{{ ucfirst($campaign->status) }}</span></td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $campaign->total_recipients }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400">{{ $campaign->sent_count ?? 0 }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-red-600 dark:text-red-400">{{ $campaign->failed_count ?? 0 }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $campaign->created_at->format('M d, Y') }}</td>
                        <td class="p-4 whitespace-nowrap text-right">
                            <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                              <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions"><svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg></button>
                              <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                <a href="{{ route('sms.campaigns.show', $campaign->id) }}" class="{{ $menuItem }}">View details</a>
                                @if(in_array($campaign->status, ['pending', 'failed']))
                                  <button type="button" onclick="sendCampaign({{ $campaign->id }})" @click="open = false" class="{{ $menuItem }}">Send campaign</button>
                                @endif
                                <button type="button" onclick="duplicateCampaign({{ $campaign->id }})" @click="open = false" class="{{ $menuItem }}">Duplicate</button>
                                <a href="{{ route('sms.campaigns.export', $campaign->id) }}" class="{{ $menuItem }}">Export CSV</a>
                                @if(in_array($campaign->status, ['pending', 'failed']))
                                  <button type="button" onclick="deleteCampaign({{ $campaign->id }})" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                                @endif
                              </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No campaigns found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><a href="{{ route('sms.campaigns.create') }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">Create your first campaign</a></p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.table.pager', ['paginator' => $campaigns->appends(request()->query())])
    </div>
</div>

@push('scripts')
<script>
// Simple AJAX actions (Send, Duplicate, Delete)
function sendCampaign(id) {
    if (!confirm('Send this campaign to all recipients?')) return;
    $.ajax({
        url: '/sms/campaigns/' + id + '/send',
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(response) {
            if (response.success) {
                toastr.success('Campaign is being sent!');
                setTimeout(function() { (window.schargeRefresh || (() => location.reload()))(); }, 2000);
            } else {
                toastr.error(response.message || 'Failed to send');
            }
        },
        error: function(xhr) {
            toastr.error('Error sending campaign');
        }
    });
}

function duplicateCampaign(id) {
    if (!confirm('Duplicate this campaign?')) return;
    $.ajax({
        url: '/sms/campaigns/' + id + '/duplicate',
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(response) {
            if (response.success) {
                toastr.success('Campaign duplicated!');
                (window.schargeRefresh || (() => location.reload()))();
            } else {
                toastr.error(response.message || 'Failed to duplicate');
            }
        },
        error: function(xhr) {
            toastr.error('Error duplicating campaign');
        }
    });
}

function deleteCampaign(id) {
    if (!confirm('Delete this campaign? This cannot be undone.')) return;
    $.ajax({
        url: '/sms/campaigns/' + id,
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(response) {
            if (response.success) {
                toastr.success('Campaign deleted');
                $('#campaign-row-' + id).remove();
            } else {
                toastr.error(response.message || 'Failed to delete');
            }
        },
        error: function(xhr) {
            toastr.error('Error deleting campaign');
        }
    });
}
</script>
@endpush
@endsection