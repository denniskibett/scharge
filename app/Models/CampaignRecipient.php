<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\SMS\Models\SmsCampaign;

class CampaignRecipient extends Model
{
    protected $table = 'campaign_recipients';

    // Status constants - MUST MATCH DATABASE ENUM
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id',
        'tenant_id',
        'phone_number',
        'message',
        'status',
        'sent_at',
        'error_message',
        'message_id',
        'provider_status',
        'provider_response',
        'attempted_at', // NEW
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'attempted_at' => 'datetime', // NEW
    ];

    /**
     * Get the campaign that owns the recipient
     */
    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'campaign_id');
    }

    /**
     * Get the tenant that owns the recipient
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Mark recipient as sent
     */
    public function markAsSent($messageId = null)
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        if ($messageId) {
            $this->message_id = $messageId;
        }
        $this->save();
    }

    /**
     * Mark recipient as failed
     */
    public function markAsFailed($errorMessage = null)
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->save();
    }

    /**
     * Check if recipient is pending
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if recipient is failed
     */
    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if recipient is sent
     */
    public function isSent()
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'sent' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }

    /**
     * Get status label for UI
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'sent' => 'Sent',
            'failed' => 'Failed',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Mark recipient as queued (alias for pending)
     * Kept for compatibility but maps to pending
     */
    public function markAsQueued($messageId = null)
    {
        $this->status = self::STATUS_PENDING;
        if ($messageId) {
            $this->message_id = $messageId;
        }
        $this->save();
    }

    /**
     * Mark recipient as delivered (alias for sent)
     * Kept for compatibility but maps to sent
     */
    public function markAsDelivered()
    {
        $this->status = self::STATUS_SENT;
        $this->save();
    }
}