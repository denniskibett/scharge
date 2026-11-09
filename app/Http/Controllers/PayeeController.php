<?php

namespace App\Http\Controllers;

use App\Modules\Expenses\Models\Payee;
use App\Services\Expenses\BusinessBooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** Payees (App\Services\Expenses\BusinessBooks): unique, shared ones for everyone plus a business's own named payees. */
class PayeeController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:255',
        'type' => 'required|in:staff,vendor,utility',
        'phone' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
        'id_number' => 'nullable|string|max:20',
        'kra_pin' => 'nullable|string|max:50',
        'nssf_number' => 'nullable|string|max:20',
        'sha_number' => 'nullable|string|max:20',
    ];

    private function wantsJson(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    /** Only the fields the payees table has (the form also offers KRA PIN, ID, NSSF, SHA). */
    private function fields(array $data): array
    {
        return array_filter($data, fn ($k) => Schema::hasColumn('payees', $k), ARRAY_FILTER_USE_KEY);
    }

    /** Payees are unique: no name this business can already see (shared or its own). */
    private function unique(string $name, ?int $companyId, ?int $exceptId = null): void
    {
        if (BusinessBooks::payeeNameTaken($name, $companyId, $exceptId)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => "A payee called \"{$name}\" already exists. Pick it from the list instead."]);
        }
    }

    private function mine(Request $request, Payee $payee): void
    {
        abort_unless(BusinessBooks::owns($request->user(), $payee), 404);
    }

    public function index(Request $request)
    {
        BusinessBooks::ensureDefaults(BusinessBooks::companyId($request->user()));
        $payees = BusinessBooks::scopePayees(Payee::query(), $request->user())->with('expenses')->orderBy('name')->get();

        return view('payees.index', compact('payees'));
    }

    public function show(Request $request, Payee $payee)
    {
        abort_unless(BusinessBooks::seesPayee($request->user(), $payee), 404);
        $payee->load(['expenses' => fn ($q) => BusinessBooks::scopeExpenses($q, $request->user()), 'expenses.property', 'expenses.category']);

        return view('payees.show', compact('payee'));
    }

    public function store(Request $request)
    {
        try {
            $data = $this->fields($request->validate(self::RULES));
            $data['company_id'] = BusinessBooks::companyId($request->user());
            $this->unique($data['name'], $data['company_id']);
            $payee = Payee::forceCreate($data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => 'Failed to create payee: '.$e->getMessage()], 500);
            }
            throw $e;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Payee created successfully!', 'data' => $payee], 201)
            : redirect()->route('payees.index')->with('success', 'Payee created successfully!');
    }

    public function update(Request $request, Payee $payee)
    {
        $this->mine($request, $payee);
        try {
            $data = $this->fields($request->validate(self::RULES));
            $this->unique($data['name'], $payee->company_id ?? BusinessBooks::companyId($request->user()), $payee->id);
            $payee->update($data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => 'Failed to update payee: '.$e->getMessage()], 500);
            }
            throw $e;
        }

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Payee updated successfully!', 'data' => $payee])
            : redirect()->route('payees.index')->with('success', 'Payee updated successfully!');
    }

    public function destroy(Request $request, Payee $payee)
    {
        $this->mine($request, $payee);
        if (($n = $payee->expenses()->count()) > 0) {
            return $this->wantsJson($request)
                ? response()->json(['success' => false, 'message' => "Cannot delete this payee because they have {$n} associated expense(s)."], 400)
                : redirect()->back()->with('error', 'Cannot delete this payee because they have associated expenses.');
        }
        $payee->delete();

        return $this->wantsJson($request)
            ? response()->json(['success' => true, 'message' => 'Payee deleted successfully!'])
            : redirect()->route('payees.index')->with('success', 'Payee deleted successfully!');
    }
}
