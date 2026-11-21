<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * A resident signed in to the dashboard sees their own records only. The company scope already keeps other
 * companies out, but a tenant holds tenants.view / invoices.view / payments.view so they can see their bills,
 * and the lists showed every resident of the company. For a user whose only roles are tenant (and guest),
 * Tenant, Tenancy, Invoice and Payment queries are narrowed to that person (see each model's "tenant_self" scope).
 *
 * Staff, account managers, sysadmin, jobs, webhooks and CompanyAccess::bypass() work are not affected.
 */
class TenantSelf
{
    /** @var array<int, int|false> per user id: their tenant id, or false when not a tenant-only user */
    private static array $memo = [];

    /** The tenant record id to narrow to, or null for no narrowing. 0 means a tenant user with no tenant record. */
    public static function id(): ?int
    {
        if (CompanyAccess::bypassing()) {
            return null;
        }
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        if (! array_key_exists($user->id, self::$memo)) {
            $roles = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)->where('model_has_roles.model_type', $user->getMorphClass())
                ->pluck('roles.name');
            $tenantOnly = $roles->contains('tenant') && $roles->diff(['tenant', 'guest'])->isEmpty();
            self::$memo[$user->id] = $tenantOnly ? (int) DB::table('tenants')->where('user_id', $user->id)->value('id') : false;
        }

        return self::$memo[$user->id] === false ? null : self::$memo[$user->id];
    }

    /** Forget what was remembered (roles changed in this request, or between tests). */
    public static function flush(): void
    {
        self::$memo = [];
    }
}
