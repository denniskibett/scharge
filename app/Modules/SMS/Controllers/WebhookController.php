<?php
// app/Modules/SMS/Controllers/WebhookController.php

namespace App\Modules\SMS\Controllers;

use App\Modules\SMS\Models\CampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WebhookController extends Controller
{
    /**
     * Handle KenyaSMS DLR webhook
     */
    public function handleDLR(Request $request)
    {
        try {
            // Get the payload
            $payload = $request->all();
            
            // Log all webhook data for debugging
            Log::channel('sms_webhooks')->info('KenyaSMS DLR Received', [
                'payload' => $payload,
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);
            
            // Validate required fields
            if (!isset($payload['message_id']) || !isset($payload['recipient'])) {
                Log::warning('Invalid webhook payload - missing required fields', $payload);
                return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
            }
            
            // Find the recipient
            $recipient = $this->findRecipient($payload);
            
            if (!$recipient) {
                Log::warning('Recipient not found for DLR', [
                    'message_id' => $payload['message_id'],
                    'recipient' => $payload['recipient']
                ]);
                return response()->json(['status' => 'ok']);
            }
            
            // Process the delivery report
            $this->processDeliveryReport($recipient, $payload);
            
            // Check if campaign is complete
            $this->checkCampaignCompletion($recipient->campaign);
            
            return response()->json(['status' => 'ok']);
            
        } catch (\Exception $e) {
            Log::error('DLR Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);
            
            // Always return 200 to prevent KenyaSMS from retrying
            return response()->json(['status' => 'error'], 200);
        }
    }
    
    /**
     * Find recipient by message_id or phone
     */
    private function findRecipient($payload)
    {
        // First try by KenyaSMS message ID
        if (!empty($payload['message_id'])) {
            $recipient = CampaignRecipient::where('kenyasms_message_id', $payload['message_id'])->first();
            if ($recipient) {
                return $recipient;
            }
        }
        
        // Try by phone number (for campaigns without message_id)
        if (!empty($payload['recipient'])) {
            $phone = $this->formatPhone($payload['recipient']);
            
            $recipient = CampaignRecipient::where('phone', $phone)
                ->whereHas('campaign', function($query) {
                    $query->whereIn('status', ['sending', 'queued', 'scheduled']);
                })
                ->latest()
                ->first();
                
            if ($recipient) {
                return $recipient;
            }
        }
        
        return null;
    }
    
    /**
     * Process the delivery report
     */
    private function processDeliveryReport($recipient, $payload)
    {
        $status = $payload['status'] ?? 'unknown';
        $statusCode = $payload['status_code'] ?? null;
        $statusDescription = $payload['status_description'] ?? null;
        $cost = $payload['cost'] ?? 0;
        $timestamp = $payload['timestamp'] ?? null;
        
        // Map KenyaSMS status to our status
        switch ($status) {
            case 'delivered':
            case 'sent':
                $recipient->update([
                    'status' => 'delivered',
                    'delivered_at' => $timestamp ? Carbon::parse($timestamp) : now(),
                    'kenyasms_status' => $status,
                    'kenyasms_status_code' => $statusCode,
                    'webhook_payload' => $payload,
                    'total_cost' => $cost,
                ]);
                
                // Update campaign delivered count
                $recipient->campaign->increment('delivered_count');
                $recipient->campaign->increment('actual_cost', $cost);
                
                Log::info('SMS delivered', [
                    'campaign_id' => $recipient->campaign_id,
                    'tenant' => $recipient->tenant_name,
                    'phone' => $recipient->phone,
                    'time' => $recipient->delivered_at,
                ]);
                break;
                
            case 'failed':
            case 'expired':
            case 'rejected':
                $reason = $statusDescription ?? $status;
                
                $recipient->update([
                    'status' => 'failed',
                    'failed_at' => $timestamp ? Carbon::parse($timestamp) : now(),
                    'failure_reason' => $reason,
                    'failure_code' => $statusCode,
                    'kenyasms_status' => $status,
                    'kenyasms_status_code' => $statusCode,
                    'webhook_payload' => $payload,
                ]);
                
                // Update campaign failed count
                $recipient->campaign->increment('failed_count');
                
                Log::warning('SMS failed', [
                    'campaign_id' => $recipient->campaign_id,
                    'tenant' => $recipient->tenant_name,
                    'phone' => $recipient->phone,
                    'reason' => $reason,
                ]);
                break;
                
            case 'pending':
            case 'queued':
                // Message still in queue
                $recipient->update([
                    'kenyasms_status' => $status,
                    'webhook_payload' => $payload,
                ]);
                break;
                
            default:
                Log::warning('Unknown DLR status', [
                    'status' => $status,
                    'payload' => $payload,
                ]);
        }
    }
    
    /**
     * Check if campaign is complete
     */
    private function checkCampaignCompletion($campaign)
    {
        // Count processed recipients (sent + delivered + failed)
        $processed = $campaign->recipients()
            ->whereIn('status', ['sent', 'delivered', 'failed'])
            ->count();
        
        $total = $campaign->total_recipients;
        
        // If all recipients processed
        if ($processed >= $total) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            
            // Get final stats
            $delivered = $campaign->recipients()->where('status', 'delivered')->count();
            $failed = $campaign->recipients()->where('status', 'failed')->count();
            $sent = $campaign->recipients()->whereIn('status', ['sent', 'delivered'])->count();
            
            Log::info('Campaign completed', [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'total' => $total,
                'sent' => $sent,
                'delivered' => $delivered,
                'failed' => $failed,
                'actual_cost' => $campaign->actual_cost,
                'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            ]);
        }
    }
    
    /**
     * Format phone number
     */
    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        
        if (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}