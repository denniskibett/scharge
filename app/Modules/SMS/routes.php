<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Controllers\SmsController;
use App\Modules\SMS\Controllers\SmsTemplateController;
use App\Modules\SMS\Controllers\CampaignController;
use App\Modules\SMS\Controllers\WebhookController;
use App\Http\Controllers\MpesaController;

// =========================================================
// 📨 PUBLIC WEBHOOKS & CALLBACKS (NO AUTH, NO CSRF)
// =========================================================

// M-PESA Callbacks
Route::post('/sms/mpesa/callback', [MpesaController::class, 'stkCallback'])
    ->name('sms.mpesa.callback')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// KenyaSMS Delivery Report Webhook
Route::post('/sms/webhook/dlr', [WebhookController::class, 'handleDLR'])
    ->name('sms.webhook.dlr')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// B2B Callbacks
Route::post('/mpesa/b2b/result', [MpesaController::class, 'b2bResult'])
    ->name('mpesa.b2b.result')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/mpesa/b2b/queue', [MpesaController::class, 'b2bQueueTimeout'])
    ->name('mpesa.b2b.queue')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// =========================================================
// 🌐 API ROUTES (Standalone, under /api/sms) – for AJAX calls
// =========================================================
Route::prefix('api/sms')->middleware(['auth'])->group(function () {

    // Campaigns API
    Route::get('/campaigns', [CampaignController::class, 'apiIndex'])->name('sms.api.campaigns.index');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('sms.api.campaigns.store');
    Route::get('/campaigns/{id}', [CampaignController::class, 'getDetails'])->name('sms.api.campaigns.show');
    Route::post('/campaigns/{id}/send', [CampaignController::class, 'send'])->name('sms.api.campaigns.send');
    Route::post('/campaigns/{id}/resend-pending', [CampaignController::class, 'resendPending'])->name('sms.api.campaigns.resend-pending');
    Route::post('/campaigns/{id}/resend-failed', [CampaignController::class, 'resendFailed'])->name('sms.api.campaigns.resend-failed');
    Route::post('/campaigns/{id}/sync-status', [CampaignController::class, 'syncStatus'])->name('sms.api.campaigns.sync-status');
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy'])->name('sms.api.campaigns.destroy');

    // Campaign Preview
    Route::post('/campaigns/preview', [CampaignController::class, 'preview'])->name('sms.api.campaigns.preview');

    // Recipient actions
    Route::get('/campaigns/{id}/invalid', [CampaignController::class, 'getInvalidRecipients'])->name('sms.api.campaigns.invalid');
    Route::get('/campaigns/{id}/other-networks', [CampaignController::class, 'getOtherNetworkRecipients'])->name('sms.api.campaigns.other-networks');
    Route::post('/recipients/{id}/resend', [CampaignController::class, 'resendIndividualRecipient'])->name('sms.api.recipients.resend');
    Route::put('/tenants/{tenantId}/phone', [CampaignController::class, 'updateTenantPhone'])->name('sms.api.tenants.update-phone');

    // Status
    Route::get('/campaigns/{id}/status-summary', [CampaignController::class, 'getStatusSummary'])->name('sms.api.campaigns.status-summary');
    Route::post('/campaigns/{id}/check-pending', [CampaignController::class, 'checkPendingStatus'])->name('sms.api.campaigns.check-pending');
});

// =========================================================
// 📱 SMS ROUTES - Authenticated (WEB UI - USER-FACING)
// =========================================================
Route::prefix('sms')->middleware(['auth'])->group(function () {

    // Broadcast & Sending
    Route::get('/broadcast', [SmsController::class, 'create'])->name('sms.broadcast');
    Route::post('/send', [SmsController::class, 'send'])->name('sms.send');
    Route::post('/send-custom', [SmsController::class, 'sendCustom'])->name('sms.send-custom');

    // Logs & History
    Route::get('/logs', [SmsController::class, 'logs'])->name('sms.logs');
    Route::get('/history', [SmsController::class, 'logs'])->name('sms.history');
    Route::get('/logs/export', [SmsController::class, 'export'])->name('sms.logs.export');

    // Templates
    Route::get('/templates', [SmsTemplateController::class, 'index'])->name('sms.templates.index');
    Route::get('/templates/create', [SmsTemplateController::class, 'create'])->name('sms.templates.create');
    Route::post('/templates', [SmsTemplateController::class, 'store'])->name('sms.templates.store');
    Route::get('/templates/{template}/edit', [SmsTemplateController::class, 'edit'])->name('sms.templates.edit');
    Route::put('/templates/{template}', [SmsTemplateController::class, 'update'])->name('sms.templates.update');
    Route::delete('/templates/{template}', [SmsTemplateController::class, 'destroy'])->name('sms.templates.destroy');

    // Settings
    Route::get('/settings', [SmsController::class, 'settings'])->name('sms.settings');
    Route::post('/settings', [SmsController::class, 'updateSettings'])->name('sms.settings.update');

    // Campaigns - Web Views & Actions
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('sms.campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('sms.campaigns.create');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('sms.campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('sms.campaigns.edit');

    // Campaign Actions (Web)
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('sms.campaigns.store');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('sms.campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('sms.campaigns.destroy');
    Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('sms.campaigns.send');
    Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('sms.campaigns.duplicate');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('sms.campaigns.cancel');
    Route::post('/campaigns/{campaign}/resend-failed', [CampaignController::class, 'resendFailed'])->name('sms.campaigns.resend-failed');
    Route::get('/campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('sms.campaigns.export');
    Route::get('/campaigns/{campaign}/status', [CampaignController::class, 'status'])->name('sms.campaigns.status');
    Route::get('/campaigns/{campaign}/failed', [CampaignController::class, 'failed'])->name('sms.campaigns.failed');
    Route::get('/campaigns/{campaign}/timeline', [CampaignController::class, 'timeline'])->name('sms.campaigns.timeline');

    // M-PESA Routes (Authenticated)
    Route::prefix('mpesa')->name('mpesa.')->group(function () {
        Route::post('/stk-push', [MpesaController::class, 'stkPush'])->name('stk-push');
        Route::post('/query-status', [MpesaController::class, 'queryStatus'])->name('query-status');
        Route::post('/b2b-payment', [MpesaController::class, 'b2bPayment'])->name('b2b-payment');
    });

    // Legacy
    Route::get('/api/tenant-payment-status/{tenantId}', [SmsController::class, 'getTenantPaymentStatus']);
});