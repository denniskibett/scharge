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
    
    public function isPaid()
    {
        return $this->status === 'paid';
    }
    
    public function isPending()
    {
        return $this->status === 'pending';
    }
    
    public function isFailed()
    {
        return $this->status === 'failed';
    }
    
    public function isRefunded()
    {
        return $this->status === 'refunded';
    }
    
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }
    
    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'refunded' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
        ];
        
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}