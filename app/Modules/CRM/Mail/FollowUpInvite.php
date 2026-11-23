<?php

namespace App\Modules\CRM\Mail;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CalendarInvite;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A CRM task as a calendar invitation for its account manager: Gmail, Outlook and Apple Mail add it to the
 * calendar (REQUEST) or take it off (CANCEL). Sent by Support\CalendarInvite; nothing for them to connect.
 */
class FollowUpInvite extends Mailable
{
    /** @param array<int,string> $attendees */
    public function __construct(public CrmFollowUp $task, public string $method = 'REQUEST', public int $sequence = 0, public array $attendees = [])
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'scharge CRM'),
            subject: ($this->method === 'CANCEL' ? 'Cancelled: ' : ($this->sequence > 0 ? 'Updated: ' : '')).CalendarInvite::summary($this->task)
                .' · '.$this->task->due_at?->timezone('Africa/Nairobi')->format('D j M, H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.crm-task-invite-text');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => CalendarInvite::ics($this->task, $this->method, $this->sequence, $this->attendees), 'invite.ics')
            ->withMime('text/calendar; charset=UTF-8; method='.$this->method)];
    }
}
