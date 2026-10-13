<?php

namespace App\Modules\CRM\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The CRM pivot between a person (user_id) and a company. One row per thing that happens:
 *   contact  – links a person to the company (details.job_title)
 *   note / call / email / whatsapp / meeting – logged (done) or to do (open)
 *   demo     – a demo request: open → scheduled → done / missed / cancelled
 *   feedback – open until the customer answers via the private link (score, notes)
 *   stage / import – history written by the system
 */
class CrmFollowUp extends Model
{
    public const TYPES = [
        'call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting', 'note' => 'Note',
        'demo' => 'Demo', 'feedback' => 'Feedback', 'contact' => 'Contact', 'stage' => 'Stage', 'import' => 'Import',
    ];

    /** Types staff schedule or log by hand. */
    public const ACTION_TYPES = ['call', 'email', 'whatsapp', 'meeting', 'note'];

    public const STATUSES = ['open' => 'Open', 'scheduled' => 'Scheduled', 'done' => 'Done', 'missed' => 'Missed', 'cancelled' => 'Cancelled'];

    public const DEMO_STATUSES = ['open' => 'New request', 'scheduled' => 'Scheduled', 'done' => 'Completed', 'missed' => 'No-show', 'cancelled' => 'Cancelled'];

    public const FEEDBACK_CONTEXTS = ['demo' => 'After a demo', 'onboarding' => 'After onboarding', 'support' => 'After support', 'general' => 'General'];

    /** Feedback scores at or below this open a recovery follow-up. */
    public const LOW_SCORE = 6;

    protected $fillable = [
        'company_id', 'user_id', 'assigned_to', 'created_by', 'type', 'status', 'title', 'notes',
        'due_at', 'done_at', 'outcome', 'score', 'token', 'details',
    ];

    protected $casts = ['due_at' => 'datetime', 'done_at' => 'datetime', 'score' => 'integer', 'details' => 'array'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScope('customers');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Things still to do (not contact links or feedback waiting on the customer). */
    public function scopeTodo(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', ['contact', 'feedback']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->todo()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->todo()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, ['open', 'scheduled'], true);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_open && $this->due_at && $this->due_at->isPast();
    }

    public function detail(string $key, $default = null)
    {
        return data_get($this->details, $key, $default);
    }

    /** Feedback band: promoter (9-10), passive (7-8), detractor (0-6). */
    public function getBandAttribute(): ?string
    {
        if ($this->score === null) {
            return null;
        }

        return $this->score >= 9 ? 'promoter' : ($this->score >= 7 ? 'passive' : 'detractor');
    }
}
