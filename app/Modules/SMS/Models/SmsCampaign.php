<?php

namespace App\Modules\SMS\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    protected $table = 'sms_campaigns';
    
    protected $fillable = [
        'name', 'template_id', 'total_recipients', 'sent_count', 'failed_count', 'status', 'created_by'
    ];

    public function logs()
    {
        return $this->hasMany(SmsLog::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}