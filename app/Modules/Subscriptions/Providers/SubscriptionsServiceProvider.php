<?php
// app/Modules/Subscriptions/Providers/SubscriptionsServiceProvider.php

namespace App\Modules\Subscriptions\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register services as singletons
        $this->app->singleton(\App\Modules\Subscriptions\Services\SubscriptionService::class);
        $this->app->singleton(\App\Modules\Subscriptions\Services\PaymentGatewayService::class);
        $this->app->singleton(\App\Modules\Subscriptions\Services\InvoiceService::class);
    }

    public function boot()
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
        
        // Note: Routes are loaded from your main web.php file
        // No need to auto-load routes from module
    }
}