<?php
// app/Modules/Subscriptions/Models/CompanySubscription.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Company;
use App\Models\Unit;

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
        return $this->belongsTo(Company::class);
    }
    
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
    
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'company_subscription_id');
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            });
    }
    
    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays($days));
    }
    
    public function scopeOnTrial($query)
    {
        return $query->where('status', 'trial')
            ->where(function($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '>', now());
            });
    }
    
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function($q) {
                $q->where('status', 'active')
                  ->whereNotNull('ends_at')
                  ->where('ends_at', '<', now());
            });
    }
    
    public function isActive()
    {
        return $this->status === 'active' && 
               (!$this->ends_at || $this->ends_at->isFuture());
    }
    
    public function isOnTrial()
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }
    
    public function isPastDue()
    {
        return $this->status === 'past_due';
    }
    
    public function isCancelled()
    {
        return $this->status === 'cancelled' || !is_null($this->cancelled_at);
    }
    
    public function isExpired()
    {
        return $this->status === 'expired' || 
               ($this->status === 'active' && $this->ends_at && $this->ends_at->isPast());
    }
    
    public function cancel($immediate = false)
    {
        if ($immediate) {
            $this->status = 'cancelled';
            $this->ends_at = now();
        } else {
            $this->cancelled_at = now();
            $this->status = 'cancelled';
        }
        $this->save();
        return $this;
    }
    
    public function resume()
    {
        if ($this->isCancelled() && (!$this->ends_at || $this->ends_at->isFuture())) {
            $this->cancelled_at = null;
            $this->status = 'active';
            $this->auto_renew = true;
            $this->save();
            return true;
        }
        return false;
    }
    
    public function renew()
    {
        if ($this->status !== 'active' && $this->status !== 'trial') {
            return false;
        }
        
        $this->starts_at = $this->ends_at ?? now();
        $this->ends_at = $this->billing_cycle === 'monthly' 
            ? $this->starts_at->addMonth()
            : $this->starts_at->addYear();
        $this->status = 'active';
        $this->save();
        
        return $this;
    }
    
    public function getCurrentPriceAttribute()
    {
        if (!$this->plan) {
            return 0;
        }
        
        $features = $this->plan->features_json ?? [];
        $pricingType = $features['pricing_type'] ?? 'fixed';
        
        if ($pricingType === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            $unitCount = $this->unit_count > 0 ? $this->unit_count : 1;
            return $pricePerUnit * $unitCount;
        }
        
        return $this->billing_cycle === 'monthly' 
            ? (float) $this->plan->price_monthly 
            : (float) $this->plan->price_yearly;
    }
    
    public function getMonthlyPriceAttribute()
    {
        if (!$this->plan) {
            return 0;
        }
        
        $features = $this->plan->features_json ?? [];
        $pricingType = $features['pricing_type'] ?? 'fixed';
        
        if ($pricingType === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            $unitCount = $this->unit_count > 0 ? $this->unit_count : 1;
            return $pricePerUnit * $unitCount;
        }
        
        return (float) $this->plan->price_monthly;
    }
    
    public function getYearlyPriceAttribute()
    {
        if (!$this->plan) {
            return 0;
        }
        
        $features = $this->plan->features_json ?? [];
        $pricingType = $features['pricing_type'] ?? 'fixed';
        
        if ($pricingType === 'per_unit') {
            $pricePerUnit = $features['price_per_unit'] ?? 0;
            $unitCount = $this->unit_count > 0 ? $this->unit_count : 1;
            return ($pricePerUnit * $unitCount * 12) * 0.9;
        }
        
        return (float) $this->plan->price_yearly;
    }
    
    public function getDaysRemainingAttribute()
    {
        if (!$this->ends_at) {
            return null;
        }
        
        if ($this->ends_at->isPast()) {
            return 0;
        }
        
        return now()->diffInDays($this->ends_at);
    }
    
    public function getTrialDaysRemainingAttribute()
    {
        if (!$this->isOnTrial() || !$this->trial_ends_at) {
            return 0;
        }
        
        return now()->diffInDays($this->trial_ends_at, false);
    }
    
    public function getStatusLabelAttribute()
    {
        $labels = [
            'trial' => 'On Trial',
            'active' => 'Active',
            'cancelled' => 'Cancelled',
            'past_due' => 'Past Due',
            'expired' => 'Expired'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }
    
    public function getStatusColorAttribute()
    {
        $colors = [
            'trial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'past_due' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'expired' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
        ];
        
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }
    
    protected static function booted()
    {
        static::saving(function ($subscription) {
            if ($subscription->company_id && $subscription->unit_count === 0) {
                $subscription->unit_count = Unit::where('company_id', $subscription->company_id)
                    ->whereIn('status', ['occupied', 'available'])
                    ->count();
            }
            
            if ($subscription->plan_id && $subscription->unit_count > 0) {
                $plan = SubscriptionPlan::find($subscription->plan_id);
                if ($plan) {
                    $features = $plan->features_json ?? [];
                    $pricingType = $features['pricing_type'] ?? 'fixed';
                    
                    if ($pricingType === 'per_unit') {
                        $pricePerUnit = $features['price_per_unit'] ?? 0;
                        $subscription->calculated_price = $pricePerUnit * $subscription->unit_count;
                    } else {
                        $subscription->calculated_price = $subscription->billing_cycle === 'monthly' 
                            ? (float) $plan->price_monthly 
                            : (float) $plan->price_yearly;
                    }
                }
            }
        });
    }
}