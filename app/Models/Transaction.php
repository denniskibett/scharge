<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'tenancy_id',
        'raw_message',
        'parsed_amount',
        'parsed_reference_number',
        'parsed_payment_method',
        'parsed_payment_datetime',
        'parsed_payer_name',
        'parsed_paid_to',
        'parsed_payment_month',
        'remaining_amount',
        'status',
        'verification_notes',
        'verified_by',
        'verified_at'
    ];
    
    protected $casts = [
        'parsed_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'parsed_payment_datetime' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}