<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\Rules\In;

class Tenancy extends Model
{
    protected $fillable = [
        'tenant_id', 'unit_id', 'move_in_date', 'move_out_date', 'status'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'tenancy_id');
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'tenancy_id');
    }




}