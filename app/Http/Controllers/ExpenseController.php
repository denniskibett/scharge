<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Models\Payee;
use App\Services\Expenses\BusinessBooks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A business's expenses (App\Services\Expenses\BusinessBooks): each company sees and records only its own,
 * against its own properties, payees and categories.
 */
class ExpenseController extends Controller
{
    private function wantsJson(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    /** Rules where the property, payee and category must belong to the user's business. */
    private function rules(Request $request): array
    {
        $ids = BusinessBooks::companyIds($request->user());
        $inScope = fn (string $table) => $ids === null
            ? Rule::exists($table, 'id')
            : Rule::exists($table, 'id')->where(fn ($q) => $q->whereIn('company_id', $ids ?: [0]));

        return [
            'property_id' => ['required', $inScope('properties')],
            'payee_id' => ['required', $ids === null ? Rule::exists('payees', 'id')
                : Rule::exists('payees', 'id')->where(fn ($q) => $q->where(fn ($w) => $w->whereIn('company_id', $ids ?: [0])->orWhereNull('company_id')))],
            'expense_category_id' => ['required', $inScope('expense_categories')],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'expense_date' => 'required|date',
            'status' => 'required|in:pending,paid',
        ];
    }

    private function mine(Request $request, Expense $expense): void
    {
        abort_unless(BusinessBooks::scopeExpenses(Expense::whereKey($expense->id), $request->user())->exists(), 404);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        BusinessBooks::ensureDefaults(BusinessBooks::companyId($user));
        $expenses = BusinessBooks::scopeExpenses(Expense::query(), $user)->with(['company', 'property.company', 'payee', 'category'])->latest('expense_date')->get();
        $properties = BusinessBooks::scope(Property::query(), $user)->orderBy('name')->get();
        $payees = BusinessBooks::scopePayees(Payee::query(), $user)->orderBy('name')->get();
        $categories = BusinessBooks::scope(ExpenseCategory::query(), $user)->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'properties', 'payees', 'categories'));
    }

    private function save(Request $request, ?Expense $expense)
    {
        try {
            $data = $request->validate($this->rules($request));
            $data['company_id'] = Property::whereKey($data['property_id'])->value('company_id') ?? BusinessBooks::companyId($request->user());
            if ($expense) {
                $expense->forceFill($data)->save();
            } else {
                $expense = Expense::forceCreate($data);
            }
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

        return $expense;
    }

    public function store(Request $request)
    {
        $result = $this->save($request, null);
        if (! $result instanceof Expense) {
            return $result;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Expense created successfully!', 'data' => $result], 201)
            : redirect()->route('expenses.index')->with('success', 'Expense created successfully!');
    }

    public function update(Request $request, Expense $expense)
    {
        $this->mine($request, $expense);
        $result = $this->save($request, $expense);
        if (! $result instanceof Expense) {
            return $result;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Expense updated successfully!', 'data' => $result])
            : redirect()->route('expenses.index')->with('success', 'Expense updated successfully!');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->mine($request, $expense);
        try {
            $expense->delete();
        } catch (\Exception $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Expense deleted successfully!'])
            : redirect()->route('expenses.index')->with('success', 'Expense deleted successfully!');
    }
}
