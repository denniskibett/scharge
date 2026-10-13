<?php

namespace App\Modules\CRM\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot between a person and a country scharge is not in yet. Holds demo details too, so when
 * we launch in the country each entry becomes a CRM follow-up.
 */
class WaitlistEntry extends Model
{
    public const STATUSES = [
        'waiting' => 'Waiting',
        'demo_requested' => 'Wants a demo',
        'invited' => 'Invited',
        'converted' => 'Moved to CRM',
        'unsubscribed' => 'Unsubscribed',
    ];

    protected $fillable = ['user_id', 'country_code', 'company_id', 'status', 'details'];

    protected $casts = ['details' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScope('customers');
    }

    public function getCountryNameAttribute(): string
    {
        return Countries::name($this->country_code) ?? $this->country_code;
    }

    public function detail(string $key, $default = null)
    {
        return data_get($this->details, $key, $default);
    }
}
