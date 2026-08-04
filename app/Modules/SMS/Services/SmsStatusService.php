<?php

namespace App\Modules\SMS\Services;

use App\Models\CampaignRecipient;
use App\Modules\SMS\Models\SmsCampaign;

class SmsStatusService
{
    /**
     * Get status summary for a campaign
     */
    public function getStatusSummary($campaignId)
    {
        $campaign = SmsCampaign::find($campaignId);
        
        if (!$campaign) {
            return [
                'total' => 0,
                'sent' => 0,
                'delivered' => 0,
                'failed' => 0,
                'pending' => 0,
            ];
        }

        $recipients = CampaignRecipient::where('campaign_id', $campaignId)->get();

        return [
            'total' => $recipients->count(),
            'sent' => $recipients->where('status', 'sent')->count(),
            'delivered' => $recipients->where('status', 'delivered')->count(),
            'failed' => $recipients->where('status', 'failed')->count(),
            'pending' => $recipients->where('status', 'pending')->count(),
            'queued' => $recipients->where('status', 'queued')->count(),
        ];
    }

    /**
     * Get detailed status for each recipient (optional)
     */
    public function getRecipientStatuses($campaignId)
    {
        return CampaignRecipient::where('campaign_id', $campaignId)
            ->select('id', 'phone_number', 'status', 'sent_at', 'error_message', 'message_id')
            ->get()
            ->toArray();
    }
}