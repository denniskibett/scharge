<?php
// app/Modules/Subscriptions/Controllers/SubscriptionController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Unit;
use App\Modules\Subscriptions\Models\CompanySubscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    // =============================================
    // PLAN MANAGEMENT ENDPOINTS
    // =============================================

    /**
     * Get plans data for AJAX (Alpine.js table)
     * Route: /admin/subscriptions/api/plans/data
     */
/**
 * Get plans data for AJAX (Alpine.js table)
 * Route: /admin/subscriptions/api/plans/data
 */
public function getPlansData()
{
    try {
        Log::info('=== getPlansData called ===');
        
        $plans = SubscriptionPlan::withCount('subscriptions')->orderBy('display_order')->get();
        
        Log::info('Plans found in database: ' . $plans->count());
        
        if ($plans->isEmpty()) {
            Log::warning('No subscription plans found in database!');
            return response()->json([
                'success' => true,
                'plans' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0],
                'debug' => ['message' => 'No plans found in database. Please run the seeder.']
            ]);
        }
        
        $formattedPlans = $plans->map(function($plan) {
            $features = $plan->features_json ?? [];
            $pricingType = $features['pricing_type'] ?? 'fixed';
            $sampleUnitCount = 100;
            
            $displayMonthlyPrice = $pricingType === 'per_unit' 
                ? ($features['price_per_unit'] ?? 0) * $sampleUnitCount
                : (float) $plan->price_monthly;
                
            $displayYearlyPrice = $pricingType === 'per_unit'
                ? (($features['price_per_unit'] ?? 0) * $sampleUnitCount * 12) * 0.9
                : (float) $plan->price_yearly;
            
            // Calculate unit range manually - don't use $plan->unit_range
            $maxUnits = $features['max_units'] ?? 0;
            $minUnits = 1;
            
            // Find previous plan's max units
            $previousPlan = SubscriptionPlan::where('display_order', '<', $plan->display_order)
                ->orderBy('display_order', 'desc')
                ->first();
            
            if ($previousPlan) {
                $prevFeatures = $previousPlan->features_json ?? [];
                $prevMax = $prevFeatures['max_units'] ?? 0;
                $minUnits = $prevMax === 0 ? 1 : $prevMax + 1;
            }
            
            $unitRange = $maxUnits === 0 ? 'Unlimited' : ($minUnits . ' - ' . number_format($maxUnits));
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => (float) $plan->price_monthly,
                'price_yearly' => (float) $plan->price_yearly,
                'display_monthly' => $displayMonthlyPrice,
                'display_yearly' => $displayYearlyPrice,
                'trial_days' => $plan->trial_days,
                'display_order' => $plan->display_order,
                'is_active' => (bool) $plan->is_active,
                'features_json' => $features['features_list'] ?? [],
                'subscribers_count' => $plan->subscriptions_count ?? 0,
                'pricing_type' => $pricingType,
                'price_per_unit' => $features['price_per_unit'] ?? null,
                'max_units' => $maxUnits,
                'unit_range' => $unitRange,
                'limits' => [
                    'max_properties' => $features['max_properties'] ?? 0,
                    'max_units' => $maxUnits,
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
            ],
            'debug' => [
                'plans_in_db' => $plans->count(),
                'formatted_count' => $formattedPlans->count(),
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in getPlansData: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'plans' => [],
            'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0],
            'debug' => ['error' => $e->getMessage()]
        ], 500);
    }
}

    /**
     * Get single plan for editing
     * Route: /admin/subscriptions/api/plans/{plan}
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
            Log::error('Error in getPlan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Plan not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get subscribers for a plan
     * Route: /admin/subscriptions/api/plans/{plan}/subscribers
     */
    public function getSubscribers($planId)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            
            $subscribers = CompanySubscription::where('plan_id', $planId)
                ->where('status', 'active')
                ->with(['company', 'plan'])
                ->get()
                ->map(function($subscription) {
                    $company = $subscription->company;
                    $plan = $subscription->plan;
                    $features = $plan ? ($plan->features_json ?? []) : [];
                    
                    $unitCount = $company ? Unit::where('company_id', $company->id)
                        ->whereIn('status', ['occupied', 'available'])
                        ->count() : 0;
                    
                    $monthlyPrice = $plan ? $plan->calculateMonthlyPrice($unitCount) : 0;
                    
                    return [
                        'id' => $subscription->id,
                        'company' => $company ? [
                            'id' => $company->id,
                            'name' => $company->name,
                            'email' => $company->email,
                        ] : null,
                        'status' => $subscription->status,
                        'billing_cycle' => $subscription->billing_cycle,
                        'unit_count' => $unitCount,
                        'monthly_price' => $monthlyPrice,
                        'starts_at' => $subscription->starts_at ? $subscription->starts_at->format('Y-m-d') : null,
                        'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
                        'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : null,
                        'auto_renew' => (bool) $subscription->auto_renew,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price_monthly' => (float) $plan->price_monthly,
                    'price_yearly' => (float) $plan->price_yearly,
                    'trial_days' => $plan->trial_days,
                    'features_json' => $plan->features_json['features_list'] ?? [],
                ],
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
     * Get all company subscriptions for the dashboard
     * Route: /admin/subscriptions/api/company-subscriptions
     */
    public function getCompanySubscriptions()
    {
        try {
            Log::info('=== getCompanySubscriptions called ===');
            
            $subscriptions = CompanySubscription::with(['company', 'plan'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            Log::info('Company subscriptions found: ' . $subscriptions->count());
            
            if ($subscriptions->isEmpty()) {
                Log::warning('No company subscriptions found in database!');
                return response()->json([
                    'success' => true,
                    'subscriptions' => [],
                    'stats' => [
                        'total' => 0,
                        'active' => 0,
                        'trial' => 0,
                        'cancelled' => 0,
                        'expired' => 0,
                        'past_due' => 0
                    ],
                    'debug' => ['message' => 'No company subscriptions found. Companies need to subscribe to plans.']
                ]);
            }
            
            $formattedSubscriptions = $subscriptions->map(function($subscription) {
                $company = $subscription->company;
                $plan = $subscription->plan;
                $features = $plan ? ($plan->features_json ?? []) : [];
                $pricingType = $features['pricing_type'] ?? 'fixed';
                
                // Calculate unit count
                $unitCount = $company ? Unit::where('company_id', $company->id)
                    ->whereIn('status', ['occupied', 'available'])
                    ->count() : 0;
                
                // Calculate monthly price
                if ($pricingType === 'per_unit') {
                    $pricePerUnit = $features['price_per_unit'] ?? 0;
                    $monthlyPrice = $pricePerUnit * $unitCount;
                } else {
                    $monthlyPrice = $subscription->billing_cycle === 'monthly' 
                        ? ($plan ? (float) $plan->price_monthly : 0)
                        : ($plan ? (float) $plan->price_yearly : 0);
                }
                
                // Calculate days remaining
                $daysRemaining = 0;
                if ($subscription->ends_at) {
                    $daysRemaining = now()->diffInDays($subscription->ends_at, false);
                    if ($daysRemaining < 0) $daysRemaining = 0;
                }
                
                // Status label and color
                $statusLabels = [
                    'trial' => 'On Trial',
                    'active' => 'Active',
                    'cancelled' => 'Cancelled',
                    'past_due' => 'Past Due',
                    'expired' => 'Expired'
                ];
                
                $statusColors = [
                    'trial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                    'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                    'past_due' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                    'expired' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
                ];
                
                return [
                    'id' => $subscription->id,
                    'company_id' => $company ? $company->id : null,
                    'company_name' => $company ? $company->name : 'N/A',
                    'company_email' => $company ? $company->email : 'N/A',
                    'plan_id' => $plan ? $plan->id : null,
                    'plan_name' => $plan ? $plan->name : 'No Plan',
                    'status' => $subscription->status,
                    'status_label' => $statusLabels[$subscription->status] ?? ucfirst($subscription->status),
                    'status_color' => $statusColors[$subscription->status] ?? 'bg-gray-100 text-gray-800',
                    'billing_cycle' => $subscription->billing_cycle,
                    'unit_count' => $unitCount,
                    'monthly_price' => $monthlyPrice,
                    'pricing_type' => $pricingType,
                    'price_per_unit' => $features['price_per_unit'] ?? null,
                    'starts_at' => $subscription->starts_at ? $subscription->starts_at->format('Y-m-d') : null,
                    'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
                    'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : null,
                    'days_remaining' => $daysRemaining,
                    'auto_renew' => (bool) $subscription->auto_renew,
                    'created_at' => $subscription->created_at ? $subscription->created_at->format('Y-m-d H:i') : null,
                ];
            });
            
            // Calculate stats
            $stats = [
                'total' => $subscriptions->count(),
                'active' => $subscriptions->where('status', 'active')->count(),
                'trial' => $subscriptions->where('status', 'trial')->count(),
                'cancelled' => $subscriptions->where('status', 'cancelled')->count(),
                'expired' => $subscriptions->where('status', 'expired')->count(),
                'past_due' => $subscriptions->where('status', 'past_due')->count(),
            ];
            
            Log::info('Company subscriptions formatted: ' . $formattedSubscriptions->count());
            
            return response()->json([
                'success' => true,
                'subscriptions' => $formattedSubscriptions,
                'stats' => $stats,
                'debug' => [
                    'total_in_db' => $subscriptions->count(),
                    'formatted_count' => $formattedSubscriptions->count(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getCompanySubscriptions: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'subscriptions' => [],
                'stats' => ['total' => 0, 'active' => 0, 'trial' => 0, 'cancelled' => 0, 'expired' => 0, 'past_due' => 0],
                'debug' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get a single company subscription for viewing/editing
     * Route: /admin/subscriptions/api/company-subscription/{id}
     */
    public function getCompanySubscription($id)
    {
        try {
            $subscription = CompanySubscription::with(['company', 'plan'])->findOrFail($id);
            
            $company = $subscription->company;
            $plan = $subscription->plan;
            $features = $plan ? ($plan->features_json ?? []) : [];
            $pricingType = $features['pricing_type'] ?? 'fixed';
            
            $unitCount = $company ? Unit::where('company_id', $company->id)
                ->whereIn('status', ['occupied', 'available'])
                ->count() : 0;
            
            if ($pricingType === 'per_unit') {
                $pricePerUnit = $features['price_per_unit'] ?? 0;
                $monthlyPrice = $pricePerUnit * $unitCount;
                $yearlyPrice = ($pricePerUnit * $unitCount * 12) * 0.9;
            } else {
                $monthlyPrice = (float) ($plan ? $plan->price_monthly : 0);
                $yearlyPrice = (float) ($plan ? $plan->price_yearly : 0);
            }
            
            return response()->json([
                'success' => true,
                'subscription' => [
                    'id' => $subscription->id,
                    'company' => $company ? [
                        'id' => $company->id,
                        'name' => $company->name,
                        'email' => $company->email,
                        'phone' => $company->phone,
                    ] : null,
                    'plan' => $plan ? [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'description' => $plan->description,
                        'features' => $features['features_list'] ?? [],
                    ] : null,
                    'status' => $subscription->status,
                    'billing_cycle' => $subscription->billing_cycle,
                    'unit_count' => $unitCount,
                    'monthly_price' => $monthlyPrice,
                    'yearly_price' => $yearlyPrice,
                    'pricing_type' => $pricingType,
                    'price_per_unit' => $features['price_per_unit'] ?? null,
                    'starts_at' => $subscription->starts_at ? $subscription->starts_at->format('Y-m-d') : null,
                    'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('Y-m-d') : null,
                    'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : null,
                    'auto_renew' => (bool) $subscription->auto_renew,
                    'created_at' => $subscription->created_at ? $subscription->created_at->format('Y-m-d H:i') : null,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getCompanySubscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found: ' . $e->getMessage()
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
            
            // Handle features JSON
            $features = $request->input('features_json', []);
            
            // If per-unit pricing, store the price_per_unit in features
            if ($request->input('price_per_unit') && $request->input('price_per_unit') > 0) {
                $features['pricing_type'] = 'per_unit';
                $features['price_per_unit'] = (float) $request->input('price_per_unit');
            } else {
                $features['pricing_type'] = 'fixed';
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
            
            // Handle features JSON
            $features = $request->input('features_json', []);
            
            // If per-unit pricing, store the price_per_unit in features
            if ($request->input('price_per_unit') && $request->input('price_per_unit') > 0) {
                $features['pricing_type'] = 'per_unit';
                $features['price_per_unit'] = (float) $request->input('price_per_unit');
            } else {
                $features['pricing_type'] = 'fixed';
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
            
            // Check if plan has active subscriptions
            if ($plan->subscriptions()->whereIn('status', ['active', 'trial'])->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete plan with active or trial subscriptions'
                ], 422);
            }
            
            $plan->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in deletePlan: ' . $e->getMessage());
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
            'payment_method_id' => 'nullable|exists:company_payment_methods,id'
        ]);

        try {
            // Simple subscription creation without service
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            
            $subscription = CompanySubscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => $plan->trial_days > 0 ? 'trial' : 'active',
                'billing_cycle' => $request->billing_cycle,
                'starts_at' => now(),
                'ends_at' => $request->billing_cycle === 'monthly' ? now()->addMonth() : now()->addYear(),
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'auto_renew' => true,
                'unit_count' => Unit::where('company_id', $company->id)->whereIn('status', ['occupied', 'available'])->count(),
            ]);

            return redirect()->route('admin.subscriptions.show', $company)
                ->with('success', 'Subscription created successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subscription: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, CompanySubscription $subscription)
    {
        try {
            $immediate = $request->input('immediate', false);
            
            if ($immediate) {
                $subscription->status = 'cancelled';
                $subscription->ends_at = now();
            } else {
                $subscription->status = 'cancelled';
                $subscription->auto_renew = false;
            }
            $subscription->save();
            
            return back()->with('success', 'Subscription cancelled successfully!');
            
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    public function resume(CompanySubscription $subscription)
    {
        try {
            if ($subscription->status === 'cancelled' && (!$subscription->ends_at || $subscription->ends_at->isFuture())) {
                $subscription->status = 'active';
                $subscription->auto_renew = true;
                $subscription->save();
            }
            
            return back()->with('success', 'Subscription resumed successfully!');
            
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resume subscription: ' . $e->getMessage());
        }
    }

    public function invoices(CompanySubscription $subscription)
    {
        $invoices = $subscription->invoices()->latest()->paginate(20);
        $summary = [
            'total' => $invoices->sum('amount'),
            'paid' => $invoices->where('status', 'paid')->sum('amount'),
            'pending' => $invoices->where('status', 'pending')->sum('amount'),
            'failed' => $invoices->where('status', 'failed')->sum('amount'),
        ];
        
        return view('subscriptions::invoices', compact('subscription', 'invoices', 'summary'));
    }

    public function downloadInvoice(SubscriptionInvoice $invoice)
    {
        // Simple placeholder - you can implement PDF generation later
        return response()->json([
            'message' => 'PDF download not yet implemented'
        ], 501);
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Get minimum units for a plan based on previous plan's max
     */
    private function getMinUnitsForPlan($plan)
    {
        $previousPlan = SubscriptionPlan::where('display_order', '<', $plan->display_order)
            ->orderBy('display_order', 'desc')
            ->first();
        
        if ($previousPlan) {
            $prevFeatures = $previousPlan->features_json ?? [];
            $prevMax = $prevFeatures['max_units'] ?? 0;
            return $prevMax === 0 ? 1 : $prevMax + 1;
        }
        
        return 1;
    }
}