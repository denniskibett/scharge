<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    protected $fillable = [
        'estate_id', 'unit_number', 'unit_type', 'rent_amount', 'status'
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class)->where('status', 'active');
    }

    public function currentUser()
    {
        return $this->hasOneThrough(User::class, Tenancy::class)
                    ->where('tenancies.status', 'active');
    }
}