<?php
// app/Modules/SMS/Models/CampaignLog.php

namespace App\Modules\SMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLog extends Model
{
    protected $table = 'sms_campaign_logs';
    
    public $timestamps = false;
    
    protected $fillable = [
        'campaign_id',
        'action',
        'description',
        'metadata',
        'user_id',
        'created_at',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
    
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public static function log($campaignId, $action, $description = null, $metadata = null)
    {
        return static::create([
            'campaign_id' => $campaignId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}