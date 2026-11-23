<?php

namespace App\Modules\CRM\Mail;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\DemoGuide;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the account manager right after the demo notice: how to handle this request, what to show, and the
 * steps to onboard them (Support\DemoGuide). "Ink" design, like the notice.
 */
class DemoHandlingGuide extends Mailable
{
    public function __construct(public CrmFollowUp $demo)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'scharge CRM'),
            subject: 'How to handle '.$this->demo->detail('ref').': '.$this->demo->company?->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-demo-guide', text: 'emails.crm-demo-guide-text', with: ['guide' => DemoGuide::for($this->demo)]);
    }
}
