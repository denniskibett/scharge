<?php
// app/Modules/Subscriptions/Controllers/Admin/PlanController.php

namespace App\Modules\Subscriptions\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Models\CompanySubscription;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::withCount('subscriptions')
            ->orderBy('display_order')
            ->orderBy('price_monthly')
            ->get();
        
        $stats = [
            'total_companies' => \App\Models\Company::count(),
            'active_companies' => \App\Models\Company::where('is_active', true)->count(),
            'total_users' => \App\Models\User::count(),
            'verified_users' => \App\Models\User::whereNotNull('email_verified_at')->count(),
            'pending_users' => \App\Models\User::whereNull('email_verified_at')->count(),
            'total_units' => \App\Models\Unit::count(),
            'total_tenants' => \App\Models\Tenant::count(),
            'total_revenue' => \App\Models\Payment::sum('amount'),
            'monthly_recurring_revenue' => CompanySubscription::where('status', 'active')->with('plan')->get()->sum(function($sub) {
                return $sub->billing_cycle === 'monthly' ? $sub->plan->price_monthly : $sub->plan->price_yearly / 12;
            }),
        ];
        
        return view('dashboard.sys-admin', compact('plans', 'stats'));
    }
    
    public function getData(Request $request)
    {
        $plans = SubscriptionPlan::withCount('subscriptions')
            ->orderBy('display_order')
            ->orderBy('price_monthly')
            ->get();
        
        return response()->json([
            'plans' => $plans,
            'stats' => [
                'total' => $plans->count(),
                'active' => $plans->where('is_active', true)->count(),
                'inactive' => $plans->where('is_active', false)->count(),
            ]
        ]);
    }
    
    public function show($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        return response()->json($plan);
    }
    
    public function getSubscribers($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $subscribers = CompanySubscription::with('company')
            ->where('plan_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'plan' => $plan,
            'subscribers' => $subscribers
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:subscription_plans,slug',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'features_json' => 'nullable|array'
        ]);
        
        $plan = SubscriptionPlan::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Subscription plan created successfully',
            'plan' => $plan
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:subscription_plans,slug,' . $id,
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean',
            'features_json' => 'nullable|array'
        ]);
        
        $plan->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Subscription plan updated successfully',
            'plan' => $plan
        ]);
    }
    
    public function destroy($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        if ($plan->subscriptions()->whereIn('status', ['active', 'trial'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with active subscriptions'
            ], 422);
        }
        
        $plan->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Subscription plan deleted successfully'
        ]);
    }
}