<?php

namespace App\Modules\SMS\Providers;

use Illuminate\Support\ServiceProvider;

class SMSServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Debug: Check if this is being called
        // dd('SMS Service Provider is booting!');
        
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadViewsFrom(resource_path('views/sms'), 'sms');
    }
}