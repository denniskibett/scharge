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

    // Simple relationship - use the model directly
    public function subscriptions()
    {
        return $this->hasMany(\App\Modules\Subscriptions\Models\CompanySubscription::class, 'plan_id');
    }

    public function getPricingTypeAttribute()
    {
        $features = $this->features_json ?? [];
        return $features['pricing_type'] ?? 'fixed';
    }

    public function getPricePerUnitAttribute()
    {
        $features = $this->features_json ?? [];
        return $features['price_per_unit'] ?? 0;
    }

    public function getFeaturesListAttribute()
    {
        $features = $this->features_json ?? [];
        return $features['features_list'] ?? [];
    }

    public function calculateMonthlyPrice($unitCount)
    {
        $features = $this->features_json ?? [];
        
        if (isset($features['pricing_type']) && $features['pricing_type'] === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            return $pricePerUnit * $unitCount;
        }
        
        return (float) $this->price_monthly;
    }

    public function calculateYearlyPrice($unitCount)
    {
        $features = $this->features_json ?? [];
        
        if (isset($features['pricing_type']) && $features['pricing_type'] === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            return ($pricePerUnit * $unitCount * 12) * 0.9;
        }
        
        return (float) $this->price_yearly;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('price_monthly');
    }

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