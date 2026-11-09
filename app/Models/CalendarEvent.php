<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An internal meeting or reminder on /calendar. See database/schema/2026_11_09_calendar_events.sql. */
class CalendarEvent extends Model
{
    protected $table = 'calendar_events';

    protected $fillable = ['company_id', 'title', 'description', 'location', 'kind', 'visibility', 'starts_at', 'ends_at', 'all_day', 'created_by'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean'];

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'calendar_event_attendees', 'calendar_event_id', 'user_id')->withPivot('status')->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Events this person may see: their team's company-wide ones, ones they made, ones they're invited to. */
    public function scopeVisibleTo($query, $user)
    {
        $staff = $user->hasAnyRole(['sysadmin', 'account_manager']);

        return $query->where(fn ($q) => $q
            ->where('created_by', $user->id)
            ->orWhereHas('attendees', fn ($a) => $a->where('users.id', $user->id))
            ->orWhere(fn ($w) => $w->where('visibility', 'company')->where('company_id', $user->company_id)->whereNotNull('company_id'))
            ->orWhere(fn ($w) => $staff ? $w->where('visibility', 'staff')->whereNull('company_id') : $w->whereRaw('1 = 0')));
    }
}
