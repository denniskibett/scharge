<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inbound webhooks from Safaricom / SMS gateway carry no CSRF token.
        $middleware->validateCsrfTokens(except: [
            'pay',
            'sms/mpesa/callback',
            'sms/webhook/*',
            'webhooks/sharet',
            't', // website analytics beacon: no cookies needed, always answers 204
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
            'access' => \App\Http\Middleware\CheckAccess::class,
        ]);

        // Signed-in people confirm their email with a code before using the app (only when real mail is set up).
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureEmailConfirmed::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
