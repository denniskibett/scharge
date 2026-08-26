<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Exclude ALL API routes from CSRF
        'api/*',
        
        // M-Pesa Callback Routes
        'api/mpesa/*',
        'api/mpesa/callback',
        'api/mpesa/result',
        'api/mpesa/timeout',
        'api/mpesa/confirmation',
        'api/mpesa/validation',
        'api/mpesa/b2b/*',
        
        // Payments M-Pesa routes
        'payments/mpesa/*',
        'payments/mpesa/webhook/*',
        
        // SMS Webhooks
        'api/sms/webhook/*',
        
        // Test routes
        'test-no-csrf',
    ];
}