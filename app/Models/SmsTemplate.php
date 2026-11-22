<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    /**
     * A company sees the templates its own people wrote plus the scharge ones (written by scharge, or with no author);
     * the platform sysadmin, jobs and webhooks see all. Same rule as SmsCampaign.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (\Illuminate\Database\Eloquent\Builder $q) {
            $user = auth()->user();
            if (! $user || $user->hasRole('sysadmin')) {
                return;
            }
            $q->where(fn ($w) => $w->whereNull('sms_templates.created_by')
                ->orWhereIn('sms_templates.created_by', fn ($s) => $s->select('id')->from('users')
                    ->where(fn ($u) => $u->where('company_id', $user->company_id ?? 0)->orWhereNull('company_id'))));
        });
    }
}
