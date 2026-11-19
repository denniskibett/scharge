<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A landlord lets an agency market and let one property (database/schema/2026_11_19_property_agents.sql). */
class PropertyAgent extends Model
{
    protected $table = 'property_agents';

    protected $fillable = ['property_id', 'agency_id', 'can_list', 'commission_pct', 'status', 'granted_by', 'revoked_at', 'notes'];

    protected $casts = ['can_list' => 'boolean', 'commission_pct' => 'decimal:2', 'revoked_at' => 'datetime'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'agency_id')->withoutGlobalScope('customers');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
