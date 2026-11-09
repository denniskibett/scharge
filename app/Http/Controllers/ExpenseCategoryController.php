<?php

namespace App\Http\Controllers;

use App\Modules\Expenses\Models\ExpenseCategory;
use App\Services\Expenses\BusinessBooks;
use Illuminate\Http\Request;

/**
 * A business's expense categories (App\Services\Expenses\BusinessBooks): each company manages its own.
 * The route is Route::resource('expense-categories'), so the bound parameter is {expense_category}.
 */
class ExpenseCategoryController extends Controller
{
    private const RULES = ['name' => 'required|string|max:255', 'description' => 'nullable|string'];

    private function wantsJson(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    private function mine(Request $request, ExpenseCategory $category): void
    {
        abort_unless($category->exists && BusinessBooks::owns($request->user(), $category), 404);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate(self::RULES) + ['company_id' => BusinessBooks::companyId($request->user())];
            $category = ExpenseCategory::forceCreate($data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Category created successfully!', 'data' => $category], 201)
            : redirect()->back()->with('success', 'Category created successfully!');
    }

    public function update(Request $request, ExpenseCategory $expense_category)
    {
        $this->mine($request, $expense_category);
        try {
            $expense_category->update($request->validate(self::RULES));
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Category updated successfully!', 'data' => $expense_category])
            : redirect()->back()->with('success', 'Category updated successfully!');
    }

    public function destroy(Request $request, ExpenseCategory $expense_category)
    {
        $this->mine($request, $expense_category);
        if (($n = $expense_category->expenses()->count()) > 0) {
            return $this->wantsJson($request)
                ? response()->json(['success' => false, 'message' => "This category has {$n} expense(s). Move them to another category first."], 400)
                : redirect()->back()->with('error', 'This category still has expenses.');
        }
        $expense_category->delete();

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Category deleted successfully!'])
            : redirect()->back()->with('success', 'Category deleted successfully!');
    }
}
