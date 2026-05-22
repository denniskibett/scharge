<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Controllers\SmsController;

Route::prefix('sms')->group(function () {
    Route::get('/', [SmsController::class, 'index']);
});