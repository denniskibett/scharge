<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Models\Region;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all regions
        $regions = Region::with('county')->get();

        // Define plans for each region
        $plans = [];

        // =============================================
        // NAIROBI REGIONS - Premium Areas
        // =============================================
        $premiumNairobi = ['kileleshwa', 'kilimani', 'westlands', 'karen', 'lavington', 'runda', 'gigiri'];
        $standardNairobi = ['langata', 'embakasi', 'kasarani', 'roysambu', 'embakasi', 'syokimau'];

        foreach ($regions as $region) {
            // Skip if region doesn't have a county
            if (!$region->county) continue;

            // Determine pricing based on county and region
            $starterPrice = 0;
            $professionalPrice = 0;
            $enterprisePrice = 0;

            // Nairobi County
            if ($region->county->county_name === 'Nairobi') {
                if (in_array($region->slug, $premiumNairobi)) {
                    $starterPrice = 350;
                    $professionalPrice = 300;
                    $enterprisePrice = 250;
                } elseif (in_array($region->slug, $standardNairobi)) {
                    $starterPrice = 200;
                    $professionalPrice = 150;
                    $enterprisePrice = 120;
                } else {
                    // Default Nairobi pricing
                    $starterPrice = 250;
                    $professionalPrice = 200;
                    $enterprisePrice = 150;
                }
            }
            // Kajiado County
            elseif ($region->county->county_name === 'Kajiado') {
                $starterPrice = 100;
                $professionalPrice = 80;
                $enterprisePrice = 65;
            }
            // Kiambu County
            elseif ($region->county->county_name === 'Kiambu') {
                $starterPrice = 75;
                $professionalPrice = 60;
                $enterprisePrice = 50;
            }
            // Machakos County
            elseif ($region->county->county_name === 'Machakos') {
                $starterPrice = 80;
                $professionalPrice = 65;
                $enterprisePrice = 50;
            }
            // Mombasa County
            elseif ($region->county->county_name === 'Mombasa') {
                $starterPrice = 200;
                $professionalPrice = 160;
                $enterprisePrice = 130;
            }
            // Kisumu County
            elseif ($region->county->county_name === 'Kisumu') {
                $starterPrice = 120;
                $professionalPrice = 100;
                $enterprisePrice = 80;
            }
            // Nakuru County
            elseif ($region->county->county_name === 'Nakuru') {
                $starterPrice = 120;
                $professionalPrice = 100;
                $enterprisePrice = 80;
            }
            // Uasin Gishu County (Eldoret)
            elseif ($region->county->county_name === 'Uasin Gishu') {
                $starterPrice = 120;
                $professionalPrice = 100;
                $enterprisePrice = 80;
            }
            // Default pricing for other counties
            else {
                $starterPrice = 100;
                $professionalPrice = 80;
                $enterprisePrice = 65;
            }

            // Create Starter Plan
            $plans[] = [
                'name' => 'Starter',
                'slug' => $region->slug . '-starter',
                'description' => 'Perfect for small property owners in ' . $region->name . '. Up to 50 units.',
                'trial_days' => 14,
                'features_json' => json_encode([
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
                        'Tenant portal',
                        'Basic income & expense tracking'
                    ],
                    'pricing_type' => 'per_unit',
                    'price_per_unit' => $starterPrice
                ]),
                'is_active' => 1,
                'display_order' => 1,
                'region_id' => $region->id,
                'subcounty_id' => null,
                'price_per_unit' => $starterPrice,
                'discount_percentage' => 10.00,
                'features' => json_encode([
                    'Basic property management reporting',
                    'Email support during business hours',
                    'Mobile app access for tenants and staff',
                    'Tenant self-service portal',
                    'Up to 50 rental units management',
                    'Basic income & expense tracking'
                ])
            ];

            // Create Professional Plan
            $plans[] = [
                'name' => 'Professional',
                'slug' => $region->slug . '-professional',
                'description' => 'Ideal for growing property portfolios in ' . $region->name . '. Up to 200 units.',
                'trial_days' => 14,
                'features_json' => json_encode([
                    'max_properties' => 5,
                    'max_units' => 200,
                    'max_users' => 10,
                    'max_tenants' => 1000,
                    'storage_gb' => 20,
                    'features_list' => [
                        'Up to 200 rental units',
                        'Advanced reporting & analytics',
                        'Priority email & phone support',
                        'Mobile app access',
                        'Tenant portal',
                        'Maintenance management',
                        'Water billing integration',
                        'Advanced income & expense tracking',
                        'SMS notification for payments'
                    ],
                    'pricing_type' => 'per_unit',
                    'price_per_unit' => $professionalPrice
                ]),
                'is_active' => 1,
                'display_order' => 2,
                'region_id' => $region->id,
                'subcounty_id' => null,
                'price_per_unit' => $professionalPrice,
                'discount_percentage' => 12.00,
                'features' => json_encode([
                    'Advanced reporting & analytics dashboards',
                    'Priority email & phone support',
                    'Mobile app access for all users',
                    'Tenant self-service portal with payment',
                    'Maintenance request management',
                    'Water billing integration',
                    'Up to 200 rental units management',
                    'Advanced income & expense tracking',
                    'SMS notification for payments'
                ])
            ];

            // Create Enterprise Plan
            $plans[] = [
                'name' => 'Enterprise',
                'slug' => $region->slug . '-enterprise',
                'description' => 'For large property portfolios in ' . $region->name . '. Unlimited units.',
                'trial_days' => 30,
                'features_json' => json_encode([
                    'max_properties' => 0,
                    'max_units' => 0,
                    'max_users' => 0,
                    'max_tenants' => 0,
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
                        'Custom branding',
                        'Gate & access control integration'
                    ],
                    'pricing_type' => 'per_unit',
                    'price_per_unit' => $enterprisePrice
                ]),
                'is_active' => 1,
                'display_order' => 3,
                'region_id' => $region->id,
                'subcounty_id' => null,
                'price_per_unit' => $enterprisePrice,
                'discount_percentage' => 15.00,
                'features' => json_encode([
                    'Custom reporting & business intelligence',
                    '24/7 priority support with dedicated team',
                    'Mobile app access with all features',
                    'Tenant self-service portal with full automation',
                    'Maintenance management with AI routing',
                    'Water billing integration with automated invoices',
                    'API access for custom integrations',
                    'SMS notifications with two-way communication',
                    'Dedicated account manager',
                    'Custom branding & white-label',
                    'Unlimited rental units',
                    'Gate & access control integration'
                ])
            ];
        }

        // Insert or update plans
        $count = 0;
        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
            $count++;
        }

        $this->command->info('Subscription plans seeded successfully!');
        $this->command->info('Total plans: ' . $count);
    }
}