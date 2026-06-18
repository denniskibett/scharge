<?php
// app/Modules/Subscriptions/Models/SubscriptionPlan.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Subcounty;
use App\Modules\Subscriptions\Models\Region;
use App\Modules\Subscriptions\Models\CompanySubscription;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name', 'slug', 'description', 'region_id', 'subcounty',
        'price_per_unit', 'trial_days', 'discount_percentage',
        'is_active', 'features'
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price_per_unit' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'trial_days' => 'integer'
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the region that owns this plan
     */
    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the subcounty (ward) by constituency name
     * Note: This returns the first matching subcounty
     */
    public function subcountyRelation()
    {
        return $this->belongsTo(Subcounty::class, 'subcounty', 'constituency_name');
    }

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
     * Get display name with region
     */
    public function getDisplayNameAttribute()
    {
        if ($this->region) {
            return $this->region->name . ' - ' . $this->name;
        }
        return $this->name;
    }

    /**
     * Get full display name with region and pricing
     */
    public function getFullDisplayAttribute()
    {
        if ($this->region) {
            return $this->region->name . ' - ' . $this->name . ' (KES ' . number_format($this->price_per_unit, 0) . '/unit)';
        }
        return $this->name . ' (KES ' . number_format($this->price_per_unit, 0) . '/unit)';
    }

    /**
     * Get formatted price per unit
     */
    public function getFormattedPricePerUnitAttribute()
    {
        return 'KES ' . number_format($this->price_per_unit, 0) . '/unit';
    }

    /**
     * Get region name attribute (shortcut)
     */
    public function getRegionNameAttribute()
    {
        return $this->region?->name;
    }

    /**
     * Get county name attribute (shortcut)
     */
    public function getCountyNameAttribute()
    {
        return $this->region?->county?->county_name;
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
     * Get wards from features
     */
    public function getWardsAttribute()
    {
        $features = $this->features ?? [];
        return $features['wards'] ?? [];
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope by region
     */
    public function scopeForRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    /**
     * Scope by constituency
     */
    public function scopeForConstituency($query, $constituencyName)
    {
        return $query->where('subcounty', $constituencyName);
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by region then id
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('region_id')->orderBy('id', 'desc');
    }
}