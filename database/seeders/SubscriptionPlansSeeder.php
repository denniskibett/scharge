<?php
// database/seeders/SubscriptionPlansSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Support\Str;

class SubscriptionPlansSeeder extends Seeder
{
    public function run()
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small property owners just getting started',
                'price_monthly' => 0, // Calculated from per-unit
                'price_yearly' => 0, // Calculated from per-unit
                'price_per_unit' => 200, // KES 200 per unit per month
                'trial_days' => 14,
                'display_order' => 1,
                'features' => [
                    'max_properties' => 1,
                    'max_units' => 50,
                    'max_users' => 3,
                    'max_tenants' => 100,
                    'storage_gb' => 5,
                    'features_list' => [
                        'Up to 50 rental units',
                        'Basic reporting',
                        'Email support',
                        'Mobile app access',
                        'Tenant portal'
                    ]
                ]
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing property management companies',
                'price_monthly' => 0, // Calculated from per-unit
                'price_yearly' => 0, // Calculated from per-unit
                'price_per_unit' => 180, // KES 180 per unit per month (10% discount)
                'trial_days' => 14,
                'display_order' => 2,
                'features' => [
                    'max_properties' => 5,
                    'max_units' => 500,
                    'max_users' => 10,
                    'max_tenants' => 1000,
                    'storage_gb' => 20,
                    'features_list' => [
                        'Up to 500 rental units',
                        'Advanced reporting & analytics',
                        'Priority email & phone support',
                        'Mobile app access',
                        'Tenant portal',
                        'Maintenance management',
                        'Water billing integration'
                    ]
                ]
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large property portfolios with advanced needs',
                'price_monthly' => 0, // Calculated from per-unit
                'price_yearly' => 0, // Calculated from per-unit
                'price_per_unit' => 150, // KES 150 per unit per month (25% discount)
                'trial_days' => 30,
                'display_order' => 3,
                'features' => [
                    'max_properties' => 0, // Unlimited
                    'max_units' => 0, // Unlimited
                    'max_users' => 0, // Unlimited
                    'max_tenants' => 0, // Unlimited
                    'storage_gb' => 100,
                    'features_list' => [
                        'Unlimited rental units',
                        'Custom reporting',
                        '24/7 priority support',
                        'Mobile app access',
                        'Tenant portal',
                        'Maintenance management',
                        'Water billing integration',
                        'API access',
                        'SMS notifications',
                        'Dedicated account manager',
                        'Custom branding'
                    ]
                ]
            ]
        ];

        foreach ($plans as $plan) {
            $features = $plan['features'];
            $pricePerUnit = $plan['price_per_unit'];
            
            // For per-unit pricing, store the base rate in features_json
            $features['pricing_type'] = 'per_unit';
            $features['price_per_unit'] = $pricePerUnit;
            
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price_monthly' => 0, // Dynamic calculation
                    'price_yearly' => 0, // Dynamic calculation
                    'trial_days' => $plan['trial_days'],
                    'features_json' => $features,
                    'is_active' => true,
                    'display_order' => $plan['display_order'],
                ]
            );
        }
    }
}