<?php

namespace App\Http\Controllers;

use App\Modules\Expenses\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\ServiceBudget;
use App\Models\ServiceBudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Service-charge budgets: per building and year, the planned cost of each line (security, cleaning ...), what was
 * actually spent (from Expenses, by category), the sinking fund, and the monthly charge per unit that covers it.
 * The owner statement is the same figures on one printable page.
 */
class BudgetController extends Controller
{
    /** Invoice item types that are service charge. */
    private const SERVICE_ITEMS = ['service', 'service_charge', 'security', 'garbage'];

    public function index(Request $request)
    {
        $properties = Property::visible()->orderBy('name')->get(['id', 'name', 'location']);
        $propertyId = (int) ($request->query('property') ?: $properties->first()?->id);
        $property = $properties->firstWhere('id', $propertyId);
        $year = (int) ($request->query('year') ?: now()->year);
        $year = max(2000, min($year, now()->year + 1));

        $budget = $property ? ServiceBudget::with('lines')->where('property_id', $property->id)->where('year', $year)->first() : null;
        $categories = ExpenseCategory::query()
            ->when(\App\Support\CompanyAccess::ids() !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('company_id', \App\Support\CompanyAccess::ids())->orWhereNull('company_id')))
            ->orderBy('name')->get(['id', 'name']);

