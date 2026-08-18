<?php

namespace App\Modules\SMS\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CampaignRecipient;
use App\Models\User;
use App\Models\SmsTemplate;

class SmsCampaign extends Model
{
    protected $table = 'sms_campaigns';

    // Status constants - MUST MATCH DATABASE ENUM
    const STATUS_PENDING = 'pending';
    const STATUS_SENDING = 'sending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
    'name',
    'description',
    'template_id',
    'filters',
    'status',
    'scheduled_at',
    'campaign_type',
    'created_by',
    'total_recipients',
    'sent_count',
    'failed_count',
    'delivered_count',
    'kenyasms_campaign_id',
    'source',      // ✅ Add this
    'source_id',   // ✅ Add this
   ];

    protected $casts = [
        'filters' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'delivered_count' => 'integer',   // 🆕 ADDED
        'total_recipients' => 'integer',
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
            'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            'sending' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        ];

        return $badges[$this->status] ?? 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200';
    }

    /**
     * Get status label for UI
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'sending' => 'Sending',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get progress percentage (based on delivered count, or sent count)
     */
    public function getProgressAttribute()
    {
        $total = $this->total_recipients ?? 0;
        if ($total == 0) {
            return 0;
        }
        // Prefer delivered count if available, else sent count
        $done = $this->delivered_count ?? $this->sent_count ?? 0;
        return round(($done / $total) * 100, 1);
    }

    /**
     * Check if campaign can be sent
     */
    public function canBeSent()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_FAILED]);
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