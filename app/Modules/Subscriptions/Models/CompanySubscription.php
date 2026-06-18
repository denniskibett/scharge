<?php
// app/Modules/Subscriptions/Models/CompanySubscription.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanySubscription extends Model
{
    use HasFactory;
    
    protected $table = 'company_subscriptions';
    
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'auto_renew' => 'boolean',
        'unit_count' => 'integer',
        'calculated_price' => 'decimal:2'
    ];
    
    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'billing_cycle',
        'unit_count',
        'calculated_price',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'auto_renew',
        'payment_method_id'
    ];
    
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
    
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
    
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'company_subscription_id');
    }
}