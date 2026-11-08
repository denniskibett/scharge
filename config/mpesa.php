<?php

return [
    //This is the mpesa environment.Can be sanbox or production
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    /*-----------------------------------------
        |The Mpesa C2B Consumer Key
        |------------------------------------------
        */
    'mpesa_consumer_key' => env('MPESA_CONSUMER_KEY'),

    /*-----------------------------------------
        |The Mpesa C2B Consumer Secret
        |------------------------------------------
        */
    'mpesa_consumer_secret' => env('MPESA_CONSUMER_SECRET'),


    /*-----------------------------------------
        |The Mpesa B2C Consumer Key
        |------------------------------------------
        */
    'b2c_consumer_key' => env('B2C_CONSUMER_KEY'),

    /*-----------------------------------------
        |The Mpesa B2C Consumer Secret
        |------------------------------------------
        */
    'b2c_consumer_secret' => env('B2C_CONSUMER_SECRET'),

    /*-----------------------------------------
        |The Lipa na Mpesa Online Passkey
        |------------------------------------------
        */
    // .env uses MPESA_PASSKEY; SAFARICOM_PASSKEY kept as a legacy fallback.
    'passkey' => env('MPESA_PASSKEY', env('SAFARICOM_PASSKEY')),

    /*--------------------------------------------------------------
        |The Lipa na Mpesa Online ShortCode (Paybill Number)
        |-----------------------------------------------------------
    */
    'shortcode' => env('MPESA_SHORTCODE', env('MPESA_BUSINESS_SHORTCODE', '174379')),

    /*--------------------------------------------------------------
        |The Lipa na Mpesa Online ShortCode  (Till Number)
        |-----------------------------------------------------------
    */
    'till_number' => env('MPESA_BUY_GOODS_TILL', '174379'),

    /*-----------------------------------------
        |The Mpesa Initator Name
        |------------------------------------------
        */
    'initiator_name' => env('MPESA_INITIATOR', env('MPESA_INITIATOR_NAME')),

    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),

    /*-----------------------------------------
        |The Mpesa Initator Password
        |------------------------------------------
    */
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),

    /*-----------------------------------------
        |Mpesa B2C ShortCode
        |------------------------------------------
    */
    'b2c_shortcode' => env('MPESA_B2C_SHORTCODE'),

    /*-----------------------------------------
        |Mpesa Callback URLS for various APIs
        |------------------------------------------
    */

    'callbacks' => [
        'c2b_validation_url' => env('MPESA_C2B_VALIDATION_URL'),
        'c2b_confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL'),
        'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),
        'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
        'callback_url' => env('MPESA_CALLBACK_URL'),
        'status_result_url' => env('MPESA_STATUS_RESULT_URL'),
        'status_timeout_url' => env('MPESA_STATUS_TIMEOUT_URL'),
        'balance_result_url' => env('MPESA_BALANCE_RESULT_URL'),
        'balance_timeout_url' => env('MPESA_BALANCE_TIMEOUT_URL'),
        'reversal_result_url' => env('MPESA_REVERSAL_RESULT_URL'),
        'reversal_timeout_url' => env('MPESA_REVERSAL_TIMEOUT_URL'),
        'b2b_result_url' => env('MPESA_B2B_RESULT_URL'),
        'b2b_timeout_url' => env('MPESA_B2B_TIMEOUT_URL', env('MPESA_QUEUE_URL')),
    ],

    /*-----------------------------------------
        | Optional allow-list of Safaricom callback IPs (comma separated).
        | Empty = accept from any IP. Only enable once request()->ip() resolves
        | the real client IP behind your proxy/load balancer (TrustProxies).
        |------------------------------------------
    */
    'callback_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('MPESA_CALLBACK_IPS', ''))))),

];
