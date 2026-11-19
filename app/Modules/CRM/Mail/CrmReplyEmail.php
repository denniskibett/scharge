<?php

namespace App\Modules\CRM\Mail;

use App\Models\Company;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\Playbook;
use App\Modules\Users\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** An account manager's answer to a prospect's reply: plain conversational email in the same thread. */
class CrmReplyEmail extends Mailable
{
    /** @param string[] $thread message-ids this answers (without <>) */
    public function __construct(public string $subjectLine, public string $body, public User $manager, public Company $company, public array $thread = [])
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(Playbook::fromFor($this->company), $this->manager->name.' · scharge'),
            replyTo: Playbook::replyTo($this->manager, Playbook::onboarding($this->company)),
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        $last = end($this->thread);

        return new Headers(references: $this->thread, text: $last ? ['In-Reply-To' => '<'.$last.'>'] : []);
    }

    public function content(): Content
    {
        $signature = '<p style="margin:16px 0 0">'.e($this->manager->name).'<br>scharge · <a href="https://scharge.sharet.africa">scharge.sharet.africa</a></p>';

        return new Content(htmlString: '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
            .EmailBody::forEmail($this->body).$signature.'</div>');
    }
}
