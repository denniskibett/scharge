<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The usual payees and expense categories of a Kenyan property business, so expenses can be recorded
 * straight away: utilities, county, statutory bodies, and the monthly cost categories (water, electricity,
 * garbage, marketing, commissions, salaries…). Names only: phone numbers and emails are left for each
 * business to fill in. Only adds what is missing (matched by name), so it is safe to run again.
 *
 *   php artisan db:seed --class=PayeesAndExpenseCategoriesSeeder --force
 */
class PayeesAndExpenseCategoriesSeeder extends Seeder
{
    /** name => type (staff | vendor | utility) */
    public const PAYEES = [
        'Kenya Power (KPLC)' => 'utility',
        'Nairobi City Water and Sewerage Company' => 'utility',
        'Nairobi City County (rates & garbage)' => 'utility',
        'Safaricom (internet & airtime)' => 'utility',
        'Zuku (internet)' => 'utility',
        'Kenya Revenue Authority (KRA)' => 'vendor',
        'NSSF' => 'vendor',
        'Social Health Authority (SHA)' => 'vendor',
    ];

    /** name => description */
    public const CATEGORIES = [
        'Water' => 'Water bills and bulk water',
        'Electricity' => 'Power for common areas, pumps, lifts and lighting',
        'Garbage collection' => 'Waste collection and disposal',
        'Security' => 'Guards, CCTV and alarm services',
        'Cleaning' => 'Cleaning services and supplies',
        'Repairs & maintenance' => 'Repairs, plumbing, electrical and general upkeep',
        'Salaries & wages' => 'Staff salaries, wages and casual labour',
        'Commissions' => 'Agent and account-manager commissions',
        'Marketing' => 'Listings, adverts, signage and promotions',
        'Internet & phone' => 'Internet, airtime and phone bills',
        'Insurance' => 'Building and liability insurance',
        'Taxes & statutory' => 'KRA, NSSF, SHA and county levies',
        'Bank & M-PESA charges' => 'Transaction and account charges',
        'Office & admin' => 'Stationery, printing and office costs',
    ];

    public function run(): void
    {
        $now = now();
        $payees = 0;
        foreach (self::PAYEES as $name => $type) {
            if (! DB::table('payees')->where('name', $name)->exists()) {
                DB::table('payees')->insert(['name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now]);
                $payees++;
            }
        }
        $categories = 0;
        foreach (self::CATEGORIES as $name => $description) {
            if (! DB::table('expense_categories')->where('name', $name)->exists()) {
                DB::table('expense_categories')->insert(['name' => $name, 'description' => $description, 'created_at' => $now, 'updated_at' => $now]);
                $categories++;
            }
        }
        $this->command?->info("Added {$payees} payees and {$categories} expense categories (existing ones kept).");
    }
}
