<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Laravel 12 reads the schedule from here; the old app/Console/Kernel.php
| was never run. Times are Africa/Nairobi (config app.schedule_timezone).
|
| The server needs one cron entry:
|   * * * * * cd /path/to/scharge && php artisan schedule:run >> /dev/null 2>&1
*/

// Retry settlement of confirmed STK pushes that never became a Payment.
Schedule::command('mpesa:reconcile --days=7')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Give new tenants their wallet.
Schedule::command('tenants:create-wallets')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Sharet pay links: pick up payments/expiries that a webhook missed (and connected-mode
// requests, whose webhooks go to the landlord's own Sharet account).
Schedule::command('sharet:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Rail-mode sweeps to landlords, 08:00 / 12:00 / 16:00 Nairobi (does nothing until Sharet is set up).
Schedule::command('payments:sweep')
    ->cron('0 8,12,16 * * *')
    ->withoutOverlapping()
    ->onOneServer();

// Smart water meters: post last month's final reading to water billing on the 1st.
Schedule::command('meters:post-water')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Subscription billing is deliberately NOT scheduled yet:
// SubscriptionService::processMonthlyBilling() charges through a stub gateway
// that always reports success, so running it would extend subscriptions for
// free. Re-enable once subscription invoices are paid via M-Pesa (P1).

// Rent automation (Integrations › Rent automation, docs/RENT_AUTOMATION.md). Only companies that switched it on.
// Monthly invoices: daily, so a company is billed on its invoice day (or the first run after it), never twice.
Schedule::command('rent:invoice')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

// Arrears reminders at the company's reminder days; 10:00 is outside SMS quiet hours.
Schedule::command('rent:remind')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->onOneServer();

// Channel sync (Airbnb, Booking.com calendars). Does nothing until Integrations › Channel sync is switched on.
Schedule::command('bookings:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// White label: each company's data copied to its own storage, nightly (Integrations › Nightly data copy).
Schedule::command('company:backup-offsite')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Open listings alerts: renters get new matching listings by SMS (at most one a day each), daytime only.
Schedule::command('listings:match')
    ->hourly()
    ->between('9:00', '19:00')
    ->withoutOverlapping()
    ->onOneServer();

// Sign-in locations (docs/SIGN_IN_LOCATIONS.md): refresh the free city database monthly; keep sign-ins 180 days.
Schedule::command('geo:update')->monthlyOn(3, '04:00')->withoutOverlapping()->onOneServer();
Schedule::call(fn () => \Illuminate\Support\Facades\Schema::hasTable('login_events')
    && \Illuminate\Support\Facades\DB::table('login_events')->where('created_at', '<', now()->subDays(180))->delete())->dailyAt('04:30')->name('prune-login-events')->onOneServer();

// CRM: new prospects in Nairobi's upmarket apartment areas (OSM + the curated agents' sheet), deduped against the
// CRM, imported as prospects; backs up the companies table first (storage/app/crm-backups). Mondays, early.
Schedule::command('crm:pull-nairobi --enrich=80')
    ->weeklyOn(1, '05:30')
    ->withoutOverlapping(180)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/crm-pull-nairobi.log'));
