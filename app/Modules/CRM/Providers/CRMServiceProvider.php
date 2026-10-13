<?php

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Console\ImportProspects;
use App\Modules\CRM\Models\Country;
use Illuminate\Support\Collection;
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

        // Country lists for the website forms (waitlist + book a demo).
        View::composer(['welcome', 'home.*'], function ($view) {
            static $countries;
            $countries ??= $this->countries();
            $view->with('siteCountries', $countries);
        });
    }

    /**
     * Reads the countries table; falls back to a fixed list so the website keeps working
     * before database/schema/2026_10_13_crm_waitlist.sql has been applied.
     */
    private function countries(): Collection
    {
        try {
            $rows = Country::active()->get(['id', 'name', 'iso2', 'status']);
            if ($rows->isNotEmpty()) {
                return $rows;
            }
        } catch (\Throwable) {
            // table not created yet
        }

        return collect([
            ['Kenya', 'KE', 'live'], ['Uganda', 'UG', 'next'], ['Tanzania', 'TZ', 'next'], ['Rwanda', 'RW', 'next'],
            ['Ethiopia', 'ET', 'waitlist'], ['Ghana', 'GH', 'waitlist'], ['Nigeria', 'NG', 'waitlist'],
            ['South Africa', 'ZA', 'waitlist'], ['Zambia', 'ZM', 'waitlist'],
        ])->map(fn ($c) => new Country(['name' => $c[0], 'iso2' => $c[1], 'status' => $c[2]]));
    }
}
