<?php
// app/Modules/Payments/Models/Wallet.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Users\Models\User;
use App\Modules\Tenants\Models\Tenant;

class Wallet extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wallets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'holder_type',
        'holder_id',
        'name',
        'slug',
        'uuid',
        'description',
        'meta',
        'balance',
        'decimal_places',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'balance' => 'decimal:2',
        'decimal_places' => 'integer',
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

    /**
     * Get the parent holder model (User or Tenant).
     */
    public function holder()
    {
        return $this->morphTo();
    }

    /**
     * Get the user that owns the wallet (if holder is User).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'holder_id')
            ->where('holder_type', User::class);
    }

    /**
     * Get the tenant that owns the wallet (if holder is Tenant).
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'holder_id')
            ->where('holder_type', Tenant::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'wallet_id');
    }

    /**
     * Get all deposits for this wallet.
     */
    public function deposits()
    {
        return $this->transactions()->where('type', 'deposit');
    }

    /**
     * Get all withdrawals for this wallet.
     */
    public function withdrawals()
    {
        return $this->transactions()->where('type', 'withdraw');
    }

    /**
     * Get formatted balance.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2);
    }

    /**
     * Get balance with currency symbol.
     */
    public function getBalanceWithCurrencyAttribute(): string
    {
        return 'KES ' . number_format($this->balance, 2);
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Add balance (credit).
     */
    public function addBalance(float $amount): self
    {
        $this->balance += $amount;
        $this->save();
        
        return $this;
    }

    /**
     * Subtract balance (debit).
     */
    public function subtractBalance(float $amount): self
    {
        if ($this->hasSufficientBalance($amount)) {
            $this->balance -= $amount;
            $this->save();
        }
        
        return $this;
    }

    /**
     * Scope to get wallets with positive balance.
     */
    public function scopePositiveBalance($query)
    {
        return $query->where('balance', '>', 0);
    }

    /**
     * Scope to get wallets with zero balance.
     */
    public function scopeZeroBalance($query)
    {
        return $query->where('balance', 0);
    }

    /**
     * Scope to get wallets by holder type.
     */
    public function scopeHolderType($query, string $type)
    {
        return $query->where('holder_type', $type);
    }
}