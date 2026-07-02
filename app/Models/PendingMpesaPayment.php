<?php
// app/Models/PendingMpesaPayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingMpesaPayment extends Model
{
    protected $table = 'pending_mpesa_payments';
    
    protected $fillable = [
        'checkout_request_id',
        'invoice_id',
        'tenant_id',
        'amount',
        'phone',
        'status'
    ];
}