<?php
// app/Modules/Payments/Controllers/WalletController.php

namespace App\Modules\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\WalletService;
use App\Modules\Users\Models\User;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Payments\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     * Deposit money (POST form submission)
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
            return redirect()->route('tenant.wallet')
                ->with('success', 'Successfully deposited KES ' . number_format($request->amount, 2));
        }
        
        return redirect()->route('tenant.wallet')
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
            return redirect()->route('tenant.wallet')
                ->with('success', 'Successfully withdrew KES ' . number_format($request->amount, 2));
        }
        
        return redirect()->route('tenant.wallet')
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
            return redirect()->route('tenant.wallet')
                ->with('error', 'You cannot transfer to yourself.');
        }
        
        if ($walletOwner instanceof Tenant && $walletOwner->user && $walletOwner->user->email === $request->recipient_email) {
            return redirect()->route('tenant.wallet')
                ->with('error', 'You cannot transfer to yourself.');
        }
        
        $recipient = User::where('email', $request->recipient_email)->first();
        
        if (!$recipient) {
            return redirect()->route('tenant.wallet')
                ->with('error', 'Recipient not found.');
        }
        
        // Get recipient's wallet owner (tenant if they are tenant, otherwise user)
        $recipientWallet = $recipient->isTenant() && $recipient->tenant ? $recipient->tenant : $recipient;
        
        $result = $this->walletService->transfer($walletOwner, $recipientWallet, $request->amount, [
            'description' => $request->description ?? 'Wallet transfer',
        ]);
        
        if ($result['success']) {
            return redirect()->route('tenant.wallet')
                ->with('success', 'Successfully transferred KES ' . number_format($request->amount, 2) . ' to ' . $request->recipient_email);
        }
        
        return redirect()->route('tenant.wallet')
            ->with('error', $result['error'] ?? 'Transfer failed. Please try again.');
    }
    
    /**
     * Pay invoice from wallet (POST form submission)
     */
    public function payInvoice(Request $request, $invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $invoice->remaining_amount,
        ]);
        
        $walletOwner = $this->getWalletOwner();
        
        // Check if wallet belongs to the tenant of this invoice
        if ($walletOwner instanceof Tenant && (!$walletOwner->activeTenancy || $walletOwner->activeTenancy->id !== $invoice->tenancy_id)) {
            return redirect()->route('tenant.wallet')
                ->with('error', 'You are not authorized to pay this invoice.');
        }
        
        $result = $this->walletService->payInvoice($walletOwner, $invoice, $request->amount);
        
        if ($result['success']) {
            return redirect()->route('tenant.wallet')
                ->with('success', 'Successfully paid KES ' . number_format($request->amount, 2) . ' towards invoice.');
        }
        
        return redirect()->route('tenant.wallet')
            ->with('error', $result['error'] ?? 'Payment failed. Please try again.');
    }
    
    /**
     * Get wallet balance (API)
     */
    public function apiGetBalance()
    {
        $walletOwner = $this->getWalletOwner();
        
        return response()->json([
            'success' => true,
            'balance' => $this->walletService->getBalance($walletOwner),
            'formatted' => 'KES ' . number_format($this->walletService->getBalance($walletOwner), 2),
        ]);
    }
    
    /**
     * Get transaction history (API)
     */
    public function apiGetTransactions(Request $request)
    {
        $walletOwner = $this->getWalletOwner();
        
        $perPage = $request->get('per_page', 15);
        
        $transactions = $this->walletService->getFilteredTransactions($walletOwner, [
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'type' => $request->type,
        ], $perPage);
        
        return response()->json($transactions);
    }
    
    /**
     * Get statement data (API)
     */
    public function apiGetStatement(Request $request)
    {
        $walletOwner = $this->getWalletOwner();
        
        $fromDate = $request->from_date ?? now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?? now()->toDateString();
        
        $transactions = $this->walletService->getFilteredTransactions($walletOwner, [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => 'all',
        ], 1000);
        
        $summary = [
            'total_credits' => $transactions->where('type', 'deposit')->sum('amount'),
            'total_debits' => $transactions->where('type', 'withdraw')->sum('amount'),
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
    }
    
    /**
     * Get opening balance before a specific date
     */
    protected function getOpeningBalance($walletOwner, $fromDate)
    {
        $transactions = $walletOwner->transactions()
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
        $request->validate([
            'pin' => 'required|string|size:4',
        ]);
        
        // Implement your PIN verification logic here
        // For now, assume PIN is correct
        return response()->json(['success' => true]);
    }

    public function exportTransactions(Request $request)
    {
        $walletOwner = $this->getWalletOwner();
        
        $transactions = $this->walletService->getFilteredTransactions($walletOwner, [
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'type' => $request->type,
        ], 10000);
        
        $filename = 'wallet_transactions_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Type', 'Amount', 'Description', 'Status']);
            
            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->created_at->format('Y-m-d H:i:s'),
                    ucfirst($tx->type),
                    $tx->amount,
                    $tx->meta['description'] ?? '',
                    'Completed',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}