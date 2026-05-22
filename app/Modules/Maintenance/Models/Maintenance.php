<?php

namespace App\Modules\Maintenance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    use HasFactory;


    protected $fillable = [
        'unit_id',
        'tenant_id',
        'assigned_to',
        'request_number',
        'name',
        'description',
        'category',
        'priority',
        'status',
        'admin_notes',
        'resolution_notes',
        'scheduled_date',
        'completed_date',
        'cost',
        'images',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'cost' => 'decimal:2',
        'images' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($request) {
            $request->request_number = 'MT-' . str_pad(static::max('id') + 1, 6, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Accessors
    public function getCategoryLabelAttribute()
    {
        $labels = [
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'hvac' => 'HVAC',
            'appliance' => 'Appliance',
            'structural' => 'Structural',
            'pest_control' => 'Pest Control',
            'cleaning' => 'Cleaning',
            'other' => 'Other'
        ];
        return $labels[$this->category] ?? ucfirst($this->category);
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'emergency' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            default => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
            'pending_parts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
            'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
            default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        };
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress', 'pending_parts']);
    }

    public function scopeByUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // Helper methods
    public function markAsInProgress($staffId = null)
    {
        $this->update([
            'status' => 'in_progress',
            'assigned_to' => $staffId ?? $this->assigned_to,
        ]);
    }

    public function markAsCompleted($resolutionNotes = null, $cost = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_date' => now(),
            'resolution_notes' => $resolutionNotes ?? $this->resolution_notes,
            'cost' => $cost ?? $this->cost,
        ]);
    }
}