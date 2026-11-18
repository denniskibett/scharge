<?php

namespace App\Modules\CRM\Mail;

use App\Modules\Users\Models\User;
use App\Modules\CRM\Support\Playbook;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A plain, personal cold email from an account manager: sent by scharge, replies go to the manager. */
class CrmOutreachEmail extends Mailable
{
    public function __construct(public string $subjectLine, public string $body, public User $manager)
    {
    }

    public function envelope(): Envelope
    {
        $replyTo = filter_var($this->manager->email, FILTER_VALIDATE_EMAIL) ? [new Address($this->manager->email, $this->manager->name)] : [];

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->manager->name.' · scharge'),
            replyTo: $replyTo,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.crm-outreach',
            text: 'emails.crm-outreach-text',
            with: ['body' => $this->body, 'optOut' => Playbook::OPT_OUT],
        );
    }
}
