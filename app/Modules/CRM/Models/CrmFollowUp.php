<?php

namespace App\Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowUp extends Model
{
    public const TYPES = ['call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting', 'task' => 'Task'];

    protected $fillable = [
        'crm_company_id', 'user_id', 'assigned_to', 'created_by', 'demo_request_id',
        'type', 'title', 'notes', 'due_at', 'completed_at', 'outcome',
    ];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function demoRequest(): BelongsTo
    {
        return $this->belongsTo(CrmDemoRequest::class, 'demo_request_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->where('due_at', '<', now());
    }

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->completed_at && $this->due_at->isPast();
    }
}
