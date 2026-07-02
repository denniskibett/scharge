<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaController;

// ============================================
// M-PESA CALLBACK - PUBLIC ROUTE
// ============================================
Route::post('/sms/mpesa/callback', [MpesaController::class, 'stkCallback'])
    ->name('sms.mpesa.callback')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);