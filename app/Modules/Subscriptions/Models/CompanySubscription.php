<?php
// app/Modules/Subscriptions/Models/CompanySubscription.php

namespace App\Modules\Subscriptions\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $table = 'company_subscriptions';

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'billing_cycle', 'starts_at',
        'ends_at', 'trial_ends_at', 'cancelled_at', 'auto_renew', 'payment_method_id'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(CompanyPaymentMethod::class, 'payment_method_id');
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
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

    public function isExpired()
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function cancel($immediate = false)
    {
        if ($immediate) {
            $this->status = 'cancelled';
            $this->ends_at = now();
        } else {
            $this->cancelled_at = now();
            $this->auto_renew = false;
        }
        $this->save();
        
        event(new \App\Modules\Subscriptions\Events\SubscriptionCancelled($this));
    }

    public function resume()
    {
        if ($this->cancelled_at && !$this->ends_at?->isPast()) {
            $this->cancelled_at = null;
            $this->auto_renew = true;
            $this->status = 'active';
            $this->save();
        }
    }
}