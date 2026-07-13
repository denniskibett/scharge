<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\SystemHelper;

class SystemServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register SystemHelper as a singleton
        $this->app->singleton('system.helper', function ($app) {
            return new SystemHelper();
        });
        
        // Create alias for easier access - only if it doesn't already exist
        if (!class_exists('SystemHelper')) {
            class_alias(SystemHelper::class, 'SystemHelper');
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // You can also publish configs or run migrations here if needed
    }
}