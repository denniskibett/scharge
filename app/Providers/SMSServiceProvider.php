<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class SMSServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Try to register route directly
        Route::get('/sms-provider-test', function () {
            return 'Route registered directly in provider!';
        });
    }

    public function register(): void
    {
        //
    }
}