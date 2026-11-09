<?php

namespace App\Services;

use App\Mail\InvoiceEmail;
use App\Modules\Payments\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the tenant's bill emails (App\Mail\InvoiceEmail), after the response so a slow mail server never
 * holds up a payment or a webhook. Only to a confirmed email (KYC code), never to test/fake addresses.
 */
class InvoiceMail
{
    /** The bill was shared with the tenant (pay link sent). */
    public static function shared(Invoice $invoice, string $payUrl, ?float $amount = null): void
    {
        self::later($invoice, 'shared', $payUrl, $amount);
    }

    /** The invoice just became fully paid. Once per invoice. */
    public static function paid(Invoice $invoice): void
    {
        if ((float) $invoice->total_amount <= 0) {
            return; // nothing was charged
        }
        // Only for a payment that just happened, not when old invoices are recalculated in bulk.
        $last = $invoice->payments()->where('status', 'completed')->latest('id')->first();
        if (! $last || $last->created_at?->lt(now()->subMinutes(30))) {
            return;
        }
        if (! Cache::add('invoice-mail:'.$invoice->id.':paid', 1, now()->addYear())) {
            return; // already sent
        }
        self::later($invoice, 'paid', null, (float) $invoice->total_amount, $last->transaction_reference ?: $last->external_reference);
    }

    public static function recipient(Invoice $invoice): ?string
    {
        $user = $invoice->tenancy?->tenant?->user;
        if (! $user || blank($user->email) || ! EmailOtp::mailWorks() || EmailOtp::needs($user)) {
            return null; // no address, mail off, or the email isn't confirmed yet
        }

        return filter_var($user->email, FILTER_VALIDATE_EMAIL) ? $user->email : null;
    }

    private static function later(Invoice $invoice, string $kind, ?string $url, ?float $amount, ?string $reference = null): void
    {
        $id = $invoice->id;
        dispatch(function () use ($id, $kind, $url, $amount, $reference) {
            $invoice = Invoice::with(['tenancy.tenant.user', 'tenancy.unit.property', 'items'])->find($id);
            $to = $invoice ? self::recipient($invoice) : null;
            if (! $to) {
                return;
            }
            try {
                Mail::to($to)->send(new InvoiceEmail($invoice, $kind, $url, $amount, $reference));
            } catch (\Throwable $e) {
                Log::warning('Invoice email failed', ['invoice_id' => $id, 'kind' => $kind, 'error' => $e->getMessage()]);
            }
        })->afterResponse();
    }
}
