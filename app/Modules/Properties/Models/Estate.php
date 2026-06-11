<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Company;

class Estate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 
        'location',
        'water_rate',
        'service_charge',
        'garbage_charge',
        'security_charge'
    ];

    protected $casts = [
        'water_rate' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'garbage_charge' => 'decimal:2',
        'security_charge' => 'decimal:2',
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    // Helper to get effective water rate for a unit
    public function getWaterRateForUnit(Unit $unit)
    {
        return $unit->custom_water_rate ?? $this->water_rate ?? 0;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}