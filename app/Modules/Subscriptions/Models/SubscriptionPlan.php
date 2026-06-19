<?php
// app/Modules/Subscriptions/Models/SubscriptionPlan.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Subscriptions\Models\CompanySubscription;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name', 'slug', 'description', 'price_per_unit', 
        'trial_days', 'discount_percentage', 'is_active', 
        'features'
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price_per_unit' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'trial_days' => 'integer',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the subscriptions for this plan
     */
    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class, 'plan_id');
    }

    /**
     * Get active subscriptions count
     */
    public function activeSubscriptions()
    {
        return $this->hasMany(CompanySubscription::class, 'plan_id')
            ->whereIn('status', ['trial', 'active']);
    }

    // =============================================
    // PRICE CALCULATION METHODS
    // =============================================

    /**
     * Calculate monthly price based on unit count
     */
    public function calculateMonthlyPrice($unitCount)
    {
        return $this->price_per_unit * max($unitCount, 1);
    }

    /**
     * Calculate yearly price with discount
     */
    public function calculateYearlyPrice($unitCount)
    {
        $monthlyPrice = $this->calculateMonthlyPrice($unitCount);
        $yearlyDiscount = 1 - ($this->discount_percentage / 100);
        return ($monthlyPrice * 12) * $yearlyDiscount;
    }

    /**
     * Get price breakdown for display
     */
    public function getPriceBreakdown($unitCount)
    {
        $monthly = $this->calculateMonthlyPrice($unitCount);
        $yearly = $this->calculateYearlyPrice($unitCount);
        $savings = ($monthly * 12) - $yearly;

        return [
            'monthly' => $monthly,
            'yearly' => $yearly,
            'savings' => $savings,
            'per_unit' => $this->price_per_unit,
            'unit_count' => $unitCount,
            'discount_percentage' => $this->discount_percentage
        ];
    }

    // =============================================
    // DISPLAY ATTRIBUTES
    // =============================================

    /**
     * Get formatted price per unit
     */
    public function getFormattedPricePerUnitAttribute()
    {
        return 'KES ' . number_format($this->price_per_unit, 0) . '/unit';
    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCountAttribute()
    {
        return $this->activeSubscriptions()->count();
    }

    /**
     * Get product capabilities from features
     */
    public function getProductCapabilitiesAttribute()
    {
        $features = $this->features ?? [];
        return $features['product_capabilities'] ?? [
            'max_units' => 0,
            'max_users' => 0,
            'max_tenants' => 0,
            'storage_gb' => 0,
            'max_properties' => 0
        ];
    }

    /**
     * Get business features from features
     */
    public function getBusinessFeaturesAttribute()
    {
        $features = $this->features ?? [];
        return $features['business_features'] ?? [];
    }

    /**
     * Get price tier label
     */
    public function getPriceTierLabelAttribute()
    {
        if ($this->price_per_unit <= 500) {
            return 'Starter';
        } elseif ($this->price_per_unit <= 1000) {
            return 'Growth';
        } elseif ($this->price_per_unit <= 2000) {
            return 'Professional';
        } else {
            return 'Enterprise';
        }
    }

    /**
     * Get color badge for price tier
     */
    public function getPriceTierColorAttribute()
    {
        if ($this->price_per_unit <= 500) {
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        } elseif ($this->price_per_unit <= 1000) {
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        } elseif ($this->price_per_unit <= 2000) {
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400';
        } else {
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
        }
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    /**
     * Scope for price range
     */
    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price_per_unit', [$min, $max]);
    }
}