<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A company paying a scharge bill (subscription_invoices). See App\Services\Sharet\BillPayments, database/schema/2026_11_20_billing_payments.sql. */
class BillingPayment extends Model
{
    protected $table = 'billing_payments';

    protected $fillable = ['company_id', 'subscription_invoice_id', 'method', 'amount', 'currency', 'status', 'environment', 'sharet_id',
        'sharet_transaction', 'pay_url', 'paid_via', 'sweep_id', 'user_id', 'paid_at', 'expires_at', 'raw'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'expires_at' => 'datetime', 'raw' => 'array'];

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
