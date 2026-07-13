<?php

namespace App\Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'estate_id', 'unit_id',
        'first_name', 'last_name', 'phone', 'email', 'id_number', 'id_type',
        'visitor_type', 'relationship', 'company', 'vehicles',
        'vehicle_registration', 'vehicle_model', 'vehicle_color',
        'is_registered', 'registered_by_tenant_id', 'valid_from', 'valid_until',
        'access_schedule', 'is_active', 'is_blacklisted', 'blacklist_reason',
        'total_visits', 'visit_count', 'last_visit_at', 'photo_url', 'notes'
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
        'total_visits' => 'integer',
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
        $vehicles = is_array($this->vehicles) ? $this->vehicles : json_decode($this->vehicles, true);
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
            'one_time' => 'One-time Visitor',
            'guest' => 'Guest',
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

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function estate()
    {
        return $this->belongsTo(\App\Models\Estate::class);
    }

    public function unit()
    {
        return $this->belongsTo(\App\Models\Unit::class);
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

    public function scopeByUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }
}