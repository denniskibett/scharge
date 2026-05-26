<?php
// app/Modules/Subscriptions/Controllers/PlanController.php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::active()->ordered()->get();
        return view('subscriptions::plans.index', compact('plans'));
    }

    public function create()
    {
        return view('subscriptions::plans.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:subscription_plans,slug',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'features' => 'required|array',
            'trial_days' => 'nullable|integer|min:0'
        ]);

        $plan = SubscriptionPlan::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'price_monthly' => $request->price_monthly,
            'price_yearly' => $request->price_yearly,
            'trial_days' => $request->trial_days ?? 0,
            'features_json' => $request->features,
            'display_order' => $request->display_order ?? 0,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return redirect()->route('subscriptions.plans.index')
            ->with('success', 'Plan created successfully!');
    }

    public function edit(SubscriptionPlan $plan)
    {
        return view('subscriptions::plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'features' => 'required|array'
        ]);

        $plan->update([
            'name' => $request->name,
            'description' => $request->description,
            'price_monthly' => $request->price_monthly,
            'price_yearly' => $request->price_yearly,
            'trial_days' => $request->trial_days ?? 0,
            'features_json' => $request->features,
            'display_order' => $request->display_order ?? 0,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return redirect()->route('subscriptions.plans.index')
            ->with('success', 'Plan updated successfully!');
    }

    public function destroy(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'Cannot delete plan with active subscriptions');
        }
        
        $plan->delete();
        
        return redirect()->route('subscriptions.plans.index')
            ->with('success', 'Plan deleted successfully!');
    }
}