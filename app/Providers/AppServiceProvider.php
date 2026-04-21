<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Models\System;
use App\Helpers\SystemHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // System model event listener
        System::updated(function ($system) {
            SystemHelper::clearCache();
        });

        // Register role-based Blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives for role checking
     */
    protected function registerBladeDirectives(): void
    {
        // Check if user has a specific role
        Blade::if('role', function ($role) {
            return Auth::check() && Auth::user()->hasRole($role);
        });

        // Check if user has any of the given roles
        Blade::if('anyrole', function ($roles) {
            if (!Auth::check()) return false;
            $roles = is_array($roles) ? $roles : func_get_args();
            return Auth::user()->hasAnyRole($roles);
        });

        // Check if user has all of the given roles
        Blade::if('allroles', function ($roles) {
            if (!Auth::check()) return false;
            $roles = is_array($roles) ? $roles : func_get_args();
            return Auth::user()->hasAllRoles($roles);
        });

        // Check if user is admin
        Blade::if('admin', function () {
            return Auth::check() && Auth::user()->isAdmin();
        });

        // Check if user is staff
        Blade::if('staff', function () {
            return Auth::check() && Auth::user()->isStaff();
        });

        // Check if user is tenant
        Blade::if('tenant', function () {
            return Auth::check() && Auth::user()->isTenant();
        });

        // Check if user can manage properties
        Blade::if('canManageProperties', function () {
            return Auth::check() && Auth::user()->canManageProperties();
        });

        // Check if user can manage finances
        Blade::if('canManageFinances', function () {
            return Auth::check() && Auth::user()->canManageFinances();
        });

        // Check if user can read meters
        Blade::if('canReadMeters', function () {
            return Auth::check() && Auth::user()->canReadMeters();
        });
    }
}