<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = [
        'name',
        'content',
        'description',
        'category',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function campaigns()
    {
        return $this->hasMany(\App\Modules\SMS\Models\SmsCampaign::class, 'template_id');
    }
}