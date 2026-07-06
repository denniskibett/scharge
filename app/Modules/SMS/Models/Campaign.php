<?php
// app/Modules/SMS/Models/Campaign.php

namespace App\Modules\SMS\Models;

use App\Models\Estate; // ← FIXED: Correct path
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use SoftDeletes;
    
    protected $table = 'sms_campaigns';
    
    protected $fillable = [
        'name',
        'message',
        'billing_month',
        'reading_date',
        'estate_id',
        'created_by',
        'sender_id',
        'message_type',
        'status',
        'scheduled_at',
        'sent_at',
        'completed_at',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'failed_count',
        'estimated_cost',
        'actual_cost',
        'cost_per_sms',
        'filters',
        'kenyasms_campaign_id',
    ];
    
    protected $casts = [
        'filters' => 'array',
        'reading_date' => 'date',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'cost_per_sms' => 'decimal:4',
    ];
    
    // Relationships
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
    
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }
    
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class);
    }
    
    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
    
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }
    
    public function scopeSending($query)
    {
        return $query->where('status', 'sending');
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    
    public function scopeByMonth($query, $month)
    {
        return $query->where('billing_month', $month);
    }
    
    public function scopeByEstate($query, $estateId)
    {
        return $query->where('estate_id', $estateId);
    }
    
    // Accessors
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_recipients == 0) {
            return 0;
        }
        
        $successful = $this->delivered_count;
        return round(($successful / $this->total_recipients) * 100, 1);
    }
    
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'draft' => '📝 Draft',
            'scheduled' => '⏳ Scheduled',
            'queued' => '⏳ Queued',
            'sending' => '📤 Sending',
            'completed' => '✅ Completed',
            'failed' => '❌ Failed',
            'cancelled' => '🚫 Cancelled',
        ];
        
        return $badges[$this->status] ?? $this->status;
    }
    
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'draft' => 'secondary',
            'scheduled' => 'info',
            'queued' => 'warning',
            'sending' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'dark',
        ];
        
        return $colors[$this->status] ?? 'secondary';
    }
    
    // Helper Methods
    public function getFailedReasons()
    {
        return $this->recipients()
            ->where('status', 'failed')
            ->select('failure_reason', \DB::raw('count(*) as count'))
            ->groupBy('failure_reason')
            ->get();
    }
    
    public function getDeliveryTimeline()
    {
        return $this->recipients()
            ->whereNotNull('delivered_at')
            ->selectRaw('
                DATE_FORMAT(delivered_at, "%H:%i") as time,
                COUNT(*) as count
            ')
            ->groupBy('time')
            ->orderBy('time')
            ->get();
    }
    
    public function getPendingCount(): int
    {
        return $this->recipients()->where('status', 'pending')->count();
    }
    
    public function getProcessingCount(): int
    {
        return $this->recipients()->whereIn('status', ['queued', 'sending', 'sent'])->count();
    }
    
    public function isComplete(): bool
    {
        $processed = $this->recipients()
            ->whereIn('status', ['delivered', 'failed'])
            ->count();
            
        return $processed >= $this->total_recipients;
    }
}