<?php
// app/Modules/Subscriptions/Models/SubscriptionPlan.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name', 'slug', 'description', 'price_monthly', 'price_yearly',
        'trial_days', 'features_json', 'is_active', 'display_order'
    ];

    protected $casts = [
        'features_json' => 'array',
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2'
    ];

    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class, 'plan_id');
    }

    /**
     * Calculate monthly price based on number of active units
     */
    public function calculateMonthlyPrice($unitCount)
    {
        $features = $this->features_json;
        
        // Check if using per-unit pricing
        if (isset($features['pricing_type']) && $features['pricing_type'] === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            return $pricePerUnit * $unitCount;
        }
        
        // Fixed pricing
        return $this->price_monthly;
    }

    /**
     * Calculate yearly price based on number of active units
     */
    public function calculateYearlyPrice($unitCount)
    {
        $features = $this->features_json;
        
        // Check if using per-unit pricing
        if (isset($features['pricing_type']) && $features['pricing_type'] === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            // 10% discount for yearly billing
            $yearlyDiscount = 0.90;
            return ($pricePerUnit * $unitCount * 12) * $yearlyDiscount;
        }
        
        // Fixed pricing
        return $this->price_yearly;
    }

    public function getPriceForCycle($cycle = 'monthly', $unitCount = null)
    {
        if ($cycle === 'monthly') {
            return $unitCount ? $this->calculateMonthlyPrice($unitCount) : $this->price_monthly;
        }
        return $unitCount ? $this->calculateYearlyPrice($unitCount) : $this->price_yearly;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('price_monthly');
    }
}