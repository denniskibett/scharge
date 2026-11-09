<?php

namespace App\Mail;

use App\Modules\Security\Models\GatePass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Gate pass emails, in the same "Ink" design as the bill emails:
 *   pass     to the guest: the entry code, the window, the car, the link to check out from the phone
 *   arrived  to the host: their guest checked in
 *   left     to the host: their guest checked out
 */
class GatePassEmail extends Mailable
{
    use Queueable;

    /** @param 'pass'|'arrived'|'left' $kind */
    public function __construct(public GatePass $pass, public string $kind)
    {
    }

    public function envelope(): Envelope
    {
        $company = $this->pass->company;
        $name = $company?->name ? $company->name.' via scharge' : 'scharge';
        $replyTo = $this->kind === 'pass' && $company?->email && filter_var($company->email, FILTER_VALIDATE_EMAIL)
            ? [new Address($company->email, $company->name)] : [];
        $place = $this->pass->placeLabel();
        $subject = match ($this->kind) {
            'arrived' => $this->pass->guest_name.' checked in · '.$place,
            'left' => $this->pass->guest_name.' checked out · '.$place,
            default => 'Your gate code '.$this->pass->code.' · '.$place,
        };

        return new Envelope(from: new Address(config('mail.from.address'), $name), replyTo: $replyTo, subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.gate-pass', text: 'emails.gate-pass-text');
    }
}
