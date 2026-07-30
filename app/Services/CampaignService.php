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
use Carbon\Carbon;

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

    public function getRecipientsWithValidation(array $filters)
    {
        Log::info('CampaignService::getRecipientsWithValidation called', ['filters' => $filters]);

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
            'tenancies.unit.waterReadings' => function($q) {
                $q->latest('reading_date')->limit(1);
            },
            'tenancies.invoices' => function($q) {
                $q->latest('created_at')->limit(1);
            }
        ]);

        $tenants = $query->get();
        
        $result = [
            'valid' => [],
            'invalid' => [],
            'other_network' => [],
            'total' => $tenants->count()
        ];

        foreach ($tenants as $tenant) {
            $phone = $tenant->phone;
            $status = PhoneHelper::getStatus($phone);
            if ($status === 'pending') {
                $tenant->formatted_phone = PhoneHelper::normalize($phone);
                $result['valid'][] = $tenant;
            } elseif ($status === 'other_network') {
                $tenant->formatted_phone = PhoneHelper::normalize($phone);
                $result['other_network'][] = $tenant;
            } else {
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

    public function getRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['valid']);
    }

    public function getValidRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['valid']);
    }

    public function getInvalidRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['invalid']);
    }

    public function getOtherNetworkRecipients(array $filters)
    {
        $result = $this->getRecipientsWithValidation($filters);
        return collect($result['other_network']);
    }

    public function createCampaign(array $data)
    {
        Log::info('CampaignService::createCampaign called', ['data' => $data]);

        $filters = $data['filters'] ?? [];
        $recipientsResult = $this->getRecipientsWithValidation($filters);
        $allRecipients = array_merge(
            $recipientsResult['valid'],
            $recipientsResult['invalid'],
            $recipientsResult['other_network']
        );
        $data['total_recipients'] = count($allRecipients);

        if (isset($data['filters']) && is_array($data['filters'])) {
            $data['filters'] = json_encode($data['filters']);
        }

        $campaign = SmsCampaign::create($data);
        
        if (!empty($allRecipients)) {
            $this->createRecipientsForCampaign($campaign);
        }

        return $campaign;
    }

    public function createRecipientsForCampaign($campaign)
    {
        try {
            Log::info('Creating recipients for campaign', ['campaign_id' => $campaign->id]);

            $filters = json_decode($campaign->filters, true) ?? [];
            $recipientsResult = $this->getRecipientsWithValidation($filters);
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
                return ['created' => 0, 'failed' => 0, 'message' => 'No recipients found'];
            }

            $createdCount = 0;
            $failedCount = 0;
            $template = SmsTemplate::find($campaign->template_id);
            $templateContent = $template ? $template->content : '';

            foreach ($allRecipients as $tenant) {
                try {
                    $phone = $tenant->phone;
                    $status = PhoneHelper::getStatus($phone);
                    $cleanPhone = PhoneHelper::normalize($phone);
                    $message = $this->renderMessage($templateContent, $tenant);

                    $existing = CampaignRecipient::where('campaign_id', $campaign->id)
                        ->where('tenant_id', $tenant->id)
                        ->first();

                    if ($existing) {
                        Log::info('Recipient already exists', ['tenant_id' => $tenant->id]);
                        continue;
                    }

                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'tenant_id' => $tenant->id,
                        'phone_number' => $cleanPhone ?: 'NO_PHONE',
                        'message' => $message,
                        'status' => $status,
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
     * Render message with tenant data - COMPLETE FIXED VERSION
     * Uses LATEST reading charge instead of invoice total
     * ALL .00 REMOVED
     * FIX: Unit number from tenancy relationship
     * FIX: Water bill from reading charge (not invoice)
     * FIX: Rate from reading (not invoice)
     */
    public function renderMessage($templateContent, $tenant)
    {
        $message = $templateContent ?: "Water bill reminder for {$tenant->user_name}";
        
        $tenancy = $tenant->tenancies->first();
        
        $unpaidInvoices = $tenancy ? $tenancy->invoices->filter(function($invoice) {
            return in_array($invoice->status, ['unpaid', 'draft', 'pending', 'overdue']);
        }) : collect();
        
        $unpaidCount = $unpaidInvoices->count();
        $unpaidTotal = $unpaidInvoices->sum('total_amount');
        
        $unpaidList = $unpaidInvoices->map(function($inv) {
            $month = $inv->billing_month ?? date('M Y', strtotime($inv->created_at ?? 'now'));
            return "{$month}: KES " . number_format($inv->total_amount, 0);
        })->implode(', ');
        
        $estateName = $tenancy ? $tenancy->unit->estate->name ?? 'N/A' : 'N/A';
        
        // FIX: Get the LATEST reading (not the first/oldest)
        $reading = $tenancy && $tenancy->unit 
            ? $tenancy->unit->waterReadings()->latest('reading_date')->first() 
            : null;
        
        $prevRead = $reading ? $reading->previous_reading : 0;
        $currRead = $reading ? $reading->current_reading : 0;
        $consumption = $reading ? $reading->consumption : 0;
        if ($consumption == 0 && $prevRead > 0 && $currRead > 0) {
            $consumption = $currRead - $prevRead;
        }
        
        // FIX: Use reading charge instead of invoice total (THIS IS THE KEY FIX!)
        $waterBill = $reading ? $reading->charge : 0;
        $rateApplied = $reading ? $reading->rate_applied : 0;
        
        // Get latest invoice for status only
        $latestInvoice = $tenancy ? $tenancy->invoices()->latest('created_at')->first() : null;
        $paymentStatus = $latestInvoice ? $latestInvoice->status : 'pending';
        
        $readingDate = $reading && $reading->reading_date ? Carbon::parse($reading->reading_date) : now();
        $billMonth = $readingDate->copy();
        $dueDate = $billMonth->copy()->addMonth()->startOfMonth()->addDays(4);
        $formattedBillMonth = $billMonth->format('F Y');
        $formattedDueDate = $dueDate->format('d M Y');
        
        $today = Carbon::now();
        $hasOverdue = $unpaidInvoices->contains(function($invoice) use ($today) {
            return $invoice->due_date && Carbon::parse($invoice->due_date)->lt($today);
        });
        
        if ($unpaidCount == 0) {
            $unpaidMessage = 'No pending invoices';
        } elseif ($hasOverdue) {
            $unpaidMessage = "{$unpaidCount} unpaid overdue invoices totalling KES " . number_format($unpaidTotal, 0);
        } elseif ($unpaidCount == 1) {
            $unpaidMessage = "1 unpaid invoice KES " . number_format($unpaidTotal, 0);
        } else {
            $unpaidMessage = "{$unpaidCount} unpaid invoices totalling KES " . number_format($unpaidTotal, 0);
        }
        
        // FIX: Get unit number from tenancy relationship
        $unitNumber = $tenancy && $tenancy->unit ? $tenancy->unit->unit_number : ($tenant->unit_number ?? 'N/A');
        
        $replacements = [
            '{{name}}' => $tenant->user_name ?? 'Tenant',
            '{{unit}}' => $unitNumber,
            '{{unit_number}}' => $unitNumber,
            '{{estate_name}}' => $estateName,
            '{{estate}}' => $estateName,
            '{{month}}' => $formattedBillMonth,
            '{{water_bill}}' => number_format($waterBill, 0),
            '{{rate}}' => $rateApplied,
            '{{payment_status}}' => ucfirst($paymentStatus),
            '{{status}}' => ucfirst($paymentStatus),
            '{{due_date}}' => $formattedDueDate,
            '{{water_consumption}}' => $consumption,
            '{{prev_read}}' => $prevRead,
            '{{curr_read}}' => $currRead,
            '{{unpaid_count}}' => $unpaidCount,
            '{{unpaid_total}}' => number_format($unpaidTotal, 0),
            '{{unpaid_list}}' => $unpaidList,
            '{{unpaid_message}}' => $unpaidMessage,
        ];

        foreach ($replacements as $key => $value) {
            $message = str_replace($key, $value, $message);
        }

        $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);
        $message = preg_replace('/\s+/', ' ', $message);
        $message = preg_replace('/\b(\d+)\.00\b/', '$1', $message);
        
        return trim($message);
    }

    /**
     * FIXED: Send campaign using stored messages
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
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->get();

            if ($recipients->isEmpty()) {
                $campaign->status = 'failed';
                $campaign->save();
                DB::commit();
                return ['error' => 'No pending recipients found'];
            }

            $campaign->status = 'sending';
            $campaign->sent_at = now();
            $campaign->save();

            $messageType = $campaign->campaign_type === 'promotional' ? 'promotional' : 'transactional';

            if ($messageType === 'promotional' && $this->kenyaSms->isQuietHours()) {
                Log::warning('Promotional campaign blocked during quiet hours', [
                    'campaign_id' => $campaign->id
                ]);
                $campaign->status = 'failed';
                $campaign->save();
                DB::commit();
                return ['error' => 'Promotional messages cannot be sent during quiet hours (20:00 - 08:00 EAT)'];
            }

            // Build messages array from stored recipients
            $messages = [];
            foreach ($recipients as $recipient) {
                $messages[] = [
                    'phone' => $recipient->phone_number,
                    'message' => $recipient->message, // Use the stored message
                ];
            }

            Log::info('Sending ' . count($messages) . ' messages via KenyaSMS');

            // Send using the KenyaSMS service
            $result = $this->kenyaSms->sendPersonalized($messages, [
                'message_type' => $messageType,
            ]);

            if ($result['success']) {
                // Update recipient statuses based on response
                $responses = $result['responses'] ?? [];
                foreach ($recipients as $index => $recipient) {
                    $response = $responses[$index] ?? [];
                    if (isset($response['status']) && $response['status'] === 'success') {
                        $recipient->status = 'sent';
                        $recipient->sent_at = now();
                        $recipient->message_id = $response['message_id'] ?? null;
                        $campaign->increment('sent_count');
                    } else {
                        $recipient->status = 'failed';
                        $recipient->error_message = $response['error'] ?? 'Unknown error';
                        $campaign->increment('failed_count');
                    }
                    $recipient->save();
                }

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
                // Mark all as failed
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

    protected function sendRecipients(SmsCampaign $campaign)
    {
        $recipients = $campaign->recipients()->where('status', 'pending')->get();
        
        Log::info('Sending recipients', [
            'campaign_id' => $campaign->id,
            'count' => $recipients->count()
        ]);

        foreach ($recipients as $recipient) {
            try {
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

    public function retryFailed($campaignId)
    {
        $campaign = SmsCampaign::find($campaignId);
        if (!$campaign) {
            return ['error' => 'Campaign not found'];
        }

        $failedRecipients = $campaign->recipients()->where('status', 'failed')->get();
        if ($failedRecipients->isEmpty()) {
            return ['error' => 'No failed messages to retry'];
        }

        $retryCount = 0;
        $retryFailed = 0;
        foreach ($failedRecipients as $recipient) {
            try {
                $recipient->status = 'pending';
                $recipient->error_message = null;
                $recipient->save();

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

        $this->updateCampaignCounters($campaign);

        return [
            'success' => true,
            'synced' => $synced,
            'updated' => $updated,
            'message' => "Synced $synced recipients, updated $updated statuses"
        ];
    }

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
            $invoice = $tenant->tenancies->first()->invoices->first() ?? null;
            
            $previews[] = [
                'tenant_id' => $tenant->id,
                'name' => $tenant->user_name ?? $tenant->name ?? 'Unknown',
                'phone' => $tenant->phone ?? '',
                'unit_number' => $tenant->unit_number ?? 'N/A',
                'estate_name' => $tenant->tenancies->first()->unit->estate->name ?? 'N/A',
                'message' => $message,
                'payment_status' => $invoice ? $invoice->status : 'pending',
                'water_bill' => $invoice ? number_format($invoice->total_amount, 0) : '0',
                'due_date' => $invoice ? $invoice->due_date : date('Y-m-d', strtotime('+14 days')),
                'message_parts' => $this->kenyaSms->getMessageParts($message),
                'estimated_cost' => $this->kenyaSms->getEstimatedCost($message),
            ];
        }

        return $previews;
    }

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

    public function getBalance()
    {
        return $this->kenyaSms->getBalance();
    }

    public function getMessageParts($message)
    {
        return $this->kenyaSms->getMessageParts($message);
    }

    public function getEstimatedCost($message, $type = null)
    {
        return $this->kenyaSms->getEstimatedCost($message, $type);
    }
}