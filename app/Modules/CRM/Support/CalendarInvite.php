<?php

namespace App\Modules\CRM\Support;

use App\Modules\CRM\Mail\FollowUpInvite;
use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Every dated CRM task reaches the account manager's own calendar (Gmail, Outlook, Apple) by itself: a calendar
 * invitation is emailed to them when the task is given a time, changes time or owner, and a cancellation when it
 * is cancelled or taken away. Nothing to connect: mail programs add invitations addressed to you to your calendar.
 *
 * What was last sent is kept on the task (details.invite: seq, key, to, cancelled), so nothing is sent twice.
 * Switch: config('crm.calendar_invites') / CRM_CALENDAR_INVITES.
 */
class CalendarInvite
{
    /** Minutes blocked on the calendar, by task type. */
    private const MINUTES = ['demo' => 45, 'meeting' => 60];

    private static int $quiet = 0;

    /** Create or change tasks without the automatic invitation (the caller sends its own, e.g. on the demo notice). */
    public static function quietly(callable $callback): mixed
    {
        self::$quiet++;
        try {
            return $callback();
        } finally {
            self::$quiet--;
        }
    }

    /** Called after every save of a task (CrmFollowUp::booted). Never lets a mail problem break the save. */
    public static function saved(CrmFollowUp $task): void
    {
        if (self::$quiet > 0 || ! config('crm.calendar_invites', true)) {
            return;
        }
        try {
            self::sync($task);
        } catch (\Throwable $e) {
            Log::warning('Calendar invite failed', ['follow_up' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    /** Requests that come in from the website: always worth a place on the calendar. */
    public const INBOUND = ['demo', 'viewing', 'meeting'];

    /**
     * True when this task belongs on someone's own calendar: a to-do with a time and an owner who has an email,
     * that a person made, a prospect asked for, or that is about a customer's onboarding (Support\Onboarding). The "follow up on the cold email" calls a campaign creates by the
     * hundred (created_by empty) stay in the CRM only: they would bury a Gmail calendar.
     */
    public static function eligible(CrmFollowUp $task): bool
    {
        return $task->due_at !== null && $task->assigned_to
            && ! in_array($task->type, array_merge(CrmFollowUp::NOT_TODO, ['import']), true)
            && ($task->created_by !== null || in_array($task->type, self::INBOUND, true) || $task->detail('onboarding'))
            && self::recipients($task) !== [];
    }

    /** @return array<int,string> */
    public static function recipients(CrmFollowUp $task): array
    {
        return $task->assignee?->mailboxes() ?? [];
    }

    /** Sends what is needed to bring the owner's calendar in line with the task; returns what it sent. */
    public static function sync(CrmFollowUp $task): ?string
    {
        $task->unsetRelation('assignee');   // the owner may just have changed
        $task->loadMissing('assignee', 'company', 'contact');
        $last = (array) $task->detail('invite', []);
        $live = ($last['key'] ?? null) && empty($last['cancelled']);
        $wanted = self::eligible($task) && in_array($task->status, ['open', 'scheduled'], true) && $task->due_at->isFuture();
        $to = self::recipients($task);
        $key = md5($task->due_at?->toIso8601String().'|'.$task->assigned_to.'|'.$task->title);

        if ($wanted) {
            if ($live && $last['key'] === $key) {
                return null;   // their calendar already has exactly this
            }
            $seq = (int) ($last['seq'] ?? -1) + 1;
            $gone = $live ? array_values(array_diff((array) ($last['to'] ?? []), $to)) : [];
            if ($gone) {   // moved to another account manager: take it off the previous one's calendar
                Mail::to($gone)->send(new FollowUpInvite($task, 'CANCEL', $seq, $gone));
            }
            Mail::to($to)->send(new FollowUpInvite($task, 'REQUEST', $seq, $to));
            self::remember($task, ['seq' => $seq, 'key' => $key, 'to' => $to, 'cancelled' => false]);

            return 'REQUEST';
        }

        // No longer on a calendar: cancelled, a no-show, unassigned or without a time. A finished task stays as history.
        if ($live && $task->status !== 'done' && ($last['to'] ?? [])) {
            $seq = (int) ($last['seq'] ?? 0) + 1;
            Mail::to($last['to'])->send(new FollowUpInvite($task, 'CANCEL', $seq, (array) $last['to']));
            self::remember($task, ['seq' => $seq, 'key' => $last['key'], 'to' => $last['to'], 'cancelled' => true]);

            return 'CANCEL';
        }

        return null;
    }

    /** Records an invitation the caller sent itself (the demo notice carries the first one). */
    public static function sent(CrmFollowUp $task): void
    {
        $task->loadMissing('assignee');
        self::remember($task, ['seq' => 0, 'key' => md5($task->due_at?->toIso8601String().'|'.$task->assigned_to.'|'.$task->title),
            'to' => self::recipients($task), 'cancelled' => false]);
    }

    private static function remember(CrmFollowUp $task, array $invite): void
    {
        $task->forceFill(['details' => array_merge((array) $task->details, ['invite' => $invite])])->saveQuietly();
    }

    /** The calendar file (RFC 5545). $method: REQUEST adds or updates the event, CANCEL removes it. */
    public static function ics(CrmFollowUp $task, string $method = 'REQUEST', int $sequence = 0, ?array $attendees = null): string
    {
        $task->loadMissing('assignee', 'company', 'contact');
        $start = ($task->due_at ?? now())->copy()->utc();
        $end = $start->copy()->addMinutes(self::MINUTES[$task->type] ?? 30);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'scharge';
        $stamp = fn ($t) => $t->format('Ymd\THis\Z');
        $contact = $task->contact;
        $link = $task->company_id ? url('/crm/companies/'.$task->company_id) : url('/crm/requests');
        $body = array_filter([
            $contact ? trim($contact->name.' · '.$contact->phone.($contact->email ? ' · '.$contact->email : ''), ' ·') : null,
            $task->notes ? 'Notes: '.$task->notes : null,
            'Open in the CRM: '.$link,
        ]);

        $lines = [
            'BEGIN:VCALENDAR', 'PRODID:-//scharge//CRM//EN', 'VERSION:2.0', 'CALSCALE:GREGORIAN', 'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:crm-task-'.$task->id.'@'.$host,
            'SEQUENCE:'.$sequence,
            'DTSTAMP:'.$stamp(now()->utc()),
            'DTSTART:'.$stamp($start),
            'DTEND:'.$stamp($end),
            'SUMMARY:'.self::text(self::summary($task)),
            'DESCRIPTION:'.self::text(implode("\n", $body)),
            'URL:'.$link,
            'ORGANIZER;CN=scharge CRM:mailto:'.config('mail.from.address'),
        ];
        foreach ($attendees ?? self::recipients($task) as $email) {
            $lines[] = 'ATTENDEE;CN='.self::text((string) $task->assignee?->name).';ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:'.$email;
        }
        $lines[] = 'STATUS:'.($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED');
        if ($method !== 'CANCEL') {
            array_push($lines, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:'.self::text(self::summary($task)), 'TRIGGER:-PT15M', 'END:VALARM');
        }
        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    /** "Demo: Grey Oak Ltd" / "Call: Grey Oak Ltd". */
    public static function summary(CrmFollowUp $task): string
    {
        return ucfirst(str_replace('_', ' ', (string) $task->type)).': '.($task->company?->name ?? $task->title ?? 'Lead');
    }

    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', ''], $value);
    }

    /** Lines longer than 75 bytes continue on the next line after a space. */
    private static function fold(string $line): string
    {
        return strlen($line) <= 75 ? $line : implode("\r\n ", mb_str_split($line, 60));
    }
}
