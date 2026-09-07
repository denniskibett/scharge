<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\SMS\Controllers\CampaignController;
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\CampaignRecipient;
use App\Models\Tenant;
use App\Modules\SMS\Services\KenyaSMS;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImportKenyaSmsCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    protected $page;
    protected $limit;

    public function __construct($page = 1, $limit = 100)
    {
        $this->page = $page;
        $this->limit = $limit;
    }

    public function handle()
    {
        Log::info('🔁 ImportKenyaSmsCampaignsJob started');

        $controller = app(CampaignController::class);
        
        try {
            $result = app(KenyaSMS::class)->listCampaigns($this->page, $this->limit);
            
            if (!$result['success'] || empty($result['data']['campaigns'] ?? [])) {
                Log::warning('⚠️ No campaigns to import, using mock data');
                $this->importMockCampaigns();
                return;
            }

            $campaigns = $result['data']['campaigns'];
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($campaigns as $remoteCampaign) {
                try {
                    $remoteId = $remoteCampaign['id'] ?? $remoteCampaign['campaign_id'] ?? null;
                    if (empty($remoteId)) {
                        $errors[] = 'Campaign has no ID';
                        continue;
                    }

                    $existing = SmsCampaign::where('kenyasms_campaign_id', $remoteId)->first();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    $name = $remoteCampaign['name'] ?? $remoteCampaign['campaign_name'] ?? 'Imported Campaign ' . $remoteId;
                    $recipients = (int) ($remoteCampaign['recipients'] ?? $remoteCampaign['total_recipients'] ?? 0);
                    $delivered = (int) ($remoteCampaign['delivered'] ?? $remoteCampaign['delivered_count'] ?? 0);
                    $failed = (int) ($remoteCampaign['failed'] ?? $remoteCampaign['failed_count'] ?? 0);
                    $status = $remoteCampaign['status'] ?? $remoteCampaign['campaign_status'] ?? 'completed';
                    $messageType = $remoteCampaign['message_type'] ?? $remoteCampaign['type'] ?? 'transactional';
                    $createdAt = $remoteCampaign['created_at'] ?? $remoteCampaign['created'] ?? now();

                    $campaign = SmsCampaign::create([
                        'name' => $name,
                        'description' => 'Imported from KenyaSMS on ' . now()->format('Y-m-d H:i:s'),
                        'template_id' => null,
                        'filters' => json_encode(['source' => 'kenyasms_import', 'remote_id' => $remoteId]),
                        'status' => $status,
                        'campaign_type' => $messageType,
                        'created_by' => 1,
                        'total_recipients' => $recipients,
                        'sent_count' => 0,
                        'failed_count' => 0,
                        'delivered_count' => 0,
                        'kenyasms_campaign_id' => $remoteId,
                        'created_at' => Carbon::parse($createdAt),
                        'source' => 'kenyasms_imported',
                    ]);

                    // Sync recipients
                    $syncResult = $controller->fetchAndSyncRecipientsFromKenyaSMS(
                        $campaign, $remoteId, false, $delivered, $failed
                    );

                    $campaign->total_recipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
                    $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                        ->whereIn('status', ['sent', 'delivered'])->count();
                    $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                        ->where('status', 'failed')->count();
                    $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)
                        ->where('status', 'delivered')->count();
                    $campaign->save();

                    $imported++;
                    
                    Log::info('✅ Imported campaign: ' . $campaign->name . ' (' . $campaign->id . ')');

                } catch (\Exception $e) {
                    $errors[] = 'Failed to import campaign: ' . $e->getMessage();
                    Log::error('Import campaign error: ' . $e->getMessage());
                }
            }

            Log::info('✅ Import completed: ' . $imported . ' imported, ' . $skipped . ' skipped');

        } catch (\Exception $e) {
            Log::error('❌ Job failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fallback for mock campaigns (sandbox mode)
     */
    protected function importMockCampaigns()
    {
        $controller = app(CampaignController::class);
        $mockCampaigns = $controller->getMockKenyaSmsCampaigns();
        
        $imported = 0;
        $skipped = 0;

        foreach ($mockCampaigns as $remoteCampaign) {
            try {
                $remoteId = $remoteCampaign['id'];
                $existing = SmsCampaign::where('kenyasms_campaign_id', $remoteId)->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                $campaign = SmsCampaign::create([
                    'name' => $remoteCampaign['name'],
                    'description' => 'Imported from mock KenyaSMS data on ' . now()->format('Y-m-d H:i:s'),
                    'template_id' => null,
                    'filters' => json_encode(['source' => 'kenyasms_import']),
                    'status' => $remoteCampaign['status'] ?? 'completed',
                    'campaign_type' => $remoteCampaign['message_type'] ?? 'transactional',
                    'created_by' => 1,
                    'total_recipients' => $remoteCampaign['recipients'] ?? 0,
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'delivered_count' => 0,
                    'kenyasms_campaign_id' => $remoteId,
                    'source' => 'kenyasms_imported',
                    'created_at' => Carbon::parse($remoteCampaign['created_at'] ?? now()),
                ]);

                $syncResult = $controller->fetchAndSyncRecipientsFromKenyaSMS(
                    $campaign,
                    $remoteId,
                    true,
                    $remoteCampaign['delivered'] ?? 0,
                    $remoteCampaign['failed'] ?? 0
                );

                $campaign->total_recipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
                $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->whereIn('status', ['sent', 'delivered'])->count();
                $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')->count();
                $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'delivered')->count();
                $campaign->save();

                $imported++;
            } catch (\Exception $e) {
                Log::error('Mock import error: ' . $e->getMessage());
            }
        }

        Log::info('✅ Mock import completed: ' . $imported . ' imported, ' . $skipped . ' skipped');
    }
}