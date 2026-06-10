<?php
// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\CompanyPaymentMethod;

class Company extends Model
{
    protected $table = 'companies';
    
    protected $fillable = [
        'name', 'registration_number', 'tax_id', 'email', 'phone', 'address', 
        'location', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'location' => 'array'
    ];
    
    // Users belonging to this company
    public function users()
    {
        return $this->hasMany(\App\Modules\Users\Models\User::class);
    }
    
    // Estates belonging to this company
    public function estates()
    {
        return $this->hasMany(\App\Models\Estate::class);
    }
    
    // Units belonging to this company (through estates)
    public function units()
    {
        return $this->hasManyThrough(\App\Models\Unit::class, \App\Models\Estate::class);
    }
    
    // Current active subscription
    public function currentSubscription()
    {
        return $this->hasOne(CompanySubscription::class)
                    ->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>', now());
                    })
                    ->latest();
    }
    
    // All subscriptions history
    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }
    
    public function paymentMethods()
    {
        return $this->hasMany(CompanyPaymentMethod::class);
    }
    
    public function defaultPaymentMethod()
    {
        return $this->hasOne(CompanyPaymentMethod::class)->where('is_default', true);
    }
    
    // Get admin users of this company
    public function adminUsers()
    {
        return $this->users()->whereHas('role', function($q) {
            $q->whereIn('name', ['super_admin', 'admin']);
        });
    }
    
    // Get staff users of this company (non-tenant)
    public function staffUsers()
    {
        return $this->users()->whereHas('role', function($q) {
            $q->whereNotIn('name', ['tenant']);
        });
    }
    
    // Get tenant users of this company
    public function tenantUsers()
    {
        return $this->users()->whereHas('role', function($q) {
            $q->where('name', 'tenant');
        });
    }
    
    // Check if company can access feature
    public function canAccess($feature)
    {
        $subscription = $this->currentSubscription;
        if (!$subscription) return false;
        
        $features = $subscription->plan->features_json;
        $limits = [
            'max_properties' => $features['max_properties'] ?? 0,
            'max_users' => $features['max_users'] ?? 0,
            'storage_gb' => $features['storage_gb'] ?? 0,
        ];
        
        return $limits[$feature] ?? false;
    }
    
    // Get user count
    public function getUserCount()
    {
        return $this->users()->count();
    }
    
    // Get available user slots
    public function getAvailableUserSlots()
    {
        $subscription = $this->currentSubscription;
        if (!$subscription) return 0;
        
        $maxUsers = $subscription->plan->features_json['max_users'] ?? 0;
        $currentUsers = $this->getUserCount();
        
        return max(0, $maxUsers - $currentUsers);
    }

    // Add wallet relationship
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'holder');
    }

    // Add method to get available funds
    public function getAvailableFundsAttribute()
    {
        return $this->wallet?->balance ?? 0;
    }

    // Add method to withdraw to bank
    public function withdrawToBank(float $amount, array $bankDetails): array
    {
        if (!$this->wallet || $this->wallet->balance < $amount) {
            return ['success' => false, 'error' => 'Insufficient funds'];
        }
        
        DB::beginTransaction();
        try {
            $transaction = $this->wallet->withdraw($amount, [
                'description' => 'Withdrawal to bank account',
                'bank_details' => $bankDetails,
                'type' => 'bank_transfer'
            ]);
            
            // Dispatch job to actually send money to bank
            dispatch(new ProcessBankWithdrawal($this, $amount, $bankDetails, $transaction));
            
            DB::commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'message' => 'Withdrawal initiated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}