@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bullhorn text-primary"></i> SMS Campaigns
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('sms.campaigns.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Campaign
                        </a>
                        <a href="{{ route('sms.broadcast') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Stats Cards -->
                    <div class="row mb-3">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>{{ $campaigns->total() }}</h3>
                                    <p>Total Campaigns</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-list"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>{{ $campaigns->where('status', 'completed')->count() }}</h3>
                                    <p>Completed</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>{{ $campaigns->whereIn('status', ['pending', 'sending'])->count() }}</h3>
                                    <p>Pending / Sending</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3>{{ $campaigns->where('status', 'failed')->count() }}</h3>
                                    <p>Failed</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Form (reloads page) -->
                    <form method="GET" action="{{ route('sms.campaigns.index') }}" class="row mb-3">
                        <div class="col-md-3">
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="sending" {{ request('status') == 'sending' ? 'selected' : '' }}>Sending</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                        <div class="col-md-1">
                            <a href="{{ route('sms.campaigns.index') }}" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>

                    <!-- Campaigns Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Recipients</th>
                                    <th>Sent</th>
                                    <th>Failed</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaigns as $campaign)
                                <tr id="campaign-row-{{ $campaign->id }}">
                                    <td>{{ $loop->iteration + ($campaigns->currentPage() - 1) * $campaigns->perPage() }}</td>
                                    <td><strong>{{ $campaign->name }}</strong></td>
                                    <td>{{ $campaign->description ? Str::limit($campaign->description, 50) : '-' }}</td>
                                    <td>{!! getStatusBadge($campaign->status) !!}</td>
                                    <td class="text-center">{{ $campaign->total_recipients }}</td>
                                    <td class="text-center text-success">{{ $campaign->sent_count ?? 0 }}</td>
                                    <td class="text-center text-danger">{{ $campaign->failed_count ?? 0 }}</td>
                                    <td>{{ $campaign->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <!-- View -->
                                            <a href="{{ route('sms.campaigns.show', $campaign->id) }}" class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <!-- Send (only for pending/failed) -->
                                            @if(in_array($campaign->status, ['pending', 'failed']))
                                            <button onclick="sendCampaign({{ $campaign->id }})" class="btn btn-sm btn-success" title="Send Campaign">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                            @endif
                                            <!-- Duplicate -->
                                            <button onclick="duplicateCampaign({{ $campaign->id }})" class="btn btn-sm btn-warning" title="Duplicate">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <!-- Delete (only for pending/failed) -->
                                            @if(in_array($campaign->status, ['pending', 'failed']))
                                            <button onclick="deleteCampaign({{ $campaign->id }})" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endif
                                            <!-- Export -->
                                            <a href="{{ route('sms.campaigns.export', $campaign->id) }}" class="btn btn-sm btn-secondary" title="Export CSV">
                                                <i class="fas fa-file-export"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        <i class="fas fa-info-circle"></i> No campaigns found.
                                        <br><a href="{{ route('sms.campaigns.create') }}" class="btn btn-primary btn-sm mt-2">Create your first campaign</a>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="row mt-3">
                        <div class="col-12">
                            {{ $campaigns->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                setTimeout(function() { location.reload(); }, 2000);
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
                location.reload();
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