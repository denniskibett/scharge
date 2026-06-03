<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    protected $paymentGateway;
    
    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }
    
    // Subscribe company to a plan
    public function subscribe(Company $company, $planId, $cycle = 'monthly', $paymentMethodId = null)
    {
        DB::beginTransaction();
        
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $price = $cycle === 'monthly' ? $plan->price_monthly : $plan->price_yearly;
            
            // Create subscription record
            $subscription = CompanySubscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'starts_at' => now(),
                'ends_at' => $cycle === 'monthly' ? now()->addMonth() : now()->addYear(),
                'trial_ends_at' => $plan->trial_days ? now()->addDays($plan->trial_days) : null,
                'auto_renew' => true,
                'billing_cycle' => $cycle
            ]);
            
            // Process payment if not trial
            if (!$plan->trial_days) {
                $payment = $this->paymentGateway->charge(
                    $company,
                    $price,
                    $paymentMethodId,
                    "{$plan->name} Subscription - {$cycle}"
                );
                
                // Create invoice
                $this->createInvoice($subscription, $payment);
            } else {
                $subscription->status = 'trial';
                $subscription->save();
            }
            
            DB::commit();
            return $subscription;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    // Handle monthly billing (run via cron job)
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
    
    // Renew subscription
    public function renewSubscription(CompanySubscription $subscription)
    {
        $price = $subscription->billing_cycle === 'monthly' 
            ? $subscription->plan->price_monthly 
            : $subscription->plan->price_yearly;
            
        // Get default payment method
        $paymentMethod = $subscription->company->defaultPaymentMethod;
        
        if (!$paymentMethod) {
            $subscription->status = 'past_due';
            $subscription->save();
            
            // Send notification to company
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
            
            // Create invoice
            $this->createInvoice($subscription, $payment);
            
            // Update subscription dates
            $subscription->starts_at = $subscription->ends_at;
            $subscription->ends_at = $subscription->billing_cycle === 'monthly' 
                ? $subscription->ends_at->addMonth()
                : $subscription->ends_at->addYear();
            $subscription->save();
            
            return true;
            
        } catch (\Exception $e) {
            $subscription->status = 'past_due';
            $subscription->save();
            
            event(new SubscriptionPaymentFailed($subscription));
            return false;
        }
    }
    
    protected function createInvoice($subscription, $payment)
    {
        return SubscriptionInvoice::create([
            'company_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'status' => 'paid',
            'payment_method' => $payment['method'],
            'payment_id' => $payment['id'],
            'due_date' => now(),
            'paid_at' => now(),
            'invoice_json' => json_encode($payment)
        ]);
    }
}