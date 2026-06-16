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

    /**
     * Get the subscriptions for this plan
     * Use \App\Models\CompanySubscription directly since it extends this model
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\CompanySubscription::class, 'plan_id');
    }

    /**
     * Get pricing type (fixed or per_unit)
     */
    public function getPricingTypeAttribute()
    {
        $features = $this->features_json ?? [];
        return $features['pricing_type'] ?? 'fixed';
    }

    /**
     * Get price per unit from features
     */
    public function getPricePerUnitAttribute()
    {
        $features = $this->features_json ?? [];
        return $features['price_per_unit'] ?? 0;
    }

    /**
     * Get the features list
     */
    public function getFeaturesListAttribute()
    {
        $features = $this->features_json ?? [];
        return $features['features_list'] ?? [];
    }

    /**
     * Get the display price for the plan
     */
    public function getDisplayPriceAttribute()
    {
        if ($this->pricing_type === 'per_unit') {
            return 'KES ' . number_format($this->price_per_unit, 0) . ' / unit / month';
        }
        return 'KES ' . number_format($this->price_monthly, 0) . ' / month';
    }

    /**
     * Calculate monthly price based on number of active units
     */
    public function calculateMonthlyPrice($unitCount)
    {
        $features = $this->features_json ?? [];
        
        if (isset($features['pricing_type']) && $features['pricing_type'] === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            return $pricePerUnit * $unitCount;
        }
        
        return (float) $this->price_monthly;
    }

    /**
     * Calculate yearly price based on number of active units
     */
    public function calculateYearlyPrice($unitCount)
    {
        $features = $this->features_json ?? [];
        
        if (isset($features['pricing_type']) && $features['pricing_type'] === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            $yearlyDiscount = 0.90;
            return ($pricePerUnit * $unitCount * 12) * $yearlyDiscount;
        }
        
        return (float) $this->price_yearly;
    }

    /**
     * Get price for a specific cycle with optional unit count
     */
    public function getPriceForCycle($cycle = 'monthly', $unitCount = null)
    {
        if ($cycle === 'monthly') {
            return $unitCount ? $this->calculateMonthlyPrice($unitCount) : (float) $this->price_monthly;
        }
        return $unitCount ? $this->calculateYearlyPrice($unitCount) : (float) $this->price_yearly;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('price_monthly');
    }

    /**
     * Get min and max unit count for display
     */
    public function getUnitRangeAttribute()
    {
        $features = $this->features_json ?? [];
        $maxUnits = $features['max_units'] ?? 0;
        
        if ($maxUnits === 0) {
            return 'Unlimited';
        }
        
        $previousPlan = self::where('display_order', '<', $this->display_order)
            ->orderBy('display_order', 'desc')
            ->first();
        
        $minUnits = $previousPlan ? ($previousPlan->features_json['max_units'] ?? 0) + 1 : 1;
        
        return $minUnits . ' - ' . number_format($maxUnits);
    }
}