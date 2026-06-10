<?php
// app/Modules/Payments/Models/Transaction.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Users\Models\User;
use App\Modules\Tenants\Models\Tenant;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'wallet_id',
        'type',
        'amount',
        'description',
        'reference',
        'status',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at',
    ];

    // Transaction types
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAW = 'withdraw';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_INVOICE_PAYMENT = 'invoice_payment';

    // Transaction statuses
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant that owns the transaction.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Check if transaction is a deposit.
     */
    public function isDeposit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    /**
     * Check if transaction is a withdrawal.
     */
    public function isWithdraw(): bool
    {
        return $this->type === self::TYPE_WITHDRAW;
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get formatted amount with sign.
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->isDeposit() ? '+' : '-';
        return $sign . ' KES ' . number_format($this->amount, 2);
    }

    /**
     * Get amount with color class.
     */
    public function getAmountColorAttribute(): string
    {
        return $this->isDeposit() ? 'text-success-600' : 'text-error-600';
    }

    /**
     * Scope for deposits only.
     */
    public function scopeDeposits($query)
    {
        return $query->where('type', self::TYPE_DEPOSIT);
    }

    /**
     * Scope for withdrawals only.
     */
    public function scopeWithdrawals($query)
    {
        return $query->where('type', self::TYPE_WITHDRAW);
    }

    /**
     * Scope for completed transactions only.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}