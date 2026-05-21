<?php
// app/Http/Controllers/TransactionController.php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Store a new transaction from the modal (tenant submits)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenancy_id' => 'required|exists:tenancies,id',
            'raw_message' => 'required|string',
            'parsed_amount' => 'nullable|numeric|min:0',
            'parsed_transaction_id' => 'nullable|string|max:255',
            'parsed_payment_method' => 'nullable|in:mpesa,bank,cash',
            'parsed_payment_datetime' => 'nullable|date',
            'parsed_payer_name' => 'nullable|string|max:255',
            'parsed_paid_to' => 'nullable|string|max:255',
            'parsed_payment_month' => 'nullable|string|max:255',
        ]);

        // Determine status based on user role
        // Role ID 9 = tenant -> pending, others can be verified
        $userRoleId = Auth::user()->role_id ?? 0;
        $status = ($userRoleId == 9) ? 'pending' : 'verified';

        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'tenancy_id' => $validated['tenancy_id'],
                'raw_message' => $validated['raw_message'],
                'parsed_amount' => $validated['parsed_amount'] ?? null,
                'parsed_transaction_id' => $validated['parsed_transaction_id'] ?? null,
                'parsed_payment_method' => $validated['parsed_payment_method'] ?? null,
                'parsed_payment_datetime' => $validated['parsed_payment_datetime'] ?? null,
                'parsed_payer_name' => $validated['parsed_payer_name'] ?? null,
                'parsed_paid_to' => $validated['parsed_paid_to'] ?? null,
                'parsed_payment_month' => $validated['parsed_payment_month'] ?? null,
                'status' => $status,
                'remaining_amount' => $validated['parsed_amount'] ?? 0,
            ]);

            // If not pending (admin/accountant created), auto-verify and allocate
            if ($status !== 'pending') {
                $this->verifyAndAllocate($transaction, [
                    'status' => 'verified',
                    'verification_notes' => 'Auto-verified by ' . Auth::user()->name,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'pending' 
                    ? 'Transaction submitted for verification! An accountant will review it within 24 hours.'
                    : 'Transaction verified and allocated successfully!',
                'transaction' => $transaction,
                'requires_verification' => $status === 'pending',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a pending transaction (accountant action)
     */
    public function verify(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:verified,rejected',
            'verification_notes' => 'nullable|string',
            'adjust_amount' => 'nullable|numeric|min:0', // Allow amount correction
            'adjust_payment_method' => 'nullable|in:mpesa,bank,cash',
            'adjust_payment_datetime' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            $verificationData = [
                'status' => $request->status,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'verification_notes' => $request->verification_notes,
            ];

            // Allow corrections during verification
            if ($request->has('adjust_amount') && $request->adjust_amount > 0) {
                $verificationData['parsed_amount'] = $request->adjust_amount;
                $verificationData['remaining_amount'] = $request->adjust_amount;
            }
            if ($request->has('adjust_payment_method')) {
                $verificationData['parsed_payment_method'] = $request->adjust_payment_method;
            }
            if ($request->has('adjust_payment_datetime')) {
                $verificationData['parsed_payment_datetime'] = $request->adjust_payment_datetime;
            }

            $transaction->update($verificationData);

            // If verified, allocate to invoices
            if ($request->status === 'verified') {
                $this->verifyAndAllocate($transaction);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction ' . $request->status . ' successfully!',
                'transaction' => $transaction->load('payments.invoice'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify and allocate a transaction to payments and invoices
     */
    protected function verifyAndAllocate(Transaction $transaction)
    {
        $remainingAmount = $transaction->remaining_amount ?? $transaction->parsed_amount;
        
        if ($remainingAmount <= 0) {
            $transaction->update(['status' => 'allocated']);
            return;
        }

        // Get unpaid invoices for this tenancy, ordered by due date (oldest first)
        $invoices = Invoice::where('tenancy_id', $transaction->tenancy_id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $allocatedAmount = 0;

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) break;

            $paidSoFar = $invoice->payments->sum('amount');
            $dueOnInvoice = $invoice->total_amount - $paidSoFar;

            if ($dueOnInvoice <= 0) continue;

            $toPay = min($remainingAmount, $dueOnInvoice);

            // Create payment record
            $payment = Payment::create([
                'tenancy_id' => $transaction->tenancy_id,
                'invoice_id' => $invoice->id,
                'transaction_id_ref' => $transaction->id,
                'amount' => $toPay,
                'payment_method' => $transaction->parsed_payment_method,
                'transaction_id' => $transaction->parsed_transaction_id,
                'transaction_message' => $transaction->raw_message,
                'paid_to' => $transaction->parsed_paid_to,
                'payer_name' => $transaction->parsed_payer_name,
                'payment_datetime' => $transaction->parsed_payment_datetime ?? now(),
                'payment_month' => $transaction->parsed_payment_month ?? now()->format('Y-m'),
                'status' => 'verified',
                'verified_by' => $transaction->verified_by ?? Auth::id(),
                'verified_at' => now(),
            ]);

            // Update invoice paid amount and status
            $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $toPay;
            
            if ($invoice->paid_amount >= $invoice->total_amount) {
                $invoice->status = 'paid';
            } else {
                $invoice->status = 'partial';
            }
            $invoice->save();

            $remainingAmount -= $toPay;
            $allocatedAmount += $toPay;
        }

        // Update remaining amount on transaction
        $transaction->remaining_amount = $remainingAmount;
        
        // If fully allocated, mark as allocated
        if ($remainingAmount <= 0) {
            $transaction->status = 'allocated';
        } else {
            $transaction->status = 'verified'; // Still has remaining amount for future allocation
        }
        
        $transaction->save();

        return $transaction;
    }

    /**
     * Manually allocate remaining transaction amount to a specific invoice
     */
    public function allocateRemaining(Request $request, Transaction $transaction)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01|max:' . ($transaction->remaining_amount ?? 0),
        ]);

        DB::beginTransaction();

        try {
            $invoice = Invoice::findOrFail($request->invoice_id);
            $paidSoFar = $invoice->payments->sum('amount');
            $dueOnInvoice = $invoice->total_amount - $paidSoFar;

            $toPay = min($request->amount, $dueOnInvoice);

            if ($toPay <= 0) {
                throw new \Exception('Invoice is already fully paid.');
            }

            $payment = Payment::create([
                'tenancy_id' => $transaction->tenancy_id,
                'invoice_id' => $invoice->id,
                'transaction_id_ref' => $transaction->id,
                'amount' => $toPay,
                'payment_method' => $transaction->parsed_payment_method,
                'transaction_id' => $transaction->parsed_transaction_id,
                'transaction_message' => $transaction->raw_message,
                'paid_to' => $transaction->parsed_paid_to,
                'payer_name' => $transaction->parsed_payer_name,
                'payment_datetime' => $transaction->parsed_payment_datetime ?? now(),
                'payment_month' => $transaction->parsed_payment_month ?? now()->format('Y-m'),
                'status' => 'verified',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);

            // Update invoice
            $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $toPay;
            $invoice->status = ($invoice->paid_amount >= $invoice->total_amount) ? 'paid' : 'partial';
            $invoice->save();

            // Update transaction remaining amount
            $newRemaining = ($transaction->remaining_amount ?? 0) - $toPay;
            $transaction->remaining_amount = $newRemaining;
            
            if ($newRemaining <= 0) {
                $transaction->status = 'allocated';
            }
            $transaction->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully allocated ' . $toPay . ' to invoice #' . ($invoice->invoice_number ?? $invoice->id),
                'remaining_amount' => $newRemaining,
                'transaction' => $transaction->load('payments.invoice'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending transactions for verification
     */
    public function getPending()
    {
        $pendingTransactions = Transaction::with(['tenancy.tenant.user', 'user'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tenancy_id' => $transaction->tenancy_id,
                    'tenant_name' => optional(optional($transaction->tenancy)->tenant->user)->name ?? 'N/A',
                    'submitted_by' => $transaction->user->name ?? 'N/A',
                    'raw_message' => $transaction->raw_message,
                    'parsed_amount' => (float) $transaction->parsed_amount,
                    'parsed_transaction_id' => $transaction->parsed_transaction_id,
                    'parsed_payment_method' => $transaction->parsed_payment_method,
                    'parsed_payment_datetime' => $transaction->parsed_payment_datetime,
                    'parsed_payer_name' => $transaction->parsed_payer_name,
                    'parsed_paid_to' => $transaction->parsed_paid_to,
                    'parsed_payment_month' => $transaction->parsed_payment_month,
                    'created_at' => $transaction->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'pending_transactions' => $pendingTransactions,
            'count' => $pendingTransactions->count(),
        ]);
    }

    /**
     * Get transactions with remaining amounts (for partial payment allocation)
     */
    public function getUnallocated()
    {
        $unallocatedTransactions = Transaction::with(['tenancy.tenant.user'])
            ->where('status', 'verified')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'tenancy_id' => $transaction->tenancy_id,
                    'tenant_name' => optional(optional($transaction->tenancy)->tenant->user)->name ?? 'N/A',
                    'remaining_amount' => (float) $transaction->remaining_amount,
                    'original_amount' => (float) $transaction->parsed_amount,
                    'parsed_transaction_id' => $transaction->parsed_transaction_id,
                    'parsed_payment_method' => $transaction->parsed_payment_method,
                ];
            });

        return response()->json([
            'success' => true,
            'unallocated_transactions' => $unallocatedTransactions,
            'count' => $unallocatedTransactions->count(),
        ]);
    }
}