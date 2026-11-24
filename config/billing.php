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

    // New accounts' first bill (App\Support\FirstBill): "pay one month, get three months". Only companies created on or
    // after first_bill_from get it; staff set each company's price per unit first, so no amount is ever guessed.
    'first_bill_from' => env('BILLING_FIRST_BILL_FROM', '2026-11-23'),
    'first_bill_due_days' => (int) env('BILLING_FIRST_BILL_DUE_DAYS', 7),
    'first_bill_months' => (int) env('BILLING_FIRST_BILL_MONTHS', 3),

    // Vacant units (App\Support\UnitPricing): months as a client => % off the price per unit. Units in use pay in full.
    // Owner, 24 Nov 2026: 0% for the first 6 months, 25% off after 6 months, 50% off after the first year.
    'vacant_discounts' => [6 => 25, 12 => 50],

    // How companies pay scharge, shown on the Billing page to the owner and accountant.
    'paybill' => env('SCHARGE_PAYBILL'),
    'account_hint' => env('SCHARGE_PAYBILL_ACCOUNT_HINT', 'your invoice number'),
    'contact_email' => env('SCHARGE_BILLING_EMAIL', 'sales@sharet.africa'),
    'contact_phone' => env('SCHARGE_BILLING_PHONE'),

    // VAT on scharge invoices. Invoices are non-VAT unless a sysadmin switches one to "VAT exclusive" (16% added on top).
    'stk_number' => env('SCHARGE_STK_NUMBER'),        // Sharet Pay STK push number shown under every invoice
    'sharet_handle' => env('SCHARGE_SHARET_HANDLE'),  // scharge's Sharet Pay @handle for Sharet to Sharet payments

    // Show Sharet's pay page (Sharet to Sharet sign-in + PIN) inside the scharge pay modal. Needs Sharet to allow
    // scharge.sharet.africa in its frame-ancestors (sharetafricanew: security.frame_ancestors); until then it opens a tab.
    'sharet_embed' => (bool) env('SCHARGE_SHARET_EMBED', false),

    'vat_rate' => (float) env('SCHARGE_VAT_RATE', 16),
    'kra_pin' => env('SCHARGE_KRA_PIN'),          // shown on every invoice when set
    'vat_number' => env('SCHARGE_VAT_NUMBER'),    // shown on VAT invoices when set
];
