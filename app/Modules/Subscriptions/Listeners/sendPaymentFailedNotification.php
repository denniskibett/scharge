<?php
// app/Modules/Subscriptions/Listeners/SendPaymentFailedNotification.php

namespace App\Modules\Subscriptions\Listeners;

use App\Modules\Subscriptions\Events\SubscriptionPaymentFailed;
use Illuminate\Support\Facades\Mail;
use App\Mail\SubscriptionPaymentFailedMail;

class SendPaymentFailedNotification
{
    public function handle(SubscriptionPaymentFailed $event)
    {
        $company = $event->subscription->company;
        
        // Send email notification
        Mail::to($company->email)->send(new SubscriptionPaymentFailedMail($event->subscription));
        
        // Log the failure
        \Log::warning('Subscription payment failed', [
            'company_id' => $company->id,
            'subscription_id' => $event->subscription->id
        ]);
    }
}