<?php

namespace App\Modules\SMS\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\SMS\Models\SmsCampaign;
use App\Modules\Tenants\Models\Tenant;

class SmsLog extends Model
{
    protected $table = 'sms_logs';

    protected $fillable = [
        'recipient_phone',
        'message',
        'message_type',
        'status',
        'error_message',
        'tenant_id',
        'campaign_id',
        'provider_message_id',
        'failure_reason',
        'cost',
        'meta',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'meta' => 'array',
        'cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants matching KenyaSMS
    const STATUS_PENDING = 'pending';
    const STATUS_QUEUED = 'queued';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';

    /**
     * Get the campaign that owns the log
     */
    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'campaign_id');
    }

    /**
     * Get the tenant that owns the log
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope a query to only include logs of a given status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include logs for a specific campaign
     */
    public function scopeCampaign($query, $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope a query to only include logs for a specific phone
     */
    public function scopePhone($query, $phone)
    {
        return $query->where('recipient_phone', 'like', '%' . $phone . '%');
    }

    /**
     * Check if the log is for a delivered message
     */
    public function isDelivered()
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if the log is for a failed message
     */
    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the log is for a sent message
     */
    public function isSent()
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the log is for a queued message
     */
    public function isQueued()
    {
        return $this->status === self::STATUS_QUEUED;
    }

    /**
     * Get the message parts count
     */
    public function getMessagePartsAttribute()
    {
        $length = strlen($this->message);
        $isUnicode = preg_match('/[^\x00-\x7F]/', $this->message);
        
        if ($isUnicode) {
            if ($length <= 70) return 1;
            return ceil(($length - 70) / 67) + 1;
        } else {
            if ($length <= 160) return 1;
            return ceil(($length - 160) / 153) + 1;
        }
    }

    /**
     * Get the estimated cost for this log
     */
    public function getEstimatedCostAttribute()
    {
        if ($this->cost) {
            return $this->cost;
        }

        $parts = $this->message_parts;
        $rates = [
            'transactional' => 0.45,
            'promotional' => 0.45
        ];
        
        $rate = $rates[$this->message_type ?? 'transactional'] ?? 0.45;
        return $parts * $rate;
    }

    /**
     * Get formatted status with badge class
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'queued' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'sent' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }

    /**
     * Get formatted status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'queued' => 'Queued',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }
}