<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\SMS\Models\SmsLog;
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Delivery Report (DLR) from KenyaSMS
     */
    public function handleDLR(Request $request)
    {
        // Log the incoming webhook data
        Log::info('KenyaSMS DLR Webhook received', $request->all());

        try {
            // Extract data from the webhook - KenyaSMS format
            $requestId = $request->input('request_id');
            $resultCode = $request->input('result_code');
            $resultDesc = $request->input('result_desc');
            $phoneNumber = $request->input('phoneNumber');
            $charge = $request->input('charge');
            $status = $request->input('status');
            
            // Alternative formats
            $messageId = $request->input('message_id', $requestId);
            $phone = $request->input('phone', $phoneNumber);
            $error = $request->input('error', $resultDesc);

            // Find the SMS log by provider_message_id
            if ($messageId) {
                $log = SmsLog::where('provider_message_id', $messageId)->first();
                
                if ($log) {
                    // Map KenyaSMS status codes to internal status
                    $internalStatus = $this->mapProviderStatus($resultCode, $status);
                    
                    // Update the log status
                    $log->status = $internalStatus ?? $log->status;
                    $log->provider_status = $resultCode ?? $status ?? $log->provider_status;
                    $log->provider_response = $resultDesc ?? $error ?? $log->provider_response;
                    $log->failure_reason = $error ?? $log->failure_reason;
                    
                    // Update cost if provided
                    if ($charge) {
                        $log->cost = $charge;
                    }
                    
                    $log->save();

                    Log::info('SMS DLR updated for message: ' . $messageId, [
                        'new_status' => $internalStatus,
                        'result_code' => $resultCode
                    ]);

                    // Update campaign recipient if exists
                    $this->updateRecipientStatus($log);
                    
                    // Update campaign counters
                    if ($log->campaign_id) {
                        $this->updateCampaignCounters($log->campaign_id);
                    }
                } else {
                    Log::warning('SMS DLR: No log found for message_id: ' . $messageId);
                    
                    // Try to find by phone number as fallback
                    if ($phone) {
                        $log = SmsLog::where('recipient_phone', $this->formatPhoneNumber($phone))
                            ->orderBy('created_at', 'desc')
                            ->first();
                        
                        if ($log) {
                            $internalStatus = $this->mapProviderStatus($resultCode, $status);
                            $log->status = $internalStatus ?? $log->status;
                            $log->provider_status = $resultCode ?? $status ?? $log->provider_status;
                            $log->provider_response = $resultDesc ?? $error ?? $log->provider_response;
                            $log->save();
                            
                            Log::info('SMS DLR updated by phone fallback for: ' . $phone);
                        }
                    }
                }
            }

            // Always return 200 OK to acknowledge receipt
            return response()->json([
                'success' => true,
                'message' => 'DLR processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('SMS DLR webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Still return 200 to prevent retries from provider
            return response()->json([
                'success' => false, 
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Update campaign recipient status based on log
     */
    protected function updateRecipientStatus($log)
    {
        if (!$log->campaign_id) {
            return;
        }

        try {
            $recipient = CampaignRecipient::where('campaign_id', $log->campaign_id)
                ->where('phone_number', $log->recipient_phone)
                ->first();

            if ($recipient) {
                $recipient->update([
                    'status' => $log->status,
                    'provider_status' => $log->provider_status,
                    'provider_response' => $log->provider_response,
                    'sent_at' => $log->status === 'delivered' ? now() : $recipient->sent_at,
                    'error_message' => $log->failure_reason ?? $recipient->error_message,
                ]);

                Log::info('Recipient status updated', [
                    'recipient_id' => $recipient->id,
                    'status' => $log->status
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update recipient status', [
                'error' => $e->getMessage(),
                'log_id' => $log->id
            ]);
        }
    }

    /**
     * Update campaign counters after status change
     */
    protected function updateCampaignCounters($campaignId)
    {
        try {
            DB::transaction(function () use ($campaignId) {
                $campaign = SmsCampaign::lockForUpdate()->find($campaignId);
                
                if (!$campaign) {
                    return;
                }

                $counts = CampaignRecipient::where('campaign_id', $campaignId)
                    ->selectRaw("
                        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count,
                        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status IN ('sending', 'queued') THEN 1 END) as sending_count
                    ")
                    ->first();

                $totalProcessed = ($counts->delivered_count ?? 0) + ($counts->failed_count ?? 0);
                $totalRecipients = $campaign->total_recipients ?? 
                    CampaignRecipient::where('campaign_id', $campaignId)->count();

                // Determine campaign status
                $campaignStatus = $this->determineCampaignStatus(
                    $totalRecipients,
                    $counts->delivered_count ?? 0,
                    $counts->failed_count ?? 0,
                    $counts->pending_count ?? 0,
                    $counts->sending_count ?? 0
                );

                $campaign->update([
                    'sent_count' => $totalProcessed,
                    'delivered_count' => $counts->delivered_count ?? 0,
                    'failed_count' => $counts->failed_count ?? 0,
                    'status' => $campaignStatus,
                ]);

                Log::info('Campaign counters updated', [
                    'campaign_id' => $campaignId,
                    'status' => $campaignStatus,
                    'delivered' => $counts->delivered_count,
                    'failed' => $counts->failed_count
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update campaign counters', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine overall campaign status
     */
    protected function determineCampaignStatus($total, $delivered, $failed, $pending, $sending)
    {
        if ($total == 0) {
            return 'draft';
        }

        if ($sending > 0 || $pending > 0) {
            return 'sending';
        }

        if ($delivered == $total) {
            return 'completed';
        }

        if ($failed == $total) {
            return 'failed';
        }

        // Some delivered, some failed
        if ($delivered > 0 && $failed > 0) {
            return 'completed';
        }

        return 'completed';
    }

    /**
     * Map provider status to internal status
     */
    protected function mapProviderStatus($resultCode, $status = null)
    {
        // If we have a status from the provider, use it
        if ($status && in_array($status, ['delivered', 'sent', 'queued', 'failed'])) {
            return $status;
        }

        // Map result codes to internal status
        $mapping = config('sms.status_mapping', [
            '200' => 'delivered',
            '1001' => 'failed', // Invalid number
            '1002' => 'failed', // Sender ID incorrect
            '1003' => 'failed', // Network not supported
            '1004' => 'failed', // Number blacklisted
            '1005' => 'failed', // Insufficient balance
            '1006' => 'failed', // Message too long
            '1007' => 'failed', // System error
            '1008' => 'failed', // Quiet hours restriction
        ]);

        return $mapping[$resultCode] ?? 'unknown';
    }

    /**
     * Format phone number to consistent format
     */
    protected function formatPhoneNumber($phone)
    {
        if (empty($phone)) {
            return null;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        if (substr($phone, 0, 1) === '7') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}