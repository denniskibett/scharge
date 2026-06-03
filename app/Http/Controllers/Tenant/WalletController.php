<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index()
    {
        $tenant = Auth::user()->tenant;
        $wallet = $tenant->wallet;
        $transactions = $wallet->transactions()->latest()->paginate(15);

        return view('tenant.wallet', compact('tenant', 'wallet', 'transactions'));
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
        ]);

        $tenant = Auth::user()->tenant;
        $wallet = $tenant->wallet;
        $wallet->deposit($request->amount, 'Manual top-up via dashboard');

        return redirect()->route('tenant.wallet')->with('success', 'Wallet topped up successfully!');
    }
}