<?php
// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\MpesaStk;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Payments\Services\WalletService;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected $walletService;
    
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Process M-Pesa STK Push payment - Bypasses wallet balance check
     */
    protected function processMpesaPayment($request, $tenant, $invoice)
    {
        Log::info('📱 Processing M-Pesa Payment - START', [
            'tenant_id' => $tenant->id ?? null,
            'invoice_id' => $invoice->id ?? null,
            'amount' => $request->amount ?? null,
        ]);
        
        // Get phone number
        $phone = $request->mpesa_phone ?? $tenant->user->phone ?? null;
        
        if (!$phone) {
            Log::error('❌ No phone number found for M-Pesa payment');
            
            return response()->json([
                'success' => false,
                'message' => 'Phone number required for M-Pesa payment. Please enter your M-Pesa phone number.'
            ], 400);
        }
        
        // Format phone number
        $mpesaService = new MpesaService();
        $phone = $mpesaService->formatPhoneNumber($phone);
        
        Log::info('📱 Formatted phone number', ['formatted_phone' => $phone]);
        
        // Send STK Push
        $result = $mpesaService->stkPush(
            $phone,
            $request->amount,
            $invoice->invoice_number ?? 'INV-' . $invoice->id,
            "Payment for Invoice #{$invoice->id}",
            auth()->id(),
            $invoice->id,
            null
        );
        
        Log::info('📥 STK Push Result', [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? null,
            'checkout_request_id' => $result['checkout_request_id'] ?? null,
        ]);
        
        if ($result['success']) {
            // Store in session
            session([
                'mpesa_invoice_id' => $invoice->id,
                'mpesa_checkout_id' => $result['checkout_request_id'],
                'mpesa_phone' => $phone,
                'mpesa_amount' => $request->amount,
                'mpesa_tenant_id' => $tenant->id,
                'mpesa_stk_id' => $result['stk_id'],
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '✅ STK Push sent to ' . $phone . '! Please check your phone and enter your M-Pesa PIN to complete payment.',
                'checkout_request_id' => $result['checkout_request_id'],
                'stk_id' => $result['stk_id'],
                'is_mpesa' => true,
                'phone' => $phone,
                'response_code' => 0,
                'status' => 'pending_confirmation'
            ]);
        }
        
        Log::error('❌ STK Push failed', [
            'message' => $result['message'] ?? 'Unknown error',
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '❌ STK Push failed: ' . ($result['message'] ?? 'Please try again.')
        ], 400);
    }

    /**
     * Check M-Pesa STK Push payment status (AJAX endpoint)
     */
    public function checkMpesaStatus(Request $request)
    {
        $request->validate([
            'checkout_request_id' => 'required|string',
        ]);

        Log::info('🔍 Checking M-Pesa Status', [
            'checkout_request_id' => $request->checkout_request_id
        ]);

        try {
            $mpesa = new MpesaService();
            $result = $mpesa->queryStatus($request->checkout_request_id);
            
            // Check local database for STK record
            $mpesaStk = MpesaStk::where('checkout_request_id', $request->checkout_request_id)->first();
            
            Log::info('📊 Status Check Result', [
                'api_success' => $result['success'] ?? false,
                'local_record_exists' => !is_null($mpesaStk),
            ]);
            
            if ($result['success']) {
                $responseData = [
                    'success' => true,
                    'status' => $result['status'] ?? 'pending',
                    'message' => $result['message'],
                    'data' => $result['data']
                ];
                
                if ($mpesaStk) {
                    $responseData['local_status'] = [
                        'response_code' => $mpesaStk->response_code,
                        'response_description' => $mpesaStk->response_description,
                        'customer_message' => $mpesaStk->customer_message,
                        'receipt_number' => $mpesaStk->mpesa_receipt_number,
                        'transaction_date' => $mpesaStk->transaction_date,
                        'is_success' => $mpesaStk->response_code == 0,
                        'has_payment' => isset($mpesaStk->metadata['payment_id']),
                        'payment_id' => $mpesaStk->metadata['payment_id'] ?? null,
                    ];
                }
                
                return response()->json($responseData);
            }
            
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Status check failed'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('❌ M-Pesa status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status'
            ], 500);
        }
    }

    /**
     * Store a new payment - M-Pesa bypasses wallet balance check
     */
    public function store(Request $request)
    {
        $request->validate([
            'tenancy_id' => 'sometimes|exists:tenancies,id',
            'tenant_id' => 'required_without:tenancy_id|exists:tenants,id',
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,mpesa_paybill,mpesa,mpesa_stk',
            'external_reference' => 'nullable|string',
            'payment_datetime' => 'required_if:payment_method,cash,bank_transfer|nullable|date',
            'payment_month' => 'required_if:payment_method,cash,bank_transfer|nullable|string',
            'notes' => 'nullable|string',
            'mpesa_phone' => 'required_if:payment_method,mpesa,mpesa_paybill,mpesa_stk|nullable|string',
        ]);
        
        try {
            // Get tenant and invoice
            $tenant = Tenant::find($request->tenant_id);
            if (!$tenant && $request->tenancy_id) {
                $tenant = Tenant::whereHas('activeTenancy', function($q) use ($request) {
                    $q->where('id', $request->tenancy_id);
                })->first();
            }
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            
            // ✅ IMPORTANT: M-Pesa payments bypass wallet balance check
            if (in_array($request->payment_method, ['mpesa', 'mpesa_paybill', 'mpesa_stk'])) {
                return $this->processMpesaPayment($request, $tenant, $invoice);
            }
            
            // For non-M-Pesa payments, check wallet balance
            return $this->processRegularPayment($request, $tenant, $invoice);
            
        } catch (\Exception $e) {
            Log::error('Error creating payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process regular payment (Cash, Bank Transfer) - Requires wallet balance
     */
    protected function processRegularPayment($request, $tenant, $invoice)
    {
        if (!$request->payment_datetime || !$request->payment_month) {
            return response()->json([
                'success' => false,
                'message' => 'Payment date and month are required for this payment method.'
            ], 400);
        }
        
        if ($invoice->tenancy->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice does not belong to this tenant'
            ], 400);
        }
        
        // Check wallet balance for regular payments
        $walletBalance = $tenant->balance ?? 0;
        if ($walletBalance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance. Available: KES ' . number_format($walletBalance, 2)
            ], 400);
        }
        
        $result = $this->walletService->processDirectPayment(
            tenant: $tenant,
            invoice: $invoice,
            amount: $request->amount,
            paymentMethod: $request->payment_method,
            externalReference: $request->external_reference ?? 'MANUAL-' . time(),
            meta: [
                'notes' => $request->notes,
                'payment_datetime' => $request->payment_datetime,
                'payment_month' => $request->payment_month,
                'source' => 'admin_panel',
                'created_by' => auth()->id(),
                'created_by_name' => auth()->user()->name,
            ]
        );
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Payment processed successfully!',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'wallet_balance' => $result['wallet_balance'],
                    'amount_paid_to_invoice' => $result['amount_paid_to_invoice'],
                    'amount_added_to_wallet' => $result['amount_added_to_wallet'],
                    'invoice' => $result['invoice'],
                ]
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Payment processing failed'
        ], 400);
    }

    /**
     * Display a listing of payments
     */
    public function index()
    {
        $user = Auth::user();
        $company = $user->company;
        
        if ($user->hasRole('sysadmin')) {
            $payments = Payment::with(['tenant.user', 'invoice', 'invoice.items', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else if ($company) {
            $payments = Payment::whereHas('invoice.tenancy.unit', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->with(['tenant.user', 'invoice', 'invoice.items', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $payments = collect();
        }
        
        $tenants = [];
        if ($company) {
            $tenants = Tenant::whereHas('user', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->with('user', 'activeTenancy.unit')
                ->get()
                ->map(function($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->user->name ?? 'Unknown',
                        'unit_number' => $tenant->activeTenancy?->unit?->unit_number ?? 'No Unit',
                    ];
                });
        }
        
        $invoices = [];
        if ($company) {
            $invoices = Invoice::whereHas('tenancy.unit', function($q) use ($company) {
                    $q->where('company_id', $company->id);
                })
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('tenancy.tenant.user')
                ->orderBy('billing_month', 'desc')
                ->get()
                ->map(function($invoice) {
                    $tenantName = $invoice->tenancy?->tenant?->user?->name ?? 'Unknown';
                    $invoiceNumber = $invoice->invoice_number ?? 'INV-' . $invoice->id;
                    $billingMonth = $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '';
                    return [
                        'id' => $invoice->id,
                        'label' => $tenantName . ' - ' . $invoiceNumber . ($billingMonth ? ' (' . $billingMonth . ')' : ''),
                        'total_amount' => (float) $invoice->total_amount,
                        'remaining_amount' => (float) $invoice->remaining_amount,
                    ];
                });
        }
        
        $paymentsData = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payer_name' => $payment->payer_name,
                'tenant_id' => $payment->tenant_id,
                'tenant_name' => $payment->tenant?->user?->name ?? 'N/A',
                'unit_number' => $payment->invoice?->tenancy?->unit?->unit_number ?? 'N/A',
                'invoice_id' => $payment->invoice_id,
                'invoice_label' => $payment->invoice_label,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_method_label' => $payment->payment_method_label,
                'transaction_reference' => $payment->transaction_reference,
                'external_reference' => $payment->external_reference,
                'paid_to' => $payment->paid_to,
                'payment_datetime' => $payment->created_at ? $payment->created_at->toISOString() : null,
                'created_at' => $payment->created_at ? $payment->created_at->toISOString() : null,
                'status' => $payment->status,
                'status_badge' => $payment->status_badge,
                'is_reconciled' => $payment->is_reconciled,
                'wallet_balance_before' => (float) ($payment->wallet_balance_before ?? 0),
                'wallet_balance_after' => (float) ($payment->wallet_balance_after ?? 0),
                'created_at_formatted' => $payment->created_at ? $payment->created_at->format('M d, Y H:i') : '-',
                'meta' => $payment->meta,
            ];
        });
        
        return view('payments.index', compact('paymentsData', 'tenants', 'invoices'));
    }

    public function show($id)
    {
        try {
            $user = Auth::user();
            $company = $user->company;
            
            $payment = Payment::with(['tenant.user', 'invoice.items', 'invoice.tenancy.unit.estate', 'user'])
                ->findOrFail($id);
            
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

    /**
     * Get invoices for a specific tenant (AJAX endpoint)
     */
    public function getTenantInvoices($tenantId)
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            
            $invoices = Invoice::whereHas('tenancy', function($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id);
                })
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('items')
                ->orderBy('billing_month', 'asc')
                ->get()
                ->map(function($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
                        'billing_month' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                        'total_amount' => (float) $invoice->total_amount,
                        'remaining_amount' => (float) $invoice->remaining_amount,
                        'status' => $invoice->status,
                        'items' => $invoice->items->map(function($item) {
                            return [
                                'id' => $item->id,
                                'description' => $item->description,
                                'amount' => (float) $item->amount,
                                'paid_amount' => (float) ($item->paid_amount ?? 0),
                            ];
                        }),
                    ];
                });
            
            return response()->json([
                'success' => true,
                'invoices' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tenant invoices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices'
            ], 500);
        }
    }

    /**
     * Get payment creation data (AJAX endpoint)
     */
    public function getCreateData(Request $request)
    {
        try {
            $user = Auth::user();
            $company = $user->company;
            
            $tenants = [];
            if ($company) {
                $tenants = Tenant::whereHas('user', function($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->with('user', 'activeTenancy.unit')
                    ->get()
                    ->map(function($tenant) {
                        return [
                            'id' => $tenant->id,
                            'name' => $tenant->user->name ?? 'Unknown',
                            'unit_number' => $tenant->activeTenancy?->unit?->unit_number ?? 'No Unit',
                        ];
                    });
            }
            
            $invoices = [];
            if ($company) {
                $invoices = Invoice::whereHas('tenancy.unit', function($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->whereIn('status', ['unpaid', 'partial'])
                    ->with('tenancy.tenant.user')
                    ->orderBy('billing_month', 'desc')
                    ->get()
                    ->map(function($invoice) {
                        $tenantName = $invoice->tenancy?->tenant?->user?->name ?? 'Unknown';
                        $invoiceNumber = $invoice->invoice_number ?? 'INV-' . $invoice->id;
                        $billingMonth = $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '';
                        return [
                            'id' => $invoice->id,
                            'label' => $tenantName . ' - ' . $invoiceNumber . ($billingMonth ? ' (' . $billingMonth . ')' : ''),
                            'total_amount' => (float) $invoice->total_amount,
                            'remaining_amount' => (float) $invoice->remaining_amount,
                            'tenant_id' => $invoice->tenancy?->tenant_id,
                        ];
                    });
            }
            
            return response()->json([
                'success' => true,
                'tenants' => $tenants,
                'invoices' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment creation data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data'
            ], 500);
        }
    }

    /**
     * Get invoice details for payment (AJAX endpoint)
     */
    public function getInvoiceDetails($invoiceId)
    {
        try {
            $invoice = Invoice::with('items', 'tenancy.tenant.user')->findOrFail($invoiceId);
            
            return response()->json([
                'success' => true,
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
                    'billing_month' => $invoice->billing_month ? Carbon::parse($invoice->billing_month)->format('M Y') : '-',
                    'total_amount' => (float) $invoice->total_amount,
                    'total_paid' => (float) $invoice->total_paid,
                    'remaining_amount' => (float) $invoice->remaining_amount,
                    'status' => $invoice->status,
                    'tenant_id' => $invoice->tenancy?->tenant_id,
                    'tenant_name' => $invoice->tenancy?->tenant?->user?->name ?? 'Unknown',
                    'items' => $invoice->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'amount' => (float) $item->amount,
                            'paid_amount' => (float) ($item->paid_amount ?? 0),
                            'remaining_amount' => (float) $item->remaining_amount,
                            'item_type' => $item->item_type,
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching invoice details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice details'
            ], 500);
        }
    }

    /**
     * Bulk store payments
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'payments' => 'required|array',
            'payments.*.tenant_id' => 'required|exists:tenants,id',
            'payments.*.invoice_id' => 'required|exists:invoices,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_method' => 'required|string|in:cash,bank_transfer,mpesa_paybill',
            'payments.*.payment_datetime' => 'required|date',
            'payments.*.payment_month' => 'required|string',
        ]);
        
        try {
            $results = [];
            $errors = [];
            
            foreach ($request->payments as $index => $paymentData) {
                try {
                    $tenant = Tenant::find($paymentData['tenant_id']);
                    $invoice = Invoice::find($paymentData['invoice_id']);
                    
                    if (!$tenant || !$invoice) {
                        $errors[] = "Row " . ($index + 1) . ": Tenant or invoice not found";
                        continue;
                    }
                    
                    $result = $this->walletService->processDirectPayment(
                        tenant: $tenant,
                        invoice: $invoice,
                        amount: $paymentData['amount'],
                        paymentMethod: $paymentData['payment_method'],
                        externalReference: $paymentData['external_reference'] ?? 'BULK-' . time() . '-' . $index,
                        meta: [
                            'payment_datetime' => $paymentData['payment_datetime'],
                            'payment_month' => $paymentData['payment_month'],
                            'source' => 'bulk_upload',
                            'created_by' => auth()->id(),
                            'created_by_name' => auth()->user()->name,
                            'bulk_index' => $index,
                        ]
                    );
                    
                    if ($result['success']) {
                        $results[] = [
                            'row' => $index + 1,
                            'tenant_id' => $tenant->id,
                            'invoice_id' => $invoice->id,
                            'amount' => $paymentData['amount'],
                            'payment_id' => $result['payment_id'],
                            'status' => 'success'
                        ];
                    } else {
                        $errors[] = "Row " . ($index + 1) . ": " . ($result['error'] ?? 'Payment failed');
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            return response()->json([
                'success' => count($errors) === 0,
                'message' => count($results) . ' payments processed successfully' . (count($errors) > 0 ? ', ' . count($errors) . ' failed' : ''),
                'results' => $results,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing bulk payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tenant payment (for tenant-facing endpoints)
     */
    public function tenantPayment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:wallet,mpesa_stk',
            'phone' => 'required_if:payment_method,mpesa_stk|nullable|string',
        ]);
        
        try {
            $user = Auth::user();
            $tenant = $user->tenant;
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 400);
            }
            
            $invoice = Invoice::findOrFail($request->invoice_id);
            
            $tenancy = $tenant->activeTenancy;
            if (!$tenancy || $tenancy->id !== $invoice->tenancy_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to pay this invoice'
                ], 403);
            }
            
            if ($request->payment_method === 'wallet') {
                // Check wallet balance
                if ($tenant->balance < $request->amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient wallet balance. Available: KES ' . number_format($tenant->balance, 2)
                    ], 400);
                }
                
                $result = $this->walletService->payInvoice($tenant, $invoice, $request->amount);
                
                if ($result['success']) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment successful! KES ' . number_format($request->amount, 2) . ' paid from wallet.',
                        'new_balance' => $result['balance'],
                        'invoice_status' => $invoice->refresh()->status,
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Payment failed'
                ], 400);
            } elseif ($request->payment_method === 'mpesa_stk') {
                // M-Pesa bypasses wallet balance
                return $this->processMpesaPayment(
                    new Request([
                        'mpesa_phone' => $request->phone,
                        'amount' => $request->amount,
                        'invoice_id' => $invoice->id,
                        'payment_method' => 'mpesa_stk',
                    ]),
                    $tenant,
                    $invoice
                );
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment method'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Error processing tenant payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage()
            ], 500);
        }
    }
}