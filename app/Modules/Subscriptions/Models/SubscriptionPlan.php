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
        'name', 'slug', 'description', 'region_id', 'subcounty_id',
        'price_per_unit', 'min_units', 'max_units',
        'trial_days', 'discount_percentage', 'features',
        'is_active', 'display_order'
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price_per_unit' => 'decimal:2',
        'min_units' => 'integer',
        'max_units' => 'integer',
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
     * Get the subcounty that owns this plan
     */
    public function subcounty()
    {
        return $this->belongsTo(Subcounty::class, 'subcounty_id');
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
        return $this->price_per_unit * max($unitCount, $this->min_units ?? 1);
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
    // VALIDATION METHODS
    // =============================================

    /**
     * Check if unit count is within plan limits
     */
    public function isWithinUnitLimit($unitCount)
    {
        if ($this->max_units > 0 && $unitCount > $this->max_units) {
            return false;
        }
        if ($unitCount < $this->min_units) {
            return false;
        }
        return true;
    }

    /**
     * Get the minimum units for this plan
     */
    public function getMinUnitsForDisplay()
    {
        return $this->min_units ?: 1;
    }

    /**
     * Get the maximum units for this plan
     */
    public function getMaxUnitsForDisplay()
    {
        return $this->max_units ?: 'Unlimited';
    }

    // =============================================
    // DISPLAY ATTRIBUTES
    // =============================================

    /**
     * Get unit range for display
     */
    public function getUnitRangeAttribute()
    {
        if ($this->max_units === 0) {
            return $this->min_units . '+ units';
        }
        return $this->min_units . ' - ' . number_format($this->max_units) . ' units';
    }

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
     * Get features list
     */
    public function getFeaturesListAttribute()
    {
        return $this->features ?? [];
    }

    /**
     * Get features as HTML bullet list
     */
    public function getFeaturesHtmlAttribute()
    {
        if (empty($this->features)) {
            return '<p class="text-sm text-gray-400">No features listed</p>';
        }
        
        $html = '<ul class="space-y-1.5">';
        foreach ($this->features as $feature) {
            $html .= '<li class="flex items-start gap-2.5">';
            $html .= '<svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
            $html .= '</svg>';
            $html .= '<span class="text-sm text-gray-600 dark:text-gray-300">' . htmlspecialchars($feature) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        
        return $html;
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
     * Get subcounty name attribute (shortcut)
     */
    public function getSubcountyNameAttribute()
    {
        return $this->subcounty?->name;
    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCountAttribute()
    {
        return $this->activeSubscriptions()->count();
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
     * Scope by subcounty
     */
    public function scopeForSubcounty($query, $subcountyId)
    {
        return $query->where('subcounty_id', $subcountyId);
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by region then display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('region_id')->orderBy('display_order');
    }

    /**
     * Scope for plans that support a given unit count
     */
    public function scopeSupportsUnitCount($query, $unitCount)
    {
        return $query->where(function($q) use ($unitCount) {
            $q->where('min_units', '<=', $unitCount)
              ->where(function($sub) use ($unitCount) {
                  $sub->where('max_units', '>=', $unitCount)
                      ->orWhere('max_units', 0);
              });
        });
    }

    /**
     * Scope with region and subcounty relations eager loaded
     */
    public function scopeWithRelations($query)
    {
        return $query->with(['region', 'subcounty', 'activeSubscriptions']);
    }
}