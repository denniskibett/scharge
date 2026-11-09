<?php

namespace App\Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;
use App\Models\Company;
use App\Models\Property;
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
        'photo_url',
        'company_id',
        'property_id',
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

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function securityLogs()
    {
        return $this->hasMany(SecurityLog::class, 'visitor_id');
    }

    public function places()
    {
        return $this->hasMany(VisitorPlace::class);
    }

    public function passes()
    {
        return $this->hasMany(GatePass::class);
    }

    /** "KDA 123A" -> "KDA123A": how plates are stored and compared. */
    public static function plate(?string $plate): ?string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $plate));

        return $p !== '' ? substr($p, 0, 20) : null;
    }

    /** Remember a car for this visitor (most recent first, at most 5). */
    public function rememberVehicle(?string $plate, ?string $desc = null): void
    {
        if (! ($plate = self::plate($plate))) {
            return;
        }
        $list = collect($this->vehicles ?? [])->map(fn ($v) => is_array($v) ? $v : ['plate' => (string) $v])
            ->reject(fn ($v) => self::plate($v['plate'] ?? null) === $plate)->values();
        $list->prepend(array_filter(['plate' => $plate, 'desc' => $desc, 'last_seen' => now()->toDateString()]));
        $this->vehicles = $list->take(5)->values()->all();
        $this->save();
    }

    /** The visitor with this phone (any format), if known. */
    public static function findByPhone(?string $phone): ?self
    {
        $e164 = \App\Helpers\PhoneHelper::e164($phone);
        if (! $e164) {
            return null;
        }
        $variants = array_unique(array_filter([$e164, ltrim($e164, '+'), str_starts_with($e164, '+254') ? '0'.substr($e164, 4) : null]));

        return static::whereIn('phone', $variants)->orderByDesc('last_visit_at')->first();
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
            'guest' => 'Short-stay guest',
        ];
        return $labels[$this->visitor_type] ?? ucfirst($this->visitor_type);
    }

    public function getFullNameAttribute()
    {
        return $this->name;
    }
}