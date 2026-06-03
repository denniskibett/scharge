<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'balance'];

    protected $casts = ['balance' => 'decimal:2'];

    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Tenants\Models\Tenant::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function deposit(float $amount, ?string $description = null, ?Invoice $invoice = null): WalletTransaction
    {
        $this->balance += $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => 'deposit',
            'amount' => $amount,
            'description' => $description,
            'invoice_id' => $invoice?->id,
        ]);
    }

    public function deduct(float $amount, ?string $description = null, ?Invoice $invoice = null): WalletTransaction
    {
        if ($this->balance < $amount) {
            throw new \Exception("Insufficient balance. Available: {$this->balance}, Required: {$amount}");
        }

        $this->balance -= $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => 'deduction',
            'amount' => $amount,
            'description' => $description,
            'invoice_id' => $invoice?->id,
        ]);
    }
}