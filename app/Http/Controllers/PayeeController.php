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
        // Check if it's an AJAX request
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'type' => 'required|in:staff,vendor,utility',
                    'phone' => 'nullable|string|max:20',
                    'email' => 'nullable|email|max:255',
                    'id_number' => 'nullable|string|max:20',
                    'kra_pin' => 'nullable|string|max:50',
                    'nssf_number' => 'nullable|string|max:20',
                    'sha_number' => 'nullable|string|max:20',
                ]);

                $payee = Payee::create($validated);

                return response()->json([
                    'success' => true,
                    'message' => 'Payee created successfully!',
                    'data' => $payee
                ], 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payee: ' . $e->getMessage()
                ], 500);
            }
        }

        // Non-AJAX request - redirect back
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:staff,vendor,utility',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'id_number' => 'nullable|string|max:20',
            'kra_pin' => 'nullable|string|max:50',
            'nssf_number' => 'nullable|string|max:20',
            'sha_number' => 'nullable|string|max:20',
        ]);

        Payee::create($validated);
        return redirect()->route('payees.index')->with('success', 'Payee created successfully!');
    }

    public function update(Request $request, Payee $payee)
    {
        // Check if it's an AJAX request
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'type' => 'required|in:staff,vendor,utility',
                    'phone' => 'nullable|string|max:20',
                    'email' => 'nullable|email|max:255',
                    'id_number' => 'nullable|string|max:20',
                    'kra_pin' => 'nullable|string|max:50',
                    'nssf_number' => 'nullable|string|max:20',
                    'sha_number' => 'nullable|string|max:20',
                ]);

                $payee->update($validated);

                return response()->json([
                    'success' => true,
                    'message' => 'Payee updated successfully!',
                    'data' => $payee
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update payee: ' . $e->getMessage()
                ], 500);
            }
        }

        // Non-AJAX request - redirect back
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:staff,vendor,utility',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'id_number' => 'nullable|string|max:20',
            'kra_pin' => 'nullable|string|max:50',
            'nssf_number' => 'nullable|string|max:20',
            'sha_number' => 'nullable|string|max:20',
        ]);

        $payee->update($validated);
        return redirect()->route('payees.index')->with('success', 'Payee updated successfully!');
    }

    public function destroy(Request $request, Payee $payee)
    {
        // Check if payee has expenses
        if ($payee->expenses()->count() > 0) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete this payee because they have ' . $payee->expenses()->count() . ' associated expense(s). Please delete the expenses first.'
                ], 400);
            }
            return redirect()->back()->with('error', 'Cannot delete this payee because they have associated expenses. Please delete the expenses first.');
        }

        // Check if it's an AJAX request
        if ($request->ajax() || $request->wantsJson()) {
            try {
                $payee->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Payee deleted successfully!'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete payee: ' . $e->getMessage()
                ], 500);
            }
        }

        // Non-AJAX request - redirect back
        $payee->delete();
        return redirect()->route('payees.index')->with('success', 'Payee deleted successfully!');
    }
}