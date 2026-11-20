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

// Sharet's FX rates for showing amounts in other currencies (App\Support\FxRates; pages also refresh lazily).
Schedule::command('fx:rates')
    ->hourly()
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

// Integrations: retry webhooks, KRA eTIMS and accounting copies that failed (docs/INTEGRATIONS.md).
Schedule::call(function () {
    \App\Services\Connectors\Webhooks::retryDue();
    \App\Services\Connectors\Accounting::retryDue();
})->everyFiveMinutes()->name('integrations-retry')->withoutOverlapping()->onOneServer();

// Integrations › SMS sender ID: switch on names the networks approved on scharge's KenyaSMS account (no staff step).
Schedule::call(fn () => \App\Services\Connectors\SenderIds::sync())->hourly()->name('sender-ids-sync')->withoutOverlapping()->onOneServer();

// Claude assistant: draft replies and follow-ups in working hours (nothing is sent until a person approves it).
Schedule::call(fn () => \App\Services\Assistant\Sources::run())->everyFifteenMinutes()->between('7:00', '19:00')
    ->name('assistant-drafts')->withoutOverlapping(30)->onOneServer();
// Claude assistant: weekly data clean-up for companies that chose it (fixes wait for approval).
Schedule::call(function () {
    if (! \App\Services\Assistant\Claude::installed()) {
        return;
    }
    \App\Models\CompanyIntegration::where('provider', 'assistant')->where('enabled', true)->whereNotNull('company_id')->get()
        ->filter(fn ($r) => $r->get('cleanup', 'weekly') === 'weekly')
        ->each(fn ($r) => rescue(fn () => \App\Services\Assistant\Cleanup::scan((int) $r->company_id)));
})->weeklyOn(1, '07:00')->name('assistant-cleanup')->withoutOverlapping()->onOneServer();

// Integrations › Equity Bank (Jenga): read yesterday's deposits and match them to tenants' bills.
Schedule::call(fn () => \App\Services\Connectors\Jenga::pullAll())->dailyAt('06:30')->name('jenga-pull')->withoutOverlapping()->onOneServer();

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

// CRM cold-email campaigns (crm:campaign, from sales@sharet.africa): a batch every 10 minutes on weekdays, 08:00-21:00 Nairobi.
// Prospects with a business email, never emailed, not opted out get their property type's email. One type at a time:
// agencies first, then residential, hotels / short-stay, commercial, utilities (--after). Each batch is 40 emails at 6 a minute (~7 min).
// Daily ramp so sales@ keeps its reputation: 800 on 19 Nov, 1,500 on 20 Nov, then 2,500 a day (all types together).
// A batch also stops for the day when more than 10% of today's emails bounced. Tracking: /crm/campaigns and /crm/funnel.
$campaignCap = fn () => match (now(config('app.schedule_timezone', 'Africa/Nairobi'))->toDateString()) {
    '2026-11-19' => 800,
    '2026-11-20' => 1500,
    default => 2500,
};
// AGENCY first (added 19 Nov, user asked for agencies to be emailed at once): ~140 agencies, then the queue moves on.
$campaignOrder = ['AGENCY', 'RESIDENTIAL', 'BNB', 'COMMERCIAL', 'UTILITIES'];
// Who signs (and owns) the new prospects of each type: Waithaka Kammau (#664) all residential, Annete Sidi Kenga (#667) BNB.
// The other types alternate between the account managers. Prospects that already have an owner keep them.
$campaignManager = ['RESIDENTIAL' => 664, 'BNB' => 667];
foreach ($campaignOrder as $i => $type) {
    $after = $i ? ' --after='.implode(',', array_slice($campaignOrder, 0, $i)) : '';
    $manager = isset($campaignManager[$type]) ? ' --manager='.$campaignManager[$type] : '';
    Schedule::command("crm:campaign {$type}{$after}{$manager} --send --limit=40 --per-minute=6 --daily-cap=".$campaignCap())
        ->weekdays()->everyTenMinutes()->between('08:00', '20:59')
        ->withoutOverlapping(15)
        ->onOneServer()
        ->appendOutputTo(storage_path('logs/crm-campaigns.log'));
}

// Email open/click details (place, device) are kept 12 months at most (Kenya Data Protection Act: keep only what is needed).
Schedule::call(fn () => \App\Modules\CRM\Support\EmailEngagement::prune())
    ->name('crm-email-events-prune')->dailyAt('03:20')->onOneServer();

// CRM recap of the last working day (Monday covers the weekend): sysadmins get the team, each account manager their own day and calls.
Schedule::command('crm:daily-recap')
    ->weekdays()->at('07:30')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/crm-campaigns.log'));
