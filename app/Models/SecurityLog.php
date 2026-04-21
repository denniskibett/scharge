<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    use HasFactory;

    protected $table = 'security_logs';

    protected $fillable = [
        'unit_id', 'tenant_id', 'visitor_id', 'verified_by_user_id',
        'visitor_name_snapshot', 'visitor_phone_snapshot', 'visitor_id_number_snapshot',
        'visitor_company_snapshot', 'vehicle_registration_snapshot',
        'access_type', 'status', 'access_time', 'exit_time', 'duration_hours',
        'purpose', 'notes', 'approved_by', 'approved_at', 'images',
        'ip_address', 'user_agent'
    ];

    protected $casts = [
        'access_time' => 'datetime',
        'exit_time' => 'datetime',
        'approved_at' => 'datetime',
        'images' => 'array',
    ];

    // Relationships
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'denied' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'expired' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
            default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        };
    }

    public function getAccessTypeLabelAttribute()
    {
        $labels = [
            'entry' => 'Entry', 'exit' => 'Exit', 'delivery' => 'Delivery',
            'guest' => 'Guest Visit', 'maintenance' => 'Maintenance',
            'emergency' => 'Emergency', 'contractor' => 'Contractor',
            'moving' => 'Moving', 'inspection' => 'Inspection'
        ];
        return $labels[$this->access_type] ?? ucfirst($this->access_type);
    }

    // Helper to create log from visitor
    public static function createFromVisitor($data, Visitor $visitor, Unit $unit, $tenant = null)
    {
        $primaryVehicle = $visitor->primary_vehicle;
        
        return self::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant?->id,
            'visitor_id' => $visitor->id,
            'visitor_name_snapshot' => $visitor->full_name,
            'visitor_phone_snapshot' => $visitor->phone,
            'visitor_id_number_snapshot' => $visitor->id_number,
            'visitor_company_snapshot' => $visitor->company,
            'vehicle_registration_snapshot' => $data['vehicle_registration'] ?? $primaryVehicle['registration'] ?? null,
            'access_type' => $data['access_type'],
            'access_time' => $data['access_time'] ?? now(),
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $visitor->isAuthorizedForUnit($unit->id) ? 'approved' : 'pending',
        ]);
    }
}