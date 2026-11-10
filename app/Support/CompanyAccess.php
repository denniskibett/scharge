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
 *  - anyone else (e.g. a tenant without a company): null, as before; their screens filter by tenant
 */
class CompanyAccess
{
    /** @var array<int, array<int>|null> per user id, for this request */
    private static array $memo = [];

    /** @return array<int>|null */
    public static function ids(): ?array
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return array_key_exists($user->id, self::$memo) ? self::$memo[$user->id] : (self::$memo[$user->id] = self::resolve($user));
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

        return null;
    }

    /** Tenancies of the given companies: directly, through the unit, or the unit's property (older rows have no company_id). */
    public static function tenancyFilter($query, array $ids)
    {
        return $query->where(fn ($q) => $q->whereIn('tenancies.company_id', $ids)
            ->orWhere(fn ($q) => $q->whereNull('tenancies.company_id')->whereHas('unit', fn ($u) => $u->whereIn('units.company_id', $ids)
                ->orWhere(fn ($u) => $u->whereNull('units.company_id')->whereHas('property', fn ($p) => $p->whereIn('properties.company_id', $ids))))));
    }
}
