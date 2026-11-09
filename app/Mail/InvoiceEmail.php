<?php

namespace App\Mail;

use App\Modules\Payments\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Bill emails to a tenant, one thread per invoice: "shared" (your bill, with the pay link) and "paid" (receipt).
 * Every email for an invoice has the same subject and points at the same thread root
 * (<invoice-{id}@host> in In-Reply-To/References), so Gmail and Outlook keep them in one conversation.
 */
class InvoiceEmail extends Mailable
{
    use Queueable;

    /** @param 'shared'|'paid' $kind */
    public function __construct(
        public Invoice $invoice,
        public string $kind,
        public ?string $payUrl = null,
        public ?float $amount = null,
        public ?string $reference = null,
    ) {
    }

    public static function subjectFor(Invoice $invoice): string
    {
        $month = $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : null;
        $unit = $invoice->tenancy?->unit?->unit_number;

        return implode(' · ', array_filter(['Invoice #'.($invoice->invoice_number ?? $invoice->id), $month, $unit ? 'Unit '.$unit : null]));
    }

    public static function threadId(Invoice $invoice): string
    {
        return 'invoice-'.$invoice->id.'@'.self::host();
    }

    private static function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'scharge.sharet.africa';
    }

    public function envelope(): Envelope
    {
        $company = ($id = $this->invoice->owningCompanyId()) ? \App\Models\Company::find($id) : null;
        $from = config('mail.from.address');
        $name = $company?->name ? $company->name.' via scharge' : 'scharge';
        $replyTo = $company?->email && filter_var($company->email, FILTER_VALIDATE_EMAIL) ? [new Address($company->email, $company->name)] : [];

        return new Envelope(from: new Address($from, $name), replyTo: $replyTo, subject: self::subjectFor($this->invoice));
    }

    public function headers(): Headers
    {
        $root = self::threadId($this->invoice);

        return new Headers(
            messageId: 'invoice-'.$this->invoice->id.'-'.$this->kind.'-'.now()->format('YmdHisv').'@'.self::host(),
            references: [$root],
            text: ['In-Reply-To' => '<'.$root.'>', 'X-Entity-Ref-ID' => 'invoice-'.$this->invoice->id],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice', text: 'emails.invoice-text');
    }
}
