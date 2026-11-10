<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Modules\System\Models\System;
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
        // Payment attempts (docs/APP_DATA.md): how each M-Pesa prompt and Sharet pay page ended.
        \App\Models\MpesaStk::updated(fn ($stk) => $stk->wasChanged('response_code')
            && \App\Services\Payments\PaymentAttempts::stkResult($stk->checkout_request_id, $stk->response_code, $stk->response_description));
        \App\Models\SharetPaymentRequest::updated(fn ($r) => $r->wasChanged('status') && \App\Services\Payments\PaymentAttempts::sharetResult((int) $r->id, (string) $r->status));

        // Receipts on the phone: a push to the tenant when a payment lands (free; docs/APP_DATA.md).
        \App\Modules\Payments\Models\Payment::created(function ($payment) {
            if ($payment->status !== 'completed' || ! \App\Services\App\PushService::enabled()) {
                return;
            }
            $userId = \App\Support\CompanyAccess::bypass(fn () => \App\Models\Tenant::find($payment->tenant_id)?->user_id);
            if ($userId) {
                dispatch(fn () => app(\App\Services\App\PushService::class)->toUser((int) $userId, 'Payment received',
                    'KES '.number_format((float) $payment->amount).' received. Thank you.', ['type' => 'receipt', 'invoice_id' => (string) $payment->invoice_id]))->afterResponse();
            }
        });

        // Sign-in locations (docs/SIGN_IN_LOCATIONS.md): website sign-ins and failed attempts.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => app(\App\Services\Analytics\SignIns::class)
            ->record($e->user, request(), 'web', $e->remember ? 'remember' : 'password'));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, fn ($e) => app(\App\Services\Analytics\SignIns::class)
            ->record($e->user, request(), 'web', 'password', false));

        // Bill emails: the tenant gets a receipt the moment an invoice turns fully paid (any payment route).
        foreach ([\App\Modules\Payments\Models\Invoice::class, \App\Models\Invoice::class] as $invoiceModel) {
            $invoiceModel::updated(function ($invoice) {
                if ($invoice->wasChanged('status') && $invoice->status === 'paid') {
                    \App\Services\InvoiceMail::paid($invoice);
                }
            });
        }

        // System model event listener - Check if System model exists
        if (class_exists('App\Modules\System\Models\System')) {
            System::updated(function ($system) {
                SystemHelper::clearCache();
            });
        }

        // One morph name per entity, whichever of the twin classes is used.
        // App\Models\Tenant/User are empty subclasses of the module models; the
        // live data (wallets.holder_type, model_has_roles.model_type) stores the
        // App\Models\* names, so those strings are kept as the aliases and both
        // classes now resolve to them. Not enforced, so other models are unaffected.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'App\Models\Tenant' => \App\Modules\Tenants\Models\Tenant::class,
            'App\Models\User' => \App\Modules\Users\Models\User::class,
        ]);

        // Mail and SMS settings saved on Settings › Integrations override .env.
        try {
            \App\Services\Integrations::applyToConfig();
        } catch (\Throwable $e) {
            report($e);   // never take the app down over integration settings
        }

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