        return view('budgets.index', [
            'properties' => $properties, 'property' => $property, 'year' => $year, 'budget' => $budget,
            'categories' => $categories,
            'figures' => $property ? $this->figures($property, $year, $budget, $categories) : null,
        ]);
    }

    /** Start this building's budget for the year with the usual lines (amounts 0) or a copy of last year's. */
    public function store(Request $request)
    {
        $data = $request->validate(['property_id' => 'required|integer', 'year' => 'required|integer|min:2000|max:2100']);
        $property = Property::visible()->findOrFail($data['property_id']);
        $budget = ServiceBudget::firstOrCreate(['property_id' => $property->id, 'year' => $data['year']],
            ['company_id' => $property->company_id ?? auth()->user()->company_id, 'created_by' => auth()->id()]);

        if ($budget->wasRecentlyCreated) {
            $last = ServiceBudget::with('lines')->where('property_id', $property->id)->where('year', $data['year'] - 1)->first();
            $lines = $last?->lines->map(fn ($l) => ['name' => $l->name, 'expense_category_id' => $l->expense_category_id, 'annual_amount' => $l->annual_amount])->all()
                ?: array_map(fn ($n) => ['name' => $n, 'expense_category_id' => null, 'annual_amount' => 0], ServiceBudget::SUGGESTED_LINES);
            if ($last) {
                $budget->update(['sinking_fund_percent' => $last->sinking_fund_percent]);
            }
            foreach (array_values($lines) as $i => $line) {
                $budget->lines()->create($line + ['sort' => $i]);
            }
        }

        return redirect()->route('budgets.index', ['property' => $property->id, 'year' => $data['year']])
            ->with('success', $budget->wasRecentlyCreated ? 'Budget started. Fill in the yearly amount for each line.' : 'This budget already exists.');
    }

    /** Save the lines (add, change, remove) and the sinking fund share. */
    public function update(Request $request, ServiceBudget $budget)
    {
        $data = $request->validate([
            'sinking_fund_percent' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'array|max:60',
            'lines.*.id' => 'nullable|integer',
            'lines.*.name' => 'required|string|max:120',
            'lines.*.annual_amount' => 'required|numeric|min:0|max:999999999',
            'lines.*.expense_category_id' => 'nullable|integer',
        ]);

        DB::transaction(function () use ($budget, $data) {
            $budget->update(['sinking_fund_percent' => $data['sinking_fund_percent'], 'notes' => $data['notes'] ?? null]);
            $keep = [];
            foreach (array_values($data['lines'] ?? []) as $i => $row) {
                $fields = ['name' => trim($row['name']), 'annual_amount' => $row['annual_amount'], 'expense_category_id' => ($row['expense_category_id'] ?? null) ?: null, 'sort' => $i];
                $line = ! empty($row['id']) ? $budget->lines()->whereKey($row['id'])->first() : null;
                $line ? $line->update($fields) : $line = $budget->lines()->create($fields);
                $keep[] = $line->id;
            }
            $budget->lines()->whereNotIn('id', $keep ?: [0])->delete();
        });

        return redirect()->route('budgets.index', ['property' => $budget->property_id, 'year' => $budget->year])->with('success', 'Budget saved.');
    }

    /** One printable page for owners and residents: budget, spend, sinking fund, service charge collected. */
    public function statement(ServiceBudget $budget)
    {
        $budget->load('lines');
        $property = Property::visible()->findOrFail($budget->property_id);
        $categories = ExpenseCategory::query()->get(['id', 'name']);

        return view('budgets.statement', ['budget' => $budget, 'property' => $property, 'year' => $budget->year,
            'figures' => $this->figures($property, $budget->year, $budget, $categories),
            'company' => \App\Models\Company::withoutGlobalScopes()->find($budget->company_id)]);
    }

    /** Budget vs actual for one building and year. */
    private function figures(Property $property, int $year, ?ServiceBudget $budget, $categories): array
    {
        $from = "$year-01-01";
        $to = "$year-12-31";
        $spentByCategory = DB::table('expenses')->where('property_id', $property->id)->whereBetween('expense_date', [$from, $to])
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['cancelled', 'rejected', 'void']))
            ->groupBy('expense_category_id')->selectRaw('expense_category_id as cid, SUM(amount) as total')->pluck('total', 'cid');
        $categoryNames = $categories->pluck('name', 'id')->map(fn ($n) => mb_strtolower(trim($n)));

        $lines = [];
        $used = [];
        foreach ($budget?->lines ?? [] as $line) {
            // A line counts the expenses of its chosen category, else of every category with the same name
            // (a company's own "Security" and the shared one).
            $cids = $line->expense_category_id ? [(int) $line->expense_category_id]
                : $categoryNames->filter(fn ($n) => $n === mb_strtolower(trim($line->name)))->keys()->map(fn ($k) => (int) $k)->all();
            $spent = (float) collect($cids)->sum(fn ($c) => (float) ($spentByCategory[$c] ?? 0));
            array_push($used, ...$cids);
            $lines[] = ['id' => $line->id, 'name' => $line->name, 'expense_category_id' => $line->expense_category_id, 'matched_category' => $cids ? ($categories->firstWhere('id', $cids[0])?->name) : null,
                'budget' => (float) $line->annual_amount, 'spent' => $spent, 'left' => (float) $line->annual_amount - $spent,
                'used_pct' => $line->annual_amount > 0 ? round($spent / $line->annual_amount * 100) : ($spent > 0 ? 100 : 0)];
        }
        $otherSpent = (float) $spentByCategory->reject(fn ($v, $cid) => in_array((int) $cid, $used, true))->sum();

        $units = DB::table('units')->where('property_id', $property->id)->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))->count();
        $planned = array_sum(array_column($lines, 'budget'));
        $sinking = $budget ? round($planned * $budget->sinking_fund_percent / 100, 2) : 0;
        $billed = DB::table('invoice_items as it')->join('invoices as i', 'i.id', '=', 'it.invoice_id')
            ->join('tenancies as tn', 'tn.id', '=', 'i.tenancy_id')->join('units as u', 'u.id', '=', 'tn.unit_id')
            ->where('u.property_id', $property->id)->whereIn('it.item_type', self::SERVICE_ITEMS)
            ->whereBetween('i.billing_month', [$from, $to])
            ->selectRaw('COALESCE(SUM(it.amount),0) as billed, COALESCE(SUM(it.paid_amount),0) as paid')->first();

        return [
            'lines' => $lines, 'planned' => $planned, 'spent' => array_sum(array_column($lines, 'spent')) + $otherSpent,
            'other_spent' => $otherSpent, 'sinking' => $sinking, 'total_needed' => $planned + $sinking, 'units' => $units,
            'per_unit_month' => $units > 0 ? round(($planned + $sinking) / $units / 12, 2) : null,
            'billed' => (float) ($billed->billed ?? 0), 'collected' => (float) ($billed->paid ?? 0),
            'months_gone' => $year < now()->year ? 12 : ($year > now()->year ? 0 : now()->month),
        ];
    }
}
