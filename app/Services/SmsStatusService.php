<?php

namespace App\Services;

use App\Models\CampaignRecipient;
use App\Modules\SMS\Models\SmsCampaign;
use App\Modules\SMS\Services\KenyaSMS;
use Illuminate\Support\Facades\Log;

class SmsStatusService
{
    protected $kenyaSms;

    public function __construct(KenyaSMS $kenyaSms)
    {
        $this->kenyaSms = $kenyaSms;
    }

    /**
     * Sync status for all recipients in a campaign
     */
    public function syncCampaignStatus($campaignId)
    {
        $campaign = SmsCampaign::find($campaignId);
        
        if (!$campaign) {
            return ['error' => 'Campaign not found'];
        }

        $recipients = CampaignRecipient::where('campaign_id', $campaignId)
            ->whereNotNull('message_id')
            ->whereIn('status', ['pending', 'sent', 'sending', 'queued'])
            ->get();

        if ($recipients->isEmpty()) {
            return [
                'success' => true,
                'synced' => 0,
                'updated' => 0,
                'message' => 'No recipients with message IDs to sync'
            ];
        }

        $synced = 0;
        $updated = 0;

        foreach ($recipients as $recipient) {
            if (!$recipient->message_id) {
                continue;
            }

            $synced++;
            $status = $this->kenyaSms->getMessageStatus($recipient->message_id);
            
            if ($status['success']) {
                $newStatus = $status['status'] ?? 'unknown';
                $internalStatus = $this->mapStatus($newStatus);
                
                if ($internalStatus !== $recipient->status) {
                    $recipient->status = $internalStatus;
                    $recipient->provider_status = $newStatus;
                    $recipient->provider_response = json_encode($status['response'] ?? []);
                    $recipient->save();
                    $updated++;
                }
            }
        }

        // Update campaign counters
        $this->updateCampaignCounters($campaign);

        return [
            'success' => true,
            'synced' => $synced,
            'updated' => $updated,
            'message' => "Synced $synced recipients, updated $updated statuses"
        ];
    }

    /**
     * Sync status for a single recipient
     */
    public function syncRecipientStatus($recipientId)
    {
        $recipient = CampaignRecipient::with(['campaign'])->find($recipientId);
        
        if (!$recipient) {
            return [
                'success' => false,
                'message' => 'Recipient not found'
            ];
        }

        // If no message_id, we can't sync status
        if (!$recipient->message_id) {
            return [
                'success' => false,
                'message' => 'No message ID found for this recipient'
            ];
        }

        // Check if recipient can be synced
        if (!in_array($recipient->status, ['pending', 'sent', 'sending', 'queued'])) {
            return [
                'success' => false,
                'message' => 'Recipient status is ' . $recipient->status . '. Cannot sync.'
            ];
        }

        try {
            $status = $this->kenyaSms->getMessageStatus($recipient->message_id);
            
            if ($status['success']) {
                $newStatus = $status['status'] ?? 'unknown';
                $internalStatus = $this->mapStatus($newStatus);
                
                if ($internalStatus !== $recipient->status) {
                    $recipient->status = $internalStatus;
                    $recipient->provider_status = $newStatus;
                    $recipient->provider_response = json_encode($status['response'] ?? []);
                    
                    if ($internalStatus === 'delivered' || $internalStatus === 'sent') {
                        $recipient->sent_at = now();
                    }
                    
                    $recipient->save();
                    
                    // Update campaign counters
                    $this->updateCampaignCounters($recipient->campaign);
                    
                    return [
                        'success' => true,
                        'message' => 'Status updated from ' . $recipient->status . ' to ' . $internalStatus,
                        'old_status' => $recipient->status,
                        'new_status' => $internalStatus,
                        'provider_status' => $newStatus
                    ];
                } else {
                    return [
                        'success' => true,
                        'message' => 'Status is already ' . $recipient->status,
                        'status' => $recipient->status
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get status: ' . ($status['error'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            Log::error('Sync recipient status failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error syncing status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get status summary for a campaign
     */
    public function getStatusSummary($campaignId)
    {
        $campaign = SmsCampaign::find($campaignId);
        
        if (!$campaign) {
            return ['error' => 'Campaign not found'];
        }

        $statusCounts = CampaignRecipient::where('campaign_id', $campaignId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($statusCounts);

        return [
            'campaign_id' => $campaignId,
            'campaign_name' => $campaign->name,
            'total' => $total,
            'statuses' => $statusCounts,
            'sent' => ($statusCounts['sent'] ?? 0) + ($statusCounts['delivered'] ?? 0),
            'pending' => $statusCounts['pending'] ?? 0,
            'failed' => $statusCounts['failed'] ?? 0,
            'queued' => $statusCounts['queued'] ?? 0,
            'delivered' => $statusCounts['delivered'] ?? 0
        ];
    }

    /**
     * Update campaign counters
     */
    protected function updateCampaignCounters($campaign)
    {
        if (!$campaign) {
            return;
        }

        $sentCount = CampaignRecipient::where('campaign_id', $campaign->id)
            ->whereIn('status', ['sent', 'delivered'])
            ->count();
        
        $failedCount = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->count();

        $campaign->sent_count = $sentCount;
        $campaign->failed_count = $failedCount;
        $campaign->save();
    }

    /**
     * Map provider status to internal status
     */
    protected function mapStatus($providerStatus)
    {
        $mapping = [
            'queued' => 'queued',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'undelivered' => 'failed',
            'expired' => 'failed',
            'rejected' => 'failed',
            '200' => 'delivered',
            '1001' => 'failed',
            '1002' => 'failed',
            '1003' => 'failed',
            '1004' => 'failed',
            '1005' => 'failed',
            '1006' => 'failed',
            '1007' => 'failed',
            '1008' => 'failed',
        ];

        return $mapping[$providerStatus] ?? 'unknown';
    }
}