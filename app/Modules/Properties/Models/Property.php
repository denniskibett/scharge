<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class Property extends Model
{
    use \App\Traits\CompanyScoped;

    /**
     * A company sees its own buildings, buildings where it owns or manages (BnB host) units, and buildings landlords
     * gave its agency (property_agents), the same rule as units. Finding any building on scharge (onboarding, host
     * sign-up, claims, the public listings) goes through withoutGlobalScope('company') or CompanyAccess::bypass.
     */
    protected static function companyScope(Builder $query, array $ids): void
    {
        $query->where(fn ($q) => $q->whereIn('properties.company_id', $ids)
            ->orWhereIn('properties.id', DB::table('units')->whereIn('company_id', $ids)->select('property_id'))
            ->orWhereIn('properties.id', DB::table('units')->whereIn('id', DB::table('unit_managements')->whereIn('company_id', $ids)->where('status', 'active')->select('unit_id'))->select('property_id'))
            ->when(\App\Services\Agents\AgentAccess::enabled(), fn ($w) => $w->orWhereIn('properties.id',
                DB::table('property_agents')->whereIn('agency_id', $ids)->where('status', 'active')->select('property_id'))));
    }

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
        // lat / lng: GPS pin inside Kenya (App\Support\PropertyPin; database/schema/2026_11_19_property_pin.sql). Set with forceFill.
        'pinned_at' => 'datetime',
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