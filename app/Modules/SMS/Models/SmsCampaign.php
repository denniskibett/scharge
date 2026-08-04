<?php

namespace App\Modules\SMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsCampaign extends Model
{
    use SoftDeletes;

    protected $table = 'sms_campaigns';

    protected $fillable = [
        'name',
        'description',
        'template_id',
        'filters',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'failed_count',
        'status',
        'scheduled_at',
        'campaign_type',
        'kenyasms_campaign_id',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'scheduled_at' => 'datetime',
    ];

    // ==============================================
    // RELATIONSHIPS
    // ==============================================

    public function template()
    {
        return $this->belongsTo(SmsTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients()
    {
        return $this->hasMany(\App\Models\CampaignRecipient::class);
    }
}