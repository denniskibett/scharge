<?php
// app/Modules/Subscriptions/Events/SubscriptionCreated.php

namespace App\Modules\Subscriptions\Events;

use App\Modules\Subscriptions\Models\CompanySubscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;

    public function __construct(CompanySubscription $subscription)
    {
        $this->subscription = $subscription;
    }
}