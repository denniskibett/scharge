<?php

namespace App\Modules\CRM\Mail;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A copy of a prospect's reply for the team (sysadmins and the account manager). Hitting reply answers the prospect. */
class CrmReplyCopy extends Mailable
{
    public function __construct(public Company $company, public CrmFollowUp $reply)
    {
    }

    public function envelope(): Envelope
    {
        $from = (string) $this->reply->detail('from');
        $name = (string) ($this->reply->detail('from_name') ?: $from);

        return new Envelope(
            from: new Address(config('mail.from.address'), 'scharge CRM'),
            replyTo: filter_var($from, FILTER_VALIDATE_EMAIL) ? [new Address($from, $name)] : [],
            subject: 'Reply from '.$name.' ('.$this->company->name.'): '.($this->reply->detail('subject') ?: '(no subject)'),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.crm-reply-copy-text');
    }
}
