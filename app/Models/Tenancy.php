<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenancy extends Model
{
    protected $fillable = [
        'company_id',
        'estate_id',
        'tenant_id',
        'unit_id',
        'move_in_date',
        'move_out_date',
        'notes',
        'status'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }
}