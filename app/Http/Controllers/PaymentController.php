<?php
// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentController extends Controller
{


    public function index()
    {
        $user = Auth::user();
        $company = $user->company;
        
        // Get payments based on user role
        if ($user->hasRole('sysadmin')) {
            $payments = Payment::with(['tenant.user', 'invoice', 'invoice.items', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else if ($company) {
            // For company-specific users, filter payments by company through invoice->tenancy->unit
            $payments = Payment::whereHas('invoice.tenancy.unit', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->with(['tenant.user', 'invoice', 'invoice.items', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $payments = collect();
        }
        
        // Map payments to structured data for the frontend
        $paymentsData = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payer_name' => $payment->payer_name,
                'tenant_id' => $payment->tenant_id,
                'invoice_id' => $payment->invoice_id,
                'invoice_label' => $payment->invoice_label,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_method_label' => $payment->payment_method_label,
                'transaction_reference' => $payment->transaction_reference,
                'external_reference' => $payment->external_reference,
                'paid_to' => $payment->paid_to,
                'payment_datetime' => $payment->created_at ? $payment->created_at->toISOString() : null,
                'status' => $payment->status,
                'status_badge' => $payment->status_badge,
                'is_reconciled' => $payment->is_reconciled,
                'wallet_balance_before' => (float) ($payment->wallet_balance_before ?? 0),
                'wallet_balance_after' => (float) ($payment->wallet_balance_after ?? 0),
                'created_at_formatted' => $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-',
            ];
        });
        
        return view('payments.index', compact('paymentsData'));
    }

    public function show($id)
    {
        try {
            $user = Auth::user();
            $company = $user->company;
            
            $payment = Payment::with(['tenant.user', 'invoice.items', 'invoice.tenancy.unit.estate', 'user'])
                ->findOrFail($id);
            
            // Check authorization
            if (!$user->hasRole('sysadmin') && $company) {
                $paymentCompany = $payment->invoice?->tenancy?->unit?->estate?->company_id;
                if ($paymentCompany != $company->id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized to view this payment'
                    ], 403);
                }
            }
            
            return response()->json([
                'success' => true,
                'payment' => [
                    'id' => $payment->id,
                    'payer_name' => $payment->payer_name,
                    'tenant_name' => $payment->tenant?->user?->name ?? 'N/A',
                    'tenant_phone' => $payment->tenant?->user?->phone ?? 'N/A',
                    'invoice_number' => $payment->invoice?->invoice_number ?? 'INV-' . ($payment->invoice_id ?? 'N/A'),
                    'invoice_label' => $payment->invoice_label,
                    'amount' => (float) $payment->amount,
                    'formatted_amount' => 'KES ' . number_format($payment->amount, 2),
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $payment->payment_method_label,
                    'transaction_reference' => $payment->transaction_reference,
                    'external_reference' => $payment->external_reference,
                    'paid_to' => $payment->paid_to,
                    'payment_datetime' => $payment->created_at ? $payment->created_at->format('M d, Y H:i:s') : '-',
                    'status' => $payment->status,
                    'status_badge' => $payment->status_badge,
                    'is_reconciled' => $payment->is_reconciled,
                    'reconciled_at' => $payment->reconciled_at ? $payment->reconciled_at->format('M d, Y H:i') : null,
                    'wallet_balance_before' => (float) ($payment->wallet_balance_before ?? 0),
                    'wallet_balance_after' => (float) ($payment->wallet_balance_after ?? 0),
                    'invoice_items' => $payment->invoice?->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'item_type' => $item->item_type,
                            'amount' => (float) $item->amount,
                            'paid_amount' => (float) ($item->paid_amount ?? 0),
                        ];
                    }) ?? [],
                    'unit_number' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                    'estate_name' => $payment->invoice?->tenancy?->unit?->estate?->name ?? 'N/A',
                    'company_name' => $payment->invoice?->tenancy?->unit?->estate?->company?->name ?? 'N/A',
                    'created_at' => $payment->created_at->toISOString(),
                    'updated_at' => $payment->updated_at->toISOString(),
                    'meta' => $payment->meta,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Payment not found'
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'transaction_reference' => 'nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $tenant = Tenant::findOrFail($request->tenant_id);
            $invoice = Invoice::findOrFail($request->invoice_id);
            
            // Verify tenant owns this invoice
            if ($invoice->tenancy_id != $tenant->activeTenancy?->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invoice does not belong to this tenant'
                ], 400);
            }
            
            $payment = Payment::create([
                'tenant_id' => $request->tenant_id,
                'user_id' => Auth::id(),
                'invoice_id' => $request->invoice_id,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'transaction_reference' => $request->transaction_reference,
                'external_reference' => $request->external_reference,
                'status' => $request->payment_method === 'wallet' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING,
                'meta' => $request->meta ?? [],
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'sometimes|string',
            'transaction_reference' => 'nullable|string',
            'status' => 'sometimes|string',
            'is_reconciled' => 'sometimes|boolean',
        ]);
        
        try {
            $payment = Payment::findOrFail($id);
            $payment->update($request->only([
                'amount', 'payment_method', 'transaction_reference', 
                'external_reference', 'status', 'is_reconciled', 'meta'
            ]));
            
            if ($request->has('is_reconciled') && $request->is_reconciled) {
                $payment->markAsReconciled(Auth::user());
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to update payment'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $payment = Payment::findOrFail($id);
            $payment->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete payment'
            ], 500);
        }
    }
}