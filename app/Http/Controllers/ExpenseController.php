<?php

namespace App\Http\Controllers;

use App\Modules\Expenses\Models\Expense;
use App\Models\Estate;
use App\Modules\Expenses\Models\Payee; 
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with(['estate', 'payee', 'category'])->get();
        $estates = Estate::all();
        $payees = Payee::all();
        $categories = ExpenseCategory::all();
        
        return view('expenses.index', compact('expenses', 'estates', 'payees', 'categories'));
    }

    public function store(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $validated = $request->validate([
                    'estate_id' => 'required|exists:estates,id',
                    'payee_id' => 'required|exists:payees,id',
                    'expense_category_id' => 'required|exists:expense_categories,id',
                    'amount' => 'required|numeric|min:0.01',
                    'description' => 'nullable|string',
                    'expense_date' => 'required|date',
                    'status' => 'required|in:pending,paid',
                ]);

                $expense = Expense::create($validated);

                return response()->json([
                    'success' => true,
                    'message' => 'Expense created successfully!',
                    'data' => $expense
                ], 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

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
        if ($request->ajax() || $request->wantsJson()) {
            try {
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

                return response()->json([
                    'success' => true,
                    'message' => 'Expense updated successfully!',
                    'data' => $expense
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

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

    public function destroy(Request $request, Expense $expense)
    {
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $expense->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Expense deleted successfully!'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted successfully!');
    }
}