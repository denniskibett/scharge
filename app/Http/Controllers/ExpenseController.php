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
        $expenses = Expense::with(['payee', 'category', 'payments', 'estate'])->get();
        $estates = Estate::all();
        $payees = Payee::all();
        $categories = ExpenseCategory::all();
        
        return view('expenses.index', compact('expenses', 'estates', 'payees', 'categories'));
    }

    public function show(Expense $expense)
    {
        $expense->load(['payee', 'category', 'payments', 'estate']);
        $estates = Estate::all();
        $payees = Payee::all();
        $categories = ExpenseCategory::all();
        
        return view('expenses.show', compact('expense', 'estates', 'payees', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'payee_id' => 'required|exists:payees,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'expense_date' => 'required|date',
            'status' => 'required|in:pending,paid',
        ]);

        Expense::create($validated);
        return redirect()->route('expenses.index')->with('success', 'Expense created successfully!');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'estate_id' => 'required|exists:estates,id',
            'payee_id' => 'required|exists:payees,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'expense_date' => 'required|date',
            'status' => 'required|in:pending,paid',
        ]);

        $expense->update($validated);
        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully!');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted successfully!');
    }
}