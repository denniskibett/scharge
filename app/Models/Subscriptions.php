<?php

namespace App\Models;

class CompanySubscription extends Model
{
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean'
    ];
    
    protected $fillable = [
        'company_id', 'plan_id', 'status', 'starts_at', 'ends_at',
        'trial_ends_at', 'cancelled_at', 'auto_renew', 'payment_method_id'
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
        return $this->hasMany(SubscriptionInvoice::class);
    }
    
    // Check if subscription is active
    public function isActive()
    {
        return $this->status === 'active' && 
               (!$this->ends_at || $this->ends_at->isFuture());
    }
    
    // Check if on trial
    public function isOnTrial()
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }
    
    // Cancel subscription
    public function cancel($immediate = false)
    {
        if ($immediate) {
            $this->status = 'cancelled';
            $this->ends_at = now();
        } else {
            $this->cancelled_at = now();
            // Will expire at period end
        }
        $this->save();
    }
    
    // Resume cancelled subscription
    public function resume()
    {
        if ($this->cancelled_at && !$this->ends_at->isPast()) {
            $this->cancelled_at = null;
            $this->status = 'active';
            $this->save();
        }
    }
