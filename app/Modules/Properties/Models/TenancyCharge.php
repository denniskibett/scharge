<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Properties\Models\Tenancy;
use App\Modules\Payments\Models\Invoice;


class TenancyCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenancy_id',
        'invoice_id',
        'charge_type',
        'description',
        'amount',
        'is_refundable',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_refundable' => 'boolean',
    ];

    const CHARGE_TYPES = [
        'deposit' => 'Security Deposit',
        'rent' => 'Rent',
        'utility_deposit' => 'Utility Deposit',
        'key_deposit' => 'Key Deposit',
        'other' => 'Other',
    ];

    const STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
        'waived' => 'Waived',
    ];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}