<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Controllers\SmsController;
use App\Modules\SMS\Controllers\SmsTemplateController;

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

    // ======================
    // Campaigns
    // ======================
    Route::get('/campaigns/{campaign}', [SmsController::class, 'showCampaign'])->name('sms.campaigns.show');
    Route::post('/campaigns/{campaign}/resend-failed', [SmsController::class, 'resendFailed'])->name('sms.campaigns.resend-failed');

    Route::get('/api/tenant-payment-status/{tenantId}', [SmsController::class, 'getTenantPaymentStatus']);
});