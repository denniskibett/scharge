<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\ApplySystemSettings;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\LogRequests;
use App\Http\Middleware\RecordUserActivity;
use App\Http\Middleware\SetUserTimezone;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\RoleMiddleware;


class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\ApplySystemSettings::class,
        \App\Http\Middleware\RoleMiddleware::class,
    ];

    // ... rest of the kernel file
}