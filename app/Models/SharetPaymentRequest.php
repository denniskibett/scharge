<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A Sharet pay link for (part of) a tenant invoice. See database/schema/2026_11_08_sharet_payments.sql. */
class SharetPaymentRequest extends Model
{
    protected $table = 'sharet_payment_requests';

    protected $fillable = ['sharet_id', 'company_id', 'invoice_id', 'tenant_id', 'environment', 'mode', 'sharet_account', 'amount',
        'status', 'pay_url', 'reference', 'paid_via', 'paid_at', 'expires_at', 'settled_payment_id', 'sweep_id', 'raw'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'expires_at' => 'datetime', 'raw' => 'array'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
