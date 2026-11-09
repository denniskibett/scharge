<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A management company asking to take over a building already in scharge. Approved by staff. */
class PropertyClaim extends Model
{
    protected $table = 'property_claims';

    protected $fillable = ['property_id', 'company_id', 'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'message', 'review_note'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
