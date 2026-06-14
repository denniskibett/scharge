<?php
// app/Modules/Subscriptions/Controllers/SubscriptionController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Subscriptions\Services\SubscriptionService;
use App\Modules\Subscriptions\Services\InvoiceService;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    protected $subscriptionService;
    protected $invoiceService;

    public function __construct(SubscriptionService $subscriptionService, InvoiceService $invoiceService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->invoiceService = $invoiceService;
    }

    // =============================================
    // PLAN MANAGEMENT ENDPOINTS
    // =============================================

/**
 * Get plans data for AJAX (Alpine.js table)
 */
public function getPlansData()
{
    try {
        \Log::info('=== getPlansData called ===');
        
        $plans = SubscriptionPlan::withCount('subscriptions')->orderBy('display_order')->get();
        
        \Log::info('Plans found in database: ' . $plans->count());
        
        if ($plans->isEmpty()) {
            \Log::warning('No subscription plans found in database!');
            return response()->json([
                'success' => true,
                'plans' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0],
                'debug' => ['message' => 'No plans found in database. Please run the seeder.']
            ]);
        }
        
        $formattedPlans = $plans->map(function($plan) {
            $features = $plan->features_json ?? [];
            
            \Log::info('Processing plan: ' . $plan->name, [
                'id' => $plan->id,
                'has_features' => !empty($features),
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'price_per_unit' => $features['price_per_unit'] ?? null
            ]);
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly' => (float) $plan->price_yearly,
                'trial_days' => $plan->trial_days,
                'display_order' => $plan->display_order,
                'is_active' => (bool) $plan->is_active,
                'features_json' => $features['features_list'] ?? $features ?? [],
                'subscribers_count' => $plan->subscriptions_count ?? 0,
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'price_per_unit' => $features['price_per_unit'] ?? null,
            ];
        });
        
        $response = [
            'success' => true,
            'plans' => $formattedPlans,
            'stats' => [
                'total' => $plans->count(),
                'active' => $plans->where('is_active', true)->count(),
                'inactive' => $plans->where('is_active', false)->count(),
            ],
            'debug' => [
                'plans_in_db' => $plans->count(),
                'formatted_count' => $formattedPlans->count(),
                'sample_plan' => $formattedPlans->first()
            ]
        ];
        
        \Log::info('Response prepared successfully', [
            'plans_count' => count($response['plans']),
            'response_keys' => array_keys($response)
        ]);
        
        return response()->json($response);
        
    } catch (\Exception $e) {
        \Log::error('Error in getPlansData: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error fetching plans: ' . $e->getMessage(),
            'plans' => [],
            'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0]
        ], 500);
    }
}

    /**
     * Get single plan for editing
     */
    public function getPlan($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $features = $plan->features_json ?? [];
        
        return response()->json([
            'success' => true,
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price_monthly' => (float) $plan->price_monthly,
            'price_yearly' => (float) $plan->price_yearly,
            'trial_days' => $plan->trial_days,
            'display_order' => $plan->display_order,
            'is_active' => (bool) $plan->is_active,
            'features_json' => $features['features_list'] ?? [],
            'features' => $features,
            'price_per_unit' => $features['price_per_unit'] ?? null,
            'limits' => [
                'max_properties' => $features['max_properties'] ?? 0,
                'max_units' => $features['max_units'] ?? 0,
                'max_users' => $features['max_users'] ?? 0,
                'max_tenants' => $features['max_tenants'] ?? 0,
                'storage_gb' => $features['storage_gb'] ?? 0,
            ]
        ]);
    }

    /**
     * Store a new subscription plan
     */
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'features_json' => 'nullable|array',
            'price_per_unit' => 'nullable|numeric|min:0',
        ]);
        
        // Handle features JSON
        $features = $request->input('features_json', []);
        
        // If per-unit pricing, store the price_per_unit in features
        if ($request->input('price_per_unit') && $request->input('price_per_unit') > 0) {
            $features['pricing_type'] = 'per_unit';
            $features['price_per_unit'] = (float) $request->input('price_per_unit');
        }
        
        // Ensure features_list exists
        if (!isset($features['features_list'])) {
            $features['features_list'] = [];
        }
        
        $plan = SubscriptionPlan::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'trial_days' => $validated['trial_days'] ?? 0,
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'features_json' => $features,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully',
            'plan' => $plan
        ]);
    }

    /**
     * Update a subscription plan
     */
    public function updatePlan(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug,' . $id,
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'features_json' => 'nullable|array',
            'price_per_unit' => 'nullable|numeric|min:0',
        ]);
        
        // Handle features JSON
        $features = $request->input('features_json', []);
        
        // If per-unit pricing, store the price_per_unit in features
        if ($request->input('price_per_unit') && $request->input('price_per_unit') > 0) {
            $features['pricing_type'] = 'per_unit';
            $features['price_per_unit'] = (float) $request->input('price_per_unit');
        }
        
        // Ensure features_list exists
        if (!isset($features['features_list'])) {
            $features['features_list'] = [];
        }
        
        $plan->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'trial_days' => $validated['trial_days'] ?? 0,
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'features_json' => $features,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully',
            'plan' => $plan
        ]);
    }

    /**
     * Delete a subscription plan
     */
    public function deletePlan($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        // Check if plan has active subscriptions
        if ($plan->subscriptions()->where('status', 'active')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with active subscriptions'
            ], 422);
        }
        
        $plan->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully'
        ]);
    }

    /**
     * Get plans for the subscriptions index page
     */
    public function plansIndex()
    {
        $plans = SubscriptionPlan::orderBy('display_order')->get();
        return view('subscriptions::plans', compact('plans'));
    }

    // =============================================
    // SUBSCRIPTION MANAGEMENT ENDPOINTS
    // =============================================

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