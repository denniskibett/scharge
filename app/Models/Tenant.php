<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'id_number', 
        'emergency_contact',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    // Get full name from user relationship
    public function getNameAttribute()
    {
        return $this->user->name ?? 'Unknown';
    }

    // Get email from user relationship
    public function getEmailAttribute()
    {
        return $this->user->email ?? null;
    }

    // Get phone from user relationship
    public function getPhoneAttribute()
    {
        return $this->user->phone ?? null;
    }

    // Get phone2 from user relationship
    public function getPhone2Attribute()
    {
        return $this->user->phone2 ?? null;
    }

    // Check if tenant has active tenancy
    public function hasActiveTenancy()
    {
        return $this->tenancies()->where('status', 'active')->exists();
    }

    // Get current unit if any
    public function getCurrentUnitAttribute()
    {
        $activeTenancy = $this->tenancies()->with('unit')->where('status', 'active')->first();
        return $activeTenancy ? $activeTenancy->unit : null;
    }
}