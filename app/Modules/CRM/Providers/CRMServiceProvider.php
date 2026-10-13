<?php

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Console\ImportProspects;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the CRM console command. Routes are loaded from routes/web.php (like the other
 * modules) and the website's country lists come from HomeController.
 */
class CRMServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportProspects::class]);
        }
    }
}
