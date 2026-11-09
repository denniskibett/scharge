<?php

namespace App\Services\Expenses;

use App\Models\Company;
use App\Services\Reports\PortfolioReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses, payees and expense categories belong to a business (company). Each business sees and manages
 * only its own; sysadmin sees all, an account manager the companies they look after.
 * A business starts with the payees and categories that fit what it is (companies.kind):
 *   manager    a building / property manager: utilities, county, security, cleaning, salaries, commissions…
 *   bnb_host   a short-stay host: cleaning & laundry, guest supplies, Airbnb/Booking commissions, tokens, rent…
 *   developer  a developer: contractors, materials, professional fees, approvals, sales commissions…
 * (database/schema/2026_11_10_business_payees.sql). Names only: each business adds its own phone numbers.
 */
class BusinessBooks
{
    /** kind => [payees name => type, categories name => description] */
    public const DEFAULTS = [
        'manager' => [
            'payees' => [
                'Kenya Power (KPLC)' => 'utility',
                'Nairobi City Water and Sewerage Company' => 'utility',
                'Nairobi City County (rates & garbage)' => 'utility',
                'Private water supplier (bowser / borehole)' => 'vendor',
                'Private garbage collector' => 'vendor',
                'Security company' => 'vendor',
                'Cleaning company' => 'vendor',
                'Safaricom (internet & airtime)' => 'utility',
                'Kenya Revenue Authority (KRA)' => 'vendor',
                'NSSF' => 'vendor',
                'Social Health Authority (SHA)' => 'vendor',
            ],
            'categories' => [
                'Water' => 'Water bills, private bowsers and borehole water',
                'Electricity' => 'Power for common areas, pumps, lifts and lighting',
                'Garbage collection' => 'County or private waste collection and disposal',
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
            ],
        ],
        'bnb_host' => [
            'payees' => [
                'Kenya Power (KPLC tokens)' => 'utility',
                'Building management (service charge)' => 'vendor',
                'Landlord (rent)' => 'vendor',
                'Cleaner' => 'staff',
                'Laundry service' => 'vendor',
                'Airbnb' => 'vendor',
                'Booking.com' => 'vendor',
                'Internet provider' => 'utility',
                'Kenya Revenue Authority (KRA)' => 'vendor',
            ],
            'categories' => [
                'Cleaning & laundry' => 'Turnover cleaning, linen and laundry',
                'Guest supplies' => 'Toiletries, coffee, tea and welcome items',
                'Platform commissions' => 'Airbnb, Booking.com and agent fees',
                'Electricity' => 'KPLC tokens and power bills',
                'Water' => 'Water bills and refills',
                'Internet & TV' => 'Wi-Fi, TV and streaming',
                'Rent' => 'Rent paid to the unit owner',
                'Service charge' => 'Service charge paid to the building',
                'Repairs & maintenance' => 'Repairs and small fixes',
                'Furnishing & replacements' => 'Furniture, appliances, linen and kitchenware',
                'Marketing & photos' => 'Listing photos, ads and promotions',
                'Taxes & statutory' => 'KRA tourism levy and income tax',
                'Bank & M-PESA charges' => 'Transaction and account charges',
            ],
        ],
        'developer' => [
            'payees' => [
                'Main contractor' => 'vendor',
                'Building materials supplier' => 'vendor',
                'Architect / engineers' => 'vendor',
                'Lawyer' => 'vendor',
                'Nairobi City County (approvals & rates)' => 'utility',
                'National Construction Authority (NCA)' => 'vendor',
                'Kenya Power (KPLC)' => 'utility',
                'Kenya Revenue Authority (KRA)' => 'vendor',
                'Sales agent' => 'vendor',
            ],
            'categories' => [
                'Construction' => 'Contractor payments and labour',
                'Materials' => 'Building materials and supplies',
                'Professional fees' => 'Architects, engineers, quantity surveyors and lawyers',
                'Approvals & permits' => 'County, NEMA and NCA approvals and levies',
                'Utilities' => 'Water and power connections and bills',
                'Sales commissions' => 'Agent commissions on unit sales',
                'Marketing' => 'Show house, adverts and launches',
                'Salaries & wages' => 'Staff salaries and site wages',
                'Insurance' => 'Contractor\'s all-risk and building insurance',
                'Taxes & statutory' => 'KRA, NSSF, SHA and county levies',
                'Bank & finance charges' => 'Loan interest and transaction charges',
            ],
        ],
    ];

    /** Company ids this user works in; null = all (sysadmin). */
    public static function companyIds($user): ?array
    {
        return PortfolioReport::companyIdsFor($user);
    }

    /** The company new payees / categories / expenses go to. */
    public static function companyId($user): ?int
    {
        return $user->company_id ? (int) $user->company_id : null;
    }

    public static function scope(Builder $q, $user, string $column = 'company_id'): Builder
    {
        $ids = self::companyIds($user);

        return $ids === null ? $q : $q->whereIn($q->getModel()->getTable().'.'.$column, $ids ?: [0]);
    }

    /** Expenses of the user's business(es): by the expense's company, else its property's. */
    public static function scopeExpenses(Builder $q, $user): Builder
    {
        $ids = self::companyIds($user);
        if ($ids === null) {
            return $q;
        }

        return $q->where(fn ($w) => $w->whereIn('expenses.company_id', $ids ?: [0])
            ->orWhere(fn ($x) => $x->whereNull('expenses.company_id')->whereIn('expenses.property_id', DB::table('properties')->whereIn('company_id', $ids ?: [0])->select('id'))));
    }

    public static function owns($user, $model): bool
    {
        $ids = self::companyIds($user);

        return $ids === null || in_array((int) $model->company_id, $ids, true);
    }

    /** Give a business its starting payees and categories (by its kind) if it has none yet. */
    public static function ensureDefaults(?int $companyId): void
    {
        if (! $companyId || ! Schema::hasColumn('payees', 'company_id')) {
            return;
        }
        $company = Company::find($companyId);
        if (! $company) {
            return;
        }
        $set = self::DEFAULTS[$company->kind ?? 'manager'] ?? self::DEFAULTS['manager'];
        $now = now();
        if (! DB::table('payees')->where('company_id', $companyId)->exists()) {
            DB::table('payees')->insert(collect($set['payees'])->map(fn ($type, $name) => [
                'company_id' => $companyId, 'name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
        }
        if (! DB::table('expense_categories')->where('company_id', $companyId)->exists()) {
            DB::table('expense_categories')->insert(collect($set['categories'])->map(fn ($desc, $name) => [
                'company_id' => $companyId, 'name' => $name, 'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
        }
    }
}
