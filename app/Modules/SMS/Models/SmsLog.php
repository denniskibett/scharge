<?php

namespace App\Modules\SMS\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'recipient_phone',
        'message',
        'status',
        'provider_message_id',
        'failure_reason',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}