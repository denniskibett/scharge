@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>SMS Logs</h4>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <table>
                                <th>Phone</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                            <tr>
                                <td>{{ $log->recipient_phone }}</td>
                                <td>{{ Str::limit($log->message, 50) }}</td>
                                <td>
                                    <span class="badge bg-{{ $log->status == 'sent' ? 'success' : 'danger' }}">
                                        {{ $log->status }}
                                    </span>
                                </td>
                                <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
