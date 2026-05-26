<?php
// app/Modules/Subscriptions/Listeners/SendExpiryReminder.php

namespace App\Modules\Subscriptions\Listeners;

use App\Modules\Subscriptions\Events\SubscriptionExpiringSoon;
use Illuminate\Support\Facades\Mail;

class SendExpiryReminder
{
    public function handle(SubscriptionExpiringSoon $event)
    {
        $company = $event->subscription->company;
        
        Mail::to($company->email)->send(new SubscriptionExpiryReminderMail($event->subscription));
    }
}