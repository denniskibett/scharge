<?php

namespace App\Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;
use App\Models\Company;
use App\Models\Estate;
use App\Models\Unit;

class Visitor extends Model
{
    use HasFactory;

    protected $table = 'visitors';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'id_number',
        'id_type',
        'company',
        'visitor_type',
        'relationship',
        'vehicles',
        'is_registered',
        'is_active',
        'is_blacklisted',
        'blacklist_reason',
        'is_pre_approved',
        'valid_until',
        'access_schedule',
        'visit_count',
        'last_visit_at',
        'company_id',
        'estate_id',
        'unit_id',
        'tenant_id',
        'registered_by_tenant_id',
        'notes',
    ];

    protected $casts = [
        'vehicles' => 'array',
        'access_schedule' => 'array',
        'valid_until' => 'date',
        'last_visit_at' => 'datetime',
        'is_registered' => 'boolean',
        'is_active' => 'boolean',
        'is_blacklisted' => 'boolean',
        'is_pre_approved' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function securityLogs()
    {
        return $this->hasMany(SecurityLog::class, 'visitor_id');
    }

    // Accessors
    public function getVisitorTypeLabelAttribute()
    {
        $labels = [
            'family' => 'Family Member',
            'employee' => 'Employee',
            'contractor' => 'Contractor',
            'regular_guest' => 'Regular Guest',
            'delivery' => 'Delivery Person',
            'maintenance' => 'Maintenance Staff',
            'one_time' => 'One Time Visitor',
        ];
        return $labels[$this->visitor_type] ?? ucfirst($this->visitor_type);
    }

    public function getFullNameAttribute()
    {
        return $this->name;
    }
}