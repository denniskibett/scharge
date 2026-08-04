<?php

namespace App\Modules\SMS\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CampaignRecipient;
use App\Models\User;
use App\Models\SmsTemplate;

class SmsCampaign extends Model
{
    protected $table = 'sms_campaigns';

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'name',
        'description',
        'template_id',
        'campaign_type',
        'filters',
        'total_recipients',
        'sent_count',
        'failed_count',
        'status',
        'scheduled_at',
        'sent_at',
        'created_by'
    ];

    protected $casts = [
        'filters' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the template for the campaign
     */
    public function template()
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }

    /**
     * Get the creator of the campaign
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the recipients for the campaign
     */
    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class, 'campaign_id');
    }

    /**
     * Get the logs for the campaign
     */
    public function logs()
    {
        return $this->hasMany(SmsLog::class, 'campaign_id');
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
            'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'sending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'partial' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        ];

        return $badges[$this->status] ?? 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
    }

    /**
     * Get status label for UI
     */
    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    /**
     * Get progress percentage
     */
    public function getProgressAttribute()
    {
        if ($this->total_recipients == 0) {
            return 0;
        }
        $sent = $this->sent_count ?? 0;
        return round(($sent / $this->total_recipients) * 100, 1);
    }

    /**
     * Check if campaign can be sent
     */
    public function canBeSent()
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_FAILED, self::STATUS_PENDING]);
    }

    /**
     * Check if campaign is complete
     */
    public function isComplete()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if campaign has failed
     */
    public function hasFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }
}