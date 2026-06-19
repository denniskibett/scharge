<?php
// app/Modules/Subscriptions/Models/AccountManager.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\Estate;

class AccountManager extends Model
{
    use HasFactory;

    protected $table = 'account_managers';

    protected $fillable = [
        'user_id', 
        'county_id', 
        'subcounty_id', 
        'title', 
        'is_primary', 
        'is_active'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean'
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function county()
    {
        return $this->belongsTo(County::class);
    }

    public function subcounty()
    {
        return $this->belongsTo(Subcounty::class);
    }

    public function estates()
    {
        return $this->hasMany(Estate::class, 'account_manager_id');
    }

    // =============================================
    // SCOPES
    // =============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByCounty($query, $countyId)
    {
        return $query->where('county_id', $countyId);
    }

    public function scopeBySubcounty($query, $subcountyId)
    {
        return $query->where('subcounty_id', $subcountyId);
    }

    // =============================================
    // ACCESSORS
    // =============================================

    public function getFullNameAttribute()
    {
        return $this->user?->name ?? 'Unknown User';
    }

    public function getEmailAttribute()
    {
        return $this->user?->email ?? 'No email';
    }

    public function getCountyNameAttribute()
    {
        return $this->county?->name ?? 'No County';
    }

    public function getSubcountyNameAttribute()
    {
        return $this->subcounty?->name ?? 'No Subcounty';
    }

    public function getEstatesCountAttribute()
    {
        return $this->estates()->count();
    }
}