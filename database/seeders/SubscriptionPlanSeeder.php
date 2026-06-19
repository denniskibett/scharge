<?php
// database/seeders/SubscriptionPlanSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // =============================================
        // RESIDENTIAL BANDS (Service Charge)
        // =============================================
        $residentialBands = [
            [
                'price' => 40,
                'name' => 'Economy Residential',
                'slug' => 'residential-40',
                'description' => 'Basic residential service charge band. Ideal for low-cost housing units.',
                'features' => [
                    'Water supply (basic)',
                    'Garbage collection (weekly)',
                    'Security patrol (basic)',
                ],
                'unit_types' => ['apartment', 'bedsitter', 'studio'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 60,
                'name' => 'Standard Residential',
                'slug' => 'residential-60',
                'description' => 'Standard residential service charge band. Suitable for mid-range apartments.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice weekly)',
                    'Security patrol (regular)',
                    'Common area cleaning',
                ],
                'unit_types' => ['apartment', 'townhouse', 'bedsitter'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 100,
                'name' => 'Premium Residential',
                'slug' => 'residential-100',
                'description' => 'Premium residential service charge band. For quality apartments with amenities.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                ],
                'unit_types' => ['apartment', 'penthouse', 'townhouse'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 150,
                'name' => 'Luxury Residential',
                'slug' => 'residential-150',
                'description' => 'Luxury residential service charge band. Premium apartments with full amenities.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                ],
                'unit_types' => ['penthouse', 'apartment', 'villa'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 200,
                'name' => 'Executive Residential',
                'slug' => 'residential-200',
                'description' => 'Executive residential service charge band. High-end apartments with premium services.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                    'Valet parking',
                    'Clubhouse access',
                ],
                'unit_types' => ['penthouse', 'villa', 'apartment'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 250,
                'name' => 'Prestige Residential',
                'slug' => 'residential-250',
                'description' => 'Prestige residential service charge band. Ultra-luxury units with exclusive services.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                    'Valet parking',
                    'Clubhouse access',
                    'Private elevator access',
                    'Butler service',
                ],
                'unit_types' => ['penthouse', 'villa', 'mansion'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 350,
                'name' => 'Elite Residential',
                'slug' => 'residential-350',
                'description' => 'Elite residential service charge band. Prime residential units with luxury amenities.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                    'Valet parking',
                    'Clubhouse access',
                    'Private elevator access',
                    'Butler service',
                    'Spa access',
                    'Private cinema room',
                ],
                'unit_types' => ['penthouse', 'villa', 'mansion'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 500,
                'name' => 'Ultra-Premium Residential',
                'slug' => 'residential-500',
                'description' => 'Ultra-premium residential service charge band. Top-tier luxury living.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                    'Valet parking',
                    'Clubhouse access',
                    'Private elevator access',
                    'Butler service',
                    'Spa access',
                    'Private cinema room',
                    'Private dining room',
                    'Guest suite access',
                ],
                'unit_types' => ['penthouse', 'villa', 'mansion'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 800,
                'name' => 'Luxury Estates',
                'slug' => 'residential-800',
                'description' => 'Luxury estates service charge band. For luxury apartments in prime locations.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                    'Valet parking',
                    'Clubhouse access',
                    'Private elevator access',
                    'Butler service',
                    'Spa access',
                    'Private cinema room',
                    'Private dining room',
                    'Guest suite access',
                    'Helicopter pad access',
                    'Wine cellar access',
                ],
                'unit_types' => ['penthouse', 'villa', 'mansion'],
                'property_types' => ['residential'],
            ],
            [
                'price' => 1000,
                'name' => 'Royal Estates',
                'slug' => 'residential-1000',
                'description' => 'Royal estates service charge band. Ultra-luxury living with exclusive amenities.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice daily)',
                    'Security patrol (24/7)',
                    'Common area cleaning',
                    'Swimming pool maintenance',
                    'Gym access',
                    'Landscaping & gardening',
                    'Concierge service',
                    'Valet parking',
                    'Clubhouse access',
                    'Private elevator access',
                    'Butler service',
                    'Spa access',
                    'Private cinema room',
                    'Private dining room',
                    'Guest suite access',
                    'Helicopter pad access',
                    'Wine cellar access',
                    'Private chef service',
                    'Personal assistant service',
                ],
                'unit_types' => ['penthouse', 'villa', 'mansion', 'estate'],
                'property_types' => ['residential'],
            ],
        ];

        // =============================================
        // COMMERCIAL BANDS
        // =============================================
        $commercialBands = [
            [
                'price' => 350,
                'name' => 'Warehouse Standard',
                'slug' => 'commercial-350',
                'description' => 'Standard warehouse service charge.',
                'features' => [
                    'Water supply (basic)',
                    'Garbage collection (weekly)',
                    'Security (24/7)',
                    'Yard cleaning',
                    'Loading bay access',
                    'Forklift charging station',
                ],
                'unit_types' => ['warehouse', 'storage'],
                'property_types' => ['commercial', 'industrial'],
            ],
            [
                'price' => 500,
                'name' => 'Industrial Warehouse',
                'slug' => 'commercial-500',
                'description' => 'Premium industrial warehouse service charge.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (twice weekly)',
                    'Security (24/7)',
                    'Yard cleaning',
                    'Loading bay access',
                    'Forklift charging station',
                    'Weighbridge access',
                    '3-phase power access',
                ],
                'unit_types' => ['warehouse', 'industrial', 'storage'],
                'property_types' => ['commercial', 'industrial'],
            ],
            [
                'price' => 800,
                'name' => 'Mixed-Use Commercial',
                'slug' => 'commercial-800',
                'description' => 'Mixed-use commercial service charge for complex developments.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (daily)',
                    'Security (24/7)',
                    'Common area cleaning',
                    'Parking (reserved)',
                    'Bathroom cleaning',
                    'Elevator maintenance',
                    'HVAC maintenance',
                    'Reception service',
                    'Conference room booking',
                    'Landscaping & gardening',
                    'Loading bay access',
                ],
                'unit_types' => ['complex', 'mixed-use', 'commercial'],
                'property_types' => ['commercial', 'mixed-use'],
            ],
            [
                'price' => 1000,
                'name' => 'Premium Complex',
                'slug' => 'commercial-1000',
                'description' => 'Premium complex service charge for high-end commercial developments.',
                'features' => [
                    'Water supply (metered)',
                    'Garbage collection (daily)',
                    'Security (24/7)',
                    'Common area cleaning',
                    'Parking (reserved)',
                    'Bathroom cleaning',
                    'Elevator maintenance',
                    'HVAC maintenance',
                    'Reception service',
                    'Conference room booking',
                    'Landscaping & gardening',
                    'Loading bay access',
                    'Weighbridge access',
                    'Event space access',
                    'Helipad access',
                ],
                'unit_types' => ['complex', 'mixed-use', 'commercial'],
                'property_types' => ['commercial', 'mixed-use'],
            ],
        ];

        // =============================================
        // CREATE PLANS
        // =============================================
        $allBands = array_merge($residentialBands, $commercialBands);

        foreach ($allBands as $band) {
            // Build the features JSON with all data
            $featuresData = [
                // The visible features (what shows in the table)
                'business_features' => $band['features'],
                
                // Pricing band metadata (stored but not displayed as features)
                'band_type' => $band['property_types'][0] ?? 'residential',
                'unit_types' => $band['unit_types'],
                'property_types' => $band['property_types'],
                
                // Product capabilities (limits)
                'product_capabilities' => [
                    'max_units' => 0, // Unlimited by default
                    'max_users' => 0,
                    'max_tenants' => 0,
                    'storage_gb' => 0,
                    'max_properties' => 0,
                ],
                
                // For service charge bands
                'band_price' => $band['price'],
                'band_name' => $band['name'],
            ];

            SubscriptionPlan::create([
                'name' => $band['name'],
                'slug' => $band['slug'],
                'description' => $band['description'],
                'price_per_unit' => $band['price'],
                'trial_days' => 14, // 14-day trial for all plans
                'discount_percentage' => 10, // 10% yearly discount
                'is_active' => true,
                'features' => json_encode($featuresData),
            ]);
        }

        $this->command->info('✅ Created ' . count($allBands) . ' subscription plans with pricing bands');
    }
}