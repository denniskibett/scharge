<?php
// app/Modules/Water/Models/WaterReading.php

namespace App\Modules\Water\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Unit;
use App\Models\User;

class WaterReading extends Model
{
    protected $table = 'water_readings';
    
    protected $fillable = [
        'unit_id',
        'is_initial',
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
        'is_initial' => 'boolean',
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
    
    /**
     * Get the user who recorded this reading
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
    
    /**
     * Scope for filtering by unit
     */
    public function scopeForUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }
    
    /**
     * Scope for date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('reading_date', [$startDate, $endDate]);
    }
    
    /**
     * Scope for non-initial readings only
     */
    public function scopeNonInitial($query)
    {
        return $query->where('is_initial', false);
    }
    
    /**
     * Scope for initial readings only
     */
    public function scopeInitial($query)
    {
        return $query->where('is_initial', true);
    }
    
    /**
     * Calculate consumption if not already set
     */
    public function calculateConsumption(): float
    {
        if ($this->consumption !== null && $this->consumption > 0) {
            return (float) $this->consumption;
        }
        
        $consumption = max(0, $this->current_reading - $this->previous_reading);
        $this->consumption = $consumption;
        
        return (float) $consumption;
    }
    
    /**
     * Calculate charge if not already set
     */
    public function calculateCharge(float $rate = null): float
    {
        if ($this->charge !== null && $this->charge > 0) {
            return (float) $this->charge;
        }
        
        $rate = $rate ?? $this->rate_applied ?? 0;
        $billingType = $this->billing_type ?? 'consumption';
        
        if ($billingType === 'flat') {
            // Flat rate is stored per unit, not here
            $charge = $this->charge ?? 0;
        } else {
            $consumption = $this->calculateConsumption();
            $charge = $consumption * $rate;
        }
        
        $this->charge = $charge;
        
        return (float) $charge;
    }
    
    /**
     * Get the previous reading from the unit or latest reading
     */
    public static function getPreviousReadingForUnit($unitId, ?string $readingDate = null): float
    {
        $query = self::where('unit_id', $unitId)
            ->where('is_initial', false);
        
        if ($readingDate) {
            $query->where('reading_date', '<', $readingDate);
        }
        
        $latest = $query->orderBy('reading_date', 'desc')->first();
        
        return $latest ? (float) $latest->current_reading : 0;
    }
    
    /**
     * Check if this is the first reading for the unit
     */
    public function isFirstReading(): bool
    {
        return $this->is_initial || 
               self::where('unit_id', $this->unit_id)
                   ->where('id', '!=', $this->id)
                   ->count() === 0;
    }
}