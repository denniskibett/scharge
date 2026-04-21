<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegisteredVisitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'id_number',
        'visitor_type',
        'relationship',
        'vehicle_details',
        'access_schedule',
        'is_active',
        'valid_until',
        'notes',
    ];

    protected $casts = [
        'vehicle_details' => 'array',
        'access_schedule' => 'array',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            });
    }
}