<?php

namespace App\Modules\SMS\Services;

use App\Modules\SMS\Models\SmsCampaign;
use App\Models\CampaignRecipient;
use App\Models\SmsTemplate;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Water\Models\WaterReading;
use App\Models\Invoice;
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
     * Build full placeholders for a tenant
     */
    protected function buildPlaceholders($tenant, $reading = null)
    {
        $tenancy = $tenant->activeTenancy;
        $unit = $tenancy ? $tenancy->unit : null;
        $reading = $reading ?? ($unit ? WaterReading::where('unit_id', $unit->id)->latest('reading_date')->first() : null);
        
        $readingDate = $reading ? $reading->reading_date : null;
        $readingMonth = $readingDate ? $readingDate->format('F Y') : Carbon::now()->format('F Y');
        $baseDate = $readingDate ? Carbon::parse($readingDate) : Carbon::now();
        $dueDate = $baseDate->copy()->addMonth()->day(5)->format('d M Y');
        $paymentStatus = $this->getPaymentStatus($tenant);
        
        return [
            'estate_name' => $unit && $unit->estate ? $unit->estate->name : 'N/A',
            'month' => $readingMonth,
            'water_consumption' => $reading ? (float) $reading->consumption : 0,
            'prev_read' => $reading ? (float) $reading->previous_reading : 0,
            'curr_read' => $reading ? (float) $reading->current_reading : 0,
            'unit' => $unit->unit_number ?? '',
            'unit_number' => $unit->unit_number ?? '',
            'water_bill' => $reading ? number_format((float) $reading->charge, 2) : '0.00',
            'due_date' => $dueDate,
            'payment_status' => ucfirst($paymentStatus),
            'status' => ucfirst($paymentStatus),
            'name' => $tenant->user->name ?? 'Tenant',
        ];
    }
    
    /**
     * Get recipients with validation
     */
    public function getRecipientsWithValidation(array $filters = []): array
    {
        $tenants = $this->getWaterBillRecipients(
            $filters['estate_id'] ?? null,
            $filters
        );
        
        $valid = [];
        $invalid = [];
        $otherNetwork = [];
        
        foreach ($tenants as $tenant) {
            $phone = $tenant['phone'] ?? '';
            if (preg_match('/^2547[0-9]{8}$/', $phone)) {
                $valid[] = $tenant;
            } elseif (!empty($phone)) {
                $otherNetwork[] = $tenant;
            } else {
                $invalid[] = $tenant;
            }
        }
        
        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'other_network' => $otherNetwork,
        ];
    }
    
    /**
     * Create a new campaign – also creates a campaign in KenyaSMS
     */
    public function createCampaign(array $data): SmsCampaign
    {
        DB::beginTransaction();
        
        try {
            $recipients = $this->getWaterBillRecipients(
                $data['filters']['estate_id'] ?? null,
                $data['filters'] ?? []
            );
            
            if (empty($recipients)) {
                throw new \Exception('No recipients found for the given filters.');
            }
            
            $template = SmsTemplate::find($data['template_id']);
            if (!$template) {
                throw new \Exception('Template not found.');
            }
            
            $kenyaRecipients = [];
            foreach ($recipients as $recipient) {
                if (!empty($recipient['phone']) && !empty($recipient['placeholders'])) {
                    $kenyaRecipients[] = [
                        'phone' => $recipient['phone'],
                        'variables' => $recipient['placeholders'],
                    ];
                }
            }
            
            if (empty($kenyaRecipients)) {
                throw new \Exception('No valid recipients with placeholders for KenyaSMS.');
            }
            
            $senderId = env('KENYASMS_SENDER_ID', 'TextSMS');
            $messageType = $data['campaign_type'] ?? 'general';
            $messageType = ($messageType === 'promotional') ? 'promotional' : 'transactional';
            $scheduleAt = $data['scheduled_at'] ?? null;
            $callbackUrl = env('KENYASMS_WEBHOOK_URL');
            
            $kenyaResult = $this->kenyaSMS->sendPersonalizedCampaign(
                $senderId,
                $messageType,
                $template->content,
                $kenyaRecipients,
                $scheduleAt,
                $callbackUrl
            );
            
            if (!$kenyaResult['success']) {
                throw new \Exception('Failed to create campaign in KenyaSMS: ' . ($kenyaResult['error'] ?? 'Unknown error'));
            }
            
            $campaign = SmsCampaign::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'template_id' => $data['template_id'],
                'filters' => json_encode($data['filters'] ?? []),
                'status' => 'pending',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'campaign_type' => $data['campaign_type'] ?? 'general',
                'created_by' => auth()->id(),
                'total_recipients' => count($recipients),
                'sent_count' => 0,
                'failed_count' => 0,
                'delivered_count' => 0,
                'kenyasms_campaign_id' => $kenyaResult['campaign_id'],
            ]);
            
            foreach ($recipients as $recipient) {
                $personalizedMessage = $this->personalizeMessage(
                    $template->content,
                    $recipient['placeholders'] ?? []
                );
                
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $recipient['tenant_id'],
                    'phone_number' => $recipient['phone'],
                    'message' => $personalizedMessage,
                    'status' => 'pending',
                    'message_id' => $kenyaResult['campaign_id'] ?? null,
                ]);
            }
            
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
     * Get water bill recipients
     */
    protected function getWaterBillRecipients($estateId, $filters = []): array
    {
        if (!$estateId) {
            return [];
        }
        
        $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
            ->get()
            ->map(function ($tenant) use ($estateId, $filters) {
                $tenancy = $tenant->activeTenancy;
                $unit = $tenancy ? $tenancy->unit : null;
                
                if (!$unit || $unit->estate_id != $estateId) {
                    return null;
                }
                
                if (!$tenant->user || !$tenant->user->phone) {
                    return null;
                }
                
                $latestWaterReading = $unit ? WaterReading::where('unit_id', $unit->id)
                    ->latest('reading_date')
                    ->first() : null;
                
                if (!$latestWaterReading) {
                    return null;
                }
                
                $paymentStatus = $this->getPaymentStatus($tenant);
                
                if (isset($filters['invoice_status']) && $filters['invoice_status'] !== 'all') {
                    if ($paymentStatus !== $filters['invoice_status']) {
                        return null;
                    }
                }
                
                $placeholders = $this->buildPlaceholders($tenant, $latestWaterReading);
                
                return [
                    'tenant_id' => $tenant->id,
                    'unit_id' => $unit->id ?? null,
                    'tenant_name' => $tenant->user->name ?? 'N/A',
                    'phone' => $this->formatPhone($tenant->user->phone),
                    'unit_number' => $unit->unit_number ?? '',
                    'reading_date' => $latestWaterReading->reading_date,
                    'previous_reading' => (float) $latestWaterReading->previous_reading,
                    'current_reading' => (float) $latestWaterReading->current_reading,
                    'consumption' => (float) $latestWaterReading->consumption,
                    'water_bill' => (float) $latestWaterReading->charge,
                    'payment_status' => $paymentStatus,
                    'due_date' => $placeholders['due_date'],
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
     * Format phone number
     */
    protected function formatPhone($phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        return $phone;
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
        
        $message = str_replace('\n', "\n", $message);
        $message = str_replace('\\n', "\n", $message);
        $message = str_replace("\r\n", "\n", $message);
        $message = str_replace("\r", "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        $message = preg_replace('/[ \t]+/', ' ', $message);
        
        if (preg_match_all('/\{\{([^}]+)\}\}/', $message, $matches)) {
            Log::warning('Unreplaced placeholders found', ['placeholders' => $matches[1], 'message' => $message]);
        }
        
        return $message;
    }
    
    /**
     * Send a campaign
     */
    public function sendCampaign($campaignId)
    {
        $campaign = SmsCampaign::findOrFail($campaignId);
        
        if (!in_array($campaign->status, ['pending', 'scheduled'])) {
            return ['error' => 'Campaign cannot be sent in current status: ' . $campaign->status];
        }

        if (!$campaign->kenyasms_campaign_id) {
            return ['error' => 'Campaign has no KenyaSMS campaign ID. Please recreate the campaign.'];
        }

        CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->update(['attempted_at' => now()]);

        $campaign->status = 'sending';
        $campaign->save();

        $statusResult = $this->kenyaSMS->getCampaignStatus($campaign->kenyasms_campaign_id);

        if (!$statusResult['success']) {
            Log::error('Failed to fetch campaign status from KenyaSMS', [
                'campaign_id' => $campaign->id,
                'kenyasms_campaign_id' => $campaign->kenyasms_campaign_id,
                'error' => $statusResult['error'] ?? 'Unknown'
            ]);
        } else {
            $data = $statusResult['data'];
            $campaign->sent_count = $data['sent'] ?? 0;
            $campaign->failed_count = $data['failed'] ?? 0;
            $campaign->delivered_count = $data['delivered'] ?? 0;
            
            if (isset($data['status']) && in_array($data['status'], ['completed', 'failed'])) {
                $campaign->status = $data['status'];
            }
            $campaign->save();
        }

        return [
            'success' => true,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'delivered' => $campaign->delivered_count,
            'kenyasms_campaign_id' => $campaign->kenyasms_campaign_id,
        ];
    }
    
    /**
     * Preserve message format (legacy)
     */
    protected function preserveMessageFormat($message): string
    {
        $message = str_replace("\r\n", "\n", $message);
        $message = str_replace("\r", "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        return $message;
    }
    
    /**
     * Retry failed messages – now use syncCampaignStatus
     */
    public function retryFailed($campaignId)
    {
        return $this->syncCampaignStatus($campaignId);
    }
    
    /**
     * Get invalid recipients
     */
    public function getInvalidRecipients(array $filters = []): array
    {
        $tenants = $this->getWaterBillRecipients(
            $filters['estate_id'] ?? null,
            $filters
        );
        
        $invalid = [];
        foreach ($tenants as $tenant) {
            $phone = $tenant['phone'] ?? '';
            if (!preg_match('/^2547[0-9]{8}$/', $phone)) {
                $invalid[] = [
                    'id' => $tenant['tenant_id'],
                    'name' => $tenant['tenant_name'] ?? 'Unknown',
                    'phone' => $phone,
                    'unit_number' => $tenant['unit_number'] ?? 'N/A',
                    'estate_name' => $tenant['placeholders']['estate_name'] ?? 'N/A',
                    'error' => empty($phone) ? 'Missing phone number' : 'Invalid Safaricom number',
                ];
            }
        }
        return $invalid;
    }
    
    /**
     * Get other network recipients
     */
    public function getOtherNetworkRecipients(array $filters = []): array
    {
        $tenants = $this->getWaterBillRecipients(
            $filters['estate_id'] ?? null,
            $filters
        );
        
        $other = [];
        foreach ($tenants as $tenant) {
            $phone = $tenant['phone'] ?? '';
            if (!empty($phone) && !preg_match('/^2547[0-9]{8}$/', $phone)) {
                $other[] = [
                    'id' => $tenant['tenant_id'],
                    'name' => $tenant['tenant_name'] ?? 'Unknown',
                    'phone' => $phone,
                    'unit_number' => $tenant['unit_number'] ?? 'N/A',
                    'estate_name' => $tenant['placeholders']['estate_name'] ?? 'N/A',
                    'error' => 'Other network (Airtel/Telkom)',
                ];
            }
        }
        return $other;
    }
  /**
 * Sync individual recipient statuses using KenyaSMS campaign logs
 * Stores the full log in provider_response as JSON.
 */
protected function syncRecipientStatuses($campaignId, $kenyasmsCampaignId)
{
    $logsResult = $this->kenyaSMS->getCampaignLogs($kenyasmsCampaignId);
    
    if (!$logsResult['success']) {
        Log::warning('Failed to fetch campaign logs', ['campaign_id' => $campaignId]);
        return 0;
    }
    
    $logs = $logsResult['logs'];
    $updated = 0;
    
    foreach ($logs as $log) {
        $newStatus = $this->mapProviderStatus($log['status'] ?? 'unknown');
        
        // Build update data – store full log as JSON
        $updateData = [
            'status' => $newStatus,
            'provider_status' => $log['status'] ?? null,
            'provider_response' => json_encode($log),
            'updated_at' => now(),
        ];
        
        // Capture sent_at if present
        if (isset($log['sent'])) {
            $updateData['sent_at'] = $log['sent'];
        }
        
        // Capture error for failed
        if ($newStatus === 'failed' && isset($log['error_code'])) {
            $updateData['error_message'] = $log['error_code'];
        }
        
        // Use Query Builder (safe quoting)
        $affected = DB::table('campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('phone_number', $log['recipient'])
            ->update($updateData);
        
        if ($affected) {
            $updated++;
        }
    }
    
    Log::info('Synced recipient statuses from logs', [
        'campaign_id' => $campaignId,
        'updated' => $updated,
        'total_logs' => count($logs),
    ]);
    
    return $updated;
}
    /**
     * Map provider status to our internal status
     */
    protected function mapProviderStatus($providerStatus): string
    {
        $map = [
            'delivered' => 'delivered',
            'sent' => 'sent',
            'failed' => 'failed',
            'undelivered' => 'failed',
            'rejected' => 'failed',
            'queued' => 'pending',
            'pending' => 'pending',
        ];
        return $map[$providerStatus] ?? 'pending';
    }
    
    /**
     * Sync campaign status – fetches aggregated stats AND individual logs
     */
    public function syncCampaignStatus($campaignId): array
    {
        $campaign = SmsCampaign::findOrFail($campaignId);
        
        if (!$campaign->kenyasms_campaign_id) {
            return [
                'success' => false,
                'error' => 'Campaign has no KenyaSMS campaign ID.'
            ];
        }
        
        // If sandbox is enabled, skip API calls
        if (env('KENYASMS_SANDBOX', true)) {
            return [
                'success' => true,
                'sent' => $campaign->sent_count,
                'failed' => $campaign->failed_count,
                'delivered' => $campaign->delivered_count ?? 0,
                'status' => $campaign->status,
                'data' => [
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                    'delivered' => $campaign->delivered_count ?? 0,
                    'status' => $campaign->status,
                ],
                'sandbox' => true,
                'message' => 'Sandbox mode: using local counts.'
            ];
        }
        
        // Production: fetch aggregated stats
        $statusResult = $this->kenyaSMS->getCampaignStatus($campaign->kenyasms_campaign_id);
        
        if (!$statusResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to get status from KenyaSMS: ' . ($statusResult['error'] ?? 'Unknown error')
            ];
        }
        
        $data = $statusResult['data'];
        $campaign->sent_count = $data['sent'] ?? 0;
        $campaign->failed_count = $data['failed'] ?? 0;
        $campaign->delivered_count = $data['delivered'] ?? 0;
        
        if (isset($data['status']) && in_array($data['status'], ['completed', 'failed', 'cancelled'])) {
            $campaign->status = $data['status'];
        }
        $campaign->save();
        
        // 🆕 Sync per-recipient logs
        $this->syncRecipientStatuses($campaign->id, $campaign->kenyasms_campaign_id);
        
        return [
            'success' => true,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'delivered' => $campaign->delivered_count,
            'status' => $campaign->status,
            'data' => $data,
        ];
    }
    
    /**
     * RESEND PENDING – uses personalized endpoint
     */
    public function resendPending($campaignId)
    {
        $campaign = SmsCampaign::findOrFail($campaignId);
        
        $pendingRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->get();
        
        if ($pendingRecipients->isEmpty()) {
            return ['error' => 'No pending messages to resend.'];
        }
        
        $template = SmsTemplate::find($campaign->template_id);
        if (!$template) {
            return ['error' => 'Template not found.'];
        }
        
        $kenyaRecipients = [];
        foreach ($pendingRecipients as $recipient) {
            $tenant = Tenant::with(['user', 'activeTenancy.unit'])->find($recipient->tenant_id);
            if (!$tenant) {
                continue;
            }
            $placeholders = $this->buildPlaceholders($tenant);
            $kenyaRecipients[] = [
                'phone' => $recipient->phone_number,
                'variables' => $placeholders,
            ];
        }
        
        if (empty($kenyaRecipients)) {
            return ['error' => 'No valid recipients with placeholders.'];
        }
        
        CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->update(['attempted_at' => now()]);
        
        $senderId = env('KENYASMS_SENDER_ID', 'TextSMS');
        $messageType = $campaign->campaign_type === 'promotional' ? 'promotional' : 'transactional';
        $callbackUrl = env('KENYASMS_WEBHOOK_URL');
        
        $kenyaResult = $this->kenyaSMS->sendPersonalizedCampaign(
            $senderId,
            $messageType,
            $template->content,
            $kenyaRecipients,
            null,
            $callbackUrl
        );
        
        if (!$kenyaResult['success']) {
            CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error_message' => 'KenyaSMS campaign failed: ' . ($kenyaResult['error'] ?? 'Unknown')
                ]);
            return ['error' => 'Failed to resend: ' . ($kenyaResult['error'] ?? 'Unknown')];
        }
        
        if ($kenyaResult['campaign_id']) {
            $campaign->kenyasms_campaign_id = $kenyaResult['campaign_id'];
            $campaign->save();
        }
        
        CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => $kenyaResult['campaign_id'] ?? null,
            ]);
        
        $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->count();
        $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->count();
        $campaign->delivered_count = 0;
        $campaign->save();
        
        if ($campaign->failed_count == 0 && $campaign->sent_count > 0) {
            $campaign->status = 'completed';
        } else {
            $campaign->status = 'sending';
        }
        $campaign->save();
        
        return [
            'success' => true,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'total' => count($kenyaRecipients),
            'kenyasms_campaign_id' => $campaign->kenyasms_campaign_id,
            'delivered' => $campaign->delivered_count,
        ];
    }
    
    /**
     * Render template with tenant data
     */
    public function renderTemplate($templateContent, $tenant)
    {
        $placeholders = $this->buildPlaceholders($tenant);
        return $this->personalizeMessage($templateContent, $placeholders);
    }
    
    /**
     * Render message for tenant
     */
    public function renderMessage($templateContent, $tenant)
    {
        return $this->renderTemplate($templateContent, $tenant);
    }
}