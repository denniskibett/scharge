<?php

namespace App\Modules\SMS\Models;

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

    /** True when the signed-in person may change or delete it: their own company's template, or any for a sysadmin. */
    public function editableByUser(): bool
    {
        $user = auth()->user();
        if (! $user || $user->hasRole('sysadmin')) {
            return true;
        }

        return $user->company_id && $this->created_by
            && (int) \Illuminate\Support\Facades\DB::table('users')->where('id', $this->created_by)->value('company_id') === (int) $user->company_id;
    }
    protected $fillable = ['name', 'content', 'placeholders', 'created_by'];

    protected $casts = [
        'placeholders' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}