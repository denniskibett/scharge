// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Process subscription renewals daily at 2 AM
    $schedule->call(function () {
        app(SubscriptionService::class)->processMonthlyBilling();
    })->dailyAt('02:00');
    
    // Handle failed subscription retries
    $schedule->call(function () {
        // Retry failed payments after 3 days
        CompanySubscription::where('status', 'past_due')
            ->where('ends_at', '<', now()->subDays(3))
            ->update(['status' => 'expired']);
    })->daily();
    
    // Send subscription expiry reminders
    $schedule->call(function () {
        // Send 7 days before expiry
        $expiring = CompanySubscription::where('status', 'active')
            ->whereDate('ends_at', now()->addDays(7));
            
        foreach ($expiring as $subscription) {
            event(new SubscriptionExpiringSoon($subscription));
        }
    })->daily();
}