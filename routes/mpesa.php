<?php

use App\Http\Controllers\MpesaController;
use Illuminate\Support\Facades\Route;

// Safaricom STK push callback. Public, no auth.
// CSRF is skipped via validateCsrfTokens(except: [...]) in bootstrap/app.php;
// the old withoutMiddleware(VerifyCsrfToken) had no effect on Laravel 12.
Route::post('/pay', [MpesaController::class, 'handleCallback'])->name('mpesa.callback');
