<?php

namespace App\Modules\SMS\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['name', 'content', 'placeholders', 'created_by'];

    protected $casts = [
        'placeholders' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}