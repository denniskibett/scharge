<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    // Constants for classification fields
    const OWNERSHIP_HOMEOWNER = 'homeowner';
    const OWNERSHIP_TENANT = 'tenant';
    const OWNERSHIP_COMPANY = 'company';

    const FURNISHING_FURNISHED = 'furnished';
    const FURNISHING_UNFURNISHED = 'unfurnished';
    const FURNISHING_SEMI_FURNISHED = 'semi_furnished';

    const STAY_LONG = 'long_stay';
    const STAY_SHORT = 'short_stay';
    const STAY_BNB = 'bnb';
    const STAY_MIXED = 'mixed';

    const CATEGORY_RESIDENTIAL = 'residential';
    const CATEGORY_COMMERCIAL = 'commercial';
    const CATEGORY_SHOWHOUSE = 'showhouse';
    const CATEGORY_OFFICE = 'office';
    const CATEGORY_RETAIL = 'retail';
    const CATEGORY_INDUSTRIAL = 'industrial';

    protected $fillable = [
        'estate_id',
        'unit_number',
        'unit_type',
        'rent_amount',
        'water_charge', // This can now be NULL for consumption-based
        'service_charge',
        'garbage_charge',
        'security_charge',
        'status',
        'previous_water_reading',
        'current_water_reading',
        'last_reading_date',
        'custom_water_rate',
        'water_billing_type',
        // New classification fields
        'ownership_type',
        'furnishing_status',
        'stay_type',
        'property_category',
        'is_active',
        'min_stay_days',
        'max_stay_days',
        'bnb_cleaning_fee',
        'bnb_nightly_rate',
        'security_deposit_amount',
        'commission_rate'
    ];

    protected $casts = [
        'rent_amount' => 'decimal:2',
        'water_charge' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'garbage_charge' => 'decimal:2',
        'security_charge' => 'decimal:2',
        'previous_water_reading' => 'decimal:2',
        'current_water_reading' => 'decimal:2',
        'custom_water_rate' => 'decimal:2',
        'last_reading_date' => 'date',
        'bnb_cleaning_fee' => 'decimal:2',
        'bnb_nightly_rate' => 'decimal:2',
        'security_deposit_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'min_stay_days' => 'integer',
        'max_stay_days' => 'integer',
    ];

    // ========== WATER MANAGEMENT METHODS (Original) ==========

    // Accessor to get total monthly charges (rent + all utilities)
    public function getTotalMonthlyChargesAttribute()
    {
        $waterCharge = $this->getCurrentWaterCharge();
        
        return ($this->rent_amount ?? 0) +
               ($waterCharge ?? 0) +
               ($this->service_charge ?? 0) +
               ($this->garbage_charge ?? 0) +
               ($this->security_charge ?? 0);
    }

    // Get current water charge (either flat fee or estimated consumption)
    public function getCurrentWaterCharge()
    {
        if ($this->water_billing_type === 'flat') {
            return $this->water_charge ?? 0;
        }
        
        // For consumption-based, return 0 until invoice is generated
        return 0;
    }

    // Calculate water consumption from meter readings
    public function calculateWaterConsumption()
    {
        if ($this->water_billing_type !== 'consumption') {
            return 0;
        }
        
        $currentReading = $this->current_water_reading ?? 0;
        $previousReading = $this->previous_water_reading ?? 0;
        
        return max(0, $currentReading - $previousReading);
    }

    // Calculate water charge based on consumption
    public function calculateWaterCharge()
    {
        if ($this->water_billing_type !== 'consumption') {
            return $this->water_charge ?? 0;
        }
        
        $consumption = $this->calculateWaterConsumption();
        $rate = $this->custom_water_rate ?? $this->estate->water_rate ?? 0;
        
        return $consumption * $rate;
    }

    // Update meter readings after invoicing
    public function updateReadingsAfterInvoice()
    {
        if ($this->water_billing_type === 'consumption' && $this->current_water_reading) {
            $this->update([
                'previous_water_reading' => $this->current_water_reading,
                'last_reading_date' => now(),
            ]);
        }
    }

    // Submit meter reading
    public function submitMeterReading($newReading, $readingDate = null)
    {
        $oldReading = $this->current_water_reading ?? $this->previous_water_reading ?? 0;
        
        $this->update([
            'previous_water_reading' => $oldReading,
            'current_water_reading' => $newReading,
            'last_reading_date' => $readingDate ?? now(),
        ]);
        
        return $this;
    }

    // Submit water reading (alias for submitMeterReading)
    public function submitWaterReading($newReading, $readingDate = null)
    {
        return $this->submitMeterReading($newReading, $readingDate);
    }

    // Get consumption for a specific period
    public function getConsumptionForPeriod($fromDate, $toDate)
    {
        // This would need a readings history table
        // Currently you only store current and previous readings
        return $this->calculateWaterConsumption();
    }

    // ========== BNB AND STAY MANAGEMENT ==========

    // Calculate nightly rate based on stay type
    public function getNightlyRateAttribute()
    {
        if ($this->stay_type === self::STAY_BNB && $this->bnb_nightly_rate) {
            return $this->bnb_nightly_rate;
        }
        
        // Convert monthly rent to nightly (30 days average)
        return ($this->rent_amount ?? 0) / 30;
    }

    // Calculate total for BNB stay
    public function calculateBnbTotal($nights, $includeCleaningFee = true)
    {
        $nightlyTotal = $this->nightly_rate * $nights;
        $cleaningFee = $includeCleaningFee ? ($this->bnb_cleaning_fee ?? 0) : 0;
        
        return $nightlyTotal + $cleaningFee;
    }

    // Get appropriate rate based on stay type and duration
    public function getRateForStay($durationDays)
    {
        switch ($this->stay_type) {
            case self::STAY_BNB:
                return $this->nightly_rate;
            case self::STAY_SHORT:
                // Short stay could have premium pricing
                return ($this->rent_amount / 30) * 1.2; // 20% premium
            case self::STAY_LONG:
            default:
                return $this->rent_amount / 30;
        }
    }

    // Check if stay duration is allowed
    public function isStayDurationAllowed($durationDays)
    {
        if ($this->min_stay_days && $durationDays < $this->min_stay_days) {
            return false;
        }
        
        if ($this->max_stay_days && $durationDays > $this->max_stay_days) {
            return false;
        }
        
        return true;
    }

    // ========== RELATIONSHIPS ==========

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class)->where('status', 'active');
    }

    public function tenant()
    {
        return $this->hasOneThrough(
            Tenant::class,
            Tenancy::class,
            'unit_id',     // Foreign key on tenancies table
            'id',          // Foreign key on tenants table
            'id',          // Local key on units table
            'tenant_id'    // Local key on tenancies table
        )->where('tenancies.status', 'active');
    }

    // ========== SCOPES FOR FILTERING ==========

    public function scopeShowhouses($query)
    {
        return $query->where('property_category', self::CATEGORY_SHOWHOUSE);
    }

    public function scopeCommercial($query)
    {
        return $query->whereIn('property_category', [
            self::CATEGORY_COMMERCIAL, 
            self::CATEGORY_OFFICE, 
            self::CATEGORY_RETAIL
        ]);
    }

    public function scopeBNB($query)
    {
        return $query->where('stay_type', self::STAY_BNB);
    }

    public function scopeFurnished($query)
    {
        return $query->where('furnishing_status', self::FURNISHING_FURNISHED);
    }

    public function scopeOwnerOccupied($query)
    {
        return $query->where('ownership_type', self::OWNERSHIP_HOMEOWNER);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVacant($query)
    {
        return $query->where('status', 'vacant');
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    // ========== HELPER METHODS ==========

    /**
     * Check if unit needs water meter reading
     */
    public function needsWaterReading()
    {
        if ($this->water_billing_type !== 'consumption') {
            return false;
        }
        
        if (!$this->last_reading_date) {
            return true;
        }
        
        // Check if last reading was more than 30 days ago
        return $this->last_reading_date->diffInDays(now()) >= 30;
    }

    /**
     * Get water consumption for current period
     */
    public function getCurrentPeriodConsumption()
    {
        return $this->calculateWaterConsumption();
    }

    /**
     * Get pending water charge
     */
    public function getPendingWaterCharge()
    {
        if ($this->water_billing_type !== 'consumption') {
            return 0;
        }
        
        $consumption = $this->calculateWaterConsumption();
        $rate = $this->custom_water_rate ?? $this->estate->water_rate ?? 0;
        
        return $consumption * $rate;
    }

    /**
     * Check if unit is a showhouse
     */
    public function isShowhouse()
    {
        return $this->property_category === self::CATEGORY_SHOWHOUSE;
    }

    /**
     * Check if unit is commercial
     */
    public function isCommercial()
    {
        return in_array($this->property_category, [
            self::CATEGORY_COMMERCIAL,
            self::CATEGORY_OFFICE,
            self::CATEGORY_RETAIL,
            self::CATEGORY_INDUSTRIAL
        ]);
    }

    /**
     * Check if unit is BNB
     */
    public function isBnb()
    {
        return $this->stay_type === self::STAY_BNB;
    }

    /**
     * Get formatted address
     */
    public function getFormattedAddressAttribute()
    {
        $parts = [];
        
        if ($this->unit_number) {
            $parts[] = "Unit {$this->unit_number}";
        }
        
        if ($this->estate) {
            $parts[] = $this->estate->name;
        }
        
        return implode(', ', $parts);
    }

    public function waterReadings()
    {
        return $this->hasMany(WaterReading::class);
    }
}