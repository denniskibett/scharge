<?php

use App\Http\Controllers\Api\MetersController;
use App\Http\Controllers\Api\Mobile;
use App\Http\Middleware\AuthenticateMobile;
use App\Http\Middleware\MobileCan;
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

// scharge app (Android; docs/MOBILE_APP.md). One app for every role: the server lists each person's
// screens (MobileAccess::screens) and checks every call again. Sign in: SMS code once per phone, then phone lock / PIN.
Route::prefix('v1/app')->group(function () {
    Route::post('/auth/code', [Mobile\AuthController::class, 'sendCode'])->middleware('throttle:6,1,app-code');
    Route::post('/auth/verify', [Mobile\AuthController::class, 'verifyCode'])->middleware('throttle:10,1,app-verify');
    Route::post('/auth/password', [Mobile\AuthController::class, 'password'])->middleware('throttle:5,1,app-password');

    Route::middleware(['throttle:180,1,app', AuthenticateMobile::class])->group(function () {
        Route::get('/me', [Mobile\AuthController::class, 'me']);
        Route::get('/notifications', [Mobile\AuthController::class, 'notifications']);
        Route::post('/auth/pin', [Mobile\AuthController::class, 'setPin']);
        Route::post('/auth/unlock', [Mobile\AuthController::class, 'unlock'])->middleware('throttle:10,1,app-unlock');
        Route::post('/auth/logout', [Mobile\AuthController::class, 'logout']);

        // Tenants: their own records only.
        Route::prefix('tenant')->group(function () {
            Route::get('/summary', [Mobile\TenantController::class, 'summary'])->middleware(MobileCan::class.':tenant,invoices.view');
            Route::get('/bills', [Mobile\TenantController::class, 'bills'])->middleware(MobileCan::class.':tenant,invoices.view');
            Route::get('/bills/{id}', [Mobile\TenantController::class, 'bill'])->whereNumber('id')->middleware(MobileCan::class.':tenant,invoices.view');
            Route::get('/payments', [Mobile\TenantController::class, 'payments'])->middleware(MobileCan::class.':tenant,payments.view');
            Route::get('/repairs', [Mobile\TenantController::class, 'repairs'])->middleware(MobileCan::class.':tenant,maintenance.view');
            Route::post('/repairs', [Mobile\TenantController::class, 'reportRepair'])->middleware(MobileCan::class.':tenant,maintenance.manage');
            Route::post('/suggestions', [Mobile\TenantController::class, 'suggest'])->middleware([MobileCan::class.':tenant,tenants.view', 'throttle:5,60,app-suggest']);
        });

        // Owners, landlord staff, field staff, BnB hosts, scharge staff: company-scoped.
        Route::get('/rent', [Mobile\StaffController::class, 'rent'])->middleware(MobileCan::class.':staff,payments.view');
        Route::get('/bills', [Mobile\StaffController::class, 'bills'])->middleware(MobileCan::class.':staff,invoices.view');
        Route::get('/bills/{id}', [Mobile\StaffController::class, 'bill'])->whereNumber('id')->middleware(MobileCan::class.':staff,invoices.view');
        Route::get('/payments', [Mobile\StaffController::class, 'payments'])->middleware(MobileCan::class.':staff,payments.view');
        Route::get('/paybill/unmatched', [Mobile\StaffController::class, 'unmatched'])->middleware(MobileCan::class.':staff,payments.view');
        Route::post('/paybill/{id}/assign', [Mobile\StaffController::class, 'assign'])->whereNumber('id')->middleware(MobileCan::class.':staff,payments.manage');
        Route::get('/tenants', [Mobile\StaffController::class, 'tenants'])->middleware(MobileCan::class.':staff,tenants.view');
        Route::get('/units', [Mobile\StaffController::class, 'units'])->middleware(MobileCan::class.':staff,properties.view');
        Route::get('/bookings', [Mobile\StaffController::class, 'bookings'])->middleware(MobileCan::class.':staff,properties.view');
        Route::get('/suggestions', [Mobile\StaffController::class, 'suggestions'])->middleware(MobileCan::class.':staff,tenants.manage');
        Route::get('/companies', [Mobile\StaffController::class, 'companies'])->middleware(MobileCan::class.':staff,companies.view');
        Route::get('/meters', [Mobile\FieldController::class, 'meters'])->middleware(MobileCan::class.':staff,water.view');
        Route::get('/meters/{id}/readings', [Mobile\FieldController::class, 'readings'])->whereNumber('id')->middleware(MobileCan::class.':staff,water.view');
        Route::post('/meters/{id}/readings', [Mobile\FieldController::class, 'record'])->whereNumber('id')->middleware(MobileCan::class.':staff,water.manage');
        Route::get('/repairs', [Mobile\FieldController::class, 'repairs'])->middleware(MobileCan::class.':staff,maintenance.view');
        Route::post('/repairs/{id}', [Mobile\FieldController::class, 'updateRepair'])->whereNumber('id')->middleware(MobileCan::class.':staff,maintenance.manage');

        // Gate & visitors: guards, managers, BnB hosts and tenants, each on their own units (Gate service).
        Route::get('/gate/passes', [Mobile\FieldController::class, 'passes']);
        Route::post('/gate/verify', [Mobile\FieldController::class, 'verify']);
        Route::post('/gate/passes', [Mobile\FieldController::class, 'issue']);
        Route::post('/gate/passes/{id}', [Mobile\FieldController::class, 'act'])->whereNumber('id');
    });
});

