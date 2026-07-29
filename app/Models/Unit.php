<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'estate_id',
        'unit_number',
        'unit_type',
        'rent_amount',
        'water_charge',
        'is_active',
        'bedrooms',
        'bathrooms',
        'floor',
        'size',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rent_amount' => 'decimal:2',
        'water_charge' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the estate that owns the unit.
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Get the tenancies for the unit.
     */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }

    /**
     * Get the water readings for the unit.
     */
    public function waterReadings(): HasMany
    {
        return $this->hasMany(WaterReading::class);
    }

    /**
     * Get the active tenancy for the unit.
     */
    public function activeTenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class)->where('status', 'active');
    }

    /**
     * Get the current tenant for the unit.
     */
    public function currentTenant()
    {
        $activeTenancy = $this->activeTenancy;
        return $activeTenancy ? $activeTenancy->tenant : null;
    }

    /**
     * Check if the unit is occupied.
     */
    public function isOccupied(): bool
    {
        return $this->activeTenancy()->exists();
    }

    /**
     * Check if the unit is vacant.
     */
    public function isVacant(): bool
    {
        return !$this->isOccupied();
    }

    /**
     * Check if this unit needs a water reading.
     * 
     * @return bool
     */
    public function needsWaterReading(): bool
    {
        $lastReading = $this->waterReadings()->latest('reading_date')->first();
        
        if (!$lastReading) {
            return true; // No reading exists, needs one
        }
        
        // If the last reading is older than 30 days, needs a new one
        // Adjust the number of days based on your business rules
        return $lastReading->reading_date->diffInDays(now()) > 30;
    }

    /**
     * Get the latest water reading for the unit.
     */
    public function latestWaterReading()
    {
        return $this->waterReadings()->latest('reading_date')->first();
    }

    /**
     * Get the total water usage for a given period.
     */
    public function getWaterUsageForPeriod($startDate, $endDate)
    {
        return $this->waterReadings()
            ->whereBetween('reading_date', [$startDate, $endDate])
            ->sum('usage');
    }

    /**
     * Check if the unit has any outstanding water bills.
     */
    public function hasOutstandingWaterBills(): bool
    {
        // This assumes you have a water bills relationship
        // Adjust based on your actual implementation
        return $this->waterReadings()
            ->where('is_billed', false)
            ->exists();
    }

    /**
     * Get the formatted unit number with estate prefix.
     */
    public function getFormattedUnitNumberAttribute(): string
    {
        if ($this->estate) {
            return $this->estate->name . ' - ' . $this->unit_number;
        }
        return $this->unit_number;
    }

    /**
     * Scope a query to only include active units.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include vacant units.
     */
    public function scopeVacant($query)
    {
        return $query->whereDoesntHave('activeTenancy');
    }

    /**
     * Scope a query to only include occupied units.
     */
    public function scopeOccupied($query)
    {
        return $query->whereHas('activeTenancy');
    }

    /**
     * Scope a query to only include units by estate.
     */
    public function scopeByEstate($query, $estateId)
    {
        return $query->where('estate_id', $estateId);
    }

    /**
     * Scope a query to only include units by type.
     */
    public function scopeByType($query, $unitType)
    {
        return $query->where('unit_type', $unitType);
    }
}