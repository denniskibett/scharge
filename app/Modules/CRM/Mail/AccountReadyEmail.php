<?php

namespace App\Modules\CRM\Mail;

use App\Models\Company;
use App\Modules\Users\Models\User;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Storage;

/** "Your scharge account is ready": the account manager's email after a demo, with the onboarding to-do PDF attached. */
class AccountReadyEmail extends CrmOutreachEmail
{
    public function __construct(string $subjectLine, string $body, User $manager, ?Company $company = null, ?string $track = null, array $thread = [], public ?string $pdf = null)
    {
        parent::__construct($subjectLine, $body, $manager, $company, $track, $thread);
    }

    public function attachments(): array
    {
        if (! $this->pdf || ! Storage::disk('local')->exists($this->pdf)) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->pdf)->as('scharge-onboarding-to-do.pdf')->withMime('application/pdf')];
    }
}
