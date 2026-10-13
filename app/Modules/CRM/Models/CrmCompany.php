<?php

namespace App\Modules\CRM\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A prospect company. Paying customers live in `companies`; `company_id` links the two once won. */
class CrmCompany extends Model
{
    use SoftDeletes;

    public const STAGES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'demo_booked' => 'Demo booked',
        'proposal' => 'Proposal',
        'negotiation' => 'Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
        'nurture' => 'Nurture',
    ];

    public const OPEN_STAGES = ['new', 'contacted', 'qualified', 'demo_booked', 'proposal', 'negotiation'];

    protected $fillable = [
        'name', 'name_key', 'domain', 'tier', 'business_type', 'portfolio', 'scale_signal', 'opportunity',
        'target_role', 'route', 'phone', 'phone_alt', 'email', 'website', 'linkedin', 'decision_maker',
        'current_system', 'estimated_units', 'country_id', 'stage', 'owner_id', 'next_action',
        'next_action_at', 'last_contacted_at', 'lost_reason', 'company_id', 'source', 'notes',
        'needs_verification',
    ];

    protected $casts = [
        'tier' => 'integer',
        'estimated_units' => 'integer',
        'next_action_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'needs_verification' => 'boolean',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_company_user')
            ->withPivot(['job_title', 'is_primary'])
            ->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->latest('occurred_at')->latest('id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class);
    }

    public function openFollowUps(): HasMany
    {
        return $this->followUps()->whereNull('completed_at')->orderBy('due_at');
    }

    public function demoRequests(): HasMany
    {
        return $this->hasMany(CrmDemoRequest::class)->latest();
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(CrmFeedback::class)->latest('requested_at');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('stage', self::OPEN_STAGES);
    }

    /** Open prospects with nobody chasing them: no open follow-up at all. */
    public function scopeUnattended(Builder $query): Builder
    {
        return $query->open()->whereDoesntHave('followUps', fn ($q) => $q->whereNull('completed_at'));
    }

    public function getStageLabelAttribute(): string
    {
        return self::STAGES[$this->stage] ?? ucfirst($this->stage);
    }

    public function getPrimaryContactAttribute(): ?User
    {
        return $this->contacts->sortByDesc(fn ($c) => $c->pivot->is_primary)->first();
    }
}
