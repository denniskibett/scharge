<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    */
    
    'default' => env('SMS_DRIVER', 'kenyasms'),
    
    'kenyasms' => [
        'base_url' => env('KENYASMS_URL', 'https://kenyasms.com/api/v1'),
        'api_key' => env('KENYASMS_KEY'),
        'sender_id' => env('KENYASMS_SENDER_ID', 'DANAFFKENYA'),
        'default_type' => env('KENYASMS_DEFAULT_TYPE', 'transactional'),
        'sandbox' => env('KENYASMS_SANDBOX', true),
        
        // Webhook URLs
        'webhook_url' => env('KENYASMS_WEBHOOK_URL'),
        'inbound_webhook_url' => env('KENYASMS_INBOUND_WEBHOOK_URL'),
        
        // Message types and their descriptions
        'message_types' => [
            'transactional' => 'OTPs, alerts, system notifications',
            'promotional' => 'Marketing, offers, campaigns',
        ],
        
        // Quiet hours (promotional messages are blocked during these hours)
        'quiet_hours' => [
            'start' => '20:00',
            'end' => '08:00',
            'timezone' => 'EAT',
        ],
        
        // Character limits per message part
        'character_limits' => [
            'gsm7' => [
                'first_part' => 160,
                'subsequent_parts' => 153,
            ],
            'unicode' => [
                'first_part' => 70,
                'subsequent_parts' => 67,
            ],
        ],
    ],
    
    'sandbox' => env('SMS_SANDBOX', true),
    
    // Provider status mapping
    'status_mapping' => [
        '200' => 'delivered',
        '1001' => 'failed', // Invalid number
        '1002' => 'failed', // Sender ID incorrect
        '1003' => 'failed', // Network not supported
        '1004' => 'failed', // Number blacklisted
        '1005' => 'failed', // Insufficient balance
        '1006' => 'failed', // Message too long
        '1007' => 'failed', // System error
        '1008' => 'failed', // Quiet hours restriction
    ],
];