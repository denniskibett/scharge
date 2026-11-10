<?php
// app/Modules/Tenants/Models/Tenant.php

namespace App\Modules\Tenants\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Tenancy;
use App\Modules\Properties\Models\Unit;
use App\Modules\Security\Models\SecurityLog;
use App\Modules\Security\Models\Visitor;
use Bavix\Wallet\Traits\HasWallet;
use Bavix\Wallet\Interfaces\Wallet;

class Tenant extends Model implements Wallet
{
    use HasFactory, HasWallet;

    /**
     * Signed-in company users only find their own company's tenants (App\Support\CompanyAccess): by tenants.company_id,
     * or, when it is empty, through a tenancy of theirs.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company', function ($query) {
            $ids = \App\Support\CompanyAccess::ids();
            if ($ids !== null) {
                $query->where(fn ($q) => $q->whereIn('tenants.company_id', $ids)
                    ->orWhere(fn ($q) => $q->whereNull('tenants.company_id')->whereHas('tenancies')));
            }
        });

        // A tenant added by a company user belongs to that company (the forms never set it, so they disappeared
        // from the company's list until a tenancy existed).
        static::creating(function ($tenant) {
            if (! $tenant->company_id && ($companyId = auth()->user()?->company_id)) {
                $tenant->company_id = $companyId;
            }
        });
    }

    protected $fillable = [
        'user_id', 
        'id_number', 
        'emergency_contact',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class, 'tenant_id');
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class, 'tenant_id')->where('status', 'active');
    }

    public function getCurrentUnitAttribute()
    {
        $activeTenancy = $this->activeTenancy;
        return $activeTenancy ? $activeTenancy->unit : null;
    }

    public function registeredVisitors()
    {
        return $this->hasMany(Visitor::class, 'registered_by_tenant_id');
    }

    public function securityLogs()
    {
        $unit = $this->current_unit;
        if (!$unit) {
            return collect();
        }
        return SecurityLog::where('unit_id', $unit->id)->latest('access_time');
    }

    public function getActiveVisitorsAttribute()
    {
        return $this->registeredVisitors()
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            })
            ->get();
    }

    public function getNameAttribute()
    {
        return $this->user->name ?? 'Unknown';
    }

    public function getEmailAttribute()
    {
        return $this->user->email ?? null;
    }

    public function getPhoneAttribute()
    {
        return $this->user->phone ?? null;
    }

    public function getPhone2Attribute()
    {
        return $this->user->phone2 ?? null;
    }

    public function hasActiveTenancy()
    {
        return $this->tenancies()->where('status', 'active')->exists();
    }

    /**
     * Get formatted wallet ID (16-digit padded from Bavix wallet ID)
     */
    public function getFormattedWalletIdAttribute()
    {
        $wallet = $this->wallet;
        
        if ($wallet) {
            // Pad the wallet ID to 16 digits
            return str_pad((string) $wallet->getKey(), 16, '0', STR_PAD_LEFT);
        }
        
        return str_pad((string) $this->id, 16, '0', STR_PAD_LEFT);
    }

    /**
     * Get masked wallet number for display
     */
    public function getMaskedWalletNumberAttribute()
    {
        $full = $this->formatted_wallet_id;
        $last4 = substr($full, -4);
        return '•••• •••• •••• ' . $last4;
    }

    public function unit()
    {
        return $this->hasOneThrough(
            Unit::class,
            Tenancy::class,
            'tenant_id',
            'id',
            'id',
            'unit_id'
        );
    }
}