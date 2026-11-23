<?php

namespace App\Modules\CRM\Mail;

use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the account manager a prospect filled in the demo form; the submission PDF is attached ("Ink" design). */
class DemoRequestNotice extends Mailable
{
    public function __construct(public CrmFollowUp $demo)
    {
    }

    public function envelope(): Envelope
    {
        $contact = $this->demo->contact;
        $replyTo = $contact?->email && filter_var($contact->email, FILTER_VALIDATE_EMAIL) ? [new Address($contact->email, $contact->name)] : [];

        return new Envelope(
            from: new Address(config('mail.from.address'), 'scharge CRM'),
            replyTo: $replyTo,
            subject: 'Demo request '.$this->demo->detail('ref').': '.$this->demo->company?->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-demo-notice', text: 'emails.crm-demo-notice-text');
    }

    public function attachments(): array
    {
        $path = $this->demo->detail('pdf');
        $files = $path ? [Attachment::fromStorageDisk('local', $path)->as(basename($path))->withMime('application/pdf')] : [];
        // The call itself, as a calendar invitation: it lands on the account manager's Gmail calendar with no step from them.
        if (config('crm.calendar_invites', true) && \App\Modules\CRM\Support\CalendarInvite::eligible($this->demo)) {
            $files[] = Attachment::fromData(fn () => \App\Modules\CRM\Support\CalendarInvite::ics($this->demo), 'invite.ics')
                ->withMime('text/calendar; charset=UTF-8; method=REQUEST');
        }

        return $files;
    }
}
