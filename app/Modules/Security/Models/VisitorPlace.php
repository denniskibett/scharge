<?php

namespace App\Modules\Security\Models;

use App\Models\Company;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;

/**
 * Pivot visitor <-> unit (with the unit's property and business): how often they came, when they were last
 * in and out, and the car they last came with. One row per visitor per unit, updated on every pass.
 */
class VisitorPlace extends Model
{
    protected $table = 'visitor_places';

    protected $fillable = [
        'visitor_id', 'unit_id', 'property_id', 'company_id', 'visit_count', 'first_visit_at',
        'last_check_in_at', 'last_check_out_at', 'last_pass_id', 'last_vehicle_plate', 'is_blocked', 'notes',
    ];

    protected $casts = [
        'first_visit_at' => 'datetime',
        'last_check_in_at' => 'datetime',
        'last_check_out_at' => 'datetime',
        'is_blocked' => 'boolean',
        'visit_count' => 'integer',
    ];

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lastPass()
    {
        return $this->belongsTo(GatePass::class, 'last_pass_id');
    }
}
