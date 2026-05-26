<?php
// app/Modules/Subscriptions/Controllers/SubscriptionController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Subscriptions\Services\SubscriptionService;
use App\Modules\Subscriptions\Services\InvoiceService;
use App\Modules\Subscriptions\Models\CompanySubscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    protected $subscriptionService;
    protected $invoiceService;

    public function __construct(SubscriptionService $subscriptionService, InvoiceService $invoiceService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->invoiceService = $invoiceService;
    }

    public function index()
    {
        $companies = Company::with('currentSubscription.plan')->paginate(20);
        return view('subscriptions::index', compact('companies'));
    }

    public function show(Company $company)
    {
        $currentSubscription = $company->currentSubscription;
        $subscriptionHistory = $company->subscriptions()->with('plan')->latest()->get();
        $invoices = $currentSubscription?->invoices()->latest()->get() ?? collect();
        
        return view('subscriptions::show', compact('company', 'currentSubscription', 'subscriptionHistory', 'invoices'));
    }

    public function subscribe(Request $request, Company $company)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'payment_method_id' => 'required_if:trial_days,0|exists:company_payment_methods,id'
        ]);

        try {
            $subscription = $this->subscriptionService->subscribe(
                $company,
                $request->plan_id,
                $request->billing_cycle,
                $request->payment_method_id
            );

            return redirect()->route('subscriptions.show', $company)
                ->with('success', 'Subscription created successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subscription: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, CompanySubscription $subscription)
    {
        $immediate = $request->input('immediate', false);
        
        $this->subscriptionService->cancelSubscription($subscription, $immediate);
        
        return back()->with('success', 'Subscription cancelled successfully!');
    }

    public function resume(CompanySubscription $subscription)
    {
        $subscription->resume();
        
        return back()->with('success', 'Subscription resumed successfully!');
    }

    public function invoices(CompanySubscription $subscription)
    {
        $invoices = $subscription->invoices()->latest()->paginate(20);
        $summary = $this->invoiceService->getInvoiceSummary($subscription);
        
        return view('subscriptions::invoices', compact('subscription', 'invoices', 'summary'));
    }

    public function downloadInvoice(SubscriptionInvoice $invoice)
    {
        $pdfPath = $this->invoiceService->generateInvoicePDF($invoice);
        
        return response()->download($pdfPath);
    }
}