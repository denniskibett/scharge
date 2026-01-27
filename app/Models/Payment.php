<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenancy_id',      // Changed from user_id to tenancy_id
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_id',
        'transaction_message',
        'paid_to',
        'payer_name',
        'payment_datetime',
        'payment_month',
    ];

    protected $casts = [
        'payment_datetime' => 'datetime',
        'amount' => 'decimal:2',
    ];


    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}