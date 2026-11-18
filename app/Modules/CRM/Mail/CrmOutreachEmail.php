<?php

namespace App\Modules\CRM\Mail;

use App\Models\Company;
use App\Modules\Users\Models\User;
use App\Modules\CRM\Support\Playbook;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** An account manager's email in the scharge "Ink" design: from sales@sharet.africa (onboarding@ once the prospect is onboarding), replies go to the manager. */
class CrmOutreachEmail extends Mailable
{
    public function __construct(public string $subjectLine, public string $body, public User $manager, public ?Company $company = null, public ?string $track = null)
    {
    }

    public function envelope(): Envelope
    {
        // Replies reach the manager's login inbox and work copy (and onboarding@ once the prospect is onboarding).
        $replyTo = Playbook::replyTo($this->manager, Playbook::onboarding($this->company));

        return new Envelope(
            from: new Address(Playbook::fromFor($this->company), $this->manager->name.' · scharge'),
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
