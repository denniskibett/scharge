<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Controllers\SmsController;
use App\Modules\SMS\Controllers\SmsTemplateController;
use App\Modules\SMS\Controllers\CampaignController;
use App\Modules\SMS\Controllers\WebhookController;
use App\Http\Controllers\MpesaController;

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