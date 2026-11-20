<?php

/*
 * Currency and conversion (docs/CURRENCY.md).
 *
 * Every amount is stored in the currency it was created in and is never rewritten. Tables with a `currency`
 * column (subscription_invoices, billing_payments) say which; every other amount (rent, invoices, payments,
 * wallets, the ledger) is in the book currency below. Changing the platform or a company's display currency only
 * changes how totals are shown, converted at Sharet's FX rate for the day.
 *
 * The list mirrors Sharet's config/fx.php: fiat only, ISO 4217.
 */
return [
    // The currency of every stored amount that has no currency column of its own. Never change this on a live system.
    'book' => 'KES',

    'currencies' => [
        'KES' => ['name' => 'Kenyan shilling', 'symbol' => 'KSh', 'decimals' => 2],
        'UGX' => ['name' => 'Ugandan shilling', 'symbol' => 'USh', 'decimals' => 0],
        'TZS' => ['name' => 'Tanzanian shilling', 'symbol' => 'TSh', 'decimals' => 0],
        'RWF' => ['name' => 'Rwandan franc', 'symbol' => 'FRw', 'decimals' => 0],
        'USD' => ['name' => 'US dollar', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2],
        'GBP' => ['name' => 'Pound sterling', 'symbol' => '£', 'decimals' => 2],
        'CNY' => ['name' => 'Chinese yuan', 'symbol' => '¥', 'decimals' => 2],
        'AED' => ['name' => 'UAE dirham', 'symbol' => 'AED', 'decimals' => 2],
        'INR' => ['name' => 'Indian rupee', 'symbol' => '₹', 'decimals' => 2],
        'ZAR' => ['name' => 'South African rand', 'symbol' => 'R', 'decimals' => 2],
    ],

    'rates' => [
        // Sharet's reference rates (Safaricom's M-PESA rate, no margin): {"base":"KES","date":"Y-m-d","rates":{"USD":"0.0077",...}}
        'url' => env('SHARET_FX_URL', rtrim((string) env('SHARET_BASE_URL', 'https://sharet.africa/api/v1'), '/').'/fx/rates'),
        'cache_minutes' => (int) env('FX_CACHE_MINUTES', 360),
        // After a failed fetch, wait this long before asking Sharet again (the last known rate is used meanwhile)
        'retry_minutes' => 15,
        'timeout' => 5,
        // The last rates Sharet gave us, kept on disk so a cache clear or an outage never loses them
        'store' => storage_path('app/fx-rates.json'),
    ],
];
