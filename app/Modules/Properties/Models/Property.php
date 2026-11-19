<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Company;

class Property extends Model
{
    use HasFactory;

    // company_id is set by the server (PropertyController@store), never from the form. Without it here,
    // create() dropped it silently and new properties belonged to no company (invisible to their owner).
    protected $fillable = [
        'company_id',
        'name',
        'location',
        'water_rate',
        'service_charge',
        'garbage_charge',
        'security_charge'
    ];

    protected $casts = [
        // listed_publicly: its vacant homes show on the public Open listings page (switched on from the property page).
        'listed_publicly' => 'boolean',
        'listed_at' => 'datetime',
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