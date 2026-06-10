<?php
// app/Modules/Payments/Services/WalletService.php

namespace App\Modules\Payments\Services;

use App\Modules\Users\Models\User;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Payments\Models\Transaction;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Get wallet balance for a user
     */
    public function getBalance($walletOwner): float
    {
        return (float) $walletOwner->balance;
    }
    
    /**
     * Deposit money into wallet
     */
    public function deposit($walletOwner, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            // Create the deposit transaction
            $transaction = $walletOwner->deposit($amount, [
                'description' => $meta['description'] ?? 'Wallet deposit',
                'payment_method' => $meta['payment_method'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'phone_number' => $meta['phone_number'] ?? null,
            ]);
            
            // Create your custom transaction record for reference
            $this->createCustomTransaction($walletOwner, [
                'type' => 'deposit',
                'amount' => $amount,
                'reference' => $meta['reference'] ?? null,
                'description' => $meta['description'] ?? 'Wallet deposit',
                'wallet_transaction_id' => $transaction->id,
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => (float) $walletOwner->balance,
                'transaction_id' => $transaction->id,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet deposit failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Withdraw money from wallet
     */
    public function withdraw($walletOwner, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            $transaction = $walletOwner->withdraw($amount, [
                'description' => $meta['description'] ?? 'Wallet withdrawal',
                'destination' => $meta['destination'] ?? null,
            ]);
            
            $this->createCustomTransaction($walletOwner, [
                'type' => 'withdraw',
                'amount' => $amount,
                'reference' => $meta['reference'] ?? null,
                'description' => $meta['description'] ?? 'Wallet withdrawal',
                'wallet_transaction_id' => $transaction->id,
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => (float) $walletOwner->balance,
                'transaction_id' => $transaction->id,
            ];
        } catch (InsufficientFunds $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Insufficient funds. Your balance is ' . $this->getBalance($walletOwner),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet withdrawal failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Transfer between wallets
     */
    public function transfer($fromWalletOwner, $toWalletOwner, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            $transfer = $fromWalletOwner->transfer($toWalletOwner, $amount, [
                'description' => $meta['description'] ?? 'Wallet transfer',
            ]);
            
            $this->createCustomTransaction($fromWalletOwner, [
                'type' => 'transfer_out',
                'amount' => $amount,
                'description' => $meta['description'] ?? 'Transfer to ' . ($toWalletOwner->email ?? $toWalletOwner->name),
                'reference' => $transfer->uuid,
                'wallet_transaction_id' => $transfer->withdraw_id,
            ]);
            
            $this->createCustomTransaction($toWalletOwner, [
                'type' => 'transfer_in',
                'amount' => $amount,
                'description' => $meta['description'] ?? 'Transfer from ' . ($fromWalletOwner->email ?? $fromWalletOwner->name),
                'reference' => $transfer->uuid,
                'wallet_transaction_id' => $transfer->deposit_id,
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => (float) $fromWalletOwner->balance,
                'transfer_id' => $transfer->id,
            ];
        } catch (InsufficientFunds $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Insufficient funds for transfer',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet transfer failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Pay invoice from wallet
     */

    public function payInvoice($walletOwner, $invoice, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            // Withdraw from wallet
            $transaction = $walletOwner->withdraw($amount, [
                'description' => 'Invoice payment - ' . ($invoice->invoice_number ?? 'INV-' . $invoice->id),
                'invoice_id' => $invoice->id,
                'type' => 'rent_payment',
            ]);
            
            // Update invoice paid amount
            $newTotalPaid = ($invoice->total_paid ?? 0) + $amount;
            $invoice->total_paid = $newTotalPaid;
            $invoice->save();
            
            // Update invoice status
            $invoice->updateStatus();
            
            // Create payment record
            $payment = new \App\Modules\Payments\Models\Payment();
            $payment->tenancy_id = $invoice->tenancy_id;
            $payment->invoice_id = $invoice->id;
            $payment->transaction_id = $transaction->id;
            $payment->reference_number = $transaction->uuid;
            $payment->amount = $amount;
            $payment->payment_method = 'wallet';
            $payment->payment_datetime = now();
            $payment->status = 'verified';
            $payment->verified_at = now();
            $payment->save();
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => (float) $walletOwner->balance,
                'payment_id' => $payment->id,
                'transaction_id' => $transaction->id,
            ];
        } catch (InsufficientFunds $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Insufficient wallet balance. Available: KES ' . number_format($walletOwner->balance, 2),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice payment failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get transaction history
     */
    public function getTransactions($walletOwner, int $perPage = 20)
    {
        return $walletOwner->transactions()
            ->with('payable')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
    
    /**
     * Get transaction history with date filters
     */
    public function getFilteredTransactions($walletOwner, array $filters = [], int $perPage = 20)
    {
        $query = $walletOwner->transactions();
        
        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }
        
        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }
        
        if (isset($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
    
    /**
     * Create custom transaction record for your app
     */
    protected function createCustomTransaction($walletOwner, array $data)
    {
        // Use your existing Transaction model
        $customTx = new \App\Modules\Payments\Models\Transaction();
        $customTx->user_id = $walletOwner instanceof User ? $walletOwner->id : ($walletOwner->user_id ?? null);
        $customTx->tenancy_id = $walletOwner instanceof Tenant ? ($walletOwner->activeTenancy->id ?? null) : null;
        $customTx->parsed_amount = $data['amount'];
        $customTx->parsed_reference_number = $data['reference'] ?? null;
        $customTx->parsed_payment_method = $data['payment_method'] ?? 'wallet';
        $customTx->parsed_payment_datetime = now();
        $customTx->status = 'verified';
        $customTx->save();
        
        return $customTx;
    }
    
    /**
     * Create payment record for invoice payment
     */
    protected function createPaymentRecord($walletOwner, $invoice, float $amount, $transaction)
    {
        $payment = new \App\Modules\Payments\Models\Payment();
        $payment->tenancy_id = $invoice->tenancy_id;
        $payment->invoice_id = $invoice->id;
        $payment->transaction_id = $transaction->id;
        $payment->reference_number = $transaction->uuid;
        $payment->amount = $amount;
        $payment->payment_method = 'wallet';
        $payment->payment_datetime = now();
        $payment->status = 'verified';
        $payment->verified_at = now();
        $payment->save();
        
        return $payment;
    }
}