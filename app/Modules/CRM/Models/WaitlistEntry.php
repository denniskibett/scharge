<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pivot between a person (users) and a country they want scharge in. */
class WaitlistEntry extends Model
{
    public const STATUSES = ['waiting', 'contacted', 'invited', 'converted', 'unsubscribed'];

    protected $fillable = [
        'user_id', 'country_id', 'country_name', 'crm_company_id', 'status', 'source',
        'notified_at', 'ip_address', 'user_agent',
    ];

    protected $casts = ['notified_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function getCountryLabelAttribute(): string
    {
        return $this->country?->name ?? $this->country_name ?? 'Another country';
    }
}
