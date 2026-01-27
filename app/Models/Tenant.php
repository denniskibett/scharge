<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    protected $fillable = [
        'user_id', 'id_number', 'emergency_contact'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class, 'tenant_id');
    }

    public function activeTenancy()
    {
        return $this->hasOne(Tenancy::class, 'tenant_id')->where('status', 'active');
    }
}