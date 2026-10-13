<?php

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Console\ImportProspects;
use App\Modules\CRM\Support\Countries;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CRMServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportProspects::class]);
        }

        // Country lists for the website forms: [code => name], live and next launches first.
        View::composer(['welcome', 'home.*'], function ($view) {
            $view->with([
                'waitlistCountries' => array_map(fn ($c) => $c['name'], Countries::waitlist()),
                'demoCountries' => array_map(fn ($c) => $c['name'], Countries::ordered()),
            ]);
        });
    }
}
