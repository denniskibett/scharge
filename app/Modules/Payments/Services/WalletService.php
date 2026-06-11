<?php
// app/Modules/Payments/Services/WalletService.php

namespace App\Modules\Payments\Services;

use App\Models\Company;
use App\Modules\Users\Models\User;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Payments\Models\Transaction;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\InvoiceItem;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Get wallet balance for a user/tenant
     */
    public function getBalance($walletOwner): float
    {
        return (float) $walletOwner->balance;
    }
    
    /**
     * Deposit money into wallet using Bavix - META FIRST APPROACH
     */
    public function deposit($walletOwner, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            // Check if transaction with same reference already exists in meta
            if (isset($meta['reference'])) {
                $existingTransaction = Transaction::where('type', 'deposit')
                    ->where('meta->reference', $meta['reference'])
                    ->first();
                    
                if ($existingTransaction) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'error' => 'Transaction with this reference already exists',
                        'duplicate' => true
                    ];
                }
            }
            
            // Prepare meta data with all relevant info
            $metaData = array_merge([
                'deposited_at' => now()->toISOString(),
                'ip_address' => request()->ip(),
            ], $meta);
            
            // Use Bavix wallet deposit with comprehensive meta
            $transaction = $walletOwner->deposit($amount, [
                'description' => $meta['description'] ?? 'Wallet deposit',
                'meta' => $metaData,  // Store everything in meta
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => (float) $walletOwner->balance,
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
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
     * Get available balance (only confirmed transactions)
     */
    public function getAvailableBalance($walletOwner): float
    {
        // In bavix, balance only includes confirmed transactions
        // The wallet's balance property already reflects only confirmed transactions
        return (float) $walletOwner->balance;
    }
    
    /**
     * Pay invoice from wallet - COMPLETE IMPLEMENTATION
     */
    public function payInvoice($walletOwner, Invoice $invoice, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            // 1. Validate the payment
            $remainingInvoiceAmount = $invoice->remaining_amount;
            if ($amount > $remainingInvoiceAmount) {
                throw new \Exception("Payment amount exceeds remaining invoice amount");
            }
            
            if ($amount > $walletOwner->balance) {
                throw new \Exception("Insufficient wallet balance. Available: KES " . number_format($walletOwner->balance, 2));
            }
            
            // 2. Withdraw from tenant's wallet (DEBIT tenant)
            $withdrawalTransaction = $walletOwner->withdraw($amount, [
                'description' => 'Invoice payment - ' . ($invoice->invoice_number ?? 'INV-' . $invoice->id),
                'invoice_id' => $invoice->id,
                'meta' => array_merge($meta, ['type' => 'invoice_payment'])
            ]);
            
            // 3. CRITICAL: Credit the company/estate owner (CREDIT company)
            $companyWallet = $this->getCompanyWallet($invoice);
            $depositTransaction = $companyWallet->deposit($amount, [
                'description' => 'Rent payment for invoice ' . ($invoice->invoice_number ?? 'INV-' . $invoice->id),
                'source_tenant_id' => $walletOwner->id,
                'invoice_id' => $invoice->id,
                'meta' => ['type' => 'rent_collection']
            ]);
            
            // 4. Create Payment record
            $payment = $this->createPaymentRecord($walletOwner, $invoice, $amount, $withdrawalTransaction);
            
            // 5. Distribute payment across invoice items
            $allocations = $this->distributePaymentToItems($invoice, $amount, $payment->id);
            
            // 6. Update invoice totals and status
            $this->updateInvoiceAfterPayment($invoice);
            
            // 7. Record the transaction in your custom transactions table
            $this->createCustomTransaction($walletOwner, [
                'type' => 'invoice_payment',
                'amount' => $amount,
                'description' => 'Payment for invoice #' . ($invoice->invoice_number ?? $invoice->id),
                'reference' => $withdrawalTransaction->uuid,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => (float) $walletOwner->balance,
                'payment_id' => $payment->id,
                'transaction_id' => $withdrawalTransaction->id,
                'invoice' => [
                    'id' => $invoice->id,
                    'remaining_amount' => $invoice->refresh()->remaining_amount,
                    'total_paid' => $invoice->total_paid,
                    'status' => $invoice->status,
                ],
                'allocations' => $allocations,
            ];
            
        } catch (InsufficientFunds $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Insufficient wallet balance. Available: KES ' . number_format($walletOwner->balance, 2),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice payment failed: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'wallet_owner_id' => $walletOwner->id
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pay multiple invoices at once
     */
    public function payMultipleInvoices($walletOwner, array $invoicesWithAmounts, array $meta = []): array
    {
        $results = [];
        $totalAmount = 0;
        
        DB::beginTransaction();
        
        try {
            foreach ($invoicesWithAmounts as $item) {
                $invoice = Invoice::find($item['invoice_id']);
                if (!$invoice) {
                    throw new \Exception("Invoice not found: " . $item['invoice_id']);
                }
                
                $amount = $item['amount'];
                $totalAmount += $amount;
                
                $result = $this->payInvoice($walletOwner, $invoice, $amount, $meta);
                
                if (!$result['success']) {
                    throw new \Exception($result['error']);
                }
                
                $results[] = $result;
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'total_paid' => $totalAmount,
                'new_balance' => (float) $walletOwner->balance,
                'results' => $results,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create the company wallet for an invoice
     * This is where the money actually goes
     */
    protected function getCompanyWallet(Invoice $invoice)
    {
        // Navigate: Invoice → Tenancy → Unit → Estate → Company
        $company = $invoice->tenancy?->unit?->estate?->company;
        
        if (!$company) {
            throw new \Exception("Cannot determine company for this invoice");
        }
        
        // Get or create wallet for the company
        $companyWallet = $company->wallet;
        
        if (!$companyWallet) {
            // Create wallet for company if it doesn't exist
            $companyWallet = $company->createWallet([
                'name' => 'Company Operating Account',
                'slug' => 'company-operating',
                'description' => 'Main operating account for ' . $company->name,
            ]);
        }
        
        return $companyWallet;
    }

    /**
     * Distribute payment across invoice items
     * This handles partial payments correctly
     */
    protected function distributePaymentToItems(Invoice $invoice, float $amount, int $paymentId): array
    {
        $items = $invoice->items()->get();
        $remainingAmount = $amount;
        $allocations = [];
        
        foreach ($items as $item) {
            if ($remainingAmount <= 0) break;
            
            $itemRemaining = $item->getRemainingAmountAttribute();
            
            if ($itemRemaining <= 0) continue;
            
            // Allocate either the full remaining amount or what's left of the payment
            $allocatedAmount = min($remainingAmount, $itemRemaining);
            
            // Record payment on this item
            $item->recordPayment($allocatedAmount, $paymentId);
            
            $allocations[] = [
                'item_id' => $item->id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'allocated_amount' => $allocatedAmount,
                'was_fully_paid' => $item->isFullyPaid()
            ];
            
            $remainingAmount -= $allocatedAmount;
        }
        
        // If there's remaining amount after all items are paid (overpayment scenario)
        if ($remainingAmount > 0) {
            $this->handleOverpayment($invoice, $remainingAmount, $paymentId);
            $allocations[] = [
                'item_id' => null,
                'item_type' => 'credit',
                'description' => 'Credit / Overpayment',
                'allocated_amount' => $remainingAmount,
                'was_fully_paid' => true
            ];
        }
        
        return $allocations;
    }

    /**
     * Update invoice after receiving payment
     */
    protected function updateInvoiceAfterPayment(Invoice $invoice): Invoice
    {
        // Recalculate total paid from items
        $totalPaidFromItems = $invoice->items()->sum('paid_amount');
        
        // Update invoice total_paid
        $invoice->total_paid = $totalPaidFromItems;
        $invoice->save();
        
        // Update status based on new totals
        $invoice->updateStatus();
        
        return $invoice->fresh();
    }

    /**
     * Handle overpayment (when tenant pays more than invoice total)
     */
    protected function handleOverpayment(Invoice $invoice, float $excessAmount, int $paymentId): void
    {
        // Create a credit item (negative balance that applies to next invoice)
        $creditItem = $invoice->items()->create([
            'description' => 'Credit / Overpayment - Applied to future bills',
            'amount' => 0, // Credit doesn't add to invoice total
            'item_type' => 'credit',
            'paid_amount' => $excessAmount,
            'payment_id' => $paymentId,
        ]);
        
        Log::info('Overpayment recorded', [
            'invoice_id' => $invoice->id,
            'excess_amount' => $excessAmount,
            'credit_item_id' => $creditItem->id
        ]);
    }

    /**
     * Create payment record for invoice payment
     */
    protected function createPaymentRecord($walletOwner, Invoice $invoice, float $amount, $transaction): Payment
    {
        $payment = new Payment();
        $payment->tenancy_id = $invoice->tenancy_id;
        $payment->invoice_id = $invoice->id;
        $payment->transaction_id = $transaction->id;
        $payment->reference_number = $transaction->uuid;
        $payment->amount = $amount;
        $payment->payment_method = 'wallet';
        $payment->payer_name = $walletOwner instanceof Tenant ? $walletOwner->name : ($walletOwner->name ?? 'Wallet User');
        $payment->payment_datetime = now();
        $payment->status = 'verified';
        $payment->verified_at = now();
        $payment->save();
        
        return $payment;
    }
    
    /**
     * Create custom transaction record for your app
     */
    protected function createCustomTransaction($walletOwner, array $data)
    {
        $customTx = new Transaction();
        $customTx->user_id = $walletOwner instanceof User ? $walletOwner->id : ($walletOwner->user_id ?? null);
        $customTx->tenant_id = $walletOwner instanceof Tenant ? $walletOwner->id : null;
        $customTx->type = $data['type'];
        $customTx->amount = $data['amount'];
        $customTx->description = $data['description'] ?? null;
        $customTx->reference = $data['reference'] ?? null;
        $customTx->status = 'completed';
        $customTx->meta = $data;
        $customTx->save();
        
        return $customTx;
    }
    
    /**
     * Get transaction history
     */
    public function getTransactions($walletOwner, int $perPage = 20)
    {
        return $walletOwner->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
    
    /**
     * Get filtered transactions
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
}