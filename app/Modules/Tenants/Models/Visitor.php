<?php

namespace App\Modules\Tenants\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'phone', 'email', 'id_number', 'id_type',
        'visitor_type', 'relationship', 'company', 'vehicles',
        'is_registered', 'registered_by_tenant_id', 'valid_from', 'valid_until',
        'access_schedule', 'is_active', 'is_blacklisted', 'blacklist_reason',
        'visit_count', 'last_visit_at', 'photo_url', 'notes'
    ];

    protected $casts = [
        'vehicles' => 'array',
        'access_schedule' => 'array',
        'is_registered' => 'boolean',
        'is_active' => 'boolean',
        'is_blacklisted' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'last_visit_at' => 'datetime',
        'visit_count' => 'integer',
    ];

    // Accessors
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getPrimaryVehicleAttribute()
    {
        if (!$this->vehicles) return null;
        $vehicles = json_decode($this->vehicles, true);
        if (empty($vehicles)) return null;
        
        $primary = collect($vehicles)->firstWhere('is_primary', true);
        return $primary ?? $vehicles[0];
    }

    public function getVisitorTypeLabelAttribute()
    {
        $labels = [
            'family' => 'Family Member',
            'employee' => 'Employee',
            'contractor' => 'Contractor',
            'regular_guest' => 'Regular Guest',
            'delivery' => 'Delivery Personnel',
            'maintenance' => 'Maintenance Staff',
            'one_time' => 'One-time Visitor'
        ];
        return $labels[$this->visitor_type] ?? ucfirst($this->visitor_type);
    }

    // Relationships
    public function registeredByTenant()
    {
        return $this->belongsTo(Tenant::class, 'registered_by_tenant_id');
    }

    public function securityLogs()
    {
        return $this->hasMany(SecurityLog::class);
    }

    // Scopes
    public function scopeRegistered($query)
    {
        return $query->where('is_registered', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            });
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('is_blacklisted', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('visitor_type', $type);
    }

    // Helper methods
    public function isAuthorizedForUnit($unitId)
    {
        if (!$this->is_active) return false;
        if ($this->is_blacklisted) return false;
        if ($this->valid_until && $this->valid_until < now()) return false;
        
        // Check if registered for this specific unit
        if ($this->is_registered && $this->registered_by_tenant_id) {
            $tenant = $this->registeredByTenant;
            if ($tenant && $tenant->activeTenancy && $tenant->activeTenancy->unit_id == $unitId) {
                return true;
            }
        }
        
        return false;
    }

    public function addVehicle($registration, $make = null, $color = null, $isPrimary = false)
    {
        $vehicles = $this->vehicles ?: [];
        $vehicles[] = [
            'registration' => $registration,
            'make' => $make,
            'color' => $color,
            'is_primary' => $isPrimary
        ];
        
        // If this is primary, remove primary from others
        if ($isPrimary) {
            foreach ($vehicles as &$v) {
                $v['is_primary'] = false;
            }
        }
        
        $this->update(['vehicles' => $vehicles]);
        return $this;
    }

    public function incrementVisit()
    {
        $this->increment('visit_count');
        $this->update(['last_visit_at' => now()]);
        return $this;
    }
}