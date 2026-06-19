<?php
// app/Modules/Subscriptions/Controllers/SubscriptionController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Models\RegionalAccountManager;
use App\Models\Company;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Display the subscriptions index page (Admin)
     * Route: GET /admin/subscriptions
     */
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('price_per_unit')
            ->orderBy('id', 'desc')
            ->get();
        
        return view('subscriptions.index', compact('plans'));
    }

    /**
     * Display a specific subscription plan (Admin Show)
     * Route: GET /admin/subscriptions/plans/{plan}
     */
public function show($id)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);
            
            // Parse features
            $features = $plan->features ?? [];
            if (is_string($features)) {
                $features = json_decode($features, true) ?? [];
            }
            if (!is_array($features)) {
                $features = [];
            }
            
            // Get subscriber count
            $subscriberCount = $plan->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->count();
            
            // Get active subscribers with company details
            $subscribers = $plan->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->with(['company'])
                ->get()
                ->map(function($subscription) {
                    $company = $subscription->company;
                    return [
                        'id' => $subscription->id,
                        'company_id' => $company?->id,
                        'company_name' => $company?->name ?? 'N/A',
                        'company_email' => $company?->email ?? 'N/A',
                        'status' => $subscription->status,
                        'billing_cycle' => $subscription->billing_cycle,
                        'unit_count' => $subscription->unit_count ?? 0,
                        'starts_at' => $subscription->starts_at,
                        'ends_at' => $subscription->ends_at,
                        'auto_renew' => $subscription->auto_renew,
                    ];
                });
            
            // Calculate revenue from this plan
            $totalRevenue = 0;
            $activeSubscriptions = $plan->subscriptions()
                ->where('status', 'active')
                ->with(['company'])
                ->get();
            
            foreach ($activeSubscriptions as $subscription) {
                $company = $subscription->company;
                if ($company) {
                    $unitCount = Unit::where('company_id', $company->id)
                        ->whereIn('status', ['occupied', 'available'])
                        ->count();
                    $totalRevenue += $plan->calculateMonthlyPrice($unitCount);
                }
            }
            
            // Get invoices for this plan
            $invoices = SubscriptionInvoice::whereHas('subscription', function($query) use ($plan) {
                $query->where('plan_id', $plan->id);
            })->orderBy('created_at', 'desc')->get();
            
            // Get companies using this plan
            $companies = CompanySubscription::with('company')
                ->where('plan_id', $plan->id)
                ->whereIn('status', ['trial', 'active'])
                ->get()
                ->pluck('company')
                ->filter();
            
            // =============================================
            // FIX: Get Account Managers for this plan
            // =============================================
            $accountManagers = collect();
            
            // Try to get account managers from plan features
            if (isset($features['account_managers']) && is_array($features['account_managers'])) {
                $managerIds = $features['account_managers'];
                if (!empty($managerIds)) {
                    $accountManagers = RegionalAccountManager::with(['user', 'region'])
                        ->whereIn('id', $managerIds)
                        ->where('is_active', true)
                        ->get();
                }
            }
            
            // If no account managers in features, try to get from region
            if ($accountManagers->isEmpty() && isset($features['region_id'])) {
                $accountManagers = RegionalAccountManager::with(['user', 'region'])
                    ->where('region_id', $features['region_id'])
                    ->where('is_active', true)
                    ->get();
            }
            
            // If still empty, get all active account managers
            if ($accountManagers->isEmpty()) {
                $accountManagers = RegionalAccountManager::with(['user', 'region'])
                    ->where('is_active', true)
                    ->limit(5)
                    ->get();
            }
            
            // Get product capabilities
            $productCapabilities = $features['product_capabilities'] ?? [
                'max_units' => 0,
                'max_users' => 0,
                'max_tenants' => 0,
                'storage_gb' => 0,
                'max_properties' => 0
            ];
            
            // Get business features
            $businessFeatures = $features['business_features'] ?? [];
            
            // =============================================
            // FIX: Get first company safely
            // =============================================
            $firstCompany = null;
            if ($companies->isNotEmpty()) {
                $firstCompany = $companies->first();
            }
            
            // Prepare plan data for view
            $planData = [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_per_unit' => (float) $plan->price_per_unit,
                'trial_days' => $plan->trial_days,
                'discount_percentage' => (float) $plan->discount_percentage,
                'is_active' => (bool) $plan->is_active,
                'created_at' => $plan->created_at?->format('M d, Y H:i'),
                'updated_at' => $plan->updated_at?->format('M d, Y H:i'),
                'subscriber_count' => $subscriberCount,
                'total_revenue' => $totalRevenue,
                'monthly_price' => $plan->calculateMonthlyPrice(1),
                'yearly_price' => $plan->calculateYearlyPrice(1),
                'product_capabilities' => $productCapabilities,
                'business_features' => $businessFeatures,
                'price_tier' => $plan->price_tier_label,
                'price_tier_color' => $plan->price_tier_color,
                'region_id' => $features['region_id'] ?? null,
                'region_name' => $features['region_name'] ?? 'N/A',
                'account_managers' => $accountManagers,
            ];
            
            return view('subscriptions.show', compact(
                'planData', 
                'subscribers', 
                'companies', 
                'invoices',
                'accountManagers',
                'firstCompany'  // Pass to view (can be null)
            ));
            
        } catch (\Exception $e) {
            Log::error('Error showing subscription plan: ' . $e->getMessage(), [
                'plan_id' => $id ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return redirect()->route('admin.subscriptions.plans.index')
                ->with('error', 'Error loading plan details: ' . $e->getMessage());
        }
    }


    /**
     * Get plans data for AJAX - OPTIMIZED with eager loading
     * Route: GET /admin/subscriptions/api/plans/data
     */
    public function getPlansData()
    {
        try {
            Log::info('=== getPlansData called ===');
            
            // Single query with eager loading - NO N+1
            $plans = SubscriptionPlan::withCount(['subscriptions as active_subscriptions_count' => function($query) {
                $query->whereIn('status', ['active', 'trial']);
            }])
            ->orderBy('price_per_unit')
            ->orderBy('id', 'desc')
            ->get();
            
            Log::info('Plans found: ' . $plans->count());
            
            if ($plans->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'plans' => [],
                    'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0]
                ]);
            }
            
            // Pre-parse features once - avoid repeated JSON decoding
            $formattedPlans = $plans->map(function($plan) {
                // Parse features once
                $features = is_string($plan->features) 
                    ? json_decode($plan->features, true) ?? [] 
                    : ($plan->features ?? []);
                
                if (!is_array($features)) {
                    $features = [];
                }
                
                $businessFeatures = $features['business_features'] ?? [];
                
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,  // KEEP SLUG
                    'description' => $plan->description,
                    'price_per_unit' => (float) $plan->price_per_unit,
                    'trial_days' => (int) $plan->trial_days,
                    'discount_percentage' => (float) $plan->discount_percentage,
                    'features' => $businessFeatures,
                    'is_active' => (bool) $plan->is_active,
                    'subscribers_count' => (int) $plan->active_subscriptions_count ?? 0,
                    'price_monthly' => $plan->calculateMonthlyPrice(1),
                    'price_yearly' => $plan->calculateYearlyPrice(1),
                    'price_tier' => $plan->price_tier_label,
                    'price_tier_color' => $plan->price_tier_color,
                ];
            });
            
            return response()->json([
                'success' => true,
                'plans' => $formattedPlans,
                'stats' => [
                    'total' => $plans->count(),
                    'active' => $plans->where('is_active', true)->count(),
                    'inactive' => $plans->where('is_active', false)->count(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getPlansData: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'plans' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0]
            ], 500);
        }
    }

    /**
     * Get single plan for editing
     * Route: GET /admin/subscriptions/api/plans/{plan}
     */
    public function getPlan($id)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);
            
            $features = $plan->features;
            if (is_string($features)) {
                $features = json_decode($features, true) ?? [];
            }
            if (!is_array($features)) {
                $features = [];
            }
            
            return response()->json([
                'success' => true,
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_per_unit' => (float) $plan->price_per_unit,
                'trial_days' => (int) $plan->trial_days,
                'discount_percentage' => (float) $plan->discount_percentage,
                'is_active' => (bool) $plan->is_active,
                'product_capabilities' => $features['product_capabilities'] ?? [
                    'max_units' => 0,
                    'max_users' => 0,
                    'max_tenants' => 0,
                    'storage_gb' => 0,
                    'max_properties' => 0
                ],
                'business_features' => $features['business_features'] ?? [],
                'region_id' => $features['region_id'] ?? null,
                'region_name' => $features['region_name'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getPlan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Plan not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Display the company subscription show page (Dashboard)
     * Route: GET /admin/subscriptions/company/{company}/dashboard
     */
    public function companyShow(Company $company)
    {
        try {
            // Get current subscription
            $currentSubscription = $company->subscriptions()
                ->with(['plan'])
                ->whereIn('status', ['active', 'trial'])
                ->first();
            
            // Get subscription history
            $subscriptionHistory = $company->subscriptions()
                ->with(['plan'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get unit count
            $unitCount = Unit::where('company_id', $company->id)
                ->whereIn('status', ['occupied', 'available'])
                ->count();
            
            // Calculate current price
            $currentPrice = 0;
            $pricePerUnit = 0;
            
            if ($currentSubscription && $currentSubscription->plan) {
                $plan = $currentSubscription->plan;
                $currentPrice = $plan->calculateMonthlyPrice($unitCount);
                $pricePerUnit = $plan->price_per_unit;
            }
            
            // Get invoices
            $invoices = SubscriptionInvoice::where('company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get all plans for subscription
            $plans = SubscriptionPlan::where('is_active', true)
                ->orderBy('price_per_unit')
                ->get();
            
            return view('subscriptions.company-show', compact(
                'company', 
                'currentSubscription', 
                'subscriptionHistory', 
                'unitCount', 
                'currentPrice',
                'pricePerUnit',
                'invoices',
                'plans'
            ));
            
        } catch (\Exception $e) {
            Log::error('Error in companyShow: ' . $e->getMessage());
            return redirect()->route('admin.companies.show', $company)
                ->with('error', 'Error loading subscription details: ' . $e->getMessage());
        }
    }

    /**
     * Display all plans for selection (Admin Plans Index)
     * Route: GET /admin/subscriptions/plans
     */
    public function plansIndex()
    {
        $plans = SubscriptionPlan::orderBy('price_per_unit')
            ->orderBy('id', 'desc')
            ->get();
        return view('subscriptions.index', compact('plans'));
    }


    /**
     * Store a new subscription plan
     * Route: POST /admin/subscriptions/plans
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:subscription_plans,slug',
                'description' => 'nullable|string',
                'price_per_unit' => 'required|numeric|min:0',
                'trial_days' => 'nullable|integer|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'boolean',
                'product_capabilities' => 'nullable|array',
                'business_features' => 'nullable|array',
            ]);
            
            // Prepare features array
            $features = [
                'product_capabilities' => $request->input('product_capabilities', [
                    'max_units' => 0,
                    'max_users' => 0,
                    'max_tenants' => 0,
                    'storage_gb' => 0,
                    'max_properties' => 0
                ]),
                'business_features' => $request->input('business_features', []),
            ];
            
            $plan = SubscriptionPlan::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'price_per_unit' => $validated['price_per_unit'],
                'trial_days' => $validated['trial_days'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
                'features' => json_encode($features),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'plan' => $plan
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a subscription plan
     * Route: PUT/PATCH /admin/subscriptions/plans/{plan}
     */
    public function update(Request $request, $id)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:subscription_plans,slug,' . $id,
                'description' => 'nullable|string',
                'price_per_unit' => 'required|numeric|min:0',
                'trial_days' => 'nullable|integer|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'boolean',
                'product_capabilities' => 'nullable|array',
                'business_features' => 'nullable|array',
            ]);
            
            // Prepare features array
            $features = [
                'product_capabilities' => $request->input('product_capabilities', [
                    'max_units' => 0,
                    'max_users' => 0,
                    'max_tenants' => 0,
                    'storage_gb' => 0,
                    'max_properties' => 0
                ]),
                'business_features' => $request->input('business_features', []),
            ];
            
            $plan->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'price_per_unit' => $validated['price_per_unit'],
                'trial_days' => $validated['trial_days'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
                'features' => json_encode($features),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
                'plan' => $plan
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a subscription plan
     * Route: DELETE /admin/subscriptions/plans/{plan}
     */
    public function destroy($id)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);
            
            if ($plan->subscriptions()->whereIn('status', ['active', 'trial'])->count() > 0) {
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
            
        } catch (\Exception $e) {
            Log::error('Error in destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscribers for a plan
     * Route: GET /admin/subscriptions/api/plans/{plan}/subscribers
     */
    public function getSubscribers($planId)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $subscribers = $plan->subscriptions()
                ->with(['company'])
                ->whereIn('status', ['active', 'trial'])
                ->get()
                ->map(function($subscription) {
                    return [
                        'id' => $subscription->id,
                        'company_id' => $subscription->company_id,
                        'company_name' => $subscription->company?->name ?? 'N/A',
                        'company_email' => $subscription->company?->email ?? 'N/A',
                        'status' => $subscription->status,
                        'billing_cycle' => $subscription->billing_cycle,
                        'unit_count' => $subscription->unit_count,
                        'starts_at' => $subscription->starts_at?->format('Y-m-d'),
                        'ends_at' => $subscription->ends_at?->format('Y-m-d'),
                    ];
                });
            
            return response()->json([
                'success' => true,
                'plan' => $plan->name,
                'subscribers' => $subscribers
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getSubscribers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading subscribers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company subscriptions for dashboard
     * Route: GET /admin/subscriptions/api/company-subscriptions
     */
    public function getCompanySubscriptions(Request $request)
    {
        try {
            $companyId = $request->input('company_id');
            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company ID required'
                ], 422);
            }
            
            $company = Company::findOrFail($companyId);
            $subscriptions = $company->subscriptions()
                ->with(['plan'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptions
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getCompanySubscriptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }
}