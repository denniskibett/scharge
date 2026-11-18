<?php

namespace App\Modules\CRM\Mail;

use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/** A website form was sent: tells its owner, with the submission PDF attached ("Ink" design, like DemoRequestNotice). */
class FormSubmissionNotice extends Mailable
{
    public function __construct(public CrmFollowUp $submission)
    {
    }

    public function envelope(): Envelope
    {
        $contact = $this->submission->contact;
        $replyTo = $contact?->email && filter_var($contact->email, FILTER_VALIDATE_EMAIL) ? [new Address($contact->email, $contact->name)] : [];
        $who = $this->submission->company?->name ?? $contact?->name ?? 'website visitor';

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->submission->detail('form.from_name', 'scharge CRM')),
            replyTo: $replyTo,
            subject: $this->submission->detail('form.name').' '.$this->submission->detail('ref').': '.$who,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-form-notice', text: 'emails.crm-form-notice-text');
    }

    public function attachments(): array
    {
        $path = $this->submission->detail('pdf');

        return $path && Storage::disk('local')->exists($path) ? [Attachment::fromStorageDisk('local', $path)->as(basename($path))->withMime('application/pdf')] : [];
    }
}
