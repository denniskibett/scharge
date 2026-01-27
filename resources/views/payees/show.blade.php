@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-3">Payee Details - {{ $payee->name }}</h3>

    <div class="card mb-3">
        <div class="card-body">
            <p><strong>Name:</strong> {{ $payee->name }}</p>
            <p><strong>Type:</strong> {{ ucfirst($payee->type) }}</p>
            <p><strong>Phone:</strong> {{ $payee->phone }}</p>
            <p><strong>Email:</strong> {{ $payee->email }}</p>
            <p><strong>Expenses Count:</strong> {{ $payee->expenses->count() }}</p>
        </div>
    </div>

    <h5>Expenses for this Payee</h5>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Estate</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payee->expenses as $expense)
            <tr>
                <td>{{ $expense->estate->name }}</td>
                <td>{{ $expense->category->name }}</td>
                <td>{{ number_format($expense->amount,2) }}</td>
                <td>{{ $expense->expense_date }}</td>
                <td>{{ ucfirst($expense->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
