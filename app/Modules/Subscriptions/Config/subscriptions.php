<?php
// app/Modules/Subscriptions/Config/subscriptions.php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */
    'payment_gateway' => env('SUBSCRIPTION_PAYMENT_GATEWAY', 'stripe'),
    
    'api_key' => env('SUBSCRIPTION_API_KEY'),
    'api_secret' => env('SUBSCRIPTION_API_SECRET'),
    
    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currency' => env('SUBSCRIPTION_CURRENCY', 'USD'),
    'currency_symbol' => env('SUBSCRIPTION_CURRENCY_SYMBOL', '$'),
    
    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */
    'invoice_prefix' => env('SUBSCRIPTION_INVOICE_PREFIX', 'INV'),
    'invoice_due_days' => env('SUBSCRIPTION_INVOICE_DUE_DAYS', 7),
    
    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'send_expiry_reminder_days' => [7, 3, 1],
        'send_failed_payment_reminder' => true,
    ],
];