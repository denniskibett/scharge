@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-3">Payees</h3>

    <!-- Create Button -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createPayeeModal">Add Payee</button>

    <!-- Payees Table -->
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Expenses Count</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payees as $payee)
            <tr>
                <td>{{ $payee->name }}</td>
                <td>{{ ucfirst($payee->type) }}</td>
                <td>{{ $payee->phone }}</td>
                <td>{{ $payee->email }}</td>
                <td>{{ $payee->expenses->count() }}</td>
                <td>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#showPayeeModal{{ $payee->id }}">View</button>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editPayeeModal{{ $payee->id }}">Edit</button>
                    <form action="{{ route('payees.destroy', $payee) }}" method="POST" class="d-inline">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete payee?')">Delete</button>
                    </form>
                </td>
            </tr>

            <!-- Show Modal -->
            <div class="modal fade" id="showPayeeModal{{ $payee->id }}">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5>View Payee</h5></div>
                        <div class="modal-body">
                            <p><strong>Name:</strong> {{ $payee->name }}</p>
                            <p><strong>Type:</strong> {{ ucfirst($payee->type) }}</p>
                            <p><strong>Phone:</strong> {{ $payee->phone }}</p>
                            <p><strong>Email:</strong> {{ $payee->email }}</p>
                            <p><strong>Expenses:</strong> {{ $payee->expenses->count() }}</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editPayeeModal{{ $payee->id }}">
                <div class="modal-dialog">
                    <form action="{{ route('payees.update', $payee) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header"><h5>Edit Payee</h5></div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label>Name</label>
                                    <input type="text" name="name" class="form-control" value="{{ $payee->name }}">
                                </div>
                                <div class="mb-2">
                                    <label>Type</label>
                                    <select name="type" class="form-select">
                                        <option value="staff" @if($payee->type=='staff') selected @endif>Staff</option>
                                        <option value="vendor" @if($payee->type=='vendor') selected @endif>Vendor</option>
                                        <option value="utility" @if($payee->type=='utility') selected @endif>Utility</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" value="{{ $payee->phone }}">
                                </div>
                                <div class="mb-2">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ $payee->email }}">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary">Update</button>
                                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @endforeach
        </tbody>
    </table>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createPayeeModal">
    <div class="modal-dialog">
        <form action="{{ route('payees.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5>Add Payee</h5></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Type</label>
                        <select name="type" class="form-select">
                            <option value="staff">Staff</option>
                            <option value="vendor">Vendor</option>
                            <option value="utility">Utility</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
