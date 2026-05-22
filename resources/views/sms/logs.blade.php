@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>SMS Logs</h4>
                    <a href="{{ route('sms.broadcast') }}" class="btn btn-primary float-end">New Broadcast</a>
                </div>
                <div class="card-body">
                    <form method="GET" class="row mb-3">
                        <div class="col-md-3">
                            <input type="text" name="phone" class="form-control" placeholder="Phone number" value="{{ request('phone') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" name="start_date" class="form-control" placeholder="Start Date" value="{{ request('start_date') }}">
                        </div>
                        <div class="col-md-3">
                            <input type="date" name="end_date" class="form-control" placeholder="End Date" value="{{ request('end_date') }}">
                        </div>
                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="{{ route('sms.logs') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Phone</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>{{ $log->recipient_phone }}</td>
                                    <td>{{ Str::limit($log->message, 80) }}</td>
                                    <td>
                                        @if($log->status == 'sent')
                                            <span class="badge bg-success">Sent</span>
                                        @elseif($log->status == 'failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @elseif($log->status == 'delivered')
                                            <span class="badge bg-info">Delivered</span>
                                        @else
                                            <span class="badge bg-warning">{{ $log->status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No SMS logs found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
