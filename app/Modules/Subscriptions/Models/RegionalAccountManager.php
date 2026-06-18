<?php
// app/Modules/Subscriptions/Models/RegionalAccountManager.php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class RegionalAccountManager extends Model
{
    use HasFactory;

    protected $table = 'regional_account_managers';

    protected $fillable = [
        'user_id', 'region_id', 'title', 'is_primary', 'is_active'
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

    public function region()
    {
        return $this->belongsTo(Region::class);
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

    public function getRegionNameAttribute()
    {
        return $this->region?->name ?? 'No Region';
    }
}