<?php
// app/Modules/Payments/Services/WalletService.php

namespace App\Modules\Payments\Services;

use App\Models\User;
use Bavix\Wallet\Models\Wallet as BavixWallet;
use Bavix\Wallet\Services\WalletService as BavixWalletService;
use Illuminate\Support\Facades\Log;

class WalletService
{
    protected $bavixWalletService;
    
    public function __construct(BavixWalletService $bavixWalletService)
    {
        $this->bavixWalletService = $bavixWalletService;
    }
    
    /**
     * Get wallet balance for a user using Bavix
     */
    public function getBalance($userId)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('User not found for wallet balance', ['user_id' => $userId]);
                return 0.00;
            }
            
            // Get Bavix wallet for the user
            $wallet = $user->wallet; // This uses Bavix's relationship
            
            if (!$wallet) {
                Log::info('No Bavix wallet found for user', ['user_id' => $userId]);
                return 0.00;
            }
            
            return (float) $wallet->balance;
            
        } catch (\Exception $e) {
            Log::error('Error getting Bavix wallet balance: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return 0.00;
        }
    }
    
    /**
     * Get Bavix wallet for a user (creates if not exists)
     */
    public function getOrCreateWallet($userId)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            // This will create a wallet if it doesn't exist
            $wallet = $user->getOrCreateWallet('default');
            
            return $wallet;
            
        } catch (\Exception $e) {
            Log::error('Error getting/creating Bavix wallet: ' . $e->getMessage(), [
                'user_id' => $userId
            ]);
            throw $e;
        }
    }
    
    /**
     * Add funds to a user's Bavix wallet
     */
    public function deposit($userId, $amount, $meta = [])
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            $wallet = $user->getOrCreateWallet('default');
            
            // Deposit using Bavix
            $transaction = $wallet->deposit($amount, $meta);
            
            Log::info('Bavix wallet deposit successful', [
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_id' => $transaction->id,
                'new_balance' => $wallet->balance
            ]);
            
            return [
                'success' => true,
                'transaction' => $transaction,
                'new_balance' => (float) $wallet->balance
            ];
            
        } catch (\Exception $e) {
            Log::error('Error depositing to Bavix wallet: ' . $e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount
            ]);
            throw $e;
        }
    }
    
    /**
     * Withdraw funds from a user's Bavix wallet
     */
    public function withdraw($userId, $amount, $meta = [])
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            $wallet = $user->getOrCreateWallet('default');
            
            // Check sufficient balance
            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }
            
            // Withdraw using Bavix
            $transaction = $wallet->withdraw($amount, $meta);
            
            Log::info('Bavix wallet withdrawal successful', [
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_id' => $transaction->id,
                'new_balance' => $wallet->balance
            ]);
            
            return [
                'success' => true,
                'transaction' => $transaction,
                'new_balance' => (float) $wallet->balance
            ];
            
        } catch (\Exception $e) {
            Log::error('Error withdrawing from Bavix wallet: ' . $e->getMessage(), [
                'user_id' => $userId,
                'amount' => $amount
            ]);
            throw $e;
        }
    }
    
    /**
     * Process a direct payment using Bavix wallet
     */
    public function processDirectPayment($tenant, $invoice, $amount, $paymentMethod, $externalReference, $meta = [])
    {
        try {
            $user = $tenant->user;
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Tenant user not found'
                ];
            }
            
            $wallet = $user->getOrCreateWallet('default');
            $invoiceRemaining = $invoice->remaining_amount;
            
            // Determine how much to pay to invoice vs wallet
            $amountToInvoice = min($amount, $invoiceRemaining);
            $amountToWallet = $amount - $amountToInvoice;
            
            $walletBalanceBefore = (float) $wallet->balance;
            
            // Process the payment
            $payment = $this->createPaymentRecord(
                $tenant,
                $invoice,
                $amount,
                $amountToInvoice,
                $amountToWallet,
                $paymentMethod,
                $externalReference,
                $meta,
                $walletBalanceBefore
            );
            
            // If there's an amount to pay to the invoice, deduct from wallet
            if ($amountToInvoice > 0) {
                // Withdraw from wallet for invoice payment
                $this->withdraw($user->id, $amountToInvoice, [
                    'type' => 'invoice_payment',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_id' => $payment->id
                ]);
            }
            
            // If there's an excess amount, add to wallet
            if ($amountToWallet > 0) {
                // Deposit excess to wallet
                $this->deposit($user->id, $amountToWallet, [
                    'type' => 'payment_excess',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_id' => $payment->id
                ]);
            }
            
            // Get updated wallet balance
            $wallet->refresh();
            $walletBalanceAfter = (float) $wallet->balance;
            
            // Update payment with wallet balances
            $payment->wallet_balance_before = $walletBalanceBefore;
            $payment->wallet_balance_after = $walletBalanceAfter;
            $payment->save();
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully!',
                'payment_id' => $payment->id,
                'wallet_balance' => $walletBalanceAfter,
                'amount_paid_to_invoice' => $amountToInvoice,
                'amount_added_to_wallet' => $amountToWallet,
                'invoice' => $invoice->fresh()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error processing direct payment with Bavix: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create payment record
     */
    protected function createPaymentRecord($tenant, $invoice, $totalAmount, $amountToInvoice, $amountToWallet, $paymentMethod, $externalReference, $meta, $walletBalanceBefore)
    {
        $payment = new \App\Models\Payment();
        $payment->tenant_id = $tenant->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $totalAmount;
        $payment->amount_paid_to_invoice = $amountToInvoice;
        $payment->amount_added_to_wallet = $amountToWallet;
        $payment->payment_method = $paymentMethod;
        $payment->external_reference = $externalReference;
        $payment->payment_datetime = $meta['payment_datetime'] ?? now();
        $payment->payment_month = $meta['payment_month'] ?? now()->format('Y-m');
        $payment->wallet_balance_before = $walletBalanceBefore;
        $payment->status = 'completed';
        $payment->is_reconciled = true;
        $payment->reconciled_at = now();
        $payment->reconciled_by = auth()->id();
        $payment->recorded_by = auth()->id();
        $payment->meta = array_merge($meta, [
            'source' => 'wallet_payment',
            'amount_to_invoice' => $amountToInvoice,
            'amount_to_wallet' => $amountToWallet,
            'payment_date' => now()->toISOString()
        ]);
        $payment->save();
        
        return $payment;
    }
}