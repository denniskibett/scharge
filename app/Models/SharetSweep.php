<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One rail-mode payout to a landlord company at a sweep slot (08:00 / 12:00 / 16:00 Nairobi). */
class SharetSweep extends Model
{
    protected $table = 'sharet_sweeps';

    protected $fillable = ['company_id', 'slot', 'requests_count', 'gross', 'fee', 'net', 'destination', 'status', 'sharet_payment_id', 'failure'];

    protected $casts = ['gross' => 'decimal:2', 'fee' => 'decimal:2', 'net' => 'decimal:2', 'destination' => 'array'];

    public function requests()
    {
        return $this->hasMany(SharetPaymentRequest::class, 'sweep_id');
    }
}
