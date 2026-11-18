<?php

namespace App\Modules\CRM\Mail;

use App\Models\Company;
use App\Modules\Users\Models\User;
use App\Modules\CRM\Support\Playbook;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A cold email from an account manager in the scharge "Ink" design: sent from sales@sharet.africa, replies go to the manager. */
class CrmOutreachEmail extends Mailable
{
    public function __construct(public string $subjectLine, public string $body, public User $manager, public ?Company $company = null)
    {
    }

    public function envelope(): Envelope
    {
        $replyTo = filter_var($this->manager->email, FILTER_VALIDATE_EMAIL) ? [new Address($this->manager->email, $this->manager->name)] : [];

        return new Envelope(
            from: new Address(Playbook::SALES_FROM, $this->manager->name.' · scharge'),
            replyTo: $replyTo,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.crm-outreach',
            text: 'emails.crm-outreach-text',
            with: [
                'optOut' => Playbook::OPT_OUT,
                'book' => $this->company ? Playbook::for($this->company) : [],
                'companyName' => $this->company ? preg_replace('/\s*\([^()]*\)\s*$/', '', $this->company->name) : 'your company',
            ],
        );
    }
}
