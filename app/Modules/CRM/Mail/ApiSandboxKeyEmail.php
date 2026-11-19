<?php

namespace App\Modules\CRM\Mail;

use App\Models\Company;
use App\Modules\CRM\Support\Playbook;
use App\Modules\Users\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A sandbox API key (shown once, in this email only) plus the partner pack PDF: white label, prices,
 * revenue share. From sales@sharet.africa; replies go to the signing manager and sales@. Sent by crm:api-sandbox-key.
 */
class ApiSandboxKeyEmail extends Mailable
{
    public function __construct(public Company $company, public string $key, public string $pdfPath, public string $ref, public ?User $manager = null, public ?string $firstName = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(Playbook::SALES_FROM, ($this->manager?->name ? $this->manager->name.' · ' : '').'scharge'),
            replyTo: Playbook::replyTo($this->manager, false),
            subject: 'Your scharge sandbox API key, white label and partner terms ('.$this->ref.')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-api-sandbox', text: 'emails.crm-api-sandbox-text');
    }

    public function attachments(): array
    {
        return [Attachment::fromPath($this->pdfPath)->as('scharge-partner-pack-'.$this->ref.'.pdf')->withMime('application/pdf')];
    }
}
