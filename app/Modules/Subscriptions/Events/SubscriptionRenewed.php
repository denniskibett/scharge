<?php
// app/Modules/Subscriptions/Events/SubscriptionRenewed.php

namespace App\Modules\Subscriptions\Events;

use App\Modules\Subscriptions\Models\CompanySubscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;

    public function __construct(CompanySubscription $subscription)
    {
        $this->subscription = $subscription;
    }
}