<?php
// database/seeders/RegionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Subscriptions\Models\Region;
use App\Models\County;

class RegionSeeder extends Seeder
{
    public function run()
    {
        // Get county IDs
        $nairobi = County::where('county_name', 'Nairobi')->first();
        $kajiado = County::where('county_name', 'Kajiado')->first();
        $kiambu = County::where('county_name', 'Kiambu')->first();
        $machakos = County::where('county_name', 'Machakos')->first();
        $mombasa = County::where('county_name', 'Mombasa')->first();
        $kisumu = County::where('county_name', 'Kisumu')->first();
        $nakuru = County::where('county_name', 'Nakuru')->first();
        $uasinGishu = County::where('county_name', 'Uasin Gishu')->first();

        $regions = [
            // =============================================
            // NAIROBI COUNTY - Complete Coverage
            // =============================================
            // Central Nairobi
            [
                'name' => 'Nairobi CBD',
                'slug' => 'nairobi-cbd',
                'description' => 'Central Business District of Nairobi - Commercial hub with offices, banks, and government buildings',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8219,
                'latitude' => -1.2921,
                'population' => 120000,
                'display_order' => 1,
            ],
            [
                'name' => 'Kilimani',
                'slug' => 'kilimani',
                'description' => 'Upscale residential and commercial area with mixed-use properties, apartments, and offices',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7900,
                'latitude' => -1.2900,
                'population' => 45000,
                'display_order' => 2,
            ],
            [
                'name' => 'Kileleshwa',
                'slug' => 'kileleshwa',
                'description' => 'Upper-middle class residential area with high-end properties, gated communities, and embassies',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7840,
                'latitude' => -1.2937,
                'population' => 35000,
                'display_order' => 3,
            ],
            [
                'name' => 'Lavington',
                'slug' => 'lavington',
                'description' => 'Upmarket residential area with large homes, embassies, and diplomatic residences',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7700,
                'latitude' => -1.2800,
                'population' => 20000,
                'display_order' => 4,
            ],
            [
                'name' => 'Westlands',
                'slug' => 'westlands',
                'description' => 'Commercial and residential hub with high-rise buildings, offices, malls, and apartments',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8060,
                'latitude' => -1.2670,
                'population' => 60000,
                'display_order' => 5,
            ],
            [
                'name' => 'Parklands',
                'slug' => 'parklands',
                'description' => 'Mixed residential and commercial area with apartments, businesses, and hospitals',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8100,
                'latitude' => -1.2700,
                'population' => 50000,
                'display_order' => 6,
            ],
            
            // Western Nairobi
            [
                'name' => 'Karen',
                'slug' => 'karen',
                'description' => 'Affluent residential area with large estates, luxury homes, and international schools',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7300,
                'latitude' => -1.3300,
                'population' => 25000,
                'display_order' => 7,
            ],
            [
                'name' => 'Langata',
                'slug' => 'langata',
                'description' => 'Residential area with diverse property types including apartments, standalone homes, and gated communities',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7300,
                'latitude' => -1.3700,
                'population' => 40000,
                'display_order' => 8,
            ],
            [
                'name' => 'Runda',
                'slug' => 'runda',
                'description' => 'Exclusive gated community with luxury homes, diplomatic residences, and embassy housing',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8200,
                'latitude' => -1.2300,
                'population' => 10000,
                'display_order' => 9,
            ],
            [
                'name' => 'Gigiri',
                'slug' => 'gigiri',
                'description' => 'Diplomatic enclave with embassies, upscale homes, and international organizations (UN)',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8000,
                'latitude' => -1.2400,
                'population' => 15000,
                'display_order' => 10,
            ],
            [
                'name' => 'Spring Valley',
                'slug' => 'spring-valley',
                'description' => 'Upper-middle class residential area with standalone homes and apartments',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7800,
                'latitude' => -1.2700,
                'population' => 18000,
                'display_order' => 11,
            ],

            // Eastern Nairobi
            [
                'name' => 'Embakasi',
                'slug' => 'embakasi',
                'description' => 'Growing residential area with affordable housing, developing infrastructure, and industrial zones',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8800,
                'latitude' => -1.3200,
                'population' => 100000,
                'display_order' => 12,
            ],
            [
                'name' => 'Kasarani',
                'slug' => 'kasarani',
                'description' => 'Residential area with mix of apartments, standalone homes, and developing commercial centers',
                'county_id' => $nairobi?->id,
                'longitude' => 36.9000,
                'latitude' => -1.2300,
                'population' => 80000,
                'display_order' => 13,
            ],
            [
                'name' => 'Roysambu',
                'slug' => 'roysambu',
                'description' => 'Residential area with affordable housing, developing infrastructure, and growing population',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8800,
                'latitude' => -1.2600,
                'population' => 70000,
                'display_order' => 14,
            ],
            [
                'name' => 'Ruai',
                'slug' => 'ruai',
                'description' => 'Emerging residential area with affordable housing and developing infrastructure',
                'county_id' => $nairobi?->id,
                'longitude' => 36.9200,
                'latitude' => -1.2500,
                'population' => 60000,
                'display_order' => 15,
            ],

            // Southern Nairobi
            [
                'name' => 'South B',
                'slug' => 'south-b',
                'description' => 'Residential area with mix of apartments, standalone homes, and commercial establishments',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8200,
                'latitude' => -1.3100,
                'population' => 45000,
                'display_order' => 16,
            ],
            [
                'name' => 'South C',
                'slug' => 'south-c',
                'description' => 'Upper-middle class residential area with well-planned housing and commercial centers',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8200,
                'latitude' => -1.3200,
                'population' => 35000,
                'display_order' => 17,
            ],
            [
                'name' => 'Syokimau',
                'slug' => 'syokimau',
                'description' => 'Growing residential area near JKIA with mix of apartments, standalone homes, and estates',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8600,
                'latitude' => -1.3600,
                'population' => 50000,
                'display_order' => 18,
            ],
            [
                'name' => 'Athi River',
                'slug' => 'athi-river',
                'description' => 'Industrial and residential area with affordable housing, industrial zones, and developing infrastructure',
                'county_id' => $nairobi?->id,
                'longitude' => 36.9300,
                'latitude' => -1.3900,
                'population' => 70000,
                'display_order' => 19,
            ],

            // Northern Nairobi
            [
                'name' => 'Mathare',
                'slug' => 'mathare',
                'description' => 'Densely populated residential area with mix of housing types and developing commercial centers',
                'county_id' => $nairobi?->id,
                'longitude' => 36.8500,
                'latitude' => -1.2500,
                'population' => 90000,
                'display_order' => 20,
            ],
            [
                'name' => 'Kawangware',
                'slug' => 'kawangware',
                'description' => 'Mixed residential and commercial area with affordable housing and developing infrastructure',
                'county_id' => $nairobi?->id,
                'longitude' => 36.7600,
                'latitude' => -1.2800,
                'population' => 80000,
                'display_order' => 21,
            ],

            // =============================================
            // KAJIADO COUNTY - Complete Coverage
            // =============================================
            [
                'name' => 'Rongai',
                'slug' => 'rongai',
                'description' => 'Growing suburban area with affordable housing, rapid development, and mixed-use properties',
                'county_id' => $kajiado?->id,
                'longitude' => 36.7111,
                'latitude' => -1.3963,
                'population' => 80000,
                'display_order' => 50,
            ],
            [
                'name' => 'Ngong',
                'slug' => 'ngong',
                'description' => 'Emerging residential area with mix of affordable and mid-range housing, and scenic views',
                'county_id' => $kajiado?->id,
                'longitude' => 36.6650,
                'latitude' => -1.3650,
                'population' => 45000,
                'display_order' => 51,
            ],
            [
                'name' => 'Kiserian',
                'slug' => 'kiserian',
                'description' => 'Residential area with growing real estate development, affordable housing, and agricultural land',
                'county_id' => $kajiado?->id,
                'longitude' => 36.6800,
                'latitude' => -1.4200,
                'population' => 35000,
                'display_order' => 52,
            ],
            [
                'name' => 'Kitengela',
                'slug' => 'kitengela',
                'description' => 'Fast-growing residential and industrial area with affordable housing, factories, and estates',
                'county_id' => $kajiado?->id,
                'longitude' => 36.9500,
                'latitude' => -1.4300,
                'population' => 100000,
                'display_order' => 53,
            ],
            [
                'name' => 'Ongata Rongai',
                'slug' => 'ongata-rongai',
                'description' => 'Major residential and commercial area with mix of housing types, businesses, and schools',
                'county_id' => $kajiado?->id,
                'longitude' => 36.7300,
                'latitude' => -1.3800,
                'population' => 60000,
                'display_order' => 54,
            ],

            // =============================================
            // KIAMBU COUNTY - Complete Coverage
            // =============================================
            [
                'name' => 'Kamakis',
                'slug' => 'kamakis',
                'description' => 'Emerging residential area with affordable housing, developing infrastructure, and growing population',
                'county_id' => $kiambu?->id,
                'longitude' => 36.9041,
                'latitude' => -1.2078,
                'population' => 25000,
                'display_order' => 100,
            ],
            [
                'name' => 'Ruaka',
                'slug' => 'ruaka',
                'description' => 'Growing residential area near Nairobi with mix of apartments, standalone homes, and commercial centers',
                'county_id' => $kiambu?->id,
                'longitude' => 36.8000,
                'latitude' => -1.2300,
                'population' => 35000,
                'display_order' => 101,
            ],
            [
                'name' => 'Thika',
                'slug' => 'thika',
                'description' => 'Industrial and residential town with diverse property types, factories, and developing infrastructure',
                'county_id' => $kiambu?->id,
                'longitude' => 37.0800,
                'latitude' => -1.0400,
                'population' => 180000,
                'display_order' => 102,
            ],
            [
                'name' => 'Kiambu Town',
                'slug' => 'kiambu-town',
                'description' => 'County headquarters with mix of residential, commercial properties, and government offices',
                'county_id' => $kiambu?->id,
                'longitude' => 36.8300,
                'latitude' => -1.1800,
                'population' => 100000,
                'display_order' => 103,
            ],
            [
                'name' => 'Juja',
                'slug' => 'juja',
                'description' => 'Residential area with affordable housing, student population (JKUAT), and developing commercial centers',
                'county_id' => $kiambu?->id,
                'longitude' => 37.0300,
                'latitude' => -1.1000,
                'population' => 75000,
                'display_order' => 104,
            ],
            [
                'name' => 'Githunguri',
                'slug' => 'githunguri',
                'description' => 'Growing residential and agricultural area with mix of housing types and farming',
                'county_id' => $kiambu?->id,
                'longitude' => 36.7700,
                'latitude' => -1.0700,
                'population' => 40000,
                'display_order' => 105,
            ],
            [
                'name' => 'Kikuyu',
                'slug' => 'kikuyu',
                'description' => 'Township with mix of residential, commercial properties, and educational institutions',
                'county_id' => $kiambu?->id,
                'longitude' => 36.6700,
                'latitude' => -1.2500,
                'population' => 50000,
                'display_order' => 106,
            ],

            // =============================================
            // MACHAKOS COUNTY - Complete Coverage
            // =============================================
            [
                'name' => 'Machakos Town',
                'slug' => 'machakos-town',
                'description' => 'County headquarters with mix of residential, commercial properties, and government offices',
                'county_id' => $machakos?->id,
                'longitude' => 37.2600,
                'latitude' => -1.5200,
                'population' => 60000,
                'display_order' => 150,
            ],
            [
                'name' => 'Athi River Town',
                'slug' => 'athi-river-town',
                'description' => 'Industrial and residential area with affordable housing, factories, and developing infrastructure',
                'county_id' => $machakos?->id,
                'longitude' => 36.9500,
                'latitude' => -1.3900,
                'population' => 70000,
                'display_order' => 151,
            ],
            [
                'name' => 'Tala',
                'slug' => 'tala',
                'description' => 'Growing residential area with affordable housing and developing commercial centers',
                'county_id' => $machakos?->id,
                'longitude' => 37.3700,
                'latitude' => -1.4700,
                'population' => 30000,
                'display_order' => 152,
            ],
            [
                'name' => 'Kangundo',
                'slug' => 'kangundo',
                'description' => 'Township with mix of residential, commercial properties, and agricultural land',
                'county_id' => $machakos?->id,
                'longitude' => 37.3500,
                'latitude' => -1.3000,
                'population' => 40000,
                'display_order' => 153,
            ],

            // =============================================
            // MOMBASA COUNTY - Complete Coverage
            // =============================================
            [
                'name' => 'Mombasa CBD',
                'slug' => 'mombasa-cbd',
                'description' => 'Central business district of Mombasa with commercial properties, offices, and historical buildings',
                'county_id' => $mombasa?->id,
                'longitude' => 39.6700,
                'latitude' => -4.0400,
                'population' => 150000,
                'display_order' => 200,
            ],
            [
                'name' => 'Nyali',
                'slug' => 'nyali',
                'description' => 'Upscale coastal residential area with luxury homes, beachfront properties, and hotels',
                'county_id' => $mombasa?->id,
                'longitude' => 39.7000,
                'latitude' => -4.0500,
                'population' => 60000,
                'display_order' => 201,
            ],
            [
                'name' => 'Bamburi',
                'slug' => 'bamburi',
                'description' => 'Coastal residential area with beachfront properties, hotels, apartments, and commercial establishments',
                'county_id' => $mombasa?->id,
                'longitude' => 39.7200,
                'latitude' => -4.0300,
                'population' => 50000,
                'display_order' => 202,
            ],
            [
                'name' => 'Likoni',
                'slug' => 'likoni',
                'description' => 'Residential and industrial area with mix of housing types, ferry connection, and developing infrastructure',
                'county_id' => $mombasa?->id,
                'longitude' => 39.6600,
                'latitude' => -4.0900,
                'population' => 80000,
                'display_order' => 203,
            ],
            [
                'name' => 'Changamwe',
                'slug' => 'changamwe',
                'description' => 'Industrial and residential area with factories, warehouses, and developing housing estates',
                'county_id' => $mombasa?->id,
                'longitude' => 39.6400,
                'latitude' => -4.0600,
                'population' => 70000,
                'display_order' => 204,
            ],

            // =============================================
            // KISUMU COUNTY - Complete Coverage
            // =============================================
            [
                'name' => 'Kisumu CBD',
                'slug' => 'kisumu-cbd',
                'description' => 'Central business district of Kisumu with commercial properties, offices, and government buildings',
                'county_id' => $kisumu?->id,
                'longitude' => 34.7600,
                'latitude' => -0.1000,
                'population' => 100000,
                'display_order' => 250,
            ],
            [
                'name' => 'Milimani Kisumu',
                'slug' => 'milimani-kisumu',
                'description' => 'Residential area with diverse property types including apartments, standalone homes, and commercial centers',
                'county_id' => $kisumu?->id,
                'longitude' => 34.7500,
                'latitude' => -0.1000,
                'population' => 40000,
                'display_order' => 251,
            ],
            [
                'name' => 'Kisumu East',
                'slug' => 'kisumu-east',
                'description' => 'Growing residential area with mix of housing developments, commercial establishments, and lakeside properties',
                'county_id' => $kisumu?->id,
                'longitude' => 34.7700,
                'latitude' => -0.0800,
                'population' => 60000,
                'display_order' => 252,
            ],
            [
                'name' => 'Kisumu West',
                'slug' => 'kisumu-west',
                'description' => 'Residential area with mix of housing types, developing infrastructure, and agricultural land',
                'county_id' => $kisumu?->id,
                'longitude' => 34.7400,
                'latitude' => -0.1200,
                'population' => 50000,
                'display_order' => 253,
            ],

            // =============================================
            // NAKURU COUNTY - Complete Coverage
            // =============================================
            [
                'name' => 'Nakuru CBD',
                'slug' => 'nakuru-cbd',
                'description' => 'Central business district of Nakuru with commercial properties, offices, and government buildings',
                'county_id' => $nakuru?->id,
                'longitude' => 36.0700,
                'latitude' => -0.2800,
                'population' => 90000,
                'display_order' => 300,
            ],
            [
                'name' => 'Milimani Nakuru',
                'slug' => 'milimani-nakuru',
                'description' => 'Residential area with mix of apartments, standalone homes, and commercial establishments',
                'county_id' => $nakuru?->id,
                'longitude' => 36.0800,
                'latitude' => -0.2900,
                'population' => 35000,
                'display_order' => 301,
            ],
            [
                'name' => 'Lanet',
                'slug' => 'lanet',
                'description' => 'Residential area with mix of housing types, military barracks, and developing infrastructure',
                'county_id' => $nakuru?->id,
                'longitude' => 36.1200,
                'latitude' => -0.3300,
                'population' => 30000,
                'display_order' => 302,
            ],
            [
                'name' => 'Nakuru East',
                'slug' => 'nakuru-east',
                'description' => 'Growing residential and commercial area with mix of housing developments and businesses',
                'county_id' => $nakuru?->id,
                'longitude' => 36.0800,
                'latitude' => -0.2800,
                'population' => 70000,
                'display_order' => 303,
            ],

            // =============================================
            // UASIN GISHU COUNTY (ELDORET) - Complete Coverage
            // =============================================
            [
                'name' => 'Eldoret CBD',
                'slug' => 'eldoret-cbd',
                'description' => 'Central business district of Eldoret with commercial properties, offices, and government buildings',
                'county_id' => $uasinGishu?->id,
                'longitude' => 35.2800,
                'latitude' => 0.5200,
                'population' => 80000,
                'display_order' => 350,
            ],
            [
                'name' => 'Kapsoya',
                'slug' => 'kapsoya',
                'description' => 'Residential area with mix of housing types, developing infrastructure, and commercial centers',
                'county_id' => $uasinGishu?->id,
                'longitude' => 35.2900,
                'latitude' => 0.5300,
                'population' => 30000,
                'display_order' => 351,
            ],
            [
                'name' => 'Langas',
                'slug' => 'langas',
                'description' => 'Residential area with affordable housing, developing infrastructure, and growing population',
                'county_id' => $uasinGishu?->id,
                'longitude' => 35.2600,
                'latitude' => 0.5100,
                'population' => 40000,
                'display_order' => 352,
            ],
            [
                'name' => 'Eldoret East',
                'slug' => 'eldoret-east',
                'description' => 'Growing residential and commercial area with mix of housing developments and businesses',
                'county_id' => $uasinGishu?->id,
                'longitude' => 35.3000,
                'latitude' => 0.5200,
                'population' => 50000,
                'display_order' => 353,
            ],
        ];

        foreach ($regions as $region) {
            Region::updateOrCreate(
                ['slug' => $region['slug']],
                $region
            );
        }

        $this->command->info('Regions seeded successfully!');
        $this->command->info('Total regions: ' . count($regions));
    }
}