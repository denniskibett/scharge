<?php

namespace App\Modules\Tenants\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Tenancy;
use App\Modules\Security\Models\SecurityLog;
use App\Modules\Security\Models\Visitor;

class Tenant extends Model
{
    use HasFactory;

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

    // Get current unit
    public function getCurrentUnitAttribute()
    {
        $activeTenancy = $this->activeTenancy;
        return $activeTenancy ? $activeTenancy->unit : null;
    }

    // Get all visitors registered by this tenant
    public function registeredVisitors()
    {
        return $this->hasMany(Visitor::class, 'registered_by_tenant_id');
    }

    // Get all security logs for this tenant's unit
    public function securityLogs()
    {
        $unit = $this->current_unit;
        if (!$unit) {
            return collect();
        }
        return SecurityLog::where('unit_id', $unit->id)->latest('access_time');
    }

    // Get active visitors (valid and not expired)
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

    // Get full name
    public function getNameAttribute()
    {
        return $this->user->name ?? 'Unknown';
    }

    // Get email
    public function getEmailAttribute()
    {
        return $this->user->email ?? null;
    }

    // Get phone
    public function getPhoneAttribute()
    {
        return $this->user->phone ?? null;
    }

    // Get phone2
    public function getPhone2Attribute()
    {
        return $this->user->phone2 ?? null;
    }

    // Check if tenant has active tenancy
    public function hasActiveTenancy()
    {
        return $this->tenancies()->where('status', 'active')->exists();
    }

    /**
     * Get the tenant's wallet.
     */
    public function wallet()
    {
        return $this->hasOne(\App\Models\Wallet::class);
    }
}