<?php
// app/Models/County.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class County extends Model
{
    use HasFactory;

    protected $table = 'counties';

    protected $fillable = [
        'county_name'
    ];

    // Relationships
    public function subcounties()
    {
        return $this->hasMany(Subcounty::class, 'county_id');
    }

    public function regions()
    {
        return $this->hasMany(\App\Modules\Subscriptions\Models\Region::class, 'county_id');
    }
}