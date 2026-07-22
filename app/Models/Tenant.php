<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'estate_id',
        'id_number',
        'emergency_contact',
        'notes'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class)->where('status', 'active');
    }

    // Accessor for name
    public function getNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    }

    // Accessor for phone
    public function getPhoneAttribute()
    {
        return $this->user ? $this->user->phone : null;
    }
}