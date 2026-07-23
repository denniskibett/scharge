<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\CampaignRecipient;
use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\SmsTemplate;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Modules\SMS\Helpers\PhoneHelper;
use App\Modules\SMS\Services\KenyaSMS;

class CampaignService
{
    protected $smsService;
    protected $kenyaSms;
    protected $phoneColumn;

    public function __construct(SMSService $smsService, KenyaSMS $kenyaSms)
    {
        $this->smsService = $smsService;
        $this->kenyaSms = $kenyaSms;
        $this->phoneColumn = $this->detectPhoneColumn();
    }

    /**
     * Detect the correct phone column name from users table
     */
    protected function detectPhoneColumn()
    {
        $columns = Schema::getColumnListing('users');
        
        $possibleColumns = ['phone', 'phone_number', 'mobile', 'mobile_number', 'contact'];
        
        foreach ($possibleColumns as $col) {
            if (in_array($col, $columns)) {
                Log::info('Detected phone column in users table: ' . $col);
                return $col;
            }
        }
        
        return 'phone';
    }

    /**
     * Get recipients with validation status
     * Returns array with valid, invalid, and other_network recipients
     */
    public function getRecipientsWithValidation(array $filters)
    {
        Log::info('CampaignService::getRecipientsWithValidation called', [
            'filters' => $filters
        ]);

        // Build the query - ACTIVE TENANCIES ONLY
        $query = Tenant::query()
            ->join('tenancies', 'tenancies.tenant_id', '=', 'tenants.id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('users', 'users.id', '=', 'tenants.user_id')
            ->select(
                'tenants.*',
                'tenancies.id as tenancy_id',
                'tenancies.unit_id',
                'units.unit_number',
                'units.estate_id as unit_estate_id',
                'users.' . $this->phoneColumn . ' as phone',
                'users.name as user_name',
                'users.email'
            )
            ->where('tenancies.status', 'active');

        // Apply filters
        if (!empty($filters['estate_id'])) {
            $query->where('units.estate_id', $filters['estate_id']);
            Log::info('Filtering by estate_id: ' . $filters['estate_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('units.company_id', $filters['company_id']);
            Log::info('Filtering by company_id: ' . $filters['company_id']);
        }

        if (!empty($filters['invoice_status'])) {
            $status = $filters['invoice_status'];
            Log::info('Filtering by invoice status: ' . $status);
            
            $query->whereHas('tenancies.invoices', function($q) use ($status) {
                if ($status === 'unpaid' || $status === 'overdue') {
                    $q->whereIn('status', ['unpaid', 'draft']);
                } elseif ($status === 'paid') {
                    $q->where('status', 'paid');
                } elseif ($status === 'partial') {
                    $q->where('status', 'partial');
                }
            });
        }

        $phoneColumn = $this->phoneColumn;
        $query->whereNotNull('users.' . $phoneColumn);

        $query->with([
            'user',
            'tenancies' => function($q) {
                $q->where('status', 'active');
            },
            'tenancies.unit',
            'tenancies.unit.estate',
            'tenancies.invoices' => function($q) {
                $q->latest()->limit(1);
            }
        ]);

        $tenants = $query->get();
        
        // Categorize tenants using PhoneHelper
        $result = [
            'valid' => [],
            'invalid' => [],
            'other_network' => [],
            'total' => $tenants->count()
        ];

        foreach ($tenants as $tenant) {
            $phone = $tenant->phone;
            
            // Use PhoneHelper::getStatus() to categorize
            $status = PhoneHelper::getStatus($phone);
            
            if ($status === 'pending') {
                $tenant->formatted_phone = PhoneHelper::normalize($phone);
                $result['valid'][] = $tenant;
            } elseif ($status === 'other_network') {
                $tenant->formatted_phone = PhoneHelper::normalize($phone);
                $result['other_network'][] = $tenant;
            } else {
                // 'invalid' - includes empty phones and invalid formats
                $result['invalid'][] = $tenant;
            }
        }

        Log::info('getRecipientsWithValidation result', [
            'valid' => count($result['valid']),
            'invalid' => count($result['invalid']),
            'other_network' => count($result['other_network']),
            'total' => $result['total']
        ]);

        return $result;
    }

    /**
     * Get only valid Safaricom recipients
     */
    public function getRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['valid']);
    }

    /**
     * Get valid recipients for a campaign
     */
    public function getValidRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['valid']);
    }

    /**
     * Get invalid recipients for display
     */
    public function getInvalidRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['invalid']);
    }

    /**
     * Get other network recipients
     */
    public function getOtherNetworkRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['other_network']);
    }

    /**
     * Create a new campaign with automatic recipient creation
     * Creates recipients for ALL tenants with proper status
     */
    public function createCampaign(array $data)
    {
        Log::info('CampaignService::createCampaign called', [
            'data' => $data
        ]);

        // Get validated recipients
        $filters = $data['filters'] ?? [];
        $recipientsResult = $this->getRecipientsWithValidation($filters);
        
        // Get ALL recipients (valid, invalid, other_network)
        $allRecipients = array_merge(
            $recipientsResult['valid'],
            $recipientsResult['invalid'],
            $recipientsResult['other_network']
        );
        
        $data['total_recipients'] = count($allRecipients);

        // Store validation stats in the data
        $data['invalid_count'] = count($recipientsResult['invalid']);
        $data['other_network_count'] = count($recipientsResult['other_network']);
        $data['valid_count'] = count($recipientsResult['valid']);

        // Ensure filters is JSON encoded
        if (isset($data['filters']) && is_array($data['filters'])) {
            $data['filters'] = json_encode($data['filters']);
        }

        // Create the campaign
        $campaign = SmsCampaign::create($data);
        
        Log::info('Campaign created', [
            'id' => $campaign->id,
            'total_recipients' => count($allRecipients),
            'valid_recipients' => count($recipientsResult['valid']),
            'invalid_recipients' => count($recipientsResult['invalid']),
            'other_network' => count($recipientsResult['other_network'])
        ]);

        // Create recipient records for ALL recipients
        if (!empty($allRecipients)) {
            $this->createRecipientsForCampaign($campaign);
        }

        return $campaign;
    }

    /**
     * Create recipient records for a campaign
     * Creates for ALL tenants with proper status
     */
    public function createRecipientsForCampaign($campaign)
    {
        try {
            Log::info('Creating recipients for campaign', [
                'campaign_id' => $campaign->id
            ]);

            $filters = json_decode($campaign->filters, true) ?? [];
            
            // Get ALL categorized recipients
            $recipientsResult = $this->getRecipientsWithValidation($filters);
            
            // Combine all recipients
            $allRecipients = array_merge(
                $recipientsResult['valid'],
                $recipientsResult['invalid'],
                $recipientsResult['other_network']
            );
            
            Log::info('Total recipients found', [
                'valid' => count($recipientsResult['valid']),
                'invalid' => count($recipientsResult['invalid']),
                'other_network' => count($recipientsResult['other_network']),
                'total' => count($allRecipients)
            ]);

            if (empty($allRecipients)) {
                Log::warning('No recipients found for campaign', [
                    'campaign_id' => $campaign->id,
                    'filters' => $filters
                ]);
                return [
                    'created' => 0,
                    'failed' => 0,
                    'message' => 'No recipients found'
                ];
            }

            $createdCount = 0;
            $failedCount = 0;

            // Get template
            $template = SmsTemplate::find($campaign->template_id);
            $templateContent = $template ? $template->content : '';

            foreach ($allRecipients as $tenant) {
                try {
                    // Get phone and status using PhoneHelper
                    $phone = $tenant->phone;
                    $status = PhoneHelper::getStatus($phone);
                    
                    // Clean phone for storage
                    $cleanPhone = PhoneHelper::normalize($phone);
                    
                    // Render message with tenant data
                    $message = $this->renderMessage($templateContent, $tenant);

                    // Check if recipient already exists
                    $existing = CampaignRecipient::where('campaign_id', $campaign->id)
                        ->where('tenant_id', $tenant->id)
                        ->first();

                    if ($existing) {
                        Log::info('Recipient already exists', [
                            'tenant_id' => $tenant->id
                        ]);
                        continue;
                    }

                    // Create recipient record with correct status
                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'tenant_id' => $tenant->id,
                        'phone_number' => $cleanPhone ?: 'NO_PHONE',
                        'message' => $message,
                        'status' => $status, // pending, invalid, or other_network
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $createdCount++;
                    
                } catch (\Exception $e) {
                    Log::error('Failed to create recipient for tenant', [
                        'tenant_id' => $tenant->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $failedCount++;
                }
            }

            // Update the campaign with actual recipient count
            $campaign->total_recipients = $createdCount;
            $campaign->save();

            Log::info('Recipients created for campaign', [
                'campaign_id' => $campaign->id,
                'created' => $createdCount,
                'failed' => $failedCount,
                'valid' => count($recipientsResult['valid']),
                'invalid' => count($recipientsResult['invalid']),
                'other_network' => count($recipientsResult['other_network'])
            ]);

            return [
                'created' => $createdCount,
                'failed' => $failedCount,
                'valid' => count($recipientsResult['valid']),
                'invalid' => count($recipientsResult['invalid']),
                'other_network' => count($recipientsResult['other_network'])
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create recipients for campaign', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'created' => 0,
                'failed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Render message with tenant data
     */
    protected function renderMessage($templateContent, $tenant)
    {
        $message = $templateContent ?: "Water bill notification for {$tenant->user_name}";
        
        // Get invoice data if available
        $invoice = $tenant->tenancies->first()->invoices->first() ?? null;
        $waterBill = $invoice ? $invoice->total_amount : '0.00';
        $paymentStatus = $invoice ? $invoice->status : 'pending';
        $dueDate = $invoice ? $invoice->due_date : date('Y-m-d', strtotime('+14 days'));
        
        // Get estate name
        $estateName = $tenant->tenancies->first()->unit->estate->name ?? 'N/A';
        
        $replacements = [
            '{{name}}' => $tenant->user_name ?? 'Tenant',
            '{{unit}}' => $tenant->unit_number ?? 'N/A',
            '{{unit_number}}' => $tenant->unit_number ?? 'N/A',
            '{{estate_name}}' => $estateName,
            '{{month}}' => date('F Y'),
            '{{water_bill}}' => number_format($waterBill, 2),
            '{{water_consumption}}' => $invoice ? $invoice->consumption ?? '0' : '0',
            '{{prev_read}}' => $invoice ? $invoice->previous_reading ?? '0' : '0',
            '{{curr_read}}' => $invoice ? $invoice->current_reading ?? '0' : '0',
            '{{due_date}}' => $dueDate,
            '{{payment_status}}' => $paymentStatus,
            '{{status}}' => $paymentStatus,
            '{{phone}}' => $tenant->phone ?? ''
        ];

        foreach ($replacements as $key => $value) {
            $message = str_replace($key, $value, $message);
        }

        return $message;
    }

    /**
     * Format phone number for SMS (legacy - use PhoneHelper instead)
     */
    protected function formatPhoneNumber($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        if (substr($phone, 0, 2) === '07') {
            $phone = '254' . substr($phone, 1);
        }
        
        if (strlen($phone) === 9 || strlen($phone) === 10) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }

    /**
     * Send a campaign using KenyaSMS
     */
    public function sendCampaign($campaignId)
    {
        $campaign = SmsCampaign::with(['template'])->find($campaignId);
        
        if (!$campaign) {
            return ['error' => 'Campaign not found'];
        }

        if ($campaign->status === 'sending') {
            return ['error' => 'Campaign is already sending'];
        }

        try {
            DB::beginTransaction();

            // Get pending recipients
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->get();

            if ($recipients->isEmpty()) {
                $campaign->status = 'failed';
                $campaign->save();
                DB::commit();
                return ['error' => 'No pending recipients found for this campaign'];
            }

            // Update campaign status
            $campaign->status = 'sending';
            $campaign->sent_at = now();
            $campaign->save();

            // Prepare recipients for KenyaSMS
            $preparedRecipients = [];
            foreach ($recipients as $recipient) {
                $preparedRecipients[] = [
                    'id' => $recipient->id,
                    'phone' => $recipient->phone_number,
                    'message' => $recipient->message,
                    'variables' => [
                        'name' => $recipient->tenant->user->name ?? 'Tenant',
                        'unit' => $recipient->tenant->unit_number ?? 'N/A',
                    ]
                ];
            }

            // Get template content
            $templateContent = $campaign->template ? $campaign->template->content : '';

            // Determine message type
            $messageType = $campaign->campaign_type === 'promotional' ? 'promotional' : 'transactional';

            // Check quiet hours for promotional messages
            if ($messageType === 'promotional' && $this->kenyaSms->isQuietHours()) {
                Log::warning('Promotional campaign blocked during quiet hours', [
                    'campaign_id' => $campaign->id
                ]);
                
                $campaign->status = 'failed';
                $campaign->save();
                DB::commit();
                
                return ['error' => 'Promotional messages cannot be sent during quiet hours (20:00 - 08:00 EAT)'];
            }

            // Send using KenyaSMS
            $result = $this->kenyaSms->sendPersonalized(
                $templateContent,
                $preparedRecipients,
                $messageType,
                $campaign->id
            );

            if ($result['success']) {
                $campaign->sent_count = $result['data']['sent'] ?? 0;
                $campaign->failed_count = $result['data']['failed'] ?? 0;
                $campaign->status = $campaign->failed_count > 0 && $campaign->sent_count == 0 ? 'failed' : 'completed';
                $campaign->save();

                Log::info('Campaign sent successfully via KenyaSMS', [
                    'campaign_id' => $campaign->id,
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'total_recipients' => count($recipients),
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                    'provider' => 'KenyaSMS'
                ];
            } else {
                // Mark all recipients as failed
                foreach ($recipients as $recipient) {
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $result['error'] ?? 'Provider error'
                    ]);
                }

                $campaign->status = 'failed';
                $campaign->save();
                DB::commit();

                Log::error('Campaign send failed via KenyaSMS', [
                    'campaign_id' => $campaign->id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                return ['error' => 'Failed to send campaign: ' . ($result['error'] ?? 'Unknown error')];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Campaign send failed: ' . $e->getMessage());
            $campaign->status = 'failed';
            $campaign->save();
            return ['error' => 'Failed to send campaign: ' . $e->getMessage()];
        }
    }

    /**
     * Send individual recipients
     */
    protected function sendRecipients(SmsCampaign $campaign)
    {
        $recipients = $campaign->recipients()->where('status', 'pending')->get();
        
        Log::info('Sending recipients', [
            'campaign_id' => $campaign->id,
            'count' => $recipients->count()
        ]);

        foreach ($recipients as $recipient) {
            try {
                // Use KenyaSMS for sending
                $result = $this->kenyaSms->sendOne(
                    $recipient->phone_number,
                    $recipient->message,
                    $campaign->campaign_type ?? 'transactional',
                    $campaign->id
                );
                
                if ($result['success'] ?? false) {
                    $recipient->status = 'sent';
                    $recipient->sent_at = now();
                    $recipient->message_id = $result['message_id'] ?? null;
                    $campaign->increment('sent_count');
                    Log::info('SMS sent to ' . $recipient->phone_number);
                } else {
                    $recipient->status = 'failed';
                    $recipient->error_message = $result['error'] ?? 'Unknown error';
                    $campaign->increment('failed_count');
                    Log::warning('SMS failed to ' . $recipient->phone_number);
                }
                
                $recipient->save();
                
            } catch (\Exception $e) {
                $recipient->status = 'failed';
                $recipient->error_message = $e->getMessage();
                $recipient->save();
                $campaign->increment('failed_count');
                Log::error('Failed to send SMS to ' . $recipient->phone_number . ': ' . $e->getMessage());
            }
        }

        if ($campaign->failed_count > 0 && $campaign->sent_count == 0) {
            $campaign->status = 'failed';
        } else {
            $campaign->status = 'completed';
        }
        
        $campaign->sent_at = now();
        $campaign->save();

        Log::info('Campaign completed', [
            'campaign_id' => $campaign->id,
            'status' => $campaign->status,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count
        ]);
    }

    /**
     * Retry failed messages in a campaign
     */
    public function retryFailed($campaignId)
    {
        $campaign = SmsCampaign::find($campaignId);
        
        if (!$campaign) {
            return ['error' => 'Campaign not found'];
        }

        $failedRecipients = $campaign->recipients()
            ->where('status', 'failed')
            ->get();

        if ($failedRecipients->isEmpty()) {
            return ['error' => 'No failed messages to retry'];
        }

        $retryCount = 0;
        $retryFailed = 0;
        
        foreach ($failedRecipients as $recipient) {
            try {
                // Reset status to pending and retry
                $recipient->status = 'pending';
                $recipient->error_message = null;
                $recipient->save();

                // Send via KenyaSMS
                $result = $this->kenyaSms->sendOne(
                    $recipient->phone_number,
                    $recipient->message,
                    $campaign->campaign_type ?? 'transactional',
                    $campaign->id
                );
                
                if ($result['success'] ?? false) {
                    $recipient->status = 'sent';
                    $recipient->sent_at = now();
                    $recipient->message_id = $result['message_id'] ?? null;
                    $recipient->error_message = null;
                    $campaign->increment('sent_count');
                    $campaign->decrement('failed_count');
                    $retryCount++;
                    Log::info('Retry successful for ' . $recipient->phone_number);
                } else {
                    $recipient->status = 'failed';
                    $recipient->error_message = $result['error'] ?? 'Retry failed';
                    $retryFailed++;
                    Log::warning('Retry failed for ' . $recipient->phone_number);
                }
                
                $recipient->save();
                
            } catch (\Exception $e) {
                Log::error('Retry failed for recipient ' . $recipient->id . ': ' . $e->getMessage());
                $recipient->status = 'failed';
                $recipient->error_message = $e->getMessage();
                $recipient->save();
                $retryFailed++;
            }
        }

        // Update campaign status
        if ($campaign->failed_count == 0 && $campaign->sent_count > 0) {
            $campaign->status = 'completed';
        } elseif ($campaign->failed_count > 0 && $campaign->sent_count == 0) {
            $campaign->status = 'failed';
        } else {
            $campaign->status = 'completed';
        }
        $campaign->save();

        return [
            'success' => true,
            'retried' => $retryCount,
            'failed' => $retryFailed,
            'total_failed' => $failedRecipients->count()
        ];
    }

    /**
     * Sync campaign status from KenyaSMS
     */
    public function syncCampaignStatus($campaignId)
    {
        $campaign = SmsCampaign::find($campaignId);
        
        if (!$campaign) {
            return ['error' => 'Campaign not found'];
        }

        $recipients = $campaign->recipients()
            ->whereNotNull('message_id')
            ->whereIn('status', ['pending', 'sent', 'sending'])
            ->get();

        if ($recipients->isEmpty()) {
            return ['error' => 'No recipients with message IDs to sync'];
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
                
                // Map to internal status
                $internalStatus = $this->kenyaSms->mapStatus($newStatus);
                
                if ($internalStatus !== $recipient->status) {
                    $recipient->status = $internalStatus;
                    $recipient->provider_status = $newStatus;
                    $recipient->provider_response = json_encode($status['response'] ?? []);
                    
                    if ($internalStatus === 'delivered') {
                        $recipient->sent_at = now();
                        $campaign->increment('sent_count');
                    } elseif ($internalStatus === 'failed') {
                        $campaign->increment('failed_count');
                    }
                    
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
     * Update campaign counters
     */
    protected function updateCampaignCounters($campaign)
    {
        $counts = CampaignRecipient::where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
                COUNT(CASE WHEN status IN ('pending', 'sent', 'sending') THEN 1 END) as pending_count
            ")
            ->first();

        $campaign->sent_count = $counts->delivered_count ?? 0;
        $campaign->failed_count = $counts->failed_count ?? 0;
        
        // Determine status
        if ($counts->pending_count > 0) {
            $campaign->status = 'sending';
        } elseif ($counts->failed_count > 0 && $counts->delivered_count > 0) {
            $campaign->status = 'completed';
        } elseif ($counts->failed_count > 0 && $counts->delivered_count == 0) {
            $campaign->status = 'failed';
        } elseif ($counts->delivered_count > 0) {
            $campaign->status = 'completed';
        }
        
        $campaign->save();
    }

    /**
     * Preview campaign recipients with rendered messages
     */
    public function previewCampaign($templateId, array $filters)
    {
        $template = SmsTemplate::find($templateId);
        if (!$template) {
            return ['error' => 'Template not found'];
        }

        $recipients = $this->getRecipients($filters);
        $previews = [];

        foreach ($recipients as $tenant) {
            $message = $this->renderMessage($template->content, $tenant);
            
            // Get invoice for additional data
            $invoice = $tenant->tenancies->first()->invoices->first() ?? null;
            
            $previews[] = [
                'tenant_id' => $tenant->id,
                'name' => $tenant->user_name ?? $tenant->name ?? 'Unknown',
                'phone' => $tenant->phone ?? '',
                'unit_number' => $tenant->unit_number ?? 'N/A',
                'estate_name' => $tenant->tenancies->first()->unit->estate->name ?? 'N/A',
                'message' => $message,
                'payment_status' => $invoice ? $invoice->status : 'pending',
                'water_bill' => $invoice ? number_format($invoice->total_amount, 2) : '0.00',
                'due_date' => $invoice ? $invoice->due_date : date('Y-m-d', strtotime('+14 days')),
                'message_parts' => $this->kenyaSms->getMessageParts($message),
                'estimated_cost' => $this->kenyaSms->getEstimatedCost($message),
            ];
        }

        return $previews;
    }

    /**
     * Add a recipient to a campaign
     */
    public function addRecipient($campaignId, $tenantId, array $data)
    {
        return CampaignRecipient::create([
            'campaign_id' => $campaignId,
            'tenant_id' => $tenantId,
            'phone_number' => $data['phone_number'] ?? '',
            'message' => $data['message'] ?? '',
            'status' => $data['status'] ?? 'pending',
            'sent_at' => $data['sent_at'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'message_id' => $data['message_id'] ?? null,
        ]);
    }

    /**
     * Get KenyaSMS balance
     */
    public function getBalance()
    {
        return $this->kenyaSms->getBalance();
    }

    /**
     * Get message parts count
     */
    public function getMessageParts($message)
    {
        return $this->kenyaSms->getMessageParts($message);
    }

    /**
     * Get estimated cost
     */
    public function getEstimatedCost($message, $type = null)
    {
        return $this->kenyaSms->getEstimatedCost($message, $type);
    }
}