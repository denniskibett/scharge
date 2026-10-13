<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmDemoRequest extends Model
{
    public const STATUSES = ['new' => 'New', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'no_show' => 'No-show', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'user_id', 'crm_company_id', 'country_id', 'project_type', 'units', 'message',
        'status', 'scheduled_at', 'source_page', 'ip_address', 'user_agent',
    ];

    protected $casts = ['units' => 'integer', 'scheduled_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class, 'demo_request_id');
    }
}
