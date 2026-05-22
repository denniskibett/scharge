<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Controllers\SmsController;

Route::prefix('sms')->middleware(['auth'])->group(function () {
    Route::get('/broadcast', [SmsController::class, 'create'])->name('sms.broadcast');
    Route::post('/send', [SmsController::class, 'send'])->name('sms.send');
    Route::post('/send-custom', [SmsController::class, 'sendCustom'])->name('sms.send-custom');
    Route::get('/logs', [SmsController::class, 'logs'])->name('sms.logs');
});
