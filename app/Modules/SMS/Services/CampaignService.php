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
    protected KenyaSMS $kenyaSMS;

    public function __construct(KenyaSMS $kenyaSMS)
    {
        $this->kenyaSMS = $kenyaSMS;
    }

    /**
     * Build full placeholders for a tenant – matches JavaScript preview exactly.
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

        // Base placeholders (✅ FIX: removed thousand separator)
        $placeholders = [
            'estate_name' => $unit && $unit->estate ? $unit->estate->name : 'N/A',
            'month' => $readingMonth,
            'water_consumption' => $reading ? (float) $reading->consumption : 0,
            'prev_read' => $reading ? (float) $reading->previous_reading : 0,
            'curr_read' => $reading ? (float) $reading->current_reading : 0,
            'unit' => $unit->unit_number ?? '',
            'unit_number' => $unit->unit_number ?? '',
            'water_bill' => $reading ? number_format((float) $reading->charge, 2, '.', '') : '0.00', // ✅ FIXED
            'due_date' => $dueDate,
            'payment_status' => ucfirst($paymentStatus),
            'status' => ucfirst($paymentStatus),
            'name' => $tenant->user->name ?? 'Tenant',
        ];

        // ---- Fetch invoices for ALL tenancies (not just active) ----
        $today = Carbon::today();
        $currentMonthY = Carbon::parse($readingMonth)->format('Y-m');

        $invoices = DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->where('tenancies.tenant_id', $tenant->id)
            ->whereIn('invoices.status', ['unpaid', 'partial', 'overdue'])
            ->select('invoices.*')
            ->orderBy('invoices.billing_month', 'asc')
            ->get();

        $olderInvoices = $invoices->filter(function($inv) use ($currentMonthY, $today) {
            $billingMonthY = Carbon::parse($inv->billing_month)->format('Y-m');
            if ($billingMonthY >= $currentMonthY) return false;
            $dueDate = Carbon::parse($inv->billing_month)->addMonth()->day(5);
            return $dueDate->lte($today);
        })->values();

        $olderCount = $olderInvoices->count();
        $olderTotal = $olderInvoices->sum('total_amount');

        $currentBill = (float) ($placeholders['water_bill'] ?? 0);

        // If tenant is PAID, zero out everything (matches preview)
        if (strtolower($paymentStatus) === 'paid') {
            $currentBill = 0;
            $olderTotal = 0;
        }

        $unpaidTotal = $olderTotal;
        $totalDue = $currentBill + $unpaidTotal;

        // Build unpaid list with status prefix only if not 'unpaid'
        $unpaidList = $olderInvoices->map(function($inv) {
            $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
            $status = $inv->status;
            $prefix = '';
            if ($status !== 'unpaid') {
                $prefix = ucfirst($status) . ' ';
            }
            return $prefix . '(' . $billingMonth . '): KES ' . number_format($inv->total_amount, 2);
        })->implode("\n");

        // Build unpaid_section with a trailing newline
        $unpaidSection = $olderCount > 0 ? "Unpaid:\n" . $unpaidList . "\n" : '';

        // Add all unpaid placeholders
        $placeholders['unpaid_count'] = $olderCount;
        $placeholders['unpaid_total'] = number_format($unpaidTotal, 2);
        $placeholders['unpaid_list'] = $unpaidList;
        $placeholders['unpaid_section'] = $unpaidSection;
        $placeholders['total_due'] = number_format($totalDue, 2);

        return $placeholders;
    }

    // --------------------------------------------------------------------
    // All other methods (getRecipientsWithValidation, createCampaign, etc.)
    // are unchanged.  I've included them below for completeness.
    // --------------------------------------------------------------------

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
            $personalizedRecipients = [];

            foreach ($recipients as $recipient) {
                if (empty($recipient['phone']) || empty($recipient['placeholders'])) {
                    continue;
                }

                $placeholders = $recipient['placeholders'];

                $personalizedMessage = $template->content;
                foreach ($placeholders as $key => $value) {
                    if ($value !== null) {
                        $personalizedMessage = str_replace('{{' . $key . '}}', $value, $personalizedMessage);
                    }
                }

                $personalizedMessage = preg_replace('/\{\{[^}]*\}\}/', '', $personalizedMessage);
                $personalizedMessage = $this->cleanAndTruncateMessage($personalizedMessage);

                $kenyaRecipients[] = [
                    'phone' => $recipient['phone'],
                    'variables' => $placeholders,
                ];

                $personalizedRecipients[] = [
                    'tenant_id' => $recipient['tenant_id'],
                    'phone' => $recipient['phone'],
                    'message' => $personalizedMessage,
                    'placeholders' => $placeholders,
                ];
            }

            if (empty($kenyaRecipients)) {
                throw new \Exception('No valid recipients with placeholders.');
            }

            // Forced sandbox mode
            $sandbox = true;
            $kenyasmsCampaignId = 'sandbox-' . uniqid() . '-' . time();

            Log::info('FORCED SANDBOX MODE: Campaign created locally without calling KenyaSMS API', [
                'campaign_id' => $kenyasmsCampaignId,
                'recipients' => count($kenyaRecipients)
            ]);

            $campaign = SmsCampaign::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'template_id' => $data['template_id'],
                'filters' => json_encode($data['filters'] ?? []),
                'status' => 'pending',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'campaign_type' => $data['campaign_type'] ?? 'general',
                'created_by' => auth()->id(),
                'total_recipients' => count($personalizedRecipients),
                'sent_count' => 0,
                'failed_count' => 0,
                'delivered_count' => 0,
                'kenyasms_campaign_id' => $kenyasmsCampaignId,
            ]);

            foreach ($personalizedRecipients as $recipient) {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $recipient['tenant_id'],
                    'phone_number' => $recipient['phone'],
                    'message' => $recipient['message'],
                    'status' => 'pending',
                    'message_id' => $kenyasmsCampaignId,
                ]);
            }

            DB::commit();

            Log::info('Campaign created successfully (FORCED SANDBOX)', [
                'campaign_id' => $campaign->id,
                'recipients' => count($personalizedRecipients),
                'kenyasms_campaign_id' => $kenyasmsCampaignId
            ]);

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

    protected function cleanAndTruncateMessage($message): string
    {
        $lines = explode("\n", $message);
        $lines = array_map(function($line) {
            return trim(preg_replace('/[ \t]+/', ' ', $line));
        }, $lines);
        $cleaned = implode("\n", $lines);
        $cleaned = preg_replace("/\n{2,}/", "\n", $cleaned);
        $cleaned = trim($cleaned);

        if (mb_strlen($cleaned) > 300) {
            $cleaned = mb_substr($cleaned, 0, 297) . '...';
        }
        return $cleaned;
    }

    // --------------------------------------------------------------------
    // The following methods are unchanged but must be present.
    // --------------------------------------------------------------------

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

    protected function preserveMessageFormat($message): string
    {
        $message = str_replace("\r\n", "\n", $message);
        $message = str_replace("\r", "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        return $message;
    }

    public function retryFailed($campaignId)
    {
        return $this->syncCampaignStatus($campaignId);
    }

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

            $updateData = [
                'status' => $newStatus,
                'provider_status' => $log['status'] ?? null,
                'provider_response' => json_encode($log),
                'updated_at' => now(),
            ];

            if (isset($log['sent'])) {
                $updateData['sent_at'] = $log['sent'];
            }

            if ($newStatus === 'failed' && isset($log['error_code'])) {
                $updateData['error_message'] = $log['error_code'];
            }

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

    public function syncCampaignStatus($campaignId): array
    {
        $campaign = SmsCampaign::findOrFail($campaignId);

        if (!$campaign->kenyasms_campaign_id) {
            return [
                'success' => false,
                'error' => 'Campaign has no KenyaSMS campaign ID.'
            ];
        }

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
        $personalizedRecipients = [];
        $today = Carbon::today();

        foreach ($pendingRecipients as $recipient) {
            $tenant = Tenant::with(['user', 'activeTenancy.invoices'])->find($recipient->tenant_id);
            if (!$tenant) {
                continue;
            }

            $placeholders = $this->buildPlaceholders($tenant);

            $olderInvoices = collect();
            if ($tenant->activeTenancy) {
                $currentMonthY = Carbon::parse($placeholders['month'] ?? now()->format('F Y'))->format('Y-m');

                $olderInvoices = $tenant->activeTenancy->invoices
                    ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                    ->filter(function($inv) use ($currentMonthY, $today) {
                        if (empty($inv->billing_month)) return false;
                        $invMonth = Carbon::parse($inv->billing_month)->format('Y-m');
                        if ($invMonth >= $currentMonthY) return false;

                        $dueDate = Carbon::parse($inv->billing_month)->addMonth()->day(5);
                        return $dueDate->lte($today);
                    })
                    ->values();
            }

            $olderCount = $olderInvoices->count();
            $olderTotal = $olderInvoices->sum('total_amount');
            $currentBill = (float) ($placeholders['water_bill'] ?? 0);
            $unpaidTotal = $olderTotal;
            $totalDue = $currentBill + $olderTotal;

            $unpaidList = $olderInvoices->map(function($inv) {
                $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
                return $inv->status . ' (' . $billingMonth . '): KES ' . number_format($inv->total_amount, 2);
            })->implode("\n");

            $unpaidSection = $olderCount > 0 ? "Unpaid:\n" . $unpaidList . "\n" : '';

            $placeholders['unpaid_count'] = $olderCount;
            $placeholders['unpaid_total'] = number_format($unpaidTotal, 2);
            $placeholders['unpaid_list'] = $unpaidList;
            $placeholders['unpaid_section'] = $unpaidSection;
            $placeholders['total_due'] = number_format($totalDue, 2);

            $personalizedMessage = $template->content;
            foreach ($placeholders as $key => $value) {
                if ($value !== null) {
                    $personalizedMessage = str_replace('{{' . $key . '}}', $value, $personalizedMessage);
                }
            }
            $personalizedMessage = preg_replace('/\{\{[^}]*\}\}/', '', $personalizedMessage);
            $personalizedMessage = $this->cleanAndTruncateMessage($personalizedMessage);

            $kenyaRecipients[] = [
                'phone' => $recipient->phone_number,
                'variables' => $placeholders,
            ];

            $personalizedRecipients[] = [
                'id' => $recipient->id,
                'tenant_id' => $recipient->tenant_id,
                'phone' => $recipient->phone_number,
                'message' => $personalizedMessage,
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

        foreach ($personalizedRecipients as $recipient) {
            CampaignRecipient::where('id', $recipient['id'])
                ->update([
                    'message' => $recipient['message'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'message_id' => $kenyaResult['campaign_id'] ?? null,
                ]);
        }

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

    public function checkPendingStatus($campaignId): array
    {
        $campaign = SmsCampaign::findOrFail($campaignId);

        if (!$campaign->kenyasms_campaign_id) {
            return [
                'success' => false,
                'error' => 'Campaign has no KenyaSMS campaign ID.'
            ];
        }

        if (env('KENYASMS_SANDBOX', true)) {
            return [
                'success' => true,
                'updated' => 0,
                'failed' => 0,
                'pending' => $campaign->sent_count,
                'message' => 'Sandbox mode: no actual status check performed.'
            ];
        }

        $pendingRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->get();

        if ($pendingRecipients->isEmpty()) {
            return [
                'success' => true,
                'updated' => 0,
                'failed' => 0,
                'pending' => 0,
                'message' => 'No pending messages to check.'
            ];
        }

        Log::info('Checking pending status for campaign', [
            'campaign_id' => $campaign->id,
            'pending_count' => $pendingRecipients->count()
        ]);

        $updated = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($pendingRecipients as $recipient) {
            if ($recipient->message_id) {
                $statusResult = $this->kenyaSMS->getMessageStatus($recipient->message_id);

                if ($statusResult['success']) {
                    $newStatus = $this->mapProviderStatus($statusResult['status'] ?? 'pending');

                    if ($newStatus !== 'pending') {
                        $recipient->status = $newStatus;
                        $recipient->provider_status = $statusResult['status'] ?? null;
                        $recipient->provider_response = json_encode($statusResult);

                        if ($newStatus === 'sent' || $newStatus === 'delivered') {
                            $recipient->sent_at = now();
                        }

                        $recipient->save();
                        $updated++;

                        Log::info('Updated recipient status from pending', [
                            'recipient_id' => $recipient->id,
                            'new_status' => $newStatus
                        ]);
                    } else {
                        $stillPending++;
                    }
                } else {
                    $failed++;
                    Log::warning('Failed to check status for recipient', [
                        'recipient_id' => $recipient->id,
                        'error' => $statusResult['error'] ?? 'Unknown'
                    ]);
                }
            } else {
                $stillPending++;
            }
        }

        // Update campaign counts
        $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
            ->whereIn('status', ['sent', 'delivered'])
            ->count();
        $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->count();
        $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'delivered')
            ->count();
        $campaign->save();

        return [
            'success' => true,
            'updated' => $updated,
            'failed' => $failed,
            'pending' => $stillPending,
            'message' => "Status check completed: {$updated} updated, {$failed} failed, {$stillPending} still pending."
        ];
    }

    public function renderTemplate($templateContent, $tenant)
    {
        $placeholders = $this->buildPlaceholders($tenant);
        return $this->personalizeMessage($templateContent, $placeholders);
    }

    public function renderMessage($templateContent, $tenant)
    {
        return $this->renderTemplate($templateContent, $tenant);
    }
}