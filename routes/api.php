<?php

use App\Http\Controllers\MpesaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================
// M-PESA CALLBACK ROUTES (No CSRF)
// ============================================
Route::prefix('mpesa')->name('api.mpesa.')->group(function () {
    Route::post('/callback', [MpesaController::class, 'stkCallback'])->name('callback');
    Route::post('/result', [MpesaController::class, 'b2bResult'])->name('result');
    Route::post('/timeout', [MpesaController::class, 'b2bQueueTimeout'])->name('timeout');
    Route::post('/b2b/result', [MpesaController::class, 'b2bResult'])->name('b2b.result');
    Route::post('/b2b/queue', [MpesaController::class, 'b2bQueueTimeout'])->name('b2b.queue');
    Route::post('/confirmation', [MpesaController::class, 'confirmation'])->name('confirmation');
    Route::post('/validation', [MpesaController::class, 'validation'])->name('validation');
});

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]);
});

// Test route
Route::get('/mpesa/test-callback', function () {
    return response()->json([
        'message' => 'API callback route is working!',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment()
    ]);
});

// ============================================
// 🆕 SMS CAMPAIGN SYNC & GENERATE
// ============================================
Route::post('/sms/campaigns/{campaign}/sync-generate', [
    App\Http\Controllers\Sms\CampaignController::class,
    'syncAndGenerate'
])->name('api.sms.campaigns.sync-generate');