<?php

namespace App\Modules\CRM\Mail;

use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * A customer uploaded their tenant list from the dashboard for us to load: their account manager gets the file
 * and the steps (OnboardingGuideController::tenantList). The task itself arrives as a calendar invitation.
 */
class TenantListUploaded extends Mailable
{
    public function __construct(public CrmFollowUp $task, public User $by)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'scharge CRM'),
            subject: 'Tenant list to load: '.($this->task->company?->name ?? 'a customer'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm-tenant-list', text: 'emails.crm-tenant-list-text');
    }

    public function attachments(): array
    {
        $path = (string) $this->task->detail('file');

        return $path && Storage::disk('local')->exists($path) && Storage::disk('local')->size($path) <= 10 * 1024 * 1024
            ? [Attachment::fromStorageDisk('local', $path)->as($this->task->detail('file_name') ?: basename($path))] : [];
    }
}
