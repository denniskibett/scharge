<?php

namespace App\Http\Controllers;

use App\Modules\Expenses\Models\Payee; 
use Illuminate\Http\Request;

class PayeeController extends Controller
{
    public function index()
    {
        $payees = Payee::with('expenses')->get();
        return view('payees.index', compact('payees'));
    }

    public function show(Payee $payee)
    {
        $payee->load('expenses.estate', 'expenses.category');
        return view('payees.show', compact('payee'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'=>'required|string',
            'type'=>'required|in:staff,vendor,utility',
            'phone'=>'nullable|string',
            'email'=>'nullable|email',
        ]);

        Payee::create($validated);
        return redirect()->route('payees.index')->with('success', 'Payee created successfully!');
    }

    public function update(Request $request, Payee $payee)
    {
        $validated = $request->validate([
            'name'=>'required|string',
            'type'=>'required|in:staff,vendor,utility',
            'phone'=>'nullable|string',
            'email'=>'nullable|email',
        ]);

        $payee->update($validated);
        return redirect()->route('payees.index')->with('success', 'Payee updated successfully!');
    }

    public function destroy(Payee $payee)
    {
        $payee->delete();
        return redirect()->route('payees.index')->with('success', 'Payee deleted successfully!');
    }
}