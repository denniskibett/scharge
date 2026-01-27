@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-3">Expense Details</h3>

    <div class="card mb-3">
        <div class="card-body">
            <p><strong>Estate:</strong> {{ $expense->estate->name }}</p>
            <p><strong>Payee:</strong> {{ $expense->payee->name }}</p>
            <p><strong>Category:</strong> {{ $expense->category->name }}</p>
            <p><strong>Amount:</strong> {{ number_format($expense->amount,2) }}</p>
            <p><strong>Date:</strong> {{ $expense->expense_date }}</p>
            <p><strong>Status:</strong> {{ ucfirst($expense->status) }}</p>
            <p><strong>Description:</strong> {{ $expense->description }}</p>
        </div>
    </div>

    <h5>Payments</h5>
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createPaymentModal">Add Payment</button>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Amount</th>
                <th>Method</th>
                <th>Paid By</th>
                <th>Transaction ID</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expense->payments as $payment)
            <tr>
                <td>{{ number_format($payment->amount,2) }}</td>
                <td>{{ strtoupper($payment->payment_method) }}</td>
                <td>{{ $payment->paid_by }}</td>
                <td>{{ $payment->transaction_id }}</td>
                <td>{{ $payment->payment_datetime }}</td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editPaymentModal{{ $payment->id }}">Edit</button>
                    <form action="{{ route('expense_payments.destroy', $payment) }}" method="POST" class="d-inline">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete payment?')">Delete</button>
                    </form>
                </td>
            </tr>

            <!-- Edit Payment Modal -->
            <div class="modal fade" id="editPaymentModal{{ $payment->id }}">
                <div class="modal-dialog">
                    <form action="{{ route('expense_payments.update', $payment) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header"><h5>Edit Payment</h5></div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label>Amount</label>
                                    <input name="amount" class="form-control" value="{{ $payment->amount }}">
                                </div>
                                <div class="mb-2">
                                    <label>Method</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="mpesa" @if($payment->payment_method=='mpesa') selected @endif>Mpesa</option>
                                        <option value="bank" @if($payment->payment_method=='bank') selected @endif>Bank</option>
                                        <option value="cash" @if($payment->payment_method=='cash') selected @endif>Cash</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Paid By</label>
                                    <input name="paid_by" class="form-control" value="{{ $payment->paid_by }}">
                                </div>
                                <div class="mb-2">
                                    <label>Transaction ID</label>
                                    <input name="transaction_id" class="form-control" value="{{ $payment->transaction_id }}">
                                </div>
                                <div class="mb-2">
                                    <label>Date</label>
                                    <input type="datetime-local" name="payment_datetime" class="form-control" value="{{ \Carbon\Carbon::parse($payment->payment_datetime)->format('Y-m-d\TH:i') }}">
                                </div>
                                <div class="mb-2">
                                    <label>Transaction Message</label>
                                    <textarea name="transaction_message" class="form-control">{{ $payment->transaction_message }}</textarea>
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

<!-- Create Payment Modal -->
<div class="modal fade" id="createPaymentModal">
    <div class="modal-dialog">
        <form action="{{ route('expense_payments.store') }}" method="POST">
            @csrf
            <input type="hidden" name="expense_id" value="{{ $expense->id }}">
            <div class="modal-content">
                <div class="modal-header"><h5>Add Payment</h5></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Amount</label>
                        <input name="amount" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="mpesa">Mpesa</option>
                            <option value="bank">Bank</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Paid By</label>
                        <input name="paid_by" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Transaction ID</label>
                        <input name="transaction_id" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Date</label>
                        <input type="datetime-local" name="payment_datetime" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Transaction Message</label>
                        <textarea name="transaction_message" class="form-control"></textarea>
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
