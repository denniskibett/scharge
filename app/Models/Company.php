<?php
// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\CompanyPaymentMethod;

class Company extends Model
{
    protected $table = 'companies';

    /** CRM pipeline stages. Prospects and customers both live in this table (see `lifecycle`). */
    public const CRM_STAGES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'demo_booked' => 'Demo booked',
        'proposal' => 'Proposal',
        'negotiation' => 'Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
        'nurture' => 'Nurture',
    ];

    public const OPEN_CRM_STAGES = ['new', 'contacted', 'qualified', 'demo_booked', 'proposal', 'negotiation'];

    protected $fillable = [
        'name', 'registration_number', 'tax_id', 'email', 'phone', 'address',
        'location', 'is_active',
        'lifecycle', 'crm_stage', 'tier', 'owner_id', 'country_code', 'domain', 'name_key', 'source', 'crm_profile',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'location' => 'array',
        'crm_profile' => 'array',
        'tier' => 'integer',
    ];

    /**
     * Prospects are hidden everywhere except the CRM. Use Company::crm() to include them.
     * The check waits for the `lifecycle` column so the app keeps working before the SQL is applied.
     */
    protected static function booted(): void
    {
        static $hasLifecycle;
        $hasLifecycle ??= rescue(fn () => \Illuminate\Support\Facades\Schema::hasColumn('companies', 'lifecycle'), false, false);

        if ($hasLifecycle) {
            static::addGlobalScope('customers', fn ($query) => $query->where('companies.lifecycle', '!=', 'prospect'));
        }
    }

    /** Every company the CRM works with: prospects plus customers that came through the pipeline. */
    public static function crm()
    {
        return static::withoutGlobalScope('customers')->whereNotNull('companies.crm_stage');
    }

    public function owner()
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'owner_id');
    }

    /** The CRM pivot: every contact link, call, note, demo and feedback request for this company. */
    public function followUps()
    {
        return $this->hasMany(\App\Modules\CRM\Models\CrmFollowUp::class);
    }

    public function openFollowUps()
    {
        return $this->followUps()->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', ['contact', 'feedback'])->orderBy('due_at');
    }

    /** People who represent this company: anyone linked through a CRM row. */
    public function contacts()
    {
        return $this->belongsToMany(\App\Modules\Users\Models\User::class, 'crm_follow_ups', 'company_id', 'user_id')->distinct();
    }

    public function getCrmStageLabelAttribute(): ?string
    {
        return $this->crm_stage ? (self::CRM_STAGES[$this->crm_stage] ?? ucfirst($this->crm_stage)) : null;
    }

    /** Sales-sheet details: website, phone_alt, business_type, portfolio, opportunity, target_role, ... */
    public function profile(string $key, $default = null)
    {
        return data_get($this->crm_profile, $key, $default);
    }

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

}