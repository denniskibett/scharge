<?php
// app/Modules/Payments/Models/Payment.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Users\Models\User;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'invoice_id',
        'invoice_item_id',
        'payment_method',
        'source',
        'amount',
        'wallet_balance_before',
        'wallet_balance_after',
        'transaction_reference',
        'external_reference',
        'status',
        'is_reconciled',
        'reconciled_at',
        'reconciled_by',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'wallet_balance_before' => 'decimal:2',
        'wallet_balance_after' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Payment methods
    const METHOD_WALLET = 'wallet';
    const METHOD_MPESA_STK = 'mpesa_stk';
    const METHOD_MPESA_PAYBILL = 'mpesa_paybill';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CASH = 'cash';
    const METHOD_MANUAL_TOPUP = 'manual_topup';
    const METHOD_MESSAGE_PASTE = 'message_paste';

    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Sources
    const SOURCE_WEB = 'web';
    const SOURCE_API = 'api';
    const SOURCE_MOBILE = 'mobile';
    const SOURCE_WEBHOOK = 'webhook';
    const SOURCE_CRON = 'cron';
    const SOURCE_ADMIN = 'admin';

    /**
     * Relationships
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    // REMOVE THIS - tenancy relationship doesn't exist directly
    // public function tenancy()
    // {
    //     return $this->hasOneThrough(Tenancy::class, Invoice::class, 'id', 'id', 'invoice_id', 'tenancy_id');
    // }

    // Instead, use this to access tenancy through invoice
    public function getTenancyAttribute()
    {
        return $this->invoice?->tenancy;
    }

    /**
     * Scopes
     */
    public function scopeWalletPayments($query)
    {
        return $query->where('payment_method', self::METHOD_WALLET);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    /**
     * Accessors
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->amount, 2);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        $labels = [
            self::METHOD_WALLET => 'Wallet Balance',
            self::METHOD_MPESA_STK => 'M-Pesa STK Push',
            self::METHOD_MPESA_PAYBILL => 'M-Pesa Paybill',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CASH => 'Cash',
            self::METHOD_MANUAL_TOPUP => 'Manual Top-up',
            self::METHOD_MESSAGE_PASTE => 'Transaction Message',
        ];
        
        return $labels[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            self::STATUS_PENDING => ['label' => 'Pending', 'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
            self::STATUS_COMPLETED => ['label' => 'Completed', 'class' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'],
            self::STATUS_FAILED => ['label' => 'Failed', 'class' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'],
            self::STATUS_CANCELLED => ['label' => 'Cancelled', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'],
            self::STATUS_REFUNDED => ['label' => 'Refunded', 'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'],
        ];
        
        return $badges[$this->status] ?? ['label' => ucfirst($this->status), 'class' => 'bg-gray-100 text-gray-800'];
    }

    public function getWalletBalanceChangeAttribute(): ?float
    {
        if ($this->wallet_balance_before !== null && $this->wallet_balance_after !== null) {
            return $this->wallet_balance_after - $this->wallet_balance_before;
        }
        return null;
    }

    public function getPayerNameAttribute(): string
    {
        if ($this->tenant && $this->tenant->user) {
            return $this->tenant->user->name;
        }
        if ($this->user) {
            return $this->user->name;
        }
        return 'N/A';
    }

    public function getPaidToAttribute(): string
    {
        // Get the company name from the invoice's tenancy's unit's estate's company
        $company = $this->invoice?->tenancy?->unit?->estate?->company;
        return $company?->name ?? 'Property Management';
    }

    public function getInvoiceLabelAttribute(): string
    {
        if (!$this->invoice) return '-';
        $invoiceNumber = $this->invoice->invoice_number ?? 'INV-' . $this->invoice->id;
        $billingMonth = $this->invoice->billing_month ? Carbon::parse($this->invoice->billing_month)->format('M Y') : '';
        return trim($invoiceNumber . ($billingMonth ? ' (' . $billingMonth . ')' : ''));
    }

    /**
     * Helpers
     */
    public function markAsReconciled(User $reconciledBy): self
    {
        $this->is_reconciled = true;
        $this->reconciled_at = now();
        $this->reconciled_by = $reconciledBy->id;
        $this->save();
        
        return $this;
    }

    public function isReconciled(): bool
    {
        return $this->is_reconciled === true;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Factory methods
     */
    public static function recordWalletPayment(
        Tenant $tenant,
        Invoice $invoice,
        ?InvoiceItem $invoiceItem,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        string $transactionReference,
        ?User $user = null,
        array $meta = []
    ): self {
        return self::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id ?? $tenant->user_id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoiceItem?->id,
            'payment_method' => self::METHOD_WALLET,
            'source' => $meta['source'] ?? self::SOURCE_WEB,
            'amount' => $amount,
            'wallet_balance_before' => $balanceBefore,
            'wallet_balance_after' => $balanceAfter,
            'transaction_reference' => $transactionReference,
            'status' => self::STATUS_COMPLETED,
            'is_reconciled' => false,
            'meta' => $meta,
        ]);
    }

    public static function recordExternalPayment(
        Tenant $tenant,
        Invoice $invoice,
        string $paymentMethod,
        float $amount,
        string $externalReference,
        ?User $user = null,
        array $meta = []
    ): self {
        return self::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id ?? $tenant->user_id,
            'invoice_id' => $invoice->id,
            'payment_method' => $paymentMethod,
            'source' => $meta['source'] ?? self::SOURCE_WEB,
            'amount' => $amount,
            'external_reference' => $externalReference,
            'status' => self::STATUS_PENDING,
            'is_reconciled' => false,
            'meta' => $meta,
        ]);
    }


    /**
     * Record a direct payment that automatically deposits to wallet and pays invoice
     */
    public static function recordDirectPayment(
        Tenant $tenant,
        Invoice $invoice,
        float $amount,
        string $paymentMethod,
        string $externalReference,
        ?User $user = null,
        array $meta = []
    ): self {
        return self::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id ?? auth()->id(),
            'invoice_id' => $invoice->id,
            'invoice_item_id' => null, // Will be updated during distribution
            'payment_method' => $paymentMethod,
            'source' => $meta['source'] ?? self::SOURCE_ADMIN,
            'amount' => $amount,
            'external_reference' => $externalReference,
            'transaction_reference' => $meta['transaction_reference'] ?? null,
            'status' => self::STATUS_COMPLETED, // Directly completed
            'is_reconciled' => true, // Auto-reconciled by accountant
            'reconciled_at' => now(),
            'reconciled_by' => $user?->id ?? auth()->id(),
            'meta' => array_merge($meta, [
                'direct_payment' => true,
                'auto_processed' => true,
                'processed_at' => now()->toISOString(),
            ]),
        ]);
    }
}