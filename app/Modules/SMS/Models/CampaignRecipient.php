<?php
// app/Modules/SMS/Models/CampaignRecipient.php

namespace App\Modules\SMS\Models;

use App\Modules\Tenants\Models\Tenant;
use App\Modules\Properties\Models\Unit; // ← FIXED
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CampaignRecipient extends Model
{
    protected $table = 'sms_campaign_recipients';
    
    protected $fillable = [
        'campaign_id',
        'tenant_id',
        'unit_id',
        'phone',
        'unit_number',
        'tenant_name',
        'message',
        'sms_parts',
        'cost_per_sms',
        'total_cost',
        'reading_date',
        'previous_reading',
        'current_reading',
        'consumption',
        'water_bill',
        'payment_status',
        'due_date',
        'status',
        'kenyasms_message_id',
        'kenyasms_status',
        'kenyasms_status_code',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'failure_code',
        'retry_count',
        'last_retry_at',
        'webhook_payload',
    ];
    
    protected $casts = [
        'webhook_payload' => 'array',
        'reading_date' => 'date',
        'due_date' => 'date',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'previous_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'water_bill' => 'decimal:2',
        'cost_per_sms' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];
    
    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    
    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
    
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    public function scopeByPhone($query, $phone)
    {
        return $query->where('phone', 'like', "%{$phone}%");
    }
    
    // Accessors
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">⏳ Pending</span>',
            'queued' => '<span class="badge badge-info">📤 Queued</span>',
            'sending' => '<span class="badge badge-info">📤 Sending</span>',
            'sent' => '<span class="badge badge-primary">✅ Sent</span>',
            'delivered' => '<span class="badge badge-success">📨 Delivered</span>',
            'failed' => '<span class="badge badge-danger">❌ Failed</span>',
        ];
        
        return $badges[$this->status] ?? $this->status;
    }
    
    public function getDeliveryTimeAttribute(): ?string
    {
        if ($this->sent_at && $this->delivered_at) {
            $seconds = $this->delivered_at->diffInSeconds($this->sent_at);
            return $seconds . ' seconds';
        }
        return null;
    }
    
    // Helper Methods
    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
            'queued_at' => Carbon::now(),
        ]);
    }
    
    public function markAsSent($messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'kenyasms_message_id' => $messageId,
            'sent_at' => Carbon::now(),
        ]);
        
        $this->campaign->increment('sent_count');
    }
    
    public function markAsDelivered($payload = null): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => Carbon::now(),
            'webhook_payload' => $payload,
        ]);
        
        $this->campaign->increment('delivered_count');
    }
    
    public function markAsFailed($reason, $code = null, $payload = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => Carbon::now(),
            'failure_reason' => $reason,
            'failure_code' => $code,
            'webhook_payload' => $payload,
        ]);
        
        $this->campaign->increment('failed_count');
    }
    
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }
}