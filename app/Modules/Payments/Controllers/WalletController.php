<?php
// app/Modules/Payments/Controllers/WalletController.php

namespace App\Modules\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\WalletService;
use App\Modules\Users\Models\User;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Transaction;
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
        $this->middleware('auth');
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
     * Deposit money (API - AJAX)
     */
    public function apiDeposit(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1|max:500000',
                'payment_method' => 'required|in:mpesa,bank,card',
                'phone_number' => 'required_if:payment_method,mpesa|nullable|string',
            ]);
            
            $walletOwner = $this->getWalletOwner();
            
            $result = $this->walletService->deposit($walletOwner, $request->amount, [
                'description' => 'Wallet deposit via ' . ucfirst($request->payment_method),
                'payment_method' => $request->payment_method,
                'reference' => $request->reference ?? 'DEP-' . time(),
                'phone_number' => $request->phone_number ?? null,
            ]);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully deposited KES ' . number_format($request->amount, 2),
                    'data' => [
                        'new_balance' => $result['balance'],
                        'transaction_id' => $result['transaction_id'],
                        'formatted_balance' => 'KES ' . number_format($result['balance'], 2),
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Deposit failed. Please try again.'
            ], 400);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'error' => 'Validation failed'
            ], 422);
        } catch (\Exception $e) {
            Log::error('API Deposit failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Transfer money (API - AJAX)
     */
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
    
    /**
     * Pay invoice from wallet (API - AJAX)
     */
    public function apiPayInvoice(Request $request, $invoiceId)
    {
        try {
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
            
            $result = $this->walletService->payInvoice($walletOwner, $invoice, $request->amount);
            
            if ($result['success']) {
                // Get updated invoice with fresh data
                $invoice->refresh();
                $invoice->load('items');
                
                return response()->json([
                    'success' => true,
                    'message' => sprintf('Successfully paid KES %s', number_format($request->amount, 2)),
                    'data' => [
                        'new_balance' => $result['balance'],
                        'formatted_balance' => 'KES ' . number_format($result['balance'], 2),
                        'payment_id' => $result['payment_id'],
                        'transaction_id' => $result['transaction_id'],
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
                        'allocations' => $result['allocations'],
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
            Log::error('API Pay Invoice failed: ' . $e->getMessage(), [
                'invoice_id' => $invoiceId,
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'An error occurred. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Pay multiple invoices at once (API - AJAX)
     */
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
    
    /**
     * Get wallet balance (API)
     */
    public function apiGetBalance()
    {
        try {
            $walletOwner = $this->getWalletOwner();
            $balance = $this->walletService->getBalance($walletOwner);
            $walletNumber = $walletOwner->wallet?->uuid ?? substr($walletOwner->id . time(), -8);
            
            return response()->json([
                'success' => true,
                'balance' => $balance,
                'wallet_number' => $walletNumber,
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
            
            $query = Transaction::where(function($q) use ($walletOwner) {
                if ($walletOwner instanceof User) {
                    $q->where('user_id', $walletOwner->id);
                } elseif ($walletOwner instanceof Tenant) {
                    $q->where('tenant_id', $walletOwner->id);
                }
            });
            
            if ($request->from_date) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            
            if ($request->to_date) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            
            if ($request->type && $request->type !== 'all') {
                $query->where('type', $request->type);
            }
            
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json($transactions);
            
        } catch (\Exception $e) {
            Log::error('API Get Transactions failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch transactions'
            ], 500);
        }
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
     * Deposit money (POST form submission - fallback for non-JS)
     */
    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:500000',
            'payment_method' => 'required|in:mpesa,bank,card',
            'phone_number' => 'required_if:payment_method,mpesa|nullable|string',
        ]);
        
        $walletOwner = $this->getWalletOwner();
        
        $result = $this->walletService->deposit($walletOwner, $request->amount, [
            'description' => 'Wallet deposit via ' . ucfirst($request->payment_method),
            'payment_method' => $request->payment_method,
            'reference' => $request->reference ?? 'DEP-' . time(),
            'phone_number' => $request->phone_number ?? null,
        ]);
        
        if ($result['success']) {
            return redirect()->route('wallet.index')
                ->with('success', 'Successfully deposited KES ' . number_format($request->amount, 2));
        }
        
        return redirect()->route('wallet.index')
            ->with('error', $result['error'] ?? 'Deposit failed. Please try again.');
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
        
        $filename = 'wallet_transactions_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Type', 'Amount', 'Description', 'Reference', 'Status']);
            
            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->created_at->format('Y-m-d H:i:s'),
                    ucfirst($tx->type),
                    $tx->amount,
                    $tx->description ?? '',
                    $tx->reference ?? '',
                    $tx->status ?? 'Completed',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}