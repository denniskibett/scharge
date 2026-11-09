<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Who manages a unit, in which role (manager | bnb_host | owner), from when. History is kept. */
class UnitManagement extends Model
{
    protected $table = 'unit_managements';

    protected $fillable = ['unit_id', 'company_id', 'role', 'status', 'starts_at', 'ends_at', 'created_by', 'notes'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** The active BnB-host link for a unit, if any. */
    public static function hostOf(int $unitId): ?self
    {
        return static::where('unit_id', $unitId)->where('role', 'bnb_host')->where('status', 'active')->first();
    }
}
