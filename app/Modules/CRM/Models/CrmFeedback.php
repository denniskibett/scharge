<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFeedback extends Model
{
    protected $table = 'crm_feedback';

    public const CONTEXTS = ['demo' => 'After a demo', 'onboarding' => 'After onboarding', 'support' => 'After support', 'general' => 'General'];

    /** Scores at or below this open a recovery follow-up (NPS "detractors"). */
    public const LOW_SCORE = 6;

    protected $fillable = [
        'crm_company_id', 'user_id', 'demo_request_id', 'follow_up_id', 'requested_by', 'token',
        'context', 'score', 'comment', 'requested_at', 'submitted_at',
    ];

    protected $casts = ['score' => 'integer', 'requested_at' => 'datetime', 'submitted_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(CrmFollowUp::class, 'follow_up_id');
    }

    public function getBandAttribute(): ?string
    {
        if ($this->score === null) {
            return null;
        }

        return $this->score >= 9 ? 'promoter' : ($this->score >= 7 ? 'passive' : 'detractor');
    }
}
