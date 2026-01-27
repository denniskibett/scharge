<?php

namespace App\Http\Controllers;

use App\Models\Payee;
use Illuminate\Http\Request;

class PayeeController extends Controller
{
    public function index()
    {
        $payees = Payee::all();
        return view('payees.index', compact('payees'));
    }

    public function show(Payee $payee)
    {
        $payee->load('expenses');
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
        return back();
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
        return back();
    }
}
