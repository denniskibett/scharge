<?php
// app/Modules/Subscriptions/Services/SubscriptionService.php

namespace App\Modules\Subscriptions\Services;

use App\Models\Company;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Events\SubscriptionCreated;
use App\Modules\Subscriptions\Events\SubscriptionRenewed;
use App\Modules\Subscriptions\Events\SubscriptionPaymentFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected $paymentGateway;

    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function subscribe(Company $company, $planId, $cycle = 'monthly', $paymentMethodId = null)
    {
        DB::beginTransaction();
        
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $price = $plan->getPriceForCycle($cycle);
            
            $subscription = CompanySubscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => $plan->trial_days > 0 ? 'trial' : 'active',
                'billing_cycle' => $cycle,
                'starts_at' => now(),
                'ends_at' => $cycle === 'monthly' ? now()->addMonth() : now()->addYear(),
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'auto_renew' => true,
                'payment_method_id' => $paymentMethodId
            ]);
            
            // Process payment if no trial
            if ($plan->trial_days == 0 && $paymentMethodId) {
                $payment = $this->paymentGateway->charge(
                    $company,
                    $price,
                    $paymentMethodId,
                    "{$plan->name} Subscription - {$cycle}"
                );
                
                $this->createInvoice($subscription, $payment);
            }
            
            DB::commit();
            event(new SubscriptionCreated($subscription));
            
            return $subscription;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function renewSubscription(CompanySubscription $subscription)
    {
        $price = $subscription->plan->getPriceForCycle($subscription->billing_cycle);
        
        $paymentMethod = $subscription->paymentMethod ?? $subscription->company->defaultPaymentMethod;
        
        if (!$paymentMethod) {
            $subscription->status = 'past_due';
            $subscription->save();
            
            event(new SubscriptionPaymentFailed($subscription));
            return false;
        }
        
        try {
            $payment = $this->paymentGateway->charge(
                $subscription->company,
                $price,
                $paymentMethod->id,
                "{$subscription->plan->name} Renewal"
            );
            
            $this->createInvoice($subscription, $payment);
            
            // Update subscription dates
            $subscription->starts_at = $subscription->ends_at;
            $subscription->ends_at = $subscription->billing_cycle === 'monthly' 
                ? $subscription->ends_at->addMonth()
                : $subscription->ends_at->addYear();
            $subscription->status = 'active';
            $subscription->save();
            
            event(new SubscriptionRenewed($subscription));
            
            return true;
            
        } catch (\Exception $e) {
            $subscription->status = 'past_due';
            $subscription->save();
            
            event(new SubscriptionPaymentFailed($subscription));
            Log::error('Subscription renewal failed: ' . $e->getMessage());
            
            return false;
        }
    }

    public function createInvoice(CompanySubscription $subscription, array $paymentData)
    {
        return SubscriptionInvoice::create([
            'company_subscription_id' => $subscription->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'USD',
            'status' => 'paid',
            'payment_method' => $paymentData['method'],
            'payment_id' => $paymentData['id'],
            'due_date' => now(),
            'paid_at' => now(),
            'invoice_json' => $paymentData
        ]);
    }

    public function processMonthlyBilling()
    {
        $subscriptions = CompanySubscription::where('status', 'active')
            ->where('auto_renew', true)
            ->whereDate('ends_at', '<=', now()->addDays(3))
            ->whereDate('ends_at', '>', now())
            ->get();
            
        foreach ($subscriptions as $subscription) {
            $this->renewSubscription($subscription);
        }
    }

    public function cancelSubscription(CompanySubscription $subscription, $immediate = false)
    {
        $subscription->cancel($immediate);
        return true;
    }

    private function generateInvoiceNumber()
    {
        return 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
    }
}