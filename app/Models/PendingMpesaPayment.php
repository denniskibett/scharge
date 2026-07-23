<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingMpesaPayment extends Model
{
    protected $table = 'pending_mpesa_payments';

    protected $fillable = [
        'checkout_request_id',
        'merchant_request_id',
        'invoice_id',
        'tenant_id',
        'phone_number',
        'amount',
        'status',
        'mpesa_receipt',
        'error_message',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(\App\Modules\Payments\Models\Invoice::class, 'invoice_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }
}