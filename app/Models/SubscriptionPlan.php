<?php

namespace App\Models;

class SubscriptionPlan extends Model
{
    protected $casts = [
        'features_json' => 'array',
        'is_active' => 'boolean'
    ];
    
    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }
    
    // Get price for billing cycle
    public function getPrice($cycle = 'monthly')
    {
        return $cycle === 'monthly' ? $this->price_monthly : $this->price_yearly;
    }
}