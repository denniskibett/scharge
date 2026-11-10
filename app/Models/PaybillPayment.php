<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money a tenant paid straight to a paybill (Safaricom C2B), and what scharge did with it. App\Services\Rent\PaybillPayments. */
class PaybillPayment extends Model
{
    protected $fillable = ['trans_id', 'company_id', 'shortcode', 'bill_ref', 'amount', 'msisdn', 'payer_name', 'paid_at', 'status',
        'tenancy_id', 'matched_by', 'payment_ids', 'note', 'handled_by', 'raw'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'payment_ids' => 'array', 'raw' => 'array'];

    public const STATUSES = ['applied' => 'Applied to bills', 'credited' => 'Kept as credit', 'unmatched' => 'Needs a tenant', 'ignored' => 'Not rent'];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }
}
