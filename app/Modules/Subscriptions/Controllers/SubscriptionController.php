<?php
// app/Modules/Subscriptions/Controllers/SubscriptionController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\Region;
use App\Modules\Subscriptions\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Models\RegionalAccountManager;
use App\Models\Company;
use App\Models\Unit;
use App\Models\Subcounty;
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
        $plans = SubscriptionPlan::with(['region'])
            ->orderBy('id', 'desc')
            ->get();
        
        $regions = Region::active()->ordered()->get();
        
        return view('subscriptions.index', compact('plans', 'regions'));
    }

    /**
     * Display a specific subscription plan (Admin Show)
     * Route: GET /admin/subscriptions/plans/{plan}
     */
    public function show($id)
    {
        try {
            $plan = SubscriptionPlan::with(['region.county'])->findOrFail($id);
            
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
            
            // Get regional account managers for this region
            $accountManagers = RegionalAccountManager::with('user')
                ->where('region_id', $plan->region_id)
                ->where('is_active', true)
                ->get();
            
            // Get invoices for this plan
            $invoices = SubscriptionInvoice::whereHas('subscription', function($query) use ($plan) {
                $query->where('plan_id', $plan->id);
            })->orderBy('created_at', 'desc')->get();
            
            // Get companies using this plan - safely
            $companies = CompanySubscription::with('company')
                ->where('plan_id', $plan->id)
                ->whereIn('status', ['trial', 'active'])
                ->get()
                ->pluck('company')
                ->filter();
            
            // Get the first company for breadcrumb (if exists)
            $firstCompany = $companies->isNotEmpty() ? $companies->first() : null;
            
            // Get all available regions for dropdown (if needed)
            $regions = Region::active()
                ->ordered()
                ->get()
                ->map(function($region) {
                    return [
                        'id' => $region->id,
                        'name' => $region->name,
                        'display_name' => $region->display_name,
                    ];
                });
            
            // Get wards from features
            $wards = $features['wards'] ?? [];
            
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
            
            // Prepare plan data for view - USE subcounty field
            $planData = [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'region_id' => $plan->region_id,
                'region_name' => $plan->region?->name,
                'county_name' => $plan->region?->county?->county_name,
                'subcounty' => $plan->subcounty,
                'wards' => $wards,
                'product_capabilities' => $productCapabilities,
                'business_features' => $businessFeatures,
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
            ];
            
            return view('subscriptions.show', compact(
                'planData', 
                'subscribers', 
                'regions', 
                'accountManagers', 
                'companies', 
                'invoices',
                'firstCompany'
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
            $pricingType = 'fixed';
            $pricePerUnit = 0;
            
            if ($currentSubscription && $currentSubscription->plan) {
                $plan = $currentSubscription->plan;
                $currentPrice = $plan->calculateMonthlyPrice($unitCount);
                $features = is_array($plan->features) ? $plan->features : json_decode($plan->features, true) ?? [];
                $pricingType = $features['pricing_type'] ?? 'fixed';
                $pricePerUnit = $features['price_per_unit'] ?? $plan->price_per_unit;
            }
            
            // Get invoices
            $invoices = SubscriptionInvoice::where('company_id', $company->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get all plans for subscription
            $plans = SubscriptionPlan::with(['region'])
                ->active()
                ->orderBy('id', 'desc')
                ->get();
            
            return view('subscriptions.company-show', compact(
                'company', 
                'currentSubscription', 
                'subscriptionHistory', 
                'unitCount', 
                'currentPrice',
                'pricingType',
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
        $plans = SubscriptionPlan::with(['region'])
            ->orderBy('id', 'desc')
            ->get();
        
        $regions = Region::active()->ordered()->get();
        
        return view('subscriptions.index', compact('plans', 'regions'));
    }

    /**
     * Get plans data for AJAX (Alpine.js table)
     * Route: GET /admin/subscriptions/api/plans/data
     */
    public function getPlansData()
    {
        try {
            Log::info('=== getPlansData called ===');
            
            $plans = SubscriptionPlan::with(['region'])
                ->withCount(['subscriptions as active_subscriptions_count' => function($query) {
                    $query->whereIn('status', ['active', 'trial']);
                }])
                ->orderBy('id', 'desc')
                ->get();
            
            Log::info('Plans found: ' . $plans->count());
            
            if ($plans->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'plans' => [],
                    'regions' => [],
                    'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0],
                    'message' => 'No plans found.'
                ]);
            }
            
            $formattedPlans = $plans->map(function($plan) {
                // Parse features - handle both array and JSON string
                $features = $plan->features;
                if (is_string($features)) {
                    $features = json_decode($features, true) ?? [];
                }
                if (!is_array($features)) {
                    $features = [];
                }
                
                // Get business features
                $businessFeatures = $features['business_features'] ?? [];
                
                // Get ward names for display (comma separated)
                $wardIds = $features['wards'] ?? [];
                $wardNames = [];
                if (!empty($wardIds)) {
                    $wardNames = Subcounty::whereIn('id', $wardIds)->pluck('ward')->toArray();
                }
                
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'region_id' => $plan->region_id,
                    'region_name' => $plan->region?->name,
                    'subcounty' => $plan->subcounty,
                    'subcounty_name' => $plan->subcounty,
                    'price_per_unit' => (float) $plan->price_per_unit,
                    'trial_days' => (int) $plan->trial_days,
                    'discount_percentage' => (float) $plan->discount_percentage,
                    'features' => $businessFeatures,
                    'ward_names' => implode(', ', $wardNames),
                    'is_active' => (bool) $plan->is_active,
                    'subscribers_count' => (int) $plan->active_subscriptions_count ?? 0,
                    'pricing_type' => $features['pricing_type'] ?? 'fixed',
                    'price_monthly' => $plan->calculateMonthlyPrice(1),
                    'price_yearly' => $plan->calculateYearlyPrice(1),
                    'created_at' => $plan->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $plan->updated_at?->format('Y-m-d H:i:s'),
                ];
            });
            
            // Get regions for filter
            $regions = Region::active()
                ->ordered()
                ->get()
                ->map(function($region) {
                    return [
                        'id' => $region->id,
                        'name' => $region->name,
                        'display_name' => $region->display_name,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'plans' => $formattedPlans,
                'regions' => $regions,
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
                'regions' => [],
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
            $plan = SubscriptionPlan::with(['region'])->findOrFail($id);
            
            // Parse features
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
                'region_id' => $plan->region_id,
                'subcounty' => $plan->subcounty,
                'wards' => $features['wards'] ?? [],
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
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'price_monthly' => $plan->calculateMonthlyPrice(1),
                'price_yearly' => $plan->calculateYearlyPrice(1),
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
     * Get subcounties for dropdown
     * Route: GET /admin/subscriptions/api/subcounties
     */
    public function getSubcounties(Request $request)
    {
        try {
            $countyId = $request->input('county_id');
            
            $query = Subcounty::select('id', 'county_id', 'constituency_name', 'ward', 'alias')
                ->orderBy('constituency_name');
            
            // Filter by county if provided
            if ($countyId) {
                $query->where('county_id', $countyId);
            }
            
            $subcounties = $query->get()->map(function($subcounty) {
                return [
                    'id' => $subcounty->id,
                    'county_id' => $subcounty->county_id,
                    'subcounty' => $subcounty->constituency_name,
                    'ward' => $subcounty->ward,
                    'alias' => $subcounty->alias,
                    'display_name' => $subcounty->constituency_name . ' - ' . $subcounty->ward,
                ];
            });
            
            // Get unique constituencies (group by constituency_name)
            $uniqueConstituencies = $subcounties->unique('subcounty')->values();
            
            return response()->json([
                'success' => true,
                'subcounties' => $uniqueConstituencies
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getSubcounties: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading subcounties: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wards for a subcounty
     * Route: GET /admin/subscriptions/api/subcounties/{subcounty}/wards
     */
    public function getWards($subcounty)
    {
        try {
            $wards = Subcounty::where('constituency_name', $subcounty)
                ->orderBy('ward')
                ->get()
                ->map(function($subcounty) {
                    return [
                        'id' => $subcounty->id,
                        'ward' => $subcounty->ward,
                        'alias' => $subcounty->alias,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'wards' => $wards
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getWards: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading wards: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get regions for dropdown
     * Route: GET /admin/subscriptions/api/regions
     */
    public function getRegions()
    {
        try {
            $regions = Region::with('county')
                ->active()
                ->ordered()
                ->get()
                ->map(function($region) {
                    return [
                        'id' => $region->id,
                        'name' => $region->name,
                        'county_id' => $region->county_id,
                        'county_name' => $region->county?->county_name,
                        'display_name' => $region->display_name,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'regions' => $regions
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getRegions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading regions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new subscription plan
     * Route: POST /admin/subscriptions/plans
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'region_id' => 'required|exists:regions,id',
                'subcounty' => 'nullable|string|max:100',
                'wards' => 'nullable|array',
                'wards.*' => 'integer',
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
                'pricing_type' => 'per_unit',
                'price_per_unit' => (float) $validated['price_per_unit'],
                'wards' => $validated['wards'] ?? [],
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
                'region_id' => $validated['region_id'],
                'subcounty' => $validated['subcounty'] ?? null,
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
                'region_id' => 'required|exists:regions,id',
                'subcounty' => 'nullable|string|max:100',
                'wards' => 'nullable|array',
                'wards.*' => 'integer',
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
                'pricing_type' => 'per_unit',
                'price_per_unit' => (float) $validated['price_per_unit'],
                'wards' => $validated['wards'] ?? [],
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
                'region_id' => $validated['region_id'],
                'subcounty' => $validated['subcounty'] ?? null,
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