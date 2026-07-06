<?php
// app/Modules/SMS/Services/CampaignService.php

namespace App\Modules\SMS\Services;

use App\Modules\SMS\Models\Campaign;
use App\Modules\SMS\Models\CampaignRecipient;
use App\Modules\SMS\Models\CampaignLog;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Water\Models\WaterReading;
use App\Modules\Invoices\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    protected KenyaSMSService $kenyaSMS;
    
    public function __construct(KenyaSMSService $kenyaSMS)
    {
        $this->kenyaSMS = $kenyaSMS;
    }
    
    /**
     * Create a new water bill campaign
     */
    public function createCampaign(array $data): Campaign
    {
        DB::beginTransaction();
        
        try {
            // Get recipients with their water reading data
            $recipients = $this->getWaterBillRecipients(
                $data['estate_id'],
                $data['filters'] ?? []
            );
            
            if (empty($recipients)) {
                throw new \Exception('No recipients found for the given filters.');
            }
            
            // Calculate estimated cost
            $messageLength = strlen($data['message']);
            $partsPerSMS = $this->calculateSMSParts($messageLength);
            $costPerRecipient = $data['message_type'] === 'transactional' ? 0.60 : 0.45;
            $estimatedCost = count($recipients) * $partsPerSMS * $costPerRecipient;
            
            // Create campaign
            $campaign = Campaign::create([
                'name' => $data['name'],
                'message' => $data['message'],
                'billing_month' => $data['billing_month'],
                'reading_date' => $data['reading_date'] ?? now(),
                'estate_id' => $data['estate_id'],
                'created_by' => auth()->id(),
                'sender_id' => $data['sender_id'] ?? 'TextSMS',
                'message_type' => $data['message_type'] ?? 'transactional',
                'status' => 'draft',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'total_recipients' => count($recipients),
                'estimated_cost' => $estimatedCost,
                'cost_per_sms' => $costPerRecipient * $partsPerSMS,
                'filters' => $data['filters'] ?? [],
            ]);
            
            // Create recipients
            foreach ($recipients as $recipient) {
                $personalizedMessage = $this->personalizeMessage(
                    $data['message'],
                    $recipient['placeholders']
                );
                
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $recipient['tenant_id'],
                    'unit_id' => $recipient['unit_id'],
                    'phone' => $recipient['phone'],
                    'unit_number' => $recipient['unit_number'],
                    'tenant_name' => $recipient['tenant_name'],
                    'message' => $personalizedMessage,
                    'sms_parts' => $partsPerSMS,
                    'cost_per_sms' => $costPerRecipient,
                    'total_cost' => $costPerRecipient * $partsPerSMS,
                    'reading_date' => $recipient['reading_date'],
                    'previous_reading' => $recipient['previous_reading'],
                    'current_reading' => $recipient['current_reading'],
                    'consumption' => $recipient['consumption'],
                    'water_bill' => $recipient['water_bill'],
                    'payment_status' => $recipient['payment_status'],
                    'due_date' => $recipient['due_date'],
                    'status' => 'pending',
                ]);
            }
            
            // Log creation
            CampaignLog::log(
                $campaign->id,
                'created',
                "Campaign created with {$campaign->total_recipients} recipients",
                ['recipients' => count($recipients), 'estimated_cost' => $estimatedCost]
            );
            
            DB::commit();
            
            return $campaign;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Campaign creation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
    
    /**
     * Get water bill recipients - USING SAME LOGIC AS SMS CONTROLLER
     */
    protected function getWaterBillRecipients($estateId, $filters = []): array
    {
        // Use the exact same logic as SmsController
        $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
            ->get()
            ->map(function ($tenant) use ($estateId, $filters) {
                $tenancy = $tenant->activeTenancy;
                $unit = $tenancy ? $tenancy->unit : null;
                
                // Skip if not the selected estate
                if ($unit && $unit->estate_id != $estateId) {
                    return null;
                }
                
                // Skip if no user or phone
                if (!$tenant->user || !$tenant->user->phone) {
                    return null;
                }
                
                // Get the latest water reading
                $latestWaterReading = $unit ? WaterReading::where('unit_id', $unit->id)
                    ->latest('reading_date')
                    ->first() : null;
                
                if (!$latestWaterReading) {
                    return null;
                }
                
                $waterBill = (float) $latestWaterReading->charge;
                $waterConsumption = (float) $latestWaterReading->consumption;
                $prevRead = (float) $latestWaterReading->previous_reading;
                $currRead = (float) $latestWaterReading->current_reading;
                $readingDate = $latestWaterReading->reading_date;
                
                // Get payment status
                $paymentStatus = $this->getPaymentStatus($tenant);
                
                // Apply payment status filter
                if (isset($filters['payment_status']) && $filters['payment_status'] !== 'all') {
                    if ($paymentStatus !== $filters['payment_status']) {
                        return null;
                    }
                }
                
                // Calculate due date
                $readingMonth = $readingDate ? $readingDate->format('F Y') : Carbon::now()->format('F Y');
                $baseDate = $readingDate ? Carbon::parse($readingDate) : Carbon::now();
                $dueDate = $baseDate->copy()->addMonth()->day(5)->format('Y-m-d');
                
                // Prepare placeholders
                $placeholders = [
                    'estate_name' => $unit && $unit->estate ? $unit->estate->name : 'N/A',
                    'month' => $readingMonth,
                    'water_consumption' => $waterConsumption,
                    'prev_read' => $prevRead,
                    'curr_read' => $currRead,
                    'unit' => $unit->unit_number ?? '',
                    'unit_number' => $unit->unit_number ?? '',
                    'water_bill' => number_format($waterBill, 2),
                    'due_date' => $dueDate,
                    'payment_status' => $paymentStatus,
                    'status' => $paymentStatus,
                ];
                
                return [
                    'tenant_id' => $tenant->id,
                    'unit_id' => $unit->id ?? null,
                    'tenant_name' => $tenant->user->name ?? 'N/A',
                    'phone' => $this->kenyaSMS->formatPhone($tenant->user->phone),
                    'unit_number' => $unit->unit_number ?? '',
                    'reading_date' => $readingDate,
                    'previous_reading' => $prevRead,
                    'current_reading' => $currRead,
                    'consumption' => $waterConsumption,
                    'water_bill' => $waterBill,
                    'payment_status' => $paymentStatus,
                    'due_date' => $dueDate,
                    'placeholders' => $placeholders,
                ];
            })
            ->filter(function ($recipient) {
                return $recipient !== null && !empty($recipient['phone']);
            })
            ->values()
            ->toArray();
        
        return $tenants;
    }
    
    /**
     * Get payment status for tenant
     */
    protected function getPaymentStatus($tenant): string
    {
        if (!$tenant->activeTenancy) {
            return 'pending';
        }
        
        $invoices = $tenant->activeTenancy->invoices;
        $unpaid = $invoices->where('status', 'unpaid')->count();
        $paid = $invoices->where('status', 'paid')->count();
        
        if ($paid > 0 && $unpaid == 0) {
            return 'paid';
        } elseif ($unpaid > 0) {
            return 'unpaid';
        }
        
        return 'pending';
    }
    
    /**
     * Calculate SMS parts based on message length
     */
    protected function calculateSMSParts($message): int
    {
        $length = mb_strlen($message);
        $hasUnicode = $length !== strlen($message);
        
        if ($hasUnicode) {
            // Unicode: 70 chars per part, 67 after first
            if ($length <= 70) return 1;
            return ceil(($length - 70) / 67) + 1;
        } else {
            // GSM-7: 160 chars per part, 153 after first
            if ($length <= 160) return 1;
            return ceil(($length - 160) / 153) + 1;
        }
    }
    
    /**
     * Personalize message with placeholders
     */
    protected function personalizeMessage($template, $placeholders): string
    {
        $message = $template;
        foreach ($placeholders as $key => $value) {
            $message = str_replace("{{{$key}}}", $value, $message);
        }
        return $message;
    }
    
    /**
     * Send a campaign
     */
    public function sendCampaign(Campaign $campaign): Campaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            throw new \Exception('Campaign cannot be sent in current status: ' . $campaign->status);
        }

        $recipients = $campaign->recipients()->where('status', 'pending')->get();

        if ($recipients->isEmpty()) {
            throw new \Exception('No pending recipients to send to.');
        }

        Log::info('Sending campaign', [
            'campaign_id' => $campaign->id,
            'recipients' => $recipients->count()
        ]);

        $campaign->update([
            'status' => 'queued',
            'sent_at' => Carbon::now(),
        ]);

        CampaignLog::log(
            $campaign->id,
            'queued',
            "Campaign queued for sending",
            ['recipients' => $recipients->count()]
        );

        try {
            // Prepare messages
            $messages = [];
            foreach ($recipients as $recipient) {
                $messages[] = [
                    'phone' => $this->kenyaSMS->formatPhone($recipient->phone),
                    'message' => $recipient->message,
                ];
            }

            Log::info('Prepared messages', ['count' => count($messages)]);

            // Mark recipients as queued
            $recipients->each(function($recipient) {
                $recipient->status = 'queued';
                $recipient->queued_at = Carbon::now();
                $recipient->save();
            });

            // Send via KenyaSMS
            $response = $this->kenyaSMS->sendPersonalized($messages, [
                'sender_id' => $campaign->sender_id,
                'message_type' => $campaign->message_type,
                'schedule_at' => $campaign->scheduled_at,
                'callback_url' => route('sms.webhook.dlr'),
            ]);

            Log::info('KenyaSMS response', ['response' => $response]);

            // Mark all recipients as sent
            $recipients->each(function($recipient) {
                $recipient->status = 'sent';
                $recipient->sent_at = Carbon::now();
                $recipient->save();
            });

            // Update campaign
            $campaign->update([
                'status' => 'sending',
                'sent_count' => $recipients->count(),
            ]);

            CampaignLog::log(
                $campaign->id,
                'sent',
                "Campaign sent to {$recipients->count()} recipients",
                ['response' => $response]
            );

            return $campaign;

        } catch (\Exception $e) {
            Log::error('Campaign send error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            // Mark recipients as failed
            $recipients->each(function($recipient) {
                $recipient->status = 'failed';
                $recipient->failure_reason = $e->getMessage();
                $recipient->failed_at = Carbon::now();
                $recipient->save();
            });

            $campaign->update([
                'status' => 'failed',
                'failed_count' => $recipients->count(),
            ]);

            CampaignLog::log(
                $campaign->id,
                'failed',
                "Campaign sending failed: " . $e->getMessage(),
                ['error' => $e->getMessage()]
            );
            
            throw $e;
        }
    }
    
    /**
     * Duplicate a campaign
     */
    public function duplicateCampaign(Campaign $campaign): Campaign
    {
        DB::beginTransaction();
        
        try {
            $newCampaign = $campaign->replicate();
            $newCampaign->name = $campaign->name . ' (Copy)';
            $newCampaign->status = 'draft';
            $newCampaign->scheduled_at = null;
            $newCampaign->sent_at = null;
            $newCampaign->completed_at = null;
            $newCampaign->sent_count = 0;
            $newCampaign->delivered_count = 0;
            $newCampaign->failed_count = 0;
            $newCampaign->actual_cost = 0;
            $newCampaign->kenyasms_campaign_id = null;
            $newCampaign->created_by = auth()->id();
            $newCampaign->save();
            
            // Duplicate recipients
            foreach ($campaign->recipients as $recipient) {
                $newRecipient = $recipient->replicate();
                $newRecipient->campaign_id = $newCampaign->id;
                $newRecipient->status = 'pending';
                $newRecipient->sent_at = null;
                $newRecipient->delivered_at = null;
                $newRecipient->failed_at = null;
                $newRecipient->kenyasms_message_id = null;
                $newRecipient->kenyasms_status = null;
                $newRecipient->kenyasms_status_code = null;
                $newRecipient->failure_reason = null;
                $newRecipient->failure_code = null;
                $newRecipient->retry_count = 0;
                $newRecipient->last_retry_at = null;
                $newRecipient->webhook_payload = null;
                $newRecipient->save();
            }
            
            CampaignLog::log(
                $newCampaign->id,
                'duplicated',
                "Campaign duplicated from campaign #{$campaign->id}",
                ['original_campaign' => $campaign->id]
            );
            
            DB::commit();
            
            return $newCampaign;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Campaign duplication failed', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaign->id
            ]);
            throw $e;
        }
    }
    
    /**
     * Cancel a campaign
     */
    public function cancelCampaign(Campaign $campaign): Campaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled', 'queued'])) {
            throw new \Exception('Campaign cannot be cancelled in current status: ' . $campaign->status);
        }
        
        $campaign->update(['status' => 'cancelled']);
        
        CampaignLog::log(
            $campaign->id,
            'cancelled',
            "Campaign cancelled",
            ['previous_status' => $campaign->getOriginal('status')]
        );
        
        return $campaign;
    }
    
    /**
     * Resend failed recipients
     */
    public function resendFailed(Campaign $campaign): Campaign
    {
        $failedRecipients = $campaign->recipients()->where('status', 'failed')->get();
        
        if ($failedRecipients->isEmpty()) {
            throw new \Exception('No failed recipients to resend.');
        }
        
        // Reset failed recipients to pending
        $failedRecipients->each(function($recipient) {
            $recipient->status = 'pending';
            $recipient->retry_count = ($recipient->retry_count ?? 0) + 1;
            $recipient->last_retry_at = Carbon::now();
            $recipient->failure_reason = null;
            $recipient->save();
        });
        
        // Re-send the campaign
        return $this->sendCampaign($campaign);
    }
}