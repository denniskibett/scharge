<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A water or electricity meter on a unit (or a property's common area).
 * kind=manual: read by a meter reader; kind=smart: reports itself with a device token (mtr_…).
 * See database/schema/2026_11_09_meters.sql.
 */
class Meter extends Model
{
    protected $table = 'meters';

    public const UTILITIES = ['water' => 'm3', 'electricity' => 'kWh'];

    protected $fillable = ['company_id', 'property_id', 'unit_id', 'utility', 'kind', 'billing', 'serial_number', 'make', 'model',
        'unit_of_measure', 'multiplier', 'rate_per_unit', 'initial_reading', 'last_reading', 'last_read_at', 'status', 'notes'];

    protected $casts = [
        'multiplier' => 'float', 'rate_per_unit' => 'float', 'initial_reading' => 'float', 'last_reading' => 'float', 'last_read_at' => 'datetime',
    ];

    protected $hidden = ['device_token_hash'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function readings()
    {
        return $this->hasMany(MeterReading::class);
    }

    /** Rate per unit of consumption: the meter's own, else the unit's/property's water rate. */
    public function rate(): ?float
    {
        if ($this->rate_per_unit !== null) {
            return $this->rate_per_unit;
        }
        if ($this->utility === 'water') {
            return (float) ($this->unit?->custom_water_rate ?? $this->unit?->property?->water_rate ?? $this->property?->water_rate ?? 0) ?: null;
        }

        return null;
    }

    /** Issue a new device token for a smart meter; returns the plain token once. */
    public function issueDeviceToken(): string
    {
        $plain = 'mtr_'.Str::random(40);
        $this->forceFill(['device_token_prefix' => 'mtr_', 'device_token_hash' => hash('sha256', $plain), 'kind' => 'smart'])->save();

        return $plain;
    }

    public static function findByDeviceToken(?string $plain): ?self
    {
        return $plain && str_starts_with($plain, 'mtr_') ? static::where('device_token_hash', hash('sha256', $plain))->where('status', 'active')->first() : null;
    }
}
