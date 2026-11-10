<?php

namespace App\Support;

/**
 * Public "view and pay this bill" links: /public/invoice/{id}?t={token}. The token is an HMAC of the invoice id with
 * APP_KEY, so a link cannot be guessed by stepping through ids, and it never expires (tenants keep old SMS).
 * Every link to the public page must come from url(); PublicInvoiceController refuses a missing or wrong token.
 */
class InvoiceLink
{
    public static function token(int|string $invoiceId): string
    {
        return substr(hash_hmac('sha256', 'public-invoice:'.(int) $invoiceId, (string) config('app.key')), 0, 32);
    }

    public static function valid(int|string $invoiceId, ?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token($invoiceId), $token);
    }

    /** Absolute link to the public bill page. */
    public static function url($invoice): string
    {
        $id = is_object($invoice) ? $invoice->getKey() : (int) $invoice;
        $path = route('public.invoice.show', ['invoice' => $id, 't' => self::token($id)], false);

        return rtrim(self::baseFor($invoice) ?? url('/'), '/').$path;
    }

    /** The company's own address (white label) when it has one, so tenants see the landlord's domain. */
    private static function baseFor($invoice): ?string
    {
        $model = is_object($invoice) ? $invoice : CompanyAccess::bypass(fn () => \App\Models\Invoice::find((int) $invoice));

        return $model ? Brand::forCompany($model->owningCompanyId())?->baseUrl() : null;
    }
}
