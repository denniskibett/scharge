<?php
// app/Modules/Subscriptions/Models/SubscriptionInvoice.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionInvoice extends Model
{
    use HasFactory;
    
    protected $table = 'subscription_invoices';
    
    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'invoice_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    protected $fillable = [
        'company_subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_id',
        'due_date',
        'paid_at',
        'invoice_json'
    ];
    
    public function subscription()
    {
        return $this->belongsTo(CompanySubscription::class, 'company_subscription_id');
    }
}