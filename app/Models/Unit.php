<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'company_id',
        'estate_id',
        'unit_number',
        'unit_type',
        'rent_amount',
        'water_charge',
        // ... other fields
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    public function waterReadings()
    {
        return $this->hasMany(WaterReading::class);
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class)->where('status', 'active');
    }
}