<?php

/*
 * scharge's own fee: the billing lock (App\Support\BillingLock, docs/BILLING_LOCK.md). A company with a scharge invoice
 * (subscription_invoices: pending or failed) past its due date is locked for everyone except scharge staff, tenants
 * and the Billing page, until it is paid. Off until BILLING_LOCK_ENABLED=true.
 */
return [
    'lock_enabled' => (bool) env('BILLING_LOCK_ENABLED', false),

    // Invoices due before this date never lock (old test or migrated invoices). Empty = every invoice counts.
    'lock_from' => env('BILLING_LOCK_FROM'),

    // How companies pay scharge, shown on the Billing page to the owner and accountant.
    'paybill' => env('SCHARGE_PAYBILL'),
    'account_hint' => env('SCHARGE_PAYBILL_ACCOUNT_HINT', 'your invoice number'),
    'contact_email' => env('SCHARGE_BILLING_EMAIL', 'sales@sharet.africa'),
    'contact_phone' => env('SCHARGE_BILLING_PHONE'),

    // VAT on scharge invoices. Invoices are non-VAT unless a sysadmin switches one to "VAT exclusive" (16% added on top).
    'stk_number' => env('SCHARGE_STK_NUMBER'),        // Sharet Pay STK push number shown under every invoice
    'sharet_handle' => env('SCHARGE_SHARET_HANDLE'),  // scharge's Sharet Pay @handle for Sharet to Sharet payments

    'vat_rate' => (float) env('SCHARGE_VAT_RATE', 16),
    'kra_pin' => env('SCHARGE_KRA_PIN'),          // shown on every invoice when set
    'vat_number' => env('SCHARGE_VAT_NUMBER'),    // shown on VAT invoices when set
];
