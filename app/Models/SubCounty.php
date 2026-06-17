<?php
// app/Models/Subcounty.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subcounty extends Model
{
    use HasFactory;

    protected $table = 'subcounties';

    protected $fillable = [
        'county_id', 'constituency_name', 'ward', 'alias'
    ];

    // Relationships
    public function county()
    {
        return $this->belongsTo(County::class, 'county_id');
    }

    public function subscriptionPlans()
    {
        return $this->hasMany(\App\Modules\Subscriptions\Models\SubscriptionPlan::class, 'subcounty_id');
    }
}