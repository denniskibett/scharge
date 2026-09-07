<?php
// app/Modules/Payments/Services/WalletService.php

namespace App\Modules\Payments\Services;

use App\Models\Company;
use App\Modules\Users\Models\User;
use App\Models\Tenant; // Use App\Models\Tenant
use App\Modules\Tenants\Models\Tenant as ModuleTenant; // Alias for the module tenant
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
     * Normalize tenant to use App\Models\Tenant
     * Ensures we always work with the correct tenant model that has the HasWallet trait
     */
    protected function normalizeTenant($tenant)
    {
        // If it's null, return null
        if (!$tenant) {
            return null;
        }
        
        // If it's already App\Models\Tenant, return it
        if ($tenant instanceof Tenant) {
            return $tenant;
        }
        
        // If it's the module tenant, convert to App\Models\Tenant
        if ($tenant instanceof ModuleTenant) {
            $normalized = Tenant::find($tenant->id);
            if ($normalized) {
                return $normalized;
            }
            // If not found in App\Models\Tenant, try to create it
            Log::warning('ModuleTenant not found in App\Models\Tenant', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name ?? 'Unknown'
            ]);
            return $tenant; // Return as is, might work if the model uses HasWallet
        }
        
        // If it's a User with a tenant relationship
        if ($tenant instanceof User) {
            $tenantRecord = $tenant->tenant;
            if ($tenantRecord instanceof Tenant) {
                return $tenantRecord;
            }
            // Try to find the tenant by user_id
            $normalized = Tenant::where('user_id', $tenant->id)->first();
            if ($normalized) {
                return $normalized;
            }
        }
        
        // If it's an integer (tenant ID), try to find it
        if (is_numeric($tenant)) {
            $normalized = Tenant::find($tenant);
            if ($normalized) {
                return $normalized;
            }
        }
        
        // Return the original - might work if it's already a wallet owner
        return $tenant;
    }

    /**
     * Get wallet balance for a user/tenant with proper normalization
     */
    public function getBalance($walletOwner): float
    {
        $walletOwner = $this->normalizeTenant($walletOwner);
        if (!$walletOwner) {
            return 0.0;
        }
        
        try {
            // Force refresh to get latest balance
            $walletOwner->refresh();
            $balance = (float) $walletOwner->balance;
            
            Log::debug('Wallet balance retrieved', [
                'owner_type' => get_class($walletOwner),
                'owner_id' => $walletOwner->id,
                'balance' => $balance,
            ]);
            
            return $balance;
        } catch (\Exception $e) {
            Log::error('Failed to get wallet balance: ' . $e->getMessage(), [
                'owner_type' => get_class($walletOwner),
                'owner_id' => $walletOwner->id ?? null,
            ]);
            return 0.0;
        }
    }
    
    /**
     * Deposit money into wallet using Bavix
     */
    public function deposit($walletOwner, float $amount, array $meta = []): array
    {
        try {
            $walletOwner = $this->normalizeTenant($walletOwner);
            
            if (!$walletOwner) {
                return [
                    'success' => false,
                    'error' => 'Invalid wallet owner'
                ];
            }
            
            DB::beginTransaction();
            
            // Force refresh to get latest balance
            $walletOwner->refresh();
            
            // Check if transaction with same reference already exists
            if (isset($meta['reference'])) {
                $existingTransaction = \Bavix\Wallet\Models\Transaction::where('type', 'deposit')
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
            
            // Refresh to get new balance
            $walletOwner->refresh();
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
            Log::error('Wallet deposit failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
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
            $walletOwner = $this->normalizeTenant($walletOwner);
            
            if (!$walletOwner) {
                return [
                    'success' => false,
                    'error' => 'Invalid wallet owner'
                ];
            }
            
            DB::beginTransaction();
            
            // Force refresh to get latest balance
            $walletOwner->refresh();
            $balanceBefore = (float) $walletOwner->balance;
            
            $transaction = $walletOwner->withdraw($amount, [
                'description' => $meta['description'] ?? 'Wallet withdrawal',
                'destination' => $meta['destination'] ?? null,
                'meta' => array_merge($meta, ['balance_before' => $balanceBefore]),
            ]);
            
            // Refresh to get new balance
            $walletOwner->refresh();
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
            $balance = $this->getBalance($walletOwner);
            return [
                'success' => false,
                'error' => 'Insufficient funds. Your balance is KES ' . number_format($balance, 2),
                'balance' => $balance,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet withdrawal failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Pay an invoice from wallet
     */
    public function payInvoice($walletOwner, Invoice $invoice, float $amount, array $meta = []): array
    {
        try {
            $walletOwner = $this->normalizeTenant($walletOwner);
            
            if (!$walletOwner) {
                return [
                    'success' => false,
                    'error' => 'Invalid wallet owner'
                ];
            }
            
            DB::beginTransaction();
            
            // Force refresh to get latest balance
            $walletOwner->refresh();
            $balanceBefore = (float) $walletOwner->balance;
            
            // Check if sufficient balance
            if ($balanceBefore < $amount) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => "Insufficient wallet balance. Available: KES " . number_format($balanceBefore, 2) . ", Required: KES " . number_format($amount, 2),
                    'balance' => $balanceBefore,
                ];
            }
            
            // Withdraw from wallet
            $transaction = $walletOwner->withdraw($amount, [
                'description' => $meta['description'] ?? "Payment for invoice #{$invoice->invoice_number}",
                'meta' => array_merge($meta, [
                    'invoice_id' => $invoice->id,
                    'payment_type' => 'invoice_payment',
                    'balance_before' => $balanceBefore,
                ])
            ]);
            
            // Refresh to get new balance
            $walletOwner->refresh();
            $balanceAfter = (float) $walletOwner->balance;
            
            // Update invoice
            $invoice->total_paid = (float) $invoice->payments()->sum('amount') + $amount;
            
            if ($invoice->total_paid >= $invoice->total_amount) {
                $invoice->status = 'paid';
            } elseif ($invoice->total_paid > 0) {
                $invoice->status = 'partial';
            }
            $invoice->save();
            
            // Update invoice items paid amounts
            $this->distributePaymentToInvoiceItems($invoice, $amount);
            
            // Create payment record (ONLY ONE)
            $payment = Payment::create([
                'tenant_id' => $walletOwner->id,
                'user_id' => auth()->id(),
                'invoice_id' => $invoice->id,
                'payment_method' => $meta['payment_method'] ?? 'wallet',
                'source' => Payment::SOURCE_WEB,
                'amount' => $amount,
                'wallet_balance_before' => $balanceBefore,
                'wallet_balance_after' => $balanceAfter,
                'transaction_reference' => $transaction->uuid,
                'external_reference' => $meta['external_reference'] ?? null,
                'status' => Payment::STATUS_COMPLETED,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'reconciled_by' => auth()->id(),
                'meta' => array_merge($meta, [
                    'invoice_id' => $invoice->id,
                    'payment_type' => 'wallet_payment',
                    'bavix_transaction_id' => $transaction->id,
                    'bavix_transaction_uuid' => $transaction->uuid,
                    'created_by' => auth()->id(),
                    'created_by_name' => auth()->user()->name ?? 'System',
                ]),
            ]);
            
            $invoice->refresh();
            
            DB::commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'payment_id' => $payment->id,
                'payment' => $payment,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ];
            
        } catch (InsufficientFunds $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Insufficient wallet balance',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('payInvoice failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
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
        $walletOwner = $this->normalizeTenant($walletOwner);
        $items = $invoice->items()->get();
        $remainingAmount = $amount;
        $allocations = [];
        
        // Get the authenticated user
        $user = auth()->user();
        $userId = $user ? $user->id : null;
        
        foreach ($items as $item) {
            if ($remainingAmount <= 0) break;
            
            $itemRemaining = $item->getRemainingAmountAttribute();
            
            if ($itemRemaining <= 0) continue;
            
            // Allocate amount to this item
            $allocatedAmount = min($remainingAmount, $itemRemaining);
            
            try {
                // Create payment record for this item - use the recordWalletPayment method
                $payment = Payment::recordWalletPayment(
                    tenant: $walletOwner,
                    invoice: $invoice,
                    invoiceItem: $item,
                    amount: $allocatedAmount,
                    balanceBefore: $balanceBefore,
                    balanceAfter: $balanceAfter,
                    transactionReference: $transactionUuid,
                    user: $user,
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
                user: $user,
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
            $from = $this->normalizeTenant($from);
            $to = $this->normalizeTenant($to);
            
            if (!$from || !$to) {
                return [
                    'success' => false,
                    'error' => 'Invalid wallet owner'
                ];
            }
            
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
            
            $from->refresh();
            $to->refresh();
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
        $walletOwner = $this->normalizeTenant($walletOwner);
        
        if (!$walletOwner) {
            return collect();
        }
        
        return $walletOwner->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
    
    /**
     * Get filtered transactions
     */
    public function getFilteredTransactions($walletOwner, array $filters = [], int $perPage = 20)
    {
        $walletOwner = $this->normalizeTenant($walletOwner);
        
        if (!$walletOwner) {
            return collect();
        }
        
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
     * FIXED: Properly normalizes tenant and checks balance correctly
     */
    public function processDirectPayment(
        $tenant,
        Invoice $invoice,
        float $amount,
        string $paymentMethod,
        string $externalReference,
        array $meta = []
    ): array {
        try {
            // FIRST: Normalize the tenant to ensure we're working with the correct model
            $tenant = $this->normalizeTenant($tenant);
            
            if (!$tenant) {
                return [
                    'success' => false,
                    'error' => 'Invalid tenant provided'
                ];
            }
            
            Log::info('Processing direct payment - Tenant normalized', [
                'tenant_id' => $tenant->id,
                'tenant_type' => get_class($tenant),
                'original_type' => get_class($this->normalizeTenant($tenant)),
            ]);
            
            DB::beginTransaction();
            
            // FORCE REFRESH: Get the latest balance from the database
            $tenant->refresh();
            $balanceBefore = (float) $tenant->balance;
            
            Log::info('Direct payment - Current balance', [
                'tenant_id' => $tenant->id,
                'balance_before' => $balanceBefore,
                'amount' => $amount,
                'invoice_remaining' => $invoice->remaining_amount,
            ]);
            
            // Calculate how much goes to invoice and how much to wallet (excess)
            $remainingAmount = $invoice->remaining_amount;
            $amountToInvoice = min($amount, $remainingAmount);
            $amountToWallet = $amount - $amountToInvoice;
            
            // Check if we have enough balance for the full amount
            // We need the full amount because we're depositing first
            if ($balanceBefore < $amount) {
                DB::rollBack();
                Log::warning('Direct payment - Insufficient balance', [
                    'balance' => $balanceBefore,
                    'amount' => $amount,
                ]);
                return [
                    'success' => false,
                    'error' => "Insufficient wallet balance. Available: KES " . number_format($balanceBefore, 2) . ", Required: KES " . number_format($amount, 2),
                    'balance' => $balanceBefore,
                ];
            }
            
            // 1. Deposit the full amount first (this adds to wallet)
            $depositTransaction = $tenant->deposit($amount, [
                'description' => "Payment received for invoice #{$invoice->invoice_number}",
                'meta' => array_merge($meta, [
                    'payment_method' => $paymentMethod,
                    'external_reference' => $externalReference,
                    'invoice_id' => $invoice->id,
                    'payment_type' => 'direct_payment',
                    'amount_to_invoice' => $amountToInvoice,
                    'amount_to_wallet' => $amountToWallet,
                    'balance_before' => $balanceBefore,
                ])
            ]);
            
            Log::info('Direct payment - Deposit completed', [
                'deposit_transaction_id' => $depositTransaction->id,
                'deposit_amount' => $amount,
            ]);
            
            // Refresh balance after deposit
            $tenant->refresh();
            $balanceAfterDeposit = (float) $tenant->balance;
            
            Log::info('Direct payment - Balance after deposit', [
                'balance_after_deposit' => $balanceAfterDeposit,
            ]);
            
            // 2. Pay the invoice from the wallet (withdraw the amount that goes to invoice)
            $walletPayment = null;
            if ($amountToInvoice > 0) {
                // Use the payInvoiceFromWallet method which handles the withdrawal and distribution
                $walletPayment = $this->payInvoiceFromWallet($tenant, $invoice, $amountToInvoice);
                
                if (!$walletPayment['success']) {
                    throw new \Exception('Failed to pay invoice: ' . ($walletPayment['error'] ?? 'Unknown error'));
                }
                
                Log::info('Direct payment - Invoice paid from wallet', [
                    'invoice_id' => $invoice->id,
                    'amount_to_invoice' => $amountToInvoice,
                    'transaction_id' => $walletPayment['transaction_id'] ?? null,
                ]);
            }
            
            // 3. Get the final balance
            $tenant->refresh();
            $balanceAfter = (float) $tenant->balance;
            
            // 4. Create the payment record with ONLY the fields that exist in the table
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'user_id' => auth()->id(),
                'invoice_id' => $invoice->id,
                'invoice_item_id' => null,
                'payment_method' => $paymentMethod,
                'source' => $meta['source'] ?? Payment::SOURCE_ADMIN,
                'amount' => $amount,
                'wallet_balance_before' => $balanceBefore,
                'wallet_balance_after' => $balanceAfter,
                'transaction_reference' => $depositTransaction ? $depositTransaction->uuid : ($walletPayment['transaction_uuid'] ?? $externalReference),
                'external_reference' => $externalReference,
                'status' => Payment::STATUS_COMPLETED,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'reconciled_by' => auth()->id(),
                'meta' => array_merge($meta, [
                    'notes' => $meta['notes'] ?? null,
                    'payment_datetime' => $meta['payment_datetime'] ?? now()->toISOString(),
                    'payment_month' => $meta['payment_month'] ?? now()->format('Y-m'),
                    'source' => 'wallet_payment',
                    'created_by' => auth()->id(),
                    'created_by_name' => auth()->user()->name ?? 'System',
                    'amount_to_invoice' => $amountToInvoice,
                    'amount_to_wallet' => $amountToWallet,
                    'deposit_transaction_id' => $depositTransaction ? $depositTransaction->id : null,
                    'deposit_transaction_uuid' => $depositTransaction ? $depositTransaction->uuid : null,
                    'wallet_transaction_id' => $walletPayment['transaction_id'] ?? null,
                    'wallet_transaction_uuid' => $walletPayment['transaction_uuid'] ?? null,
                    'payment_date' => now()->toISOString(),
                    'invoice_remaining_before' => $remainingAmount,
                    'invoice_remaining_after' => $invoice->refresh()->remaining_amount,
                ]),
            ]);
            
            // Refresh invoice to get latest status
            $invoice->refresh();
            
            DB::commit();
            
            Log::info('Direct payment completed successfully', [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'amount_to_invoice' => $amountToInvoice,
                'amount_to_wallet' => $amountToWallet,
                'final_balance' => $balanceAfter,
                'invoice_status' => $invoice->status,
            ]);
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_id' => $payment->id,
                'wallet_balance' => $balanceAfter,
                'amount_paid_to_invoice' => $amountToInvoice,
                'amount_added_to_wallet' => $amountToWallet,
                'payment' => $payment,
                'invoice' => $invoice,
            ];
            
        } catch (InsufficientFunds $e) {
            DB::rollBack();
            $balance = $this->getBalance($tenant);
            return [
                'success' => false,
                'error' => "Insufficient wallet balance. Available: KES " . number_format($balance, 2),
                'balance' => $balance,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Direct payment failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id ?? null,
                'invoice_id' => $invoice->id ?? null,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pay an invoice from the tenant's wallet balance
     * FIXED: Properly normalizes tenant and checks balance
     */
    protected function payInvoiceFromWallet($tenant, Invoice $invoice, float $amount): array
    {
        try {
            // Normalize tenant
            $tenant = $this->normalizeTenant($tenant);
            
            if (!$tenant) {
                return [
                    'success' => false,
                    'error' => 'Invalid tenant provided'
                ];
            }
            
            // Force refresh to get latest balance
            $tenant->refresh();
            $balanceBefore = (float) $tenant->balance;
            
            Log::debug('payInvoiceFromWallet called', [
                'tenant_id' => $tenant->id,
                'tenant_type' => get_class($tenant),
                'balance_before' => $balanceBefore,
                'amount' => $amount,
                'invoice_id' => $invoice->id,
            ]);
            
            // Check if sufficient balance
            if ($balanceBefore < $amount) {
                return [
                    'success' => false,
                    'error' => "Insufficient wallet balance. Available: KES " . number_format($balanceBefore, 2) . ", Required: KES " . number_format($amount, 2),
                    'balance' => $balanceBefore,
                ];
            }
            
            // Withdraw from wallet (this is the payment)
            $transaction = $tenant->withdraw($amount, [
                'description' => "Payment for invoice #{$invoice->invoice_number}",
                'meta' => [
                    'invoice_id' => $invoice->id,
                    'payment_type' => 'invoice_payment',
                    'paid_by' => auth()->id(),
                    'paid_by_name' => auth()->user()->name ?? 'System',
                    'balance_before' => $balanceBefore,
                ]
            ]);
            
            // Refresh to get new balance
            $tenant->refresh();
            $balanceAfter = (float) $tenant->balance;
            
            Log::debug('Withdrawal completed in payInvoiceFromWallet', [
                'transaction_id' => $transaction->id,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);
            
            // Update invoice payment status using the centralized method
            $invoice->total_paid = (float) $invoice->payments()->sum('amount') + $amount;
            
            if ($invoice->total_paid >= $invoice->total_amount) {
                $invoice->status = 'paid';
            } elseif ($invoice->total_paid > 0) {
                $invoice->status = 'partial';
            }
            $invoice->save();
            
            // Update invoice items paid amounts
            $this->distributePaymentToInvoiceItems($invoice, $amount);
            
            // Refresh tenant balance again
            $tenant->refresh();
            $balanceAfterFinal = (float) $tenant->balance;
            
            // Create payment record for the wallet transaction
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'user_id' => auth()->id(),
                'invoice_id' => $invoice->id,
                'payment_method' => 'wallet',
                'source' => Payment::SOURCE_WEB,
                'amount' => $amount,
                'wallet_balance_before' => $balanceBefore,
                'wallet_balance_after' => $balanceAfterFinal,
                'transaction_reference' => $transaction->uuid,
                'status' => Payment::STATUS_COMPLETED,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'reconciled_by' => auth()->id(),
                'meta' => [
                    'invoice_id' => $invoice->id,
                    'payment_type' => 'wallet_payment',
                    'bavix_transaction_id' => $transaction->id,
                    'paid_by' => auth()->id(),
                    'paid_by_name' => auth()->user()->name ?? 'System',
                    'amount' => $amount,
                ],
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'payment_id' => $payment->id,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfterFinal,
            ];
            
        } catch (InsufficientFunds $e) {
            Log::error('payInvoiceFromWallet - InsufficientFunds: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Insufficient wallet balance. Available: KES ' . number_format($this->getBalance($tenant), 2),
            ];
        } catch (\Exception $e) {
            Log::error('payInvoiceFromWallet failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id ?? null,
                'invoice_id' => $invoice->id ?? null,
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Distribute payment to invoice items
     * FIXED: Use actual database columns instead of accessor
     */
    protected function distributePaymentToInvoiceItems(Invoice $invoice, float $amount): void
    {
        // Use the actual columns to filter items with remaining balance
        // Instead of where('remaining_amount', '>', 0) which doesn't exist as a column
        $items = $invoice->items()
            ->whereRaw('amount > COALESCE(paid_amount, 0)')
            ->get();
        
        if ($items->isEmpty()) {
            return;
        }
        
        $remainingToDistribute = $amount;
        
        foreach ($items as $item) {
            if ($remainingToDistribute <= 0) {
                break;
            }
            
            // Calculate remaining using the accessor (works on the model instance)
            $itemRemaining = $item->remaining_amount;
            $amountForItem = min($remainingToDistribute, $itemRemaining);
            
            if ($amountForItem > 0) {
                $item->paid_amount = ($item->paid_amount ?? 0) + $amountForItem;
                $item->save();
                
                $remainingToDistribute -= $amountForItem;
            }
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

    /**
     * Check if a tenant has a wallet
     */
    public function hasWallet($walletOwner): bool
    {
        $walletOwner = $this->normalizeTenant($walletOwner);
        
        if (!$walletOwner) {
            return false;
        }
        
        try {
            $wallet = $walletOwner->wallet;
            return $wallet !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get wallet details
     */
    public function getWalletDetails($walletOwner): ?array
    {
        $walletOwner = $this->normalizeTenant($walletOwner);
        
        if (!$walletOwner) {
            return null;
        }
        
        try {
            $wallet = $walletOwner->wallet;
            
            if (!$wallet) {
                return null;
            }
            
            return [
                'id' => $wallet->id,
                'uuid' => $wallet->uuid,
                'name' => $wallet->name,
                'slug' => $wallet->slug,
                'balance' => (float) $wallet->balance,
                'formatted_balance' => 'KES ' . number_format($wallet->balance, 2),
                'created_at' => $wallet->created_at,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get wallet details: ' . $e->getMessage());
            return null;
        }
    }
}