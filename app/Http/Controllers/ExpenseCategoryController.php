<?php

namespace App\Http\Controllers;

use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                ]);

                $category = ExpenseCategory::create($validated);

                return response()->json([
                    'success' => true,
                    'message' => 'Category created successfully!',
                    'data' => $category
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        ExpenseCategory::create($validated);
        return redirect()->back()->with('success', 'Category created successfully!');
    }

    public function update(Request $request, ExpenseCategory $category)
    {
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                ]);

                $category->update($validated);

                return response()->json([
                    'success' => true,
                    'message' => 'Category updated successfully!',
                    'data' => $category
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);
        return redirect()->back()->with('success', 'Category updated successfully!');
    }

    public function destroy(Request $request, ExpenseCategory $category)
    {
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $category->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Category deleted successfully!'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $category->delete();
        return redirect()->back()->with('success', 'Category deleted successfully!');
    }
}