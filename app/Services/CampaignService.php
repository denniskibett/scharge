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

class CampaignService
{
    protected $smsService;
    protected $phoneColumn;

    public function __construct(SMSService $smsService)
    {
        $this->smsService = $smsService;
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
     * Get recipients with validation status (Safaricom only)
     * Returns array with valid, invalid, and other_network recipients
     */
    public function getRecipientsWithValidation(array $filters)
    {
        Log::info('CampaignService::getRecipientsWithValidation called', [
            'filters' => $filters
        ]);

        // Build the query directly here - NO recursion
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
        $query->whereNotNull('users.' . $phoneColumn)
              ->where('users.' . $phoneColumn, '!=', '')
              ->where('users.' . $phoneColumn, '!=', 'null');

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
            
            if (empty($phone)) {
                $result['invalid'][] = $tenant;
                continue;
            }

            // Clean the phone number
            $cleanedPhone = PhoneHelper::clean($phone);
            
            // Check if it's a valid Safaricom number
            if (PhoneHelper::isValid($cleanedPhone)) {
                $tenant->formatted_phone = $cleanedPhone;
                $result['valid'][] = $tenant;
            } else {
                // Check if it's a valid Kenyan number but not Safaricom
                $normalized = PhoneHelper::normalize($phone);
                if ($normalized && preg_match('/^254[7-9][0-9]{8}$/', $normalized)) {
                    // It's a valid Kenyan number but not Safaricom (Airtel, Telkom)
                    $tenant->formatted_phone = $normalized;
                    $result['other_network'][] = $tenant;
                } else {
                    // Invalid format
                    $result['invalid'][] = $tenant;
                }
            }
        }

        Log::info('getRecipientsWithValidation result', [
            'valid' => count($result['valid']),
            'invalid' => count($result['invalid']),
            'other_network' => count($result['other_network'])
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
     * Only includes valid Safaricom numbers
     */
    public function createCampaign(array $data)
    {
        Log::info('CampaignService::createCampaign called', [
            'data' => $data
        ]);

        // Get validated recipients
        $filters = $data['filters'] ?? [];
        $recipientsResult = $this->getRecipientsWithValidation($filters);
        
        // Only use valid recipients for the campaign
        $validRecipients = collect($recipientsResult['valid']);
        $data['total_recipients'] = $validRecipients->count();

        // Store validation stats in the data
        $data['invalid_count'] = count($recipientsResult['invalid']);
        $data['other_network_count'] = count($recipientsResult['other_network']);

        // Ensure filters is JSON encoded
        if (isset($data['filters']) && is_array($data['filters'])) {
            $data['filters'] = json_encode($data['filters']);
        }

        // Create the campaign
        $campaign = SmsCampaign::create($data);
        
        Log::info('Campaign created', [
            'id' => $campaign->id,
            'valid_recipients' => $validRecipients->count(),
            'invalid_recipients' => count($recipientsResult['invalid']),
            'other_network' => count($recipientsResult['other_network'])
        ]);

        // Create recipient records for valid recipients only
        if ($validRecipients->isNotEmpty()) {
            $this->createRecipientsForCampaign($campaign);
        }

        return $campaign;
    }

    /**
     * Create recipient records for a campaign
     * Only creates for valid Safaricom numbers
     */
    public function createRecipientsForCampaign($campaign)
    {
        try {
            Log::info('Creating recipients for campaign', [
                'campaign_id' => $campaign->id
            ]);

            $filters = json_decode($campaign->filters, true) ?? [];
            
            Log::info('Filters decoded', ['filters' => $filters]);
            
            // Get template
            $template = SmsTemplate::find($campaign->template_id);
            $templateContent = $template ? $template->content : '';

            Log::info('Template found', [
                'template_id' => $campaign->template_id,
                'has_content' => !empty($templateContent)
            ]);

            // Get ONLY valid Safaricom recipients
            $tenants = $this->getValidRecipients($filters);
            
            Log::info('Found valid tenants for campaign', [
                'count' => $tenants->count()
            ]);

            if ($tenants->isEmpty()) {
                Log::warning('No valid tenants found for campaign', [
                    'campaign_id' => $campaign->id,
                    'filters' => $filters
                ]);
                return [
                    'created' => 0,
                    'failed' => 0,
                    'message' => 'No valid Safaricom numbers found'
                ];
            }

            $createdCount = 0;
            $failedCount = 0;

            foreach ($tenants as $tenant) {
                try {
                    // Render message with tenant data
                    $message = $this->renderMessage($templateContent, $tenant);
                    
                    // Format phone number using PhoneHelper
                    $phone = PhoneHelper::clean($tenant->phone);

                    if (empty($phone)) {
                        $failedCount++;
                        continue;
                    }

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

                    // Create recipient record
                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'tenant_id' => $tenant->id,
                        'phone_number' => $phone,
                        'message' => $message,
                        'status' => 'pending',
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
                'failed' => $failedCount
            ]);

            return [
                'created' => $createdCount,
                'failed' => $failedCount
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
        
        $replacements = [
            '{{name}}' => $tenant->user_name ?? 'Tenant',
            '{{unit}}' => $tenant->unit_number ?? 'N/A',
            '{{unit_number}}' => $tenant->unit_number ?? 'N/A',
            '{{estate_name}}' => $tenant->estate->name ?? 'N/A',
            '{{month}}' => date('F Y'),
            '{{water_bill}}' => '0.00',
            '{{water_consumption}}' => '0',
            '{{prev_read}}' => '0',
            '{{curr_read}}' => '0',
            '{{due_date}}' => date('Y-m-d', strtotime('+14 days')),
            '{{payment_status}}' => 'pending',
            '{{status}}' => 'pending',
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
     * Send a campaign
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

            $filters = json_decode($campaign->filters, true) ?? [];
            
            Log::info('Sending campaign', [
                'campaign_id' => $campaign->id,
                'filters' => $filters
            ]);

            $tenants = $this->getRecipients($filters);
            
            if ($tenants->isEmpty()) {
                $campaign->status = 'failed';
                $campaign->save();
                DB::commit();
                return ['error' => 'No recipients found for this campaign'];
            }

            if ($campaign->total_recipients == 0) {
                $campaign->total_recipients = $tenants->count();
            }

            $campaign->status = 'sending';
            $campaign->save();

            $templateContent = '';
            if ($campaign->template_id) {
                $template = SmsTemplate::find($campaign->template_id);
                $templateContent = $template ? $template->content : '';
            }

            $recipients = [];
            foreach ($tenants as $tenant) {
                $message = $this->renderMessage($templateContent, $tenant);
                $phone = PhoneHelper::clean($tenant->phone);
                
                if (empty($phone)) {
                    continue;
                }
                
                $recipients[] = [
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $tenant->id,
                    'phone_number' => $phone,
                    'message' => $message,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            CampaignRecipient::insert($recipients);
            $campaign->total_recipients = count($recipients);
            $campaign->save();

            $this->sendRecipients($campaign);

            DB::commit();

            return [
                'success' => true,
                'total_recipients' => count($recipients),
                'sent' => $campaign->sent_count,
                'failed' => $campaign->failed_count
            ];

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
                $result = $this->smsService->sendSMS(
                    $recipient->phone_number,
                    $recipient->message,
                    $campaign->id,
                    $recipient->id
                );
                
                if ($result['success'] ?? false) {
                    $recipient->status = 'sent';
                    $recipient->sent_at = now();
                    $campaign->increment('sent_count');
                    Log::info('SMS sent to ' . $recipient->phone_number);
                } else {
                    $recipient->status = 'failed';
                    $recipient->error_message = $result['message'] ?? 'Unknown error';
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
        
        foreach ($failedRecipients as $recipient) {
            try {
                $result = $this->smsService->sendSMS(
                    $recipient->phone_number,
                    $recipient->message,
                    $campaign->id,
                    $recipient->id
                );
                
                if ($result['success'] ?? false) {
                    $recipient->status = 'sent';
                    $recipient->sent_at = now();
                    $recipient->error_message = null;
                    $campaign->increment('sent_count');
                    $campaign->decrement('failed_count');
                    $retryCount++;
                } else {
                    $recipient->error_message = $result['message'] ?? 'Retry failed';
                }
                
                $recipient->save();
                
            } catch (\Exception $e) {
                Log::error('Retry failed for recipient ' . $recipient->id . ': ' . $e->getMessage());
            }
        }

        if ($campaign->failed_count == 0) {
            $campaign->status = 'completed';
        }
        $campaign->save();

        return [
            'success' => true,
            'retried' => $retryCount,
            'total_failed' => $failedRecipients->count()
        ];
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
            $previews[] = [
                'tenant_id' => $tenant->id,
                'name' => $tenant->user_name ?? $tenant->name ?? 'Unknown',
                'phone' => $tenant->phone ?? '',
                'unit_number' => $tenant->unit_number ?? 'N/A',
                'estate_name' => $tenant->estate->name ?? 'N/A',
                'message' => $message,
                'payment_status' => 'pending',
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
}