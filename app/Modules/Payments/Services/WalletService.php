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
     * Deposit money into wallet using Bavix
     */
    public function deposit($walletOwner, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            // Check if transaction with same reference already exists
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
                
                // Also check payments table for duplicate
                $existingPayment = Payment::where('transaction_reference', $meta['reference'])
                    ->first();
                    
                if ($existingPayment) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'error' => 'Payment with this reference already exists',
                        'duplicate' => true
                    ];
                }
            }
            
            // Get balance before deposit
            $balanceBefore = (float) $walletOwner->balance;
            
            // Prepare meta data
            $metaData = array_merge([
                'deposited_at' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'balance_before' => $balanceBefore,
            ], $meta);
            
            // Perform deposit
            $transaction = $walletOwner->deposit($amount, [
                'description' => $meta['description'] ?? 'Wallet deposit',
                'meta' => $metaData,
            ]);
            
            $balanceAfter = (float) $walletOwner->balance;
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => $balanceAfter,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
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
            
            $balanceBefore = (float) $walletOwner->balance;
            
            $transaction = $walletOwner->withdraw($amount, [
                'description' => $meta['description'] ?? 'Wallet withdrawal',
                'destination' => $meta['destination'] ?? null,
                'meta' => array_merge($meta, ['balance_before' => $balanceBefore]),
            ]);
            
            $balanceAfter = (float) $walletOwner->balance;
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => $balanceAfter,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
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
     * Pay invoice from wallet
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
            
            // Get balance before withdrawal
            $balanceBefore = (float) $walletOwner->balance;
            
            // 2. Withdraw from tenant's wallet
            $withdrawalTransaction = $walletOwner->withdraw($amount, [
                'description' => 'Invoice payment - ' . ($invoice->invoice_number ?? 'INV-' . $invoice->id),
                'invoice_id' => $invoice->id,
                'meta' => array_merge($meta, [
                    'type' => 'invoice_payment',
                    'balance_before' => $balanceBefore
                ])
            ]);
            
            $balanceAfter = (float) $walletOwner->balance;
            
            // 3. Distribute payment across invoice items and create payment records
            $allocations = $this->distributePaymentToItems(
                $walletOwner,
                $invoice,
                $amount,
                $withdrawalTransaction->uuid,
                $balanceBefore,
                $balanceAfter,
                $meta
            );
            
            // 4. Update invoice totals and status
            $this->updateInvoiceAfterPayment($invoice);
            
            DB::commit();
            
            return [
                'success' => true,
                'balance' => $balanceAfter,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_id' => $allocations[0]['payment_id'] ?? null,
                'transaction_id' => $withdrawalTransaction->id,
                'transaction_uuid' => $withdrawalTransaction->uuid,
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
                'wallet_owner_id' => $walletOwner->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Distribute payment across invoice items and create payment records
     */
    protected function distributePaymentToItems(
        $walletOwner,
        Invoice $invoice,
        float $amount,
        string $transactionUuid,
        float $balanceBefore,
        float $balanceAfter,
        array $meta = []
    ): array {
        $items = $invoice->items()->get();
        $remainingAmount = $amount;
        $allocations = [];
        
        foreach ($items as $item) {
            if ($remainingAmount <= 0) break;
            
            $itemRemaining = $item->getRemainingAmountAttribute();
            
            if ($itemRemaining <= 0) continue;
            
            // Allocate amount to this item
            $allocatedAmount = min($remainingAmount, $itemRemaining);
            
            try {
                // Create payment record for this item
                $payment = Payment::recordWalletPayment(
                    tenant: $walletOwner,
                    invoice: $invoice,
                    invoiceItem: $item,
                    amount: $allocatedAmount,
                    balanceBefore: $balanceBefore,
                    balanceAfter: $balanceAfter,
                    transactionReference: $transactionUuid,
                    user: $walletOwner->user ?? null,
                    meta: array_merge($meta, [
                        'allocated_from_total_payment' => $amount,
                        'payment_distribution_date' => now()->toISOString(),
                        'invoice_item_description' => $item->description,
                    ])
                );
                
                // Update the invoice item's paid_amount
                $item->recordPayment($allocatedAmount, $payment->id);
                
                $allocations[] = [
                    'payment_id' => $payment->id,
                    'item_id' => $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'allocated_amount' => $allocatedAmount,
                    'was_fully_paid' => $item->isFullyPaid()
                ];
                
                $remainingAmount -= $allocatedAmount;
            } catch (\Exception $e) {
                Log::error('Failed to create payment record for item', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        
        // Handle overpayment (if any amount remains)
        if ($remainingAmount > 0) {
            $creditItem = $this->handleOverpayment($invoice, $remainingAmount);
            
            $payment = Payment::recordWalletPayment(
                tenant: $walletOwner,
                invoice: $invoice,
                invoiceItem: $creditItem,
                amount: $remainingAmount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                transactionReference: $transactionUuid,
                user: $walletOwner->user ?? null,
                meta: array_merge($meta, [
                    'type' => 'overpayment_credit',
                    'allocated_from_total_payment' => $amount,
                    'payment_distribution_date' => now()->toISOString(),
                ])
            );
            
            $creditItem->recordPayment($remainingAmount, $payment->id);
            
            $allocations[] = [
                'payment_id' => $payment->id,
                'item_id' => $creditItem->id,
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
    protected function handleOverpayment(Invoice $invoice, float $excessAmount): InvoiceItem
    {
        // Create a credit item that applies to future bills
        $creditItem = $invoice->items()->create([
            'description' => 'Credit / Overpayment - Applied to future bills',
            'amount' => 0,
            'item_type' => 'credit',
            'paid_amount' => 0,
        ]);
        
        Log::info('Overpayment credit created', [
            'invoice_id' => $invoice->id,
            'excess_amount' => $excessAmount,
            'credit_item_id' => $creditItem->id
        ]);
        
        return $creditItem;
    }

    /**
     * Transfer money between wallets
     */
    public function transfer($from, $to, float $amount, array $meta = []): array
    {
        try {
            DB::beginTransaction();
            
            $fromBalanceBefore = (float) $from->balance;
            $toBalanceBefore = (float) $to->balance;
            
            // Use Bavix transfer
            $transfer = $from->transfer($to, $amount, [
                'description' => $meta['description'] ?? 'Wallet transfer',
                'meta' => array_merge($meta, [
                    'from_balance_before' => $fromBalanceBefore,
                    'to_balance_before' => $toBalanceBefore,
                ])
            ]);
            
            $fromBalanceAfter = (float) $from->balance;
            $toBalanceAfter = (float) $to->balance;
            
            DB::commit();
            
            return [
                'success' => true,
                'transfer_id' => $transfer->id,
                'from_balance' => $fromBalanceAfter,
                'to_balance' => $toBalanceAfter,
                'from_balance_before' => $fromBalanceBefore,
                'from_balance_after' => $fromBalanceAfter,
                'to_balance_before' => $toBalanceBefore,
                'to_balance_after' => $toBalanceAfter,
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


    /**
     * Process a direct payment (deposit + pay invoice in one transaction)
     * Supports overpayment - excess amount stays in wallet
     */
/**
 * Process a direct payment (deposit + pay invoice in one transaction)
 * Supports overpayment - excess amount stays in wallet
 */
public function processDirectPayment(
    Tenant $tenant,
    Invoice $invoice,
    float $amount,
    string $paymentMethod,
    string $externalReference,
    array $meta = []
): array {
    try {
        DB::beginTransaction();
        
        // 1. Validate the payment
        $remainingInvoiceAmount = $invoice->remaining_amount;
        
        // Calculate how much goes to invoice vs wallet
        $amountToPayInvoice = min($amount, $remainingInvoiceAmount);
        $amountToRemainInWallet = $amount - $amountToPayInvoice;
        
        \Log::info('Processing direct payment', [
            'total_amount' => $amount,
            'invoice_remaining' => $remainingInvoiceAmount,
            'to_invoice' => $amountToPayInvoice,
            'to_wallet' => $amountToRemainInWallet
        ]);
        
        // Get balances before any operations
        $balanceBeforeDeposit = (float) $tenant->balance;
        
        // 2. DEPOSIT FULL amount to tenant's wallet
        $depositTransaction = $tenant->deposit($amount, [
            'description' => 'Direct payment deposit of KES ' . number_format($amount, 2),
            'invoice_id' => $invoice->id,
            'meta' => array_merge($meta, [
                'type' => 'direct_payment_deposit',
                'payment_method' => $paymentMethod,
                'external_reference' => $externalReference,
                'is_direct_payment' => true,
                'total_amount' => $amount,
                'invoice_payment_amount' => $amountToPayInvoice,
                'wallet_credit_amount' => $amountToRemainInWallet,
            ])
        ]);
        
        $balanceAfterDeposit = (float) $tenant->balance;
        \Log::info('After deposit', ['balance' => $balanceAfterDeposit]);
        
        // 3. WITHDRAW only the invoice amount (not the full amount)
        $withdrawalTransaction = null;
        $balanceAfterWithdrawal = $balanceAfterDeposit;
        
        if ($amountToPayInvoice > 0) {
            $withdrawalTransaction = $tenant->withdraw($amountToPayInvoice, [
                'description' => 'Invoice payment - ' . ($invoice->invoice_number ?? 'INV-' . $invoice->id),
                'invoice_id' => $invoice->id,
                'meta' => array_merge($meta, [
                    'type' => 'invoice_payment',
                    'payment_method' => $paymentMethod,
                    'external_reference' => $externalReference,
                    'is_direct_payment' => true,
                    'balance_before' => $balanceAfterDeposit,
                    'amount_paid_to_invoice' => $amountToPayInvoice,
                ])
            ]);
            
            $balanceAfterWithdrawal = (float) $tenant->balance;
            \Log::info('After withdrawal', ['balance' => $balanceAfterWithdrawal]);
        }
        
        // 4. Create Payment record (direct, completed)
        $payment = Payment::recordDirectPayment(
            tenant: $tenant,
            invoice: $invoice,
            amount: $amountToPayInvoice,  // Only the invoice portion
            paymentMethod: $paymentMethod,
            externalReference: $externalReference,
            user: auth()->user(),
            meta: array_merge($meta, [
                'total_deposited' => $amount,
                'amount_paid_to_invoice' => $amountToPayInvoice,
                'amount_added_to_wallet' => $amountToRemainInWallet,
                'deposit_transaction_id' => $depositTransaction->id,
                'deposit_transaction_uuid' => $depositTransaction->uuid,
                'withdrawal_transaction_id' => $withdrawalTransaction?->id,
                'withdrawal_transaction_uuid' => $withdrawalTransaction?->uuid,
                'balance_before_deposit' => $balanceBeforeDeposit,
                'balance_after_deposit' => $balanceAfterDeposit,
                'balance_after_withdrawal' => $balanceAfterWithdrawal,
                'has_excess_wallet_balance' => $amountToRemainInWallet > 0,
            ])
        );
        
        // 5. Distribute payment across invoice items (only if paying invoice)
        $allocations = [];
        if ($amountToPayInvoice > 0) {
            $allocations = $this->distributePaymentToItemsDirect(
                $invoice,
                $amountToPayInvoice,
                $payment->id
            );
        }
        
        // 6. Update invoice totals and status (only if paying invoice)
        if ($amountToPayInvoice > 0) {
            $this->updateInvoiceAfterPayment($invoice);
        }
        
        DB::commit();
        
        // Build response message
        $message = '';
        if ($amountToPayInvoice > 0 && $amountToRemainInWallet > 0) {
            $message = sprintf(
                'Payment processed! KES %s paid towards invoice #%s. KES %s added to wallet balance.',
                number_format($amountToPayInvoice, 2),
                $invoice->invoice_number ?? $invoice->id,
                number_format($amountToRemainInWallet, 2)
            );
        } elseif ($amountToPayInvoice > 0) {
            $message = sprintf(
                'Invoice #%s paid! KES %s deducted.',
                $invoice->invoice_number ?? $invoice->id,
                number_format($amountToPayInvoice, 2)
            );
        } else {
            $message = sprintf(
                'KES %s added to wallet balance.',
                number_format($amountToRemainInWallet, 2)
            );
        }
        
        return [
            'success' => true,
            'message' => $message,
            'payment_id' => $payment->id,
            'payment' => $payment,
            'deposit_transaction_id' => $depositTransaction->id,
            'withdrawal_transaction_id' => $withdrawalTransaction?->id,
            'invoice' => $amountToPayInvoice > 0 ? [
                'id' => $invoice->id,
                'remaining_amount' => $invoice->refresh()->remaining_amount,
                'total_paid' => $invoice->total_paid,
                'status' => $invoice->status,
            ] : null,
            'allocations' => $allocations,
            'wallet_balance' => $balanceAfterWithdrawal,
            'wallet_balance_before' => $balanceBeforeDeposit,
            'amount_paid_to_invoice' => $amountToPayInvoice,
            'amount_added_to_wallet' => $amountToRemainInWallet,
        ];
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Direct payment failed: ' . $e->getMessage(), [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

    /**
     * Distribute payment across invoice items for direct payments
     */
    protected function distributePaymentToItemsDirect(
        Invoice $invoice,
        float $amount,
        int $paymentId
    ): array {
        $items = $invoice->items()->get();
        $remainingAmount = $amount;
        $allocations = [];
        
        foreach ($items as $item) {
            if ($remainingAmount <= 0) break;
            
            $itemRemaining = $item->getRemainingAmountAttribute();
            
            if ($itemRemaining <= 0) continue;
            
            $allocatedAmount = min($remainingAmount, $itemRemaining);
            
            // Update the invoice item's paid_amount
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
        
        return $allocations;
    }
}