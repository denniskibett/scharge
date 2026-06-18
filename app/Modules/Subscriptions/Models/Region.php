<?php
// app/Modules/Subscriptions/Models/Region.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\County;

class Region extends Model
{
    use HasFactory;

    protected $table = 'regions';

    protected $fillable = [
        'name', 'slug', 'description', 'county_id',
        'longitude', 'latitude', 'population', 
        'is_active', 'display_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'longitude' => 'decimal:8',
        'latitude' => 'decimal:8',
        'population' => 'integer'
    ];

    // Relationships
    public function plans()
    {
        return $this->hasMany(SubscriptionPlan::class);
    }

    public function companies()
    {
        return $this->hasMany(\App\Models\Company::class);
    }

    public function accountManagers()
    {
        return $this->hasMany(RegionalAccountManager::class);
    }

    public function primaryAccountManager()
    {
        return $this->hasOne(RegionalAccountManager::class)->where('is_primary', true);
    }

    // County relationship - using the App\Models\County
    public function county()
    {
        return $this->belongsTo(County::class, 'county_id');
    }

    // Accessor for display name with county
    public function getDisplayNameAttribute()
    {
        if ($this->county) {
            return $this->name . ' (' . $this->county->county_name . ')';
        }
        return $this->name;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // Get all regions with their counties for dropdown
    public static function getRegionsForDropdown()
    {
        return self::with('county')
            ->active()
            ->ordered()
            ->get()
            ->map(function($region) {
                return [
                    'id' => $region->id,
                    'name' => $region->name,
                    'county_id' => $region->county_id,
                    'county_name' => $region->county?->county_name,
                    'display_name' => $region->display_name,
                ];
            });
    }
}