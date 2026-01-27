<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Estate;
use App\Models\Payee;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('payee','category','payments','estate')->get();
        return view('expenses.index', compact('expenses'));
    }

    public function show(Expense $expense)
    {
        $expense->load('payee','category','payments','estate');
        return view('expenses.show', compact('expense'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estate_id'=>'required|exists:estates,id',
            'payee_id'=>'required|exists:payees,id',
            'expense_category_id'=>'required|exists:expense_categories,id',
            'amount'=>'required|numeric',
            'description'=>'nullable|string',
            'expense_date'=>'required|date',
            'status'=>'required|in:pending,paid',
        ]);

        Expense::create($validated);
        return back();
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'estate_id'=>'required|exists:estates,id',
            'payee_id'=>'required|exists:payees,id',
            'expense_category_id'=>'required|exists:expense_categories,id',
            'amount'=>'required|numeric',
            'description'=>'nullable|string',
            'expense_date'=>'required|date',
            'status'=>'required|in:pending,paid',
        ]);

        $expense->update($validated);
        return back();
    }
}
