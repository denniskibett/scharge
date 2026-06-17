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

    // =============================================
    // PRICE CALCULATION METHODS
    // =============================================

    /**
     * Calculate monthly price based on unit count
     */
    public function calculateMonthlyPrice($unitCount)
    {
        return $this->price_per_unit * max($unitCount, $this->min_units);
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
     * Scope for plans with min units less than or equal to given count
     */
    public function scopeMinUnitsLessThanOrEqual($query, $unitCount)
    {
        return $query->where('min_units', '<=', $unitCount);
    }

    /**
     * Scope for plans with max units greater than or equal to given count or unlimited
     */
    public function scopeMaxUnitsGreaterThanOrEqual($query, $unitCount)
    {
        return $query->where(function($q) use ($unitCount) {
            $q->where('max_units', '>=', $unitCount)
              ->orWhere('max_units', 0);
        });
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Get all available features as a mapped list with descriptions
     */
    public static function getAvailableFeatures()
    {
        return [
            'Basic reporting' => 'Generate and export basic reports',
            'Advanced reporting & analytics' => 'Advanced analytics with custom reports',
            'Email support' => 'Standard email support during business hours',
            'Priority email & phone support' => 'Priority support with phone access',
            '24/7 priority support' => 'Round-the-clock priority support',
            'Mobile app access' => 'Full mobile application access',
            'Tenant portal' => 'Self-service tenant web portal',
            'Maintenance management' => 'Track and manage maintenance requests',
            'Water billing integration' => 'Integration with water billing system',
            'API access' => 'REST API for custom integrations',
            'SMS notifications' => 'Automated SMS alerts and notifications',
            'Dedicated account manager' => 'Personal account manager assigned',
            'Custom branding' => 'White-label with your company branding',
            'Custom reporting' => 'Build custom reports with data filters',
            'Income management' => 'Track and manage all income streams',
            'Expense management' => 'Track and categorize all expenses',
            'Gate management' => 'Gate access control and visitor management',
            'Property management' => 'Comprehensive property management tools',
            'Tenant management' => 'Complete tenant lifecycle management',
            'Lease management' => 'Lease agreement and renewal management',
            'Document management' => 'Store and manage property documents',
            'Payment processing' => 'Online payment collection and reconciliation',
            'Automated invoicing' => 'Automated invoice generation and delivery',
        ];
    }

    /**
     * Get recommended plans for a company based on unit count and region
     */
    public static function getRecommendedPlans($regionId, $unitCount)
    {
        return self::forRegion($regionId)
            ->active()
            ->supportsUnitCount($unitCount)
            ->ordered()
            ->get()
            ->map(function($plan) use ($unitCount) {
                return [
                    'plan' => $plan,
                    'monthly_price' => $plan->calculateMonthlyPrice($unitCount),
                    'yearly_price' => $plan->calculateYearlyPrice($unitCount),
                    'is_recommended' => $unitCount >= $plan->min_units && ($plan->max_units === 0 || $unitCount <= $plan->max_units)
                ];
            });
    }

    /**
     * Get the next plan up (for upgrades)
     */
    public function getNextPlan()
    {
        return self::where('region_id', $this->region_id)
            ->where('display_order', '>', $this->display_order)
            ->orderBy('display_order')
            ->first();
    }

    /**
     * Get the previous plan down (for downgrades)
     */
    public function getPreviousPlan()
    {
        return self::where('region_id', $this->region_id)
            ->where('display_order', '<', $this->display_order)
            ->orderBy('display_order', 'desc')
            ->first();
    }

    /**
     * Check if this plan has any active subscriptions
     */
    public function hasActiveSubscriptions()
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->exists();
    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCountAttribute()
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->count();
    }

    /**
     * Get total revenue from this plan
     */
    public function getTotalRevenueAttribute()
    {
        $total = 0;
        $subscriptions = $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->with('company')
            ->get();
        
        foreach ($subscriptions as $subscription) {
            $company = $subscription->company;
            if ($company) {
                $unitCount = \App\Models\Unit::where('company_id', $company->id)
                    ->whereIn('status', ['occupied', 'available'])
                    ->count();
                $total += $this->calculateMonthlyPrice($unitCount);
            }
        }
        
        return $total;
    }

    /**
     * Get the plan's features as a comma-separated string
     */
    public function getFeaturesStringAttribute()
    {
        return implode(', ', $this->features ?? []);
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature($feature)
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get all plans grouped by region
     */
    public static function getPlansGroupedByRegion()
    {
        return self::with('region')
            ->active()
            ->ordered()
            ->get()
            ->groupBy('region_id')
            ->map(function($plans, $regionId) {
                $region = $plans->first()->region;
                return [
                    'region' => $region,
                    'plans' => $plans
                ];
            });
    }
}