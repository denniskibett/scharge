<?php
// app/Modules/Payments/Controllers/WalletController.php

namespace App\Modules\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\WalletService;
use App\Modules\Users\Models\User;
use App\Models\Tenant;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Transaction;
use App\Modules\Payments\Models\CompanyPaymentMethod;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    protected $walletService;
    
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }
    
    /**
     * Get the wallet owner based on authenticated user
     */
    protected function getWalletOwner()
    {
        $user = Auth::user();
        
        // If user is tenant and has tenant record, return tenant (owns the wallet)
        if ($user->isTenant() && $user->tenant) {
            return $user->tenant;
        }
        
        // Otherwise return user
        return $user;
    }
    
    /**
     * Get wallet instance for the owner
     */
    protected function getWallet($owner)
    {
        return $owner->wallet;
    }
    
    /**
     * Display wallet page
     */
    public function index()
    {
        $walletOwner = $this->getWalletOwner();
        $balance = $this->walletService->getBalance($walletOwner);
        $transactions = $this->walletService->getTransactions($walletOwner, 15);
        $wallet = $this->getWallet($walletOwner);
        
        // Get pending invoices for the tenant
        $pendingInvoices = collect();
        if ($walletOwner instanceof Tenant && $walletOwner->activeTenancy) {
            $pendingInvoices = Invoice::where('tenancy_id', $walletOwner->activeTenancy->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('items')
                ->get();
        }

        $cards = auth()->user()->cards ?? [
            [
                'id' => 1,
                'cardholderName' => auth()->user()->name ?? 'Card Holder',
                'cardNumber' => '4983',
                'expiry' => '09/29',
                'cvc' => '659',
                'active' => true,
                'cardType' => 'mastercard',
                'bgClass' => 'bg-gradient-to-br from-gray-800 to-gray-900 dark:from-gray-900 dark:to-gray-950'
            ]
        ];
        
        return view('tenant.wallet', compact('balance', 'transactions', 'pendingInvoices', 'wallet', 'walletOwner', 'cards'));
    }
    
    /**
     * API Deposit with confirmation status based on method
     */
    public function apiDeposit(Request $request)
    {
        try {
            Log::info('API Deposit called', $request->all());
            
            $request->validate([
                'amount' => 'required|numeric|min:1|max:500000',
                'payment_method' => 'required|in:mpesa,bank,card,message,manual',
                'phone_number' => 'nullable|string',
                'reference' => 'nullable|string',
                'transaction_message' => 'nullable|string',
                'bill_month' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);
            
            $walletOwner = $this->getWalletOwner();
            
            // Determine confirmation status
            // STK Push (mpesa, bank, card) = auto-confirmed (confirmed = 1)
            // Message and Manual = pending approval (confirmed = 0)
            $autoConfirmedMethods = ['mpesa', 'bank', 'card'];
            $isAutoConfirmed = in_array($request->payment_method, $autoConfirmedMethods);
            $confirmed = $isAutoConfirmed ? 1 : 0;
            
            // Generate unique reference
            $reference = $request->reference ?? 'DEP-' . time() . '-' . uniqid();
            
            // Check for duplicate only for auto-confirmed transactions
            if ($isAutoConfirmed) {
                $existingTx = Transaction::where('type', 'deposit')
                    ->where('confirmed', 1)
                    ->where(function ($q) use ($reference) {
                        $q->where('meta->reference', $reference)
                        ->orWhere('uuid', $reference);
                    })
                    ->first();

                if ($existingTx) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Duplicate transaction detected. This payment has already been processed.',
                        'duplicate' => true
                    ], 400);
                }
            }
            
            $amount = (float) $request->amount;
            
            // Build comprehensive meta data
            $metaData = [
                'type' => 'wallet_deposit',
                'payment_method' => $request->payment_method,
                'reference' => $reference,
                'phone_number' => $request->phone_number,
                'bill_month' => $request->bill_month,
                'transaction_message' => $request->transaction_message,
                'source' => $request->inputMode ?? 'manual',
                'deposited_at' => now()->toISOString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'confirmed' => $confirmed,
                'status' => $confirmed ? 'completed' : 'pending_approval',
                'requires_approval' => !$confirmed,
            ];
            
            // Add notes for manual top-up
            if ($request->payment_method === 'manual') {
                $metaData['notes'] = $request->notes;
                $metaData['initiated_by'] = Auth::user()->id;
                $metaData['initiated_by_name'] = Auth::user()->name;
            }
            
            // Add parsed data if in message mode
            if ($request->transaction_message) {
                $parsedData = $this->parseTransactionMessage($request->transaction_message);
                $metaData['parsed_data'] = $parsedData;
            }
            
            // For auto-confirmed deposits, update balance immediately
            // For pending deposits, just create the transaction record without updating balance
            if ($confirmed) {
                $transaction = $walletOwner->deposit($amount, [
                    'description' => 'Wallet deposit via ' . ucfirst($request->payment_method) . 
                                    ($request->bill_month ? ' for ' . $request->bill_month : ''),
                    'meta' => $metaData,
                ]);
                $walletOwner->refresh();
                $newBalance = (float) $walletOwner->balance;
            } else {
                $transaction = $this->createPendingTransaction($walletOwner, $amount, $metaData);
                $newBalance = (float) $walletOwner->balance; // Balance unchanged
            }
            
            // Get wallet details
            $wallet = $walletOwner->wallet;
            $walletId = $wallet ? $wallet->getKey() : null;
            $walletNumber = $walletId ? str_pad((string) $walletId, 16, '0', STR_PAD_LEFT) : null;
            
            // Dispatch event only for confirmed transactions
            if ($confirmed) {
                event(new \App\Events\WalletUpdated($walletOwner, $newBalance, $transaction));
            }
            
            $message = $confirmed 
                ? 'Successfully deposited KES ' . number_format($amount, 2)
                : 'Deposit request submitted. Pending approval by accountant.';
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'new_balance' => $newBalance,
                    'transaction_id' => $transaction->id,
                    'transaction_uuid' => $transaction->uuid,
                    'formatted_balance' => 'KES ' . number_format($newBalance, 2),
                    'wallet_number' => $walletNumber,
                    'confirmed' => $confirmed,
                    'requires_approval' => !$confirmed,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'error' => 'Validation failed: ' . json_encode($e->errors())
            ], 422);
        } catch (\Exception $e) {
            Log::error('API Deposit failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a pending transaction without updating wallet balance
     */
    protected function createPendingTransaction($walletOwner, float $amount, array $metaData)
    {
        // Get the wallet
        $wallet = $walletOwner->wallet;
        
        if (!$wallet) {
            $wallet = $walletOwner->createWallet([
                'name' => 'Default Wallet',
                'slug' => 'default',
                'description' => 'Main wallet',
            ]);
        }
        
        // Create transaction record directly (Bavix would normally do this)
        $transaction = new \Bavix\Wallet\Models\Transaction();
        $transaction->payable_type = get_class($walletOwner);
        $transaction->payable_id = $walletOwner->getKey();
        $transaction->wallet_id = $wallet->getKey();
        $transaction->type = 'deposit';
        $transaction->amount = $amount;
        $transaction->confirmed = false;
        $transaction->meta = $metaData;
        $transaction->uuid = (string) \Illuminate\Support\Str::uuid();
        $transaction->created_at = now();
        $transaction->updated_at = now();
        $transaction->save();
        
        return $transaction;
    }

/**
 * Approve a pending deposit (Accountant only)
 */
public function apiApproveDeposit(Request $request, $transactionId)
{
    try {
        \Log::info('=== API Approve Deposit Called ===', [
            'transaction_id' => $transactionId,
            'user_id' => Auth::id()
        ]);
        
        // Check if user is accountant
        $user = Auth::user();
        
        if (!$user->hasRole('accountant') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized. Only accountants can approve deposits.'
            ], 403);
        }
        
        // Get the transaction from Bavix wallet model
        $transaction = \Bavix\Wallet\Models\Transaction::findOrFail($transactionId);
        
        \Log::info('Transaction found:', [
            'id' => $transaction->id,
            'confirmed' => $transaction->confirmed,
            'payable_type' => $transaction->payable_type,
            'payable_id' => $transaction->payable_id,
            'amount' => $transaction->amount
        ]);
        
        if ($transaction->confirmed) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction already confirmed'
            ], 400);
        }
        
        // Find the wallet owner
        $walletOwner = null;
        $payableType = $transaction->payable_type;
        $payableId = $transaction->payable_id;
        
        \Log::info('Looking for wallet owner', [
            'payable_type' => $payableType,
            'payable_id' => $payableId
        ]);
        
        // Try to find the tenant using the correct model
        if ($payableType === 'App\Models\Tenant' || $payableType === 'App\Modules\Tenants\Models\Tenant') {
            // Use the actual tenant model that implements Wallet
            $tenantModel = $payableType === 'App\Models\Tenant' 
                ? \App\Models\Tenant::class 
                : \App\Modules\Tenants\Models\Tenant::class;
            
            $walletOwner = $tenantModel::with('user')->find($payableId);
            
            if (!$walletOwner) {
                \Log::error('Tenant not found', [
                    'payable_type' => $payableType,
                    'payable_id' => $payableId
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot find tenant for this transaction.'
                ], 400);
            }
            
            \Log::info('Tenant found:', [
                'tenant_id' => $walletOwner->id,
                'tenant_name' => $walletOwner->user?->name ?? 'Unknown'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Unknown payable type: ' . $payableType
            ], 400);
        }
        
        $amount = (float) $transaction->amount;
        
        // Ensure the wallet exists
        $wallet = $walletOwner->wallet;
        
        if (!$wallet) {
            \Log::info('Creating wallet for tenant', ['tenant_id' => $walletOwner->id]);
            $wallet = $walletOwner->createWallet([
                'name' => ($walletOwner->user?->name ?? 'Tenant') . "'s Wallet",
                'slug' => 'wallet-' . $walletOwner->id . '-' . time(),
                'description' => 'Main wallet for ' . ($walletOwner->user?->name ?? 'Tenant'),
            ]);
        }
        
        \Log::info('Wallet found/created:', [
            'wallet_id' => $wallet->id,
            'current_balance' => $wallet->balance
        ]);
        
        // Process the deposit using the wallet owner
        $depositTx = $walletOwner->deposit($amount, [
            'description' => $transaction->description ?? 'Approved deposit',
            'meta' => array_merge($transaction->meta ?? [], [
                'approved_at' => now()->toISOString(),
                'approved_by' => $user->id,
                'approved_by_name' => $user->name,
                'original_transaction_id' => $transaction->id,
                'original_transaction_uuid' => $transaction->uuid,
            ])
        ]);
        
        \Log::info('Deposit processed:', [
            'deposit_tx_id' => $depositTx->id,
            'new_balance' => $walletOwner->balance
        ]);
        
        // Update the original transaction
        $transaction->confirmed = true;
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'approved_at' => now()->toISOString(),
            'approved_by' => $user->id,
            'approved_by_name' => $user->name,
            'bavix_transaction_id' => $depositTx->id,
            'status' => 'completed'
        ]);
        $transaction->save();
        
        $walletOwner->refresh();
        $newBalance = (float) $walletOwner->balance;
        
        // Update payment record if it exists
        $this->updatePaymentRecordForApproval($transaction, $user);
        
        // Dispatch event for real-time updates
        event(new \App\Events\WalletUpdated($walletOwner, $newBalance, $depositTx));
        
        return response()->json([
            'success' => true,
            'message' => 'Deposit approved successfully! KES ' . number_format($amount, 2) . ' added to wallet.',
            'data' => [
                'new_balance' => $newBalance,
                'formatted_balance' => 'KES ' . number_format($newBalance, 2),
                'tenant_name' => $walletOwner->user?->name ?? 'Unknown',
                'transaction_id' => $depositTx->id,
            ]
        ]);
        
    } catch (\Bavix\Wallet\Exceptions\WalletNotFound $e) {
        \Log::error('Wallet not found error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Wallet not found. Please ensure the tenant has a wallet.'
        ], 400);
    } catch (\Exception $e) {
        \Log::error('API Approve Deposit failed: ' . $e->getMessage(), [
            'transaction_id' => $transactionId,
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Failed to approve deposit: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Update payment record when a deposit is approved
 */
private function updatePaymentRecordForApproval($transaction, $user)
{
    try {
        $meta = $transaction->meta ?? [];
        $reference = $meta['reference'] ?? $transaction->uuid;
        
        \Log::info('Looking for payment record', ['reference' => $reference]);
        
        $payment = \App\Models\Payment::where('transaction_reference', $reference)
            ->orWhere('external_reference', $reference)
            ->first();
        
        if ($payment) {
            $payment->status = 'completed';
            $payment->is_reconciled = true;
            $payment->reconciled_at = now();
            $payment->reconciled_by = $user->id;
            $payment->meta = array_merge($payment->meta ?? [], [
                'approved_at' => now()->toISOString(),
                'approved_by' => $user->id,
                'approved_by_name' => $user->name,
                'bavix_transaction_id' => $transaction->id,
            ]);
            $payment->save();
            
            \Log::info('Payment record updated', ['payment_id' => $payment->id]);
        } else {
            \Log::warning('No payment record found for reference', ['reference' => $reference]);
        }
    } catch (\Exception $e) {
        \Log::warning('Could not update payment record: ' . $e->getMessage());
    }
}



/**
 * Get pending deposits for accountant approval
 */
public function apiGetPendingDeposits(Request $request)
{
    try {
        $user = Auth::user();
        
        // Check if user is accountant
        if (!$user->hasRole('accountant') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized'
            ], 403);
        }
        
        // Get company ID from user
        $companyId = $user->company_id;
        
        // Get tenant IDs for this company
        $tenantIds = \App\Modules\Tenants\Models\Tenant::whereHas('activeTenancy.unit.estate', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->pluck('id')->toArray();
        
        // Find pending transactions for tenants in this company
        $pendingDeposits = \Bavix\Wallet\Models\Transaction::where('type', 'deposit')
            ->where('confirmed', false)
            ->where(function($query) use ($tenantIds) {
                $query->where(function($q) use ($tenantIds) {
                    $q->where('payable_type', 'App\Modules\Tenants\Models\Tenant')
                      ->whereIn('payable_id', $tenantIds);
                })->orWhere(function($q) use ($tenantIds) {
                    $q->where('payable_type', 'App\Models\Tenant')
                      ->whereIn('payable_id', $tenantIds);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Format the response
        $formatted = $pendingDeposits->map(function($tx) {
            // Get tenant from payable relationship or manually
            $tenant = $tx->payable;
            
            if (!$tenant) {
                // Try to find tenant manually
                $payableType = $tx->payable_type;
                $payableId = $tx->payable_id;
                
                if ($payableType === 'App\Models\Tenant' || $payableType === 'App\Modules\Tenants\Models\Tenant') {
                    $tenantClass = $payableType === 'App\Models\Tenant' 
                        ? \App\Models\Tenant::class 
                        : \App\Modules\Tenants\Models\Tenant::class;
                    $tenant = $tenantClass::with('user', 'activeTenancy.unit')->find($payableId);
                }
            }
            
            $meta = $tx->meta ?? [];
            
            return [
                'id' => $tx->id,
                'amount' => (float) $tx->amount,
                'tenant_name' => $tenant?->user?->name ?? 'Unknown',
                'tenant_unit' => $tenant?->activeTenancy?->unit?->unit_number ?? 'Unknown',
                'payment_method' => $meta['payment_method'] ?? 'unknown',
                'bill_month' => $meta['bill_month'] ?? null,
                'notes' => $meta['notes'] ?? $meta['transaction_message'] ?? null,
                'created_at' => $tx->created_at,
                'meta' => $meta,
                'tenant_id' => $tenant?->id ?? null,
                'reference' => $meta['reference'] ?? null,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formatted->values()->toArray(),
            'total' => $formatted->count(),
        ]);
        
    } catch (\Exception $e) {
        Log::error('API Get Pending Deposits failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Failed to fetch pending deposits'
        ], 500);
    }
}

    /**
     * Parse transaction message to extract data
     */
    private function parseTransactionMessage($message)
    {
        $parsed = [
            'amount' => null,
            'transaction_id' => null,
            'date' => null,
            'time' => null,
            'sender' => null,
            'receiver' => null,
            'phone_number' => null,
            'paybill_number' => null,
            'account_number' => null,
        ];
        
        if (!$message) return $parsed;
        
        // Extract amount
        if (preg_match('/(?:KES|KSH|KSh|Ksh)[\s\.]*([\d,]+(?:\.\d{2})?)/i', $message, $match)) {
            $parsed['amount'] = (float) str_replace(',', '', $match[1]);
        }
        
        // Extract transaction ID
        if (preg_match('/\b([A-Z0-9]{8,12})\b/', $message, $match)) {
            $parsed['transaction_id'] = $match[1];
        }
        
        // Extract date
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $message, $match)) {
            $parsed['date'] = "{$match[1]}/{$match[2]}/{$match[3]}";
        }
        
        // Extract phone number
        if (preg_match('/\b(07\d{8}|01\d{8})\b/', $message, $match)) {
            $parsed['phone_number'] = $match[1];
        }
        
        // Extract paybill
        if (preg_match('/\((\d{5,7})\)/', $message, $match)) {
            $parsed['paybill_number'] = $match[1];
        }
        
        return $parsed;
    }
        
   
    public function apiTransfer(Request $request)
    {
        try {
            $request->validate([
                'recipient_email' => 'required|email|exists:users,email',
                'amount' => 'required|numeric|min:1|max:100000',
                'description' => 'nullable|string|max:255',
                'pin' => 'required|string|size:4',
            ]);
            
            $walletOwner = $this->getWalletOwner();
            
            // Don't allow self-transfer
            if ($walletOwner instanceof User && $walletOwner->email === $request->recipient_email) {
                return response()->json([
                    'success' => false,
                    'error' => 'You cannot transfer to yourself.'
                ], 400);
            }
            
            if ($walletOwner instanceof Tenant && $walletOwner->user && $walletOwner->user->email === $request->recipient_email) {
                return response()->json([
                    'success' => false,
                    'error' => 'You cannot transfer to yourself.'
                ], 400);
            }
            
            $recipient = User::where('email', $request->recipient_email)->first();
            
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'error' => 'Recipient not found.'
                ], 404);
            }
            
            // Get recipient's wallet owner (tenant if they are tenant, otherwise user)
            $recipientWallet = $recipient->isTenant() && $recipient->tenant ? $recipient->tenant : $recipient;
            
            $result = $this->walletService->transfer($walletOwner, $recipientWallet, $request->amount, [
                'description' => $request->description ?? 'Wallet transfer',
            ]);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully transferred KES ' . number_format($request->amount, 2) . ' to ' . $request->recipient_email,
                    'data' => [
                        'new_balance' => $result['balance'],
                        'transfer_id' => $result['transfer_id'],
                        'formatted_balance' => 'KES ' . number_format($result['balance'], 2),
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Transfer failed. Please try again.'
            ], 400);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'error' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            Log::error('API Transfer failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred. Please try again.'
            ], 500);
        }
    }
    
   
    public function apiPayInvoice(Request $request, $invoiceId)
    {
        try {
            \Log::info('=== STARTING PAYMENT ===');
            
            $invoice = Invoice::with('items')->findOrFail($invoiceId);
            
            $request->validate([
                'amount' => 'required|numeric|min:0.01|max:' . $invoice->remaining_amount,
            ]);
            
            $walletOwner = $this->getWalletOwner();
            
            // Verify tenant owns this invoice
            if ($walletOwner instanceof Tenant) {
                $tenancy = $walletOwner->activeTenancy;
                if (!$tenancy || $tenancy->id !== $invoice->tenancy_id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'You are not authorized to pay this invoice.'
                    ], 403);
                }
            }
            
            // Check sufficient balance
            if ($walletOwner->balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient wallet balance. Available: KES ' . number_format($walletOwner->balance, 2)
                ], 400);
            }
            
            \Log::info('Calling walletService->payInvoice', [
                'amount' => $request->amount,
                'balance' => $walletOwner->balance
            ]);
            
            $result = $this->walletService->payInvoice($walletOwner, $invoice, $request->amount);
            
            \Log::info('WalletService result', ['result' => $result]);
            
            if ($result['success']) {
                // Get updated invoice with fresh data
                $invoice->refresh();
                $invoice->load('items');
                
                \Log::info('Payment successful, returning response');
                
                return response()->json([
                    'success' => true,
                    'message' => sprintf('Successfully paid KES %s', number_format($request->amount, 2)),
                    'data' => [
                        'new_balance' => $result['balance'],
                        'formatted_balance' => 'KES ' . number_format($result['balance'], 2),
                        'payment_id' => $result['payment_id'] ?? null,
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'invoice' => [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'total_amount' => (float) $invoice->total_amount,
                            'total_paid' => (float) $invoice->total_paid,
                            'remaining_amount' => (float) $invoice->remaining_amount,
                            'payment_percentage' => $invoice->payment_percentage,
                            'status' => $invoice->status,
                            'items' => $invoice->items->map(function($item) {
                                return [
                                    'id' => $item->id,
                                    'description' => $item->description,
                                    'amount' => (float) $item->amount,
                                    'paid_amount' => (float) ($item->paid_amount ?? 0),
                                    'remaining_amount' => (float) $item->remaining_amount,
                                    'is_fully_paid' => $item->isFullyPaid(),
                                    'payment_percentage' => $item->payment_percentage,
                                ];
                            }),
                        ],
                        'allocations' => $result['allocations'] ?? [],
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Payment failed. Please try again.'
            ], 400);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'error' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            \Log::error('API Pay Invoice failed: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    public function apiPayMultipleInvoices(Request $request)
    {
        try {
            $request->validate([
                'payments' => 'required|array',
                'payments.*.invoice_id' => 'required|exists:invoices,id',
                'payments.*.amount' => 'required|numeric|min:0.01',
            ]);
            
            $walletOwner = $this->getWalletOwner();
            
            // Calculate total amount
            $totalAmount = collect($request->payments)->sum('amount');
            
            // Check sufficient balance
            if ($walletOwner->balance < $totalAmount) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient wallet balance. Available: KES ' . number_format($walletOwner->balance, 2)
                ], 400);
            }
            
            $result = $this->walletService->payMultipleInvoices($walletOwner, $request->payments);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => sprintf('Successfully paid total of KES %s', number_format($result['total_paid'], 2)),
                    'data' => [
                        'new_balance' => $result['new_balance'],
                        'formatted_balance' => 'KES ' . number_format($result['new_balance'], 2),
                        'total_paid' => $result['total_paid'],
                        'results' => $result['results'],
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Payment failed. Please try again.'
            ], 400);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'error' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            Log::error('API Pay Multiple Invoices failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred. Please try again.'
            ], 500);
        }
    }
    
    public function getBalance($walletOwner): float
    {
        return (float) $walletOwner->balance;
    }
    

    public function apiGetBalance()
    {
        try {
            $walletOwner = $this->getWalletOwner();
            $walletOwner->refresh(); // Force fresh data from database
            
            // Clear any cached balance
            cache()->forget('wallet_balance_' . $walletOwner->id);
            
            $balance = (float) $walletOwner->balance;
            
            // Get the actual wallet from the correct relationship
            $wallet = $walletOwner->wallet;
            
            $walletNumber = null;
            $maskedWalletNumber = null;
            
            if ($wallet && $wallet->getKey()) {
                $walletId = $wallet->getKey();
                $walletNumber = str_pad((string) $walletId, 16, '0', STR_PAD_LEFT);
                $maskedWalletNumber = '•••• •••• •••• ' . substr($walletNumber, -4);
            } else {
                $walletNumber = str_pad((string) $walletOwner->id, 16, '0', STR_PAD_LEFT);
                $maskedWalletNumber = '•••• •••• •••• ' . substr($walletNumber, -4);
            }
            
            return response()->json([
                'success' => true,
                'balance' => $balance,
                'wallet_number' => $walletNumber,
                'masked_wallet_number' => $maskedWalletNumber,
                'formatted' => 'KES ' . number_format($balance, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('API Get Balance failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch balance'
            ], 500);
        }
    }

    
/**
 * Get transaction history (API)
 */
public function apiGetTransactions(Request $request)
{
    try {
        $walletOwner = $this->getWalletOwner();
        
        $perPage = $request->get('per_page', 15);
        $includePending = $request->get('include_pending', true);
        
        $query = $walletOwner->transactions()
            ->orderBy('created_at', 'desc');
        
        // Only exclude pending if explicitly requested
        if (!$includePending) {
            $query->where('confirmed', true);
        }
        
        $transactions = $query->paginate($perPage);
        
        $formattedTransactions = $transactions->through(function($tx) {
            // Determine status based on confirmed flag and meta
            $status = 'Completed';
            $requiresApproval = false;
            
            if (!$tx->confirmed) {
                $meta = $tx->meta ?? [];
                $status = $meta['status'] ?? 'Pending Approval';
                $requiresApproval = true;
                
                // Check if it's a manual top-up
                if (($meta['payment_method'] ?? '') === 'manual') {
                    $status = 'Pending Approval (Manual Top-up)';
                } elseif (($meta['payment_method'] ?? '') === 'message') {
                    $status = 'Pending Approval (Transaction Message)';
                }
            }
            
            return [
                'id' => $tx->id,
                'uuid' => $tx->uuid,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'confirmed' => (bool) $tx->confirmed,
                'created_at' => $tx->created_at,
                'description' => $tx->description ?? $this->getTransactionDescription($tx),
                'payment_method' => $tx->meta['payment_method'] ?? ($tx->type === 'deposit' ? 'Unknown' : 'Wallet'),
                'reference' => $tx->meta['reference'] ?? substr($tx->uuid, 0, 8),
                'phone_number' => $tx->meta['phone_number'] ?? null,
                'bill_month' => $tx->meta['bill_month'] ?? null,
                'status' => $status,
                'requires_approval' => $requiresApproval,
                'is_pending' => !$tx->confirmed,
                'meta' => $tx->meta,
                'notes' => $tx->meta['notes'] ?? null,
                'initiated_by' => $tx->meta['initiated_by_name'] ?? null,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedTransactions->items(),
            'current_page' => $transactions->currentPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
            'from' => $transactions->firstItem(),
            'to' => $transactions->lastItem(),
        ]);
        
    } catch (\Exception $e) {
        Log::error('API Get Transactions failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Failed to fetch transactions'
        ], 500);
    }
}

/**
 * Get transaction description from meta
 */
private function getTransactionDescription($tx): string
{
    $meta = $tx->meta ?? [];
    
    if ($tx->type === 'deposit') {
        if (($meta['payment_method'] ?? '') === 'manual') {
            return 'Manual Top-up - ' . ($meta['notes'] ?? 'Pending Approval');
        }
        if (($meta['payment_method'] ?? '') === 'message') {
            return 'Transaction Message - ' . ($meta['bill_month'] ?? '');
        }
        return 'Wallet Deposit';
    }
    
    if ($tx->type === 'withdraw') {
        return 'Wallet Withdrawal';
    }
    
    return $tx->description ?? 'Transaction';
}
    
    /**
     * Get statement data (API)
     */
    public function apiGetStatement(Request $request)
    {
        try {
            $walletOwner = $this->getWalletOwner();
            
            $fromDate = $request->from_date ?? now()->startOfMonth()->toDateString();
            $toDate = $request->to_date ?? now()->toDateString();
            
            $transactions = Transaction::where(function($q) use ($walletOwner) {
                    if ($walletOwner instanceof User) {
                        $q->where('user_id', $walletOwner->id);
                    } elseif ($walletOwner instanceof Tenant) {
                        $q->where('tenant_id', $walletOwner->id);
                    }
                })
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->orderBy('created_at', 'asc')
                ->get();
            
            $summary = [
                'total_credits' => (float) $transactions->where('type', 'deposit')->sum('amount'),
                'total_debits' => (float) $transactions->where('type', 'withdraw')->sum('amount'),
                'opening_balance' => $this->getOpeningBalance($walletOwner, $fromDate),
                'closing_balance' => $this->walletService->getBalance($walletOwner),
            ];
            
            return response()->json([
                'success' => true,
                'transactions' => $transactions,
                'summary' => $summary,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Get Statement failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch statement'
            ], 500);
        }
    }
    
    /**
     * Get invoice details for payment (API)
     */
    public function apiGetInvoiceDetails($invoiceId)
    {
        try {
            $invoice = Invoice::with('items')->findOrFail($invoiceId);
            $walletOwner = $this->getWalletOwner();
            
            // Verify authorization
            if ($walletOwner instanceof Tenant) {
                $tenancy = $walletOwner->activeTenancy;
                if (!$tenancy || $tenancy->id !== $invoice->tenancy_id) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized'
                    ], 403);
                }
            }
            
            return response()->json([
                'success' => true,
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => (float) $invoice->total_amount,
                    'total_paid' => (float) $invoice->total_paid,
                    'remaining_amount' => (float) $invoice->remaining_amount,
                    'status' => $invoice->status,
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
                ],
                'wallet_balance' => $this->walletService->getBalance($walletOwner),
                'formatted_wallet_balance' => 'KES ' . number_format($this->walletService->getBalance($walletOwner), 2),
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Get Invoice Details failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch invoice details'
            ], 500);
        }
    }
    
    /**
     * Get tenant details for deposit modal (API)
     */
    public function apiGetTenantDetails()
    {
        try {
            $user = Auth::user();
            
            Log::info('API Get Tenant Details called', ['user_id' => $user->id, 'user_role' => $user->role?->name]);
            
            if (!$user->isTenant()) {
                return response()->json([
                    'success' => false,
                    'error' => 'User is not a tenant'
                ], 400);
            }
            
            $tenant = $user->tenant;
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'error' => 'No tenant record found'
                ], 400);
            }
            
            $activeTenancy = $tenant->activeTenancy;
            
            // Get wallet from Bavix
            $wallet = $tenant->wallet;
            $walletId = $wallet ? $wallet->getKey() : null;
            
            // Generate wallet number (16-digit padded from wallet ID)
            $fullWalletNumber = $walletId ? str_pad((string) $walletId, 16, '0', STR_PAD_LEFT) : str_pad((string) $tenant->id, 16, '0', STR_PAD_LEFT);
            $maskedWalletNumber = '•••• •••• •••• ' . substr($fullWalletNumber, -4);
            
            // Initialize default values
            $tenantDetails = [
                'name' => $user->name ?? 'N/A',
                'company' => null,
                'estate' => null,
                'unit' => null,
                'wallet_id' => $wallet?->uuid ?? null,
                'wallet_number' => $fullWalletNumber,
                'masked_wallet_number' => $maskedWalletNumber,
            ];
            
            $companyDetails = [
                'name' => null,
                'estate_name' => null,
                'payment_methods' => [],
                'default_payment_method' => null,
            ];
            
            // Get unit and estate information from active tenancy
            if ($activeTenancy) {
                $unit = $activeTenancy->unit;
                if ($unit) {
                    $tenantDetails['unit'] = $unit->unit_number;
                    
                    $estate = $unit->estate;
                    if ($estate) {
                        $tenantDetails['estate'] = $estate->name;
                        $companyDetails['estate_name'] = $estate->name;
                        
                        $company = $estate->company;
                        if ($company) {
                            $tenantDetails['company'] = $company->name;
                            $companyDetails['name'] = $company->name;
                            
                            // Get company payment methods
                            $paymentMethods = CompanyPaymentMethod::where('company_id', $company->id)
                                ->where('is_active', true)
                                ->get();
                            
                            foreach ($paymentMethods as $pm) {
                                $methodData = [
                                    'id' => $pm->id,
                                    'type' => $pm->type,
                                    'provider' => $pm->provider,
                                    'account_name' => $pm->account_name,
                                    'account_number' => $pm->account_number,
                                    'paybill_number' => $pm->paybill_number,
                                    'till_number' => $pm->till_number,
                                    'bank_name' => $pm->bank_name,
                                    'branch_name' => $pm->branch_name,
                                    'swift_code' => $pm->swift_code,
                                    'instructions' => $pm->instructions,
                                    'is_default' => (bool) $pm->is_default,
                                    'display_name' => $pm->type === 'mobile_money' ? 'M-Pesa Paybill' : 'Bank Transfer',
                                ];
                                
                                $companyDetails['payment_methods'][] = $methodData;
                                
                                if ($pm->is_default) {
                                    $companyDetails['default_payment_method'] = $methodData;
                                }
                            }
                            
                            if (!$companyDetails['default_payment_method'] && count($companyDetails['payment_methods']) > 0) {
                                $companyDetails['default_payment_method'] = $companyDetails['payment_methods'][0];
                            }
                        }
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'tenant' => $tenantDetails,
                'company' => $companyDetails,
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Get Tenant Details failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch tenant details: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get opening balance before a specific date
     */
    protected function getOpeningBalance($walletOwner, $fromDate)
    {
        $transactions = Transaction::where(function($q) use ($walletOwner) {
                if ($walletOwner instanceof User) {
                    $q->where('user_id', $walletOwner->id);
                } elseif ($walletOwner instanceof Tenant) {
                    $q->where('tenant_id', $walletOwner->id);
                }
            })
            ->whereDate('created_at', '<', $fromDate)
            ->get();
        
        $credits = $transactions->where('type', 'deposit')->sum('amount');
        $debits = $transactions->where('type', 'withdraw')->sum('amount');
        
        return $credits - $debits;
    }
    
    /**
     * Verify PIN for sensitive operations (API)
     */
    public function verifyPin(Request $request)
    {
        try {
            $request->validate([
                'pin' => 'required|string|size:4',
            ]);
            
            // Implement your PIN verification logic here
            $user = Auth::user();
            
            // Example: Check against stored PIN hash
            // if (Hash::check($request->pin, $user->wallet_pin)) {
            if ($request->pin === '1234') { // Placeholder - replace with actual verification
                return response()->json(['success' => true]);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Invalid PIN'
            ], 400);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'error' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to verify PIN'
            ], 500);
        }
    }

    
    /**
     * Withdraw money (POST form submission)
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'destination' => 'required|string',
        ]);
        
        $walletOwner = $this->getWalletOwner();
        
        $result = $this->walletService->withdraw($walletOwner, $request->amount, [
            'description' => 'Wallet withdrawal to ' . $request->destination,
            'destination' => $request->destination,
            'reference' => 'WIT-' . time(),
        ]);
        
        if ($result['success']) {
            return redirect()->route('wallet.index')
                ->with('success', 'Successfully withdrew KES ' . number_format($request->amount, 2));
        }
        
        return redirect()->route('wallet.index')
            ->with('error', $result['error'] ?? 'Withdrawal failed. Please try again.');
    }
    
    /**
     * Transfer to another user (POST form submission)
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'recipient_email' => 'required|email|exists:users,email',
            'amount' => 'required|numeric|min:1|max:100000',
            'description' => 'nullable|string|max:255',
        ]);
        
        $walletOwner = $this->getWalletOwner();
        
        // Don't allow self-transfer
        if ($walletOwner instanceof User && $walletOwner->email === $request->recipient_email) {
            return redirect()->route('wallet.index')
                ->with('error', 'You cannot transfer to yourself.');
        }
        
        if ($walletOwner instanceof Tenant && $walletOwner->user && $walletOwner->user->email === $request->recipient_email) {
            return redirect()->route('wallet.index')
                ->with('error', 'You cannot transfer to yourself.');
        }
        
        $recipient = User::where('email', $request->recipient_email)->first();
        
        if (!$recipient) {
            return redirect()->route('wallet.index')
                ->with('error', 'Recipient not found.');
        }
        
        // Get recipient's wallet owner (tenant if they are tenant, otherwise user)
        $recipientWallet = $recipient->isTenant() && $recipient->tenant ? $recipient->tenant : $recipient;
        
        $result = $this->walletService->transfer($walletOwner, $recipientWallet, $request->amount, [
            'description' => $request->description ?? 'Wallet transfer',
        ]);
        
        if ($result['success']) {
            return redirect()->route('wallet.index')
                ->with('success', 'Successfully transferred KES ' . number_format($request->amount, 2) . ' to ' . $request->recipient_email);
        }
        
        return redirect()->route('wallet.index')
            ->with('error', $result['error'] ?? 'Transfer failed. Please try again.');
    }
    
    /**
     * Pay invoice from wallet (POST form submission - fallback)
     */
    public function payInvoiceForm(Request $request, $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $invoice->remaining_amount,
        ]);
        
        $walletOwner = $this->getWalletOwner();
        
        // Verify tenant owns this invoice
        if ($walletOwner instanceof Tenant) {
            $tenancy = $walletOwner->activeTenancy;
            if (!$tenancy || $tenancy->id !== $invoice->tenancy_id) {
                return redirect()->back()->with('error', 'You are not authorized to pay this invoice.');
            }
        }
        
        $result = $this->walletService->payInvoice($walletOwner, $invoice, $request->amount);
        
        if ($result['success']) {
            return redirect()->back()->with('success', 
                sprintf('Successfully paid KES %s towards invoice #%s. Remaining: KES %s',
                    number_format($request->amount, 2),
                    $invoice->invoice_number ?? $invoice->id,
                    number_format($invoice->refresh()->remaining_amount, 2)
                )
            );
        }
        
        return redirect()->back()->with('error', $result['error'] ?? 'Payment failed. Please try again.');
    }
    
    /**
     * Export transactions to CSV
     */
    public function exportTransactions(Request $request)
    {
        $walletOwner = $this->getWalletOwner();
        
        $transactions = Transaction::where(function($q) use ($walletOwner) {
                if ($walletOwner instanceof User) {
                    $q->where('user_id', $walletOwner->id);
                } elseif ($walletOwner instanceof Tenant) {
                    $q->where('tenant_id', $walletOwner->id);
                }
            })
            ->when($request->from_date, function($q, $date) {
                $q->whereDate('created_at', '>=', $date);
            })
            ->when($request->to_date, function($q, $date) {
                $q->whereDate('created_at', '<=', $date);
            })
            ->when($request->type && $request->type !== 'all', function($q, $type) {
                $q->where('type', $type);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        
        $filename = 'wallet_transactions_' . date('d-m-Y_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Type', 'Amount', 'Description', 'Meta', 'Status']);
            
            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->created_at->format('Y-m-d H:i:s'),
                    ucfirst($tx->type),
                    $tx->amount,
                    $tx->description ?? '',
                    $tx->meta ? json_encode($tx->meta) : '',
                    $tx->status ?? 'Completed',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }


    public function apiGetPendingInvoices()
    {
        try {
            Log::info('API Get Pending Invoices called', [
                'user_id' => Auth::id(),
                'user_role' => Auth::user()->role?->name ?? 'unknown'
            ]);
            
            $walletOwner = $this->getWalletOwner();
            
            // Check if user is tenant
            if (!($walletOwner instanceof Tenant)) {
                Log::warning('Non-tenant attempted to access pending invoices', [
                    'user_id' => Auth::id(),
                    'owner_type' => get_class($walletOwner)
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Only tenants can view invoices',
                    'debug_info' => [
                        'user_type' => get_class($walletOwner),
                        'is_tenant' => false
                    ]
                ], 400);
            }
            
            Log::info('Wallet owner identified', [
                'tenant_id' => $walletOwner->id,
                'tenant_name' => $walletOwner->name
            ]);
            
            // Check for active tenancy
            $activeTenancy = $walletOwner->activeTenancy;
            
            if (!$activeTenancy) {
                Log::warning('Tenant has no active tenancy', [
                    'tenant_id' => $walletOwner->id
                ]);
                
                return response()->json([
                    'success' => true,
                    'invoices' => [],
                    'debug_info' => [
                        'has_active_tenancy' => false,
                        'message' => 'No active tenancy found for this tenant'
                    ]
                ]);
            }
            
            Log::info('Active tenancy found', [
                'tenancy_id' => $activeTenancy->id,
                'unit_id' => $activeTenancy->unit_id
            ]);
            
            // Fetch invoices
            $invoices = Invoice::where('tenancy_id', $activeTenancy->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->with('items')
                ->orderBy('billing_month', 'desc')
                ->get();
            
            Log::info('Invoices fetched', [
                'count' => $invoices->count(),
                'tenancy_id' => $activeTenancy->id
            ]);
            
            if ($invoices->isEmpty()) {
                Log::info('No pending invoices found for tenancy', [
                    'tenancy_id' => $activeTenancy->id
                ]);
            }
            
            $formattedInvoices = $invoices->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? 'INV-' . $invoice->id,
                    'billing_month' => $invoice->billing_month,
                    'total_amount' => (float) $invoice->total_amount,
                    'total_paid' => (float) ($invoice->total_paid ?? 0),
                    'remaining_amount' => (float) $invoice->remaining_amount,
                    'status' => $invoice->status,
                    'payment_percentage' => $invoice->payment_percentage,
                    'items' => $invoice->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_type' => $item->item_type,
                            'description' => $item->description,
                            'amount' => (float) $item->amount,
                            'paid_amount' => (float) ($item->paid_amount ?? 0),
                            'remaining_amount' => (float) ($item->amount - ($item->paid_amount ?? 0))
                        ];
                    })
                ];
            });
            
            return response()->json([
                'success' => true,
                'invoices' => $formattedInvoices,
                'debug_info' => [
                    'tenant_id' => $walletOwner->id,
                    'tenancy_id' => $activeTenancy->id,
                    'invoice_count' => $invoices->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Get Pending Invoices failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch invoices: ' . $e->getMessage(),
                'debug_info' => [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Reject a pending deposit
     */
    public function apiRejectDeposit(Request $request, $transactionId)
    {
        try {
            $user = Auth::user();
            
            // Check if user is accountant
            if (!$user->hasRole('accountant') && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only accountants can reject deposits.'
                ], 403);
            }
            
            $transaction = Transaction::findOrFail($transactionId);
            
            if ($transaction->confirmed) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transaction already confirmed'
                ], 400);
            }
            
            // Update the transaction as rejected
            $transaction->confirmed = false;
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'rejected_at' => now()->toISOString(),
                'rejected_by' => $user->id,
                'rejected_by_name' => $user->name,
                'status' => 'rejected'
            ]);
            $transaction->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Deposit rejected successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('API Reject Deposit failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}