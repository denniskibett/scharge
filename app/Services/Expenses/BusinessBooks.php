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
 *
 * Payees are unique (SHARED_PAYEES, company_id NULL): every business uses the same "Cleaning company"; only sysadmin
 * edits those. Which business and month an expense is for lives on the expense (Expense::label(): payee · client · month).
 */
class BusinessBooks
{
    /** Payees are unique and normalized: one row each, shared by every business, matched by slug (stable across
     *  local, live and other projects even if the display name changes): slug => [name, type].
     *  A business adds its own (a named cleaner, a supplier; slug NULL) and those must not repeat a name it can see. */
    public const SHARED_PAYEES = [
        // utilities & government
        'kplc' => ['Kenya Power (KPLC)', 'utility'],
        'nairobi-water' => ['Nairobi City Water and Sewerage Company', 'utility'],
        'nairobi-county' => ['Nairobi City County', 'utility'],
        'safaricom' => ['Safaricom (internet & airtime)', 'utility'],
        'internet-provider' => ['Internet provider', 'utility'],
        'kra' => ['Kenya Revenue Authority (KRA)', 'vendor'],
        'nssf' => ['NSSF', 'vendor'],
        'sha' => ['Social Health Authority (SHA)', 'vendor'],
        'nca' => ['National Construction Authority (NCA)', 'vendor'],
        // property services
        'private-water' => ['Private water supplier (bowser / borehole)', 'vendor'],
        'private-garbage' => ['Private garbage collector', 'vendor'],
        'security-company' => ['Security company', 'vendor'],
        'cleaning-company' => ['Cleaning company', 'vendor'],
        'cleaner' => ['Cleaner', 'staff'],
        'laundry' => ['Laundry service', 'vendor'],
        'building-management' => ['Building management (service charge)', 'vendor'],
        'landlord' => ['Landlord (rent)', 'vendor'],
        // platforms & sales
        'airbnb' => ['Airbnb', 'vendor'],
        'booking-com' => ['Booking.com', 'vendor'],
        'sales-agent' => ['Sales agent', 'vendor'],
        // development
        'main-contractor' => ['Main contractor', 'vendor'],
        'materials-supplier' => ['Building materials supplier', 'vendor'],
        'architect-engineers' => ['Architect / engineers', 'vendor'],
        'lawyer' => ['Lawyer', 'vendor'],
    ];

    /** Is this name taken among the payees this company can see (shared + its own)? Case-insensitive. */
    public static function payeeNameTaken(string $name, ?int $companyId, ?int $exceptId = null): bool
    {
        return DB::table('payees')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->where(fn ($q) => $q->whereNull('company_id')->when($companyId, fn ($w) => $w->orWhere('company_id', $companyId)))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /** kind => [payees name => type, categories name => description] */
    public const DEFAULTS = [
        'manager' => [
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
    public static function scopeExpenses($q, $user) // an Eloquent builder or a relation (eager-load constraint)
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

        return $ids === null || ($model->company_id !== null && in_array((int) $model->company_id, $ids, true));
    }

    /** Payees a user can pick: their business's own plus the shared ones. */
    public static function scopePayees(Builder $q, $user): Builder
    {
        $ids = self::companyIds($user);

        return $ids === null ? $q : $q->where(fn ($w) => $w->whereIn('payees.company_id', $ids ?: [0])->orWhereNull('payees.company_id'));
    }

    /** Can see (not necessarily edit) this payee: own business's or shared. */
    public static function seesPayee($user, $payee): bool
    {
        return $payee->company_id === null || self::owns($user, $payee);
    }

    /** "Danaff Kenya Co. Limited" → "Danaff Kenya". */
    public static function shortName(string $name): string
    {
        return trim(preg_replace('/\s*(co\.?|company)?\s*(limited|ltd\.?)$/i', '', trim($name))) ?: trim($name);
    }

    /** Make sure each shared payee exists once, keyed by slug (adopts an existing shared row of the same name). */
    public static function ensureSharedPayees(): void
    {
        if (! Schema::hasColumn('payees', 'slug')) {
            return;
        }
        $now = now();
        foreach (self::SHARED_PAYEES as $slug => [$name, $type]) {
            if (DB::table('payees')->where('slug', $slug)->exists()) {
                continue;
            }
            $id = DB::table('payees')->whereNull('company_id')->whereNull('slug')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->value('id');
            $id
                ? DB::table('payees')->where('id', $id)->update(['slug' => $slug, 'updated_at' => $now])
                : DB::table('payees')->insert(['slug' => $slug, 'company_id' => null, 'name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** The shared payee id for a slug (e.g. 'kplc'), for matching imports and other projects. */
    public static function payeeId(string $slug): ?int
    {
        return DB::table('payees')->where('slug', $slug)->value('id');
    }

    /** Make sure the shared payees exist and give a business its starting categories (by its kind) if it has none yet. */
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
        self::ensureSharedPayees();
        if (! DB::table('expense_categories')->where('company_id', $companyId)->exists()) {
            DB::table('expense_categories')->insert(collect($set['categories'])->map(fn ($desc, $name) => [
                'company_id' => $companyId, 'name' => $name, 'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
        }
    }
}
