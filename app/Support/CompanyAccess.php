<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Which landlord companies the signed-in user may see. Used by the "company" global scope on invoices and tenancies,
 * so every list, search and {invoice}/{tenancy} URL only finds the user's own company's records.
 *
 *  - not signed in (scheduler, queue, M-Pesa/Sharet callbacks, public pages): null = no filter
 *  - sysadmin: null = all companies
 *  - a user with a company: that company
 *  - an account manager without a company: the companies they own
 *  - a tenant without a company: the companies of their tenant record and tenancies ([0] if none)
 *  - scharge sales staff: null = all companies
 *  - anyone else without a company (not yet activated, a guest, no role): [0] = no company's records
 */
class CompanyAccess
{
    /** @var array<string, array<int>|null> per user and company, for this request */
    private static array $memo = [];

    private static int $bypass = 0;

    /**
     * Run system work without the company filter, whoever is signed in: payment settlement, host units, the public
     * bill page. Relations loaded inside (invoice -> tenancy -> tenant) are unfiltered too.
     */
    public static function bypass(callable $callback): mixed
    {
        self::$bypass++;
        try {
            return $callback();
        } finally {
            self::$bypass--;
        }
    }

    /** True while system work runs inside bypass(). */
    public static function bypassing(): bool
    {
        return self::$bypass > 0;
    }

    /** @return array<int>|null */
    public static function ids(): ?array
    {
        $user = self::$bypass > 0 ? null : auth()->user();
        if (! $user) {
            return null;
        }

        // Keyed by the company too: sign-up gives the user a company part-way through a request.
        $key = $user->id.':'.(int) $user->company_id;

        return array_key_exists($key, self::$memo) ? self::$memo[$key] : (self::$memo[$key] = self::resolve($user));
    }

    public static function flush(): void
    {
        self::$memo = [];
    }

    /** @return array<int>|null */
    private static function resolve($user): ?array
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('sysadmin')) {
            return null;
        }
        if ($user->company_id) {
            return [(int) $user->company_id];
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('account_manager')) {
            return DB::table('companies')->where('owner_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all() ?: [0];
        }
        // A resident's user row has no company: they belong to their landlords' companies (their tenant record and
        // tenancies). Without this every company-scoped list (units, repairs...) showed all companies to a tenant.
        if (method_exists($user, 'hasRole') && $user->hasRole('tenant')) {
            $tenantIds = DB::table('tenants')->where('user_id', $user->id)->pluck('id');
            $ids = DB::table('tenants')->whereIn('id', $tenantIds)->whereNotNull('company_id')->pluck('company_id')
                ->merge(DB::table('tenancies')->whereIn('tenant_id', $tenantIds)->whereNotNull('company_id')->pluck('company_id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all();

            return $ids ?: [0];
        }
        // scharge sales staff work every prospect and customer in the CRM.
        if (method_exists($user, 'hasRole') && $user->hasRole('sales')) {
            return null;
        }

        // Anyone else without a company (just signed up and not activated, a guest, a login with no role) belongs to
        // no landlord: they find no company's records. This used to be "no filter", which showed them everyone's.
        return [0];
    }

    /** Tenancies of the given companies: directly, through the unit, or the unit's property (older rows have no company_id). */
    public static function tenancyFilter($query, array $ids)
    {
        return $query->where(fn ($q) => $q->whereIn('tenancies.company_id', $ids)
            ->orWhere(fn ($q) => $q->whereNull('tenancies.company_id')->whereHas('unit', fn ($u) => $u->whereIn('units.company_id', $ids)
                ->orWhere(fn ($u) => $u->whereNull('units.company_id')->whereHas('property', fn ($p) => $p->whereIn('properties.company_id', $ids))))));
    }
}
