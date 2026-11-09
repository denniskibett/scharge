<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The one-time code that confirms someone's email (App\Services\EmailOtp). Sent straight away, not queued. */
class EmailOtpCode extends Mailable
{
    use Queueable;

    public function __construct(public $user, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your scharge code");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp', text: 'emails.otp-text');
    }
}
