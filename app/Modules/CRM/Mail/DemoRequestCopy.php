<?php

namespace App\Modules\CRM\Mail;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\Playbook;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The prospect's own copy of their demo request, with the PDF attached; replies go to their account manager. */
class DemoRequestCopy extends Mailable
{
    public function __construct(public CrmFollowUp $demo)
    {
    }

    public function envelope(): Envelope
    {
        $am = $this->demo->assignee;
        $replyTo = $am?->email && filter_var($am->email, FILTER_VALIDATE_EMAIL) ? [new Address($am->email, $am->name)] : [];

        return new Envelope(
            from: new Address(Playbook::SALES_FROM, ($am?->name ? $am->name.' · ' : '').'scharge'),
            replyTo: $replyTo,
            subject: 'Your scharge demo request '.$this->demo->detail('ref'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-demo-copy', text: 'emails.crm-demo-copy-text');
    }

    public function attachments(): array
    {
        $path = $this->demo->detail('pdf');

        return $path ? [Attachment::fromStorageDisk('local', $path)->as('scharge-demo-request-'.$this->demo->detail('ref').'.pdf')->withMime('application/pdf')] : [];
    }
}
