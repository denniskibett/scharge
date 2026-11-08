<?php

use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

// scharge API v1. Keys are managed on Settings › API keys; see docs/SHARET_PAYMENTS.md.
Route::prefix('v1')->middleware(['throttle:60,1', 'api.key'])->group(function () {
    Route::get('/me', [V1Controller::class, 'me']);
    Route::get('/invoices/{id}', [V1Controller::class, 'invoice'])->whereNumber('id');
    Route::post('/invoices/{id}/pay-link', [V1Controller::class, 'payLink'])->whereNumber('id')->middleware('api.key:collect');
});
