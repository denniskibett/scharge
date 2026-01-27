@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-3">Expenses</h3>

    <!-- Buttons -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createExpenseModal">Add Expense</button>
    <button class="btn btn-secondary mb-3" data-bs-toggle="modal" data-bs-target="#createCategoryModal">Add Category</button>

    <!-- Expenses Table -->
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Estate</th>
                <th>Payee</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Status</th>
                <th>Payments</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $expense)
            <tr>
                <td>{{ $expense->estate->name }}</td>
                <td>{{ $expense->payee->name }}</td>
                <td>{{ $expense->category->name }}</td>
                <td>{{ number_format($expense->amount,2) }}</td>
                <td>{{ $expense->expense_date }}</td>
                <td>{{ ucfirst($expense->status) }}</td>
                <td>{{ $expense->payments->count() }}</td>
                <td>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#showExpenseModal{{ $expense->id }}">View</button>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editExpenseModal{{ $expense->id }}">Edit</button>
                    <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="d-inline">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete expense?')">Delete</button>
                    </form>
                </td>
            </tr>

            <!-- Show Modal -->
            <div class="modal fade" id="showExpenseModal{{ $expense->id }}">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header"><h5>Expense Details</h5></div>
                        <div class="modal-body">
                            <p><strong>Estate:</strong> {{ $expense->estate->name }}</p>
                            <p><strong>Payee:</strong> {{ $expense->payee->name }}</p>
                            <p><strong>Category:</strong> {{ $expense->category->name }}</p>
                            <p><strong>Amount:</strong> {{ number_format($expense->amount,2) }}</p>
                            <p><strong>Date:</strong> {{ $expense->expense_date }}</p>
                            <p><strong>Status:</strong> {{ ucfirst($expense->status) }}</p>

                            <h6 class="mt-3">Payments</h6>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Paid By</th>
                                        <th>Transaction ID</th>
                                        <th>Date</th>
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
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>

                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editExpenseModal{{ $expense->id }}">
                <div class="modal-dialog">
                    <form action="{{ route('expenses.update', $expense) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header"><h5>Edit Expense</h5></div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label>Estate</label>
                                    <select name="estate_id" class="form-select">
                                        @foreach(\App\Models\Estate::all() as $estate)
                                        <option value="{{ $estate->id }}" @if($estate->id==$expense->estate_id) selected @endif>{{ $estate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Payee</label>
                                    <select name="payee_id" class="form-select">
                                        @foreach(\App\Models\Payee::all() as $payee)
                                        <option value="{{ $payee->id }}" @if($payee->id==$expense->payee_id) selected @endif>{{ $payee->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Category</label>
                                    <select name="expense_category_id" class="form-select">
                                        @foreach(\App\Models\ExpenseCategory::all() as $cat)
                                        <option value="{{ $cat->id }}" @if($cat->id==$expense->expense_category_id) selected @endif>{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Amount</label>
                                    <input name="amount" class="form-control" value="{{ $expense->amount }}">
                                </div>
                                <div class="mb-2">
                                    <label>Date</label>
                                    <input type="date" name="expense_date" class="form-control" value="{{ $expense->expense_date }}">
                                </div>
                                <div class="mb-2">
                                    <label>Status</label>
                                    <select name="status" class="form-select">
                                        <option value="pending" @if($expense->status=='pending') selected @endif>Pending</option>
                                        <option value="paid" @if($expense->status=='paid') selected @endif>Paid</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control">{{ $expense->description }}</textarea>
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

<!-- Create Expense Modal -->
<div class="modal fade" id="createExpenseModal">
    <div class="modal-dialog">
        <form action="{{ route('expenses.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5>Add Expense</h5></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Estate</label>
                        <select name="estate_id" class="form-select">
                            @foreach(\App\Models\Estate::all() as $estate)
                            <option value="{{ $estate->id }}">{{ $estate->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Payee</label>
                        <select name="payee_id" class="form-select">
                            @foreach(\App\Models\Payee::all() as $payee)
                            <option value="{{ $payee->id }}">{{ $payee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Category</label>
                        <select name="expense_category_id" class="form-select">
                            @foreach(\App\Models\ExpenseCategory::all() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Amount</label>
                        <input name="amount" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Date</label>
                        <input type="date" name="expense_date" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Description</label>
                        <textarea name="description" class="form-control"></textarea>
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

<!-- Create Category Modal -->
<div class="modal fade" id="createCategoryModal">
    <div class="modal-dialog">
        <form action="{{ route('expense_categories.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5>Add Category</h5></div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label>Name</label>
                        <input name="name" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label>Description</label>
                        <textarea name="description" class="form-control"></textarea>
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
