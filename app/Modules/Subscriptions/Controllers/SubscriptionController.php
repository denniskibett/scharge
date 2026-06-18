<?php
// app/Modules/Subscriptions/Controllers/SubscriptionController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{

    /**
     * Display a specific subscription plan
     * Route: /admin/subscriptions/company/{plan}
     */
    public function show(SubscriptionPlan $plan)
    {
        try {
            // Load relationships
            $plan->load(['region.county', 'subcounty']);
            
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
                    $unitCount = \App\Models\Unit::where('company_id', $company->id)
                        ->whereIn('status', ['occupied', 'available'])
                        ->count();
                    $totalRevenue += $plan->calculateMonthlyPrice($unitCount);
                }
            }
            
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
            
            // Prepare plan data for view
            $planData = [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'region_id' => $plan->region_id,
                'region_name' => $plan->region?->name,
                'county_name' => $plan->region?->county?->county_name,
                'subcounty_id' => $plan->subcounty_id,
                'subcounty_name' => $plan->subcounty?->ward ?? $plan->subcounty?->constituency_name,
                'price_per_unit' => (float) $plan->price_per_unit,
                'min_units' => (int) $plan->min_units,
                'max_units' => (int) $plan->max_units,
                'unit_range' => $plan->unit_range,
                'trial_days' => $plan->trial_days,
                'discount_percentage' => (float) $plan->discount_percentage,
                'features' => $plan->features ?? [],
                'is_active' => (bool) $plan->is_active,
                'display_order' => $plan->display_order,
                'created_at' => $plan->created_at?->format('M d, Y H:i'),
                'updated_at' => $plan->updated_at?->format('M d, Y H:i'),
                'subscriber_count' => $subscriberCount,
                'total_revenue' => $totalRevenue,
            ];
            
            return view('subscriptions::show', compact('planData', 'subscribers', 'regions'));
            
        } catch (\Exception $e) {
            Log::error('Error showing subscription plan: ' . $e->getMessage(), [
                'plan_id' => $plan->id ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return redirect()->route('admin.subscriptions.plans.index')
                ->with('error', 'Error loading plan details: ' . $e->getMessage());
        }
    }


    /**
     * Get plans data for AJAX (Alpine.js table)
     * Route: /admin/subscriptions/api/plans/data
     */
    public function getPlansData()
    {
        try {
            Log::info('=== getPlansData called ===');
            
            $plans = SubscriptionPlan::withCount('subscriptions')
                ->orderBy('display_order')
                ->get();
            
            Log::info('Plans found in database: ' . $plans->count());
            
            if ($plans->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'plans' => [],
                    'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0],
                    'message' => 'No plans found. Please run the seeder.'
                ]);
            }
            
            $formattedPlans = $plans->map(function($plan) {
                $features = $plan->features_json ?? [];
                $pricingType = $features['pricing_type'] ?? 'fixed';
                $sampleUnitCount = 100;
                
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price_monthly' => (float) $plan->price_monthly,
                    'price_yearly' => (float) $plan->price_yearly,
                    'display_monthly' => $pricingType === 'per_unit' 
                        ? ($features['price_per_unit'] ?? 0) * $sampleUnitCount
                        : (float) $plan->price_monthly,
                    'display_yearly' => $pricingType === 'per_unit'
                        ? (($features['price_per_unit'] ?? 0) * $sampleUnitCount * 12) * 0.9
                        : (float) $plan->price_yearly,
                    'trial_days' => $plan->trial_days,
                    'display_order' => $plan->display_order,
                    'is_active' => (bool) $plan->is_active,
                    'features_json' => $features['features_list'] ?? [],
                    'subscribers_count' => $plan->subscriptions_count ?? 0,
                    'pricing_type' => $pricingType,
                    'price_per_unit' => $features['price_per_unit'] ?? null,
                    'max_units' => $features['max_units'] ?? 0,
                    'unit_range' => $plan->unit_range ?? 'Unlimited',
                    'limits' => [
                        'max_properties' => $features['max_properties'] ?? 0,
                        'max_units' => $features['max_units'] ?? 0,
                        'max_users' => $features['max_users'] ?? 0,
                        'max_tenants' => $features['max_tenants'] ?? 0,
                        'storage_gb' => $features['storage_gb'] ?? 0,
                    ]
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
     */
    public function getPlan($id)
    {
        try {
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
                'pricing_type' => $features['pricing_type'] ?? 'fixed',
                'limits' => [
                    'max_properties' => $features['max_properties'] ?? 0,
                    'max_units' => $features['max_units'] ?? 0,
                    'max_users' => $features['max_users'] ?? 0,
                    'max_tenants' => $features['max_tenants'] ?? 0,
                    'storage_gb' => $features['storage_gb'] ?? 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }
    }

    /**
     * Store a new subscription plan
     */
    public function storePlan(Request $request)
    {
        try {
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
            
            $features = $request->input('features_json', []);
            
            if ($request->input('price_per_unit') && $request->input('price_per_unit') > 0) {
                $features['pricing_type'] = 'per_unit';
                $features['price_per_unit'] = (float) $request->input('price_per_unit');
            } else {
                $features['pricing_type'] = 'fixed';
            }
            
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
            
        } catch (\Exception $e) {
            Log::error('Error in storePlan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a subscription plan
     */
    public function updatePlan(Request $request, $id)
    {
        try {
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
            
            $features = $request->input('features_json', []);
            
            if ($request->input('price_per_unit') && $request->input('price_per_unit') > 0) {
                $features['pricing_type'] = 'per_unit';
                $features['price_per_unit'] = (float) $request->input('price_per_unit');
            } else {
                $features['pricing_type'] = 'fixed';
            }
            
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
            
        } catch (\Exception $e) {
            Log::error('Error in updatePlan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a subscription plan
     */
    public function deletePlan($id)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);
            
            if ($plan->subscriptions()->count() > 0) {
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
            return response()->json([
                'success' => false,
                'message' => 'Error deleting plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get plans for the subscriptions index page
     */
    public function plansIndex()
    {
        $plans = SubscriptionPlan::orderBy('display_order')->get();
        $currentSubscription = auth()->user()->currentSubscription;
        $company = auth()->user()->company;
        $pricingType = $plans->first()?->features_json['pricing_type'] ?? 'fixed';
        $subscriptionHistory = $company->subscriptions()->with('plan')->orderBy('created_at', 'desc')->get();
        $invoices = \App\Models\Invoice::where('company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return view('subscriptions.show', compact('plans', 'currentSubscription', 'company', 'pricingType', 'subscriptionHistory', 'invoices'));
    }

    /**
     * Get subscribers for a plan
     */
    public function getSubscribers($planId)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $subscribers = $plan->subscriptions()->with('company')->get();
            
            return response()->json([
                'success' => true,
                'plan' => $plan,
                'subscribers' => $subscribers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading subscribers'
            ], 500);
        }
    }

    /**
     * Get regions for dropdown
     * Route: /admin/subscriptions/api/regions
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
}