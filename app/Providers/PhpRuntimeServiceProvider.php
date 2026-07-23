<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PhpRuntimeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Apply PHP runtime settings
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Apply for console commands as well
        if ($this->app->runningInConsole()) {
            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');
        }
    }
}