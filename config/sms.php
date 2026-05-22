<?php

use Illuminate\Support\Str;

return [
    'base_url' => env('KENYASMS_URL'),
    'api_key' => env('KENYASMS_KEY'),
    'sender_id' => env('KENYASMS_SENDER_ID'),
    'default_type' => env('KENYASMS_DEFAULT_TYPE', 'transactional'),

];