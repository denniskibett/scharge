<?php

namespace App\Modules\CRM\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 07:30 CRM recap: the whole team's day for sysadmins, or one account manager's own day and today's calls. */
class CrmDailyRecap extends Mailable
{
    public function __construct(public string $subjectLine, public array $data)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address(config('mail.from.address'), 'scharge CRM'), subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-daily-recap', with: $this->data);
    }
}
