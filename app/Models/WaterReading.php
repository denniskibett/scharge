<?php
// app/Models/WaterReading.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterReading extends Model
{
    protected $fillable = [
        'unit_id',
        'previous_reading',
        'current_reading',
        'consumption',
        'rate_applied',
        'charge',
        'billing_type',
        'reading_date',
        'recorded_by',
        'notes'
    ];
    
    protected $casts = [
        'reading_date' => 'date',
        'previous_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'consumption' => 'decimal:2',
        'rate_applied' => 'decimal:2',
        'charge' => 'decimal:2'
    ];
    
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    
    // Scope for filtering
    public function scopeForUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }
    
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('reading_date', [$startDate, $endDate]);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}