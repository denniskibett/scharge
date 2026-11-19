<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** A reply or follow-up written with the Claude assistant and sent by a person (plain text, kept in the thread). */
class AssistantEmail extends Mailable
{
    public function __construct(public string $subjectLine, public string $text, public ?string $inReplyTo = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function headers(): Headers
    {
        $id = $this->inReplyTo ? trim($this->inReplyTo, '<> ') : null;

        return new Headers(references: $id ? [$id] : [], text: $id ? ['In-Reply-To' => '<'.$id.'>'] : []);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.55;color:#111">'
            .nl2br(e($this->text)).'</div>', text: null);
    }
}
