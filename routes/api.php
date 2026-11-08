<?php

use App\Http\Controllers\Api\MetersController;
use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

// scharge API v1. Keys: Settings › API keys. Docs: docs/SHARET_PAYMENTS.md, docs/METERS_API.md.
Route::prefix('v1')->group(function () {
    // Reader app sign-in (email + password -> personal key) and smart meters (device token).
    Route::post('/auth/token', [MetersController::class, 'token'])->middleware('throttle:5,1');
    Route::post('/smart/readings', [MetersController::class, 'smart'])->middleware('throttle:240,1');

    Route::middleware(['throttle:120,1', 'api.key'])->group(function () {
        Route::get('/me', [V1Controller::class, 'me']);
        Route::delete('/auth/token', [MetersController::class, 'revoke']);

        Route::get('/invoices/{id}', [V1Controller::class, 'invoice'])->whereNumber('id');
        Route::post('/invoices/{id}/pay-link', [V1Controller::class, 'payLink'])->whereNumber('id')->middleware('api.key:collect');

        Route::get('/meters', [MetersController::class, 'index']);
        Route::get('/meters/{id}', [MetersController::class, 'show'])->whereNumber('id');
        Route::get('/meters/{id}/readings', [MetersController::class, 'readingsIndex'])->whereNumber('id');
        Route::middleware('api.key:meters')->group(function () {
            Route::post('/meters', [MetersController::class, 'store']);
            Route::post('/meters/{id}/readings', [MetersController::class, 'record'])->whereNumber('id');
            Route::post('/meters/{id}/device-token', [MetersController::class, 'deviceToken'])->whereNumber('id');
            Route::post('/readings/batch', [MetersController::class, 'batch']);
        });
    });
});
