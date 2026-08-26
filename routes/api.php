<?php

use App\Http\Controllers\MpesaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ============================================
// M-PESA CALLBACK ROUTES (No CSRF)
// ============================================
Route::prefix('mpesa')->name('api.mpesa.')->group(function () {
    // STK Push Callback - Safaricom calls this after user enters PIN
    Route::post('/callback', [MpesaController::class, 'stkCallback'])->name('callback');
    
    // B2B Callbacks
    Route::post('/result', [MpesaController::class, 'b2bResult'])->name('result');
    Route::post('/timeout', [MpesaController::class, 'b2bQueueTimeout'])->name('timeout');
    Route::post('/b2b/result', [MpesaController::class, 'b2bResult'])->name('b2b.result');
    Route::post('/b2b/queue', [MpesaController::class, 'b2bQueueTimeout'])->name('b2b.queue');
    
    // C2B Callbacks
    Route::post('/confirmation', [MpesaController::class, 'confirmation'])->name('confirmation');
    Route::post('/validation', [MpesaController::class, 'validation'])->name('validation');
});

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString()
    ]);
});

// Test route to verify API is accessible
Route::get('/mpesa/test-callback', function () {
    return response()->json([
        'message' => 'API callback route is working!',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment()
    ]);
});