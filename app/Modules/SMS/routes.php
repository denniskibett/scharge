<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Controllers\SmsController;
use App\Modules\SMS\Controllers\SmsTemplateController;
use App\Modules\SMS\Controllers\CampaignController;
use App\Modules\SMS\Controllers\WebhookController;
use App\Http\Controllers\MpesaController;
use Illuminate\Support\Facades\DB;

// =========================================================
// 📨 M-PESA CALLBACK - PUBLIC ROUTE (No Auth, No CSRF)
// =========================================================
Route::post('/sms/mpesa/callback', [MpesaController::class, 'stkCallback'])
    ->name('sms.mpesa.callback')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// =========================================================
// 📱 SMS ROUTES - Authenticated
// =========================================================
Route::prefix('sms')->middleware(['auth'])->group(function () {

    // ======================
    // Broadcast & Sending
    // ======================
    Route::get('/broadcast', [SmsController::class, 'create'])->name('sms.broadcast');
    Route::post('/send', [SmsController::class, 'send'])->name('sms.send');
    Route::post('/send-custom', [SmsController::class, 'sendCustom'])->name('sms.send-custom');

    // ======================
    // Logs & History
    // ======================
    Route::get('/logs', [SmsController::class, 'logs'])->name('sms.logs');
    Route::get('/history', [SmsController::class, 'logs'])->name('sms.history');
    Route::get('/logs/export', [SmsController::class, 'export'])->name('sms.logs.export');

    // ======================
    // Templates
    // ======================
    Route::get('/templates', [SmsTemplateController::class, 'index'])->name('sms.templates.index');
    Route::get('/templates/create', [SmsTemplateController::class, 'create'])->name('sms.templates.create');
    Route::post('/templates', [SmsTemplateController::class, 'store'])->name('sms.templates.store');
    Route::get('/templates/{template}/edit', [SmsTemplateController::class, 'edit'])->name('sms.templates.edit');
    Route::put('/templates/{template}', [SmsTemplateController::class, 'update'])->name('sms.templates.update');
    Route::delete('/templates/{template}', [SmsTemplateController::class, 'destroy'])->name('sms.templates.destroy');

    // ======================
    // Settings
    // ======================
    Route::get('/settings', [SmsController::class, 'settings'])->name('sms.settings');
    Route::post('/settings', [SmsController::class, 'updateSettings'])->name('sms.settings.update');

    // =========================================================
    // 📊 CAMPAIGNS - Full CRUD
    // =========================================================
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('sms.campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('sms.campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('sms.campaigns.store');

    // ⚠️ HISTORY MUST COME BEFORE THE {campaign} ROUTE
    Route::get('/campaigns/history', [CampaignController::class, 'history'])->name('sms.campaigns.history');

    // Campaign routes with parameters - these come AFTER history
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('sms.campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('sms.campaigns.edit');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('sms.campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('sms.campaigns.destroy');
    
    // Campaign Actions
    Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('sms.campaigns.send');
    Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('sms.campaigns.duplicate');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('sms.campaigns.cancel');
    Route::post('/campaigns/{campaign}/resend-failed', [CampaignController::class, 'resendFailed'])->name('sms.campaigns.resend-failed');
    
    // Reports & Export
    Route::get('/campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('sms.campaigns.export');
    Route::get('/campaigns/{campaign}/status', [CampaignController::class, 'status'])->name('sms.campaigns.status');
    Route::get('/campaigns/{campaign}/failed', [CampaignController::class, 'failed'])->name('sms.campaigns.failed');
    Route::get('/campaigns/{campaign}/timeline', [CampaignController::class, 'timeline'])->name('sms.campaigns.timeline');

    // =========================================================
    // 📨 WEBHOOK - KenyaSMS
    // =========================================================
    Route::post('/webhook/dlr', [WebhookController::class, 'handleDLR'])
        ->name('sms.webhook.dlr')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

    // =========================================================
    // 📱 M-PESA ROUTES (Authenticated)
    // =========================================================
    Route::prefix('mpesa')->name('mpesa.')->group(function () {
        Route::post('/stk-push', [MpesaController::class, 'stkPush'])->name('stk-push');
        Route::post('/query-status', [MpesaController::class, 'queryStatus'])->name('query-status');
        Route::post('/b2b-payment', [MpesaController::class, 'b2bPayment'])->name('b2b-payment');
    });

    // =========================================================
    // 🏷️ LEGACY
    // =========================================================
    Route::get('/api/tenant-payment-status/{tenantId}', [SmsController::class, 'getTenantPaymentStatus']);
});

// =========================================================
// 📨 B2B CALLBACKS - PUBLIC ROUTES (No Auth, No CSRF)
// =========================================================
Route::post('/mpesa/b2b/result', [MpesaController::class, 'b2bResult'])
    ->name('mpesa.b2b.result')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/mpesa/b2b/queue', [MpesaController::class, 'b2bQueueTimeout'])
    ->name('mpesa.b2b.queue')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// =========================================================
// 🧪 TEST ROUTES - KenyaSMS
// =========================================================

// Test connection
Route::get('/sms/test-kenyasms', function() {
    try {
        $sms = new \App\Modules\SMS\Services\KenyaSMSService();
        $balance = $sms->getBalance();
        
        return response()->json([
            'success' => true,
            'message' => 'KenyaSMS connection successful!',
            'balance' => $balance,
            'base_url' => env('KENYASMS_URL'),
            'api_key' => substr(env('KENYASMS_KEY'), 0, 10) . '...',
            'sandbox' => env('KENYASMS_SANDBOX', true),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'KenyaSMS connection failed!',
            'error' => $e->getMessage(),
            'base_url' => env('KENYASMS_URL'),
        ]);
    }
})->name('sms.test-kenyasms');

// Test send SMS
Route::get('/sms/test-send', function() {
    try {
        $sms = new \App\Modules\SMS\Services\KenyaSMSService();
        
        $phone = '254727371496';
        
        $messages = [
            [
                'phone' => $phone,
                'message' => 'Hello! This is a test SMS from SCHARGE system. If you receive this, KenyaSMS integration is working!',
            ]
        ];
        
        $response = $sms->sendPersonalized($messages, [
            'sender_id' => 'SHARETENT',
            'message_type' => 'transactional',
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'SMS sent successfully!',
            'phone' => $phone,
            'response' => $response,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'SMS sending failed!',
            'error' => $e->getMessage(),
        ]);
    }
})->name('sms.test-send');

// Test campaigns endpoint
Route::get('/sms/test-campaigns', function() {
    try {
        $client = new \GuzzleHttp\Client();
        
        $response = $client->get('https://kenyasms.com/api/v1/campaigns', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('KENYASMS_KEY'),
                'Accept' => 'application/json',
            ],
            'query' => [
                'limit' => 10,
                'page' => 1,
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        
        return response()->json([
            'success' => true,
            'message' => 'Campaigns fetched successfully!',
            'data' => $data,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch campaigns!',
            'error' => $e->getMessage(),
            'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
        ]);
    }
})->name('sms.test-campaigns');

// =========================================================
// 📥 SYNC CAMPAIGNS FROM KENYASMS
// =========================================================
Route::get('/sms/sync-campaigns', function() {
    try {
        $client = new \GuzzleHttp\Client();
        $allCampaigns = [];
        $page = 1;
        $hasMore = true;
        $synced = 0;
        $skipped = 0;
        
        while ($hasMore) {
            $response = $client->get('https://kenyasms.com/api/v1/campaigns', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('KENYASMS_KEY'),
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'limit' => 100,
                    'page' => $page,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['data']['campaigns']) && count($data['data']['campaigns']) > 0) {
                $allCampaigns = array_merge($allCampaigns, $data['data']['campaigns']);
                $page++;
            } else {
                $hasMore = false;
            }
            
            // Safety limit
            if ($page > 20) break;
        }
        
        // Store in database
        foreach ($allCampaigns as $campaign) {
            // Extract estate from message
            $estateId = null;
            
            // Look for estate name in message (e.g., "Danaff Towers")
            if (preg_match('/^([a-zA-Z\s]+) (July|June|May|April|March)/', $campaign['message'] ?? '', $matches)) {
                $estateName = trim($matches[1]);
                $estate = DB::table('estates')->where('name', 'LIKE', '%' . $estateName . '%')->first();
                if ($estate) {
                    $estateId = $estate->id;
                }
            }
            
            // Check if already exists
            $exists = DB::table('sms_campaign_history')->where('kenyasms_campaign_id', $campaign['id'])->first();
            
            if (!$exists) {
                // Convert datetime format from ISO to MySQL
                $sentAt = null;
                $completedAt = null;
                
                if (isset($campaign['started_at'])) {
                    $sentAt = str_replace(['T', 'Z'], [' ', ''], $campaign['started_at']);
                }
                if (isset($campaign['completed_at'])) {
                    $completedAt = str_replace(['T', 'Z'], [' ', ''], $campaign['completed_at']);
                }
                
                DB::table('sms_campaign_history')->insert([
                    'kenyasms_campaign_id' => $campaign['id'],
                    'name' => $campaign['name'] ?? 'Unknown Campaign',
                    'message' => $campaign['message'] ?? '',
                    'sender_id' => $campaign['sender_id'] ?? 'SHARETENT',
                    'message_type' => $campaign['message_type'] ?? 'transactional',
                    'status' => $campaign['status'] ?? 'unknown',
                    'total_recipients' => $campaign['total_recipients'] ?? 0,
                    'sent_count' => $campaign['sent_count'] ?? 0,
                    'delivered_count' => $campaign['delivered_count'] ?? 0,
                    'failed_count' => $campaign['failed_count'] ?? 0,
                    'estimated_cost' => isset($campaign['estimated_cost']) ? $campaign['estimated_cost'] / 100 : 0,
                    'actual_cost' => isset($campaign['actual_cost']) ? $campaign['actual_cost'] / 100 : 0,
                    'cost_per_sms' => isset($campaign['price_per_sms']) ? $campaign['price_per_sms'] / 100 : 0,
                    'sent_at' => $sentAt,
                    'completed_at' => $completedAt,
                    'estate_id' => $estateId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $synced++;
            } else {
                $skipped++;
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Sync completed!",
            'total_fetched' => count($allCampaigns),
            'new_synced' => $synced,
            'already_existing' => $skipped,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Sync failed!',
            'error' => $e->getMessage(),
        ]);
    }
})->name('sms.sync-campaigns');