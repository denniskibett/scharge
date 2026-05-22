@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>SMS Broadcast</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('sms.send') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label>Select Recipients</label>
                            <select name="recipients" class="form-control" required>
                                <option value="">Select tenants...</option>
                                @foreach($tenants as $tenant)
                                <option value="{{ json_encode([['phone' => $tenant['phone'], 'variables' => ['name' => $tenant['name']]]) }}">
                                    {{ $tenant['name'] }} - {{ $tenant['unit_number'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Message Template</label>
                            <textarea name="template" class="form-control" rows="5" required></textarea>
                            <small>Use {{'{{'}}name{{'}}'}} for personalization</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Message Type</label>
                            <select name="message_type" class="form-control">
                                <option value="transactional">Transactional</option>
                                <option value="promotional">Promotional</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send SMS</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
