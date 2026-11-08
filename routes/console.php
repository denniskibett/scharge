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

// Collection sweeps, 08:00 / 12:00 / 16:00 Nairobi.
// Enable when the payments:sweep command lands (rail mode, P1):
// Schedule::command('payments:sweep')
//     ->cron('0 8,12,16 * * *')
//     ->withoutOverlapping()
//     ->onOneServer();

// Subscription billing is deliberately NOT scheduled yet:
// SubscriptionService::processMonthlyBilling() charges through a stub gateway
// that always reports success, so running it would extend subscriptions for
// free. Re-enable once subscription invoices are paid via M-Pesa (P1).
