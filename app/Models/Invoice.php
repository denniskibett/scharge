<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'tenancy_id',
        'invoice_type',
        'billing_month',
        'total_amount',
        'total_paid',
        'status',
        'company_id',
        'estate_id'
    ];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }
}