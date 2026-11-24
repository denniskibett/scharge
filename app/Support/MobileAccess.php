<?php

namespace App\Support;

use Spatie\Permission\PermissionRegistrar;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Gate\Gate;
use App\Services\Rent\RentRun;

/**
 * What the scharge app shows each person (docs/MOBILE_APP.md). The app never decides access: it draws the screens and
 * buttons listed here, and every endpoint checks the same rules again (MobileCan, CompanyAccess, Gate).
 * Permissions come from App\Support\Access through Spatie, so the Roles page's fine-tuning applies to the app too.
 */
class MobileAccess
{
    public const SHORT_STAY = ['short_stay', 'bnb', 'mixed'];

    /** Roles that act on their own records only. Everyone else with a company (or scharge staff) is staff. */
    private const NOT_STAFF = ['tenant', 'guest'];

    /** Does the user hold this permission? sysadmin: always. Before `access:sync` has run: the role defaults in Access. */
    public static function can(User $user, string $permission): bool
    {
        if ($user->hasRole('sysadmin')) {
            return true;
        }
        if (self::catalogueInstalled()) {
            return $user->can($permission);
        }

        return $user->getRoleNames()->contains(fn ($role) => in_array($permission, Access::grantsFor($role), true));
    }

    /** Has `php artisan access:sync` written the catalogue? (Spatie caches the list; not memoised here.) */
    private static function catalogueInstalled(): bool
    {
        return app(PermissionRegistrar::class)->getPermissions()->contains('name', 'properties.view');
    }

    /** Company staff (admin, manager, accountant, readers, maintenance, security, BnB hosts) or scharge staff. */
    public static function isStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasAnyRole(['sysadmin', 'account_manager'])) {
            return true;
        }

        // A company user is limited to that company by CompanyAccess; without a company there is nothing to scope to.
        return $user->company_id && CompanyAccess::ids() !== null && $user->getRoleNames()->diff(self::NOT_STAFF)->isNotEmpty();
    }

    /**
     * scharge's sales people in the CRM (sysadmin, account managers, the "sales" role), as on the web
     * (app/Modules/CRM/routes.php: role:sysadmin|account_manager|sales + crm.view). What each sees is CrmAccess:
     * their own book plus unassigned leads, everything for sysadmins and "crm.all".
     */
    public static function isCrm(?User $user): bool
    {
        return $user && $user->hasAnyRole(['sysadmin', 'account_manager', 'sales']) && self::can($user, 'crm.view');
    }

    /** Admin › Companies on the web is sysadmin + account managers only (account managers: the companies they own). */
    public static function seesCompanies(?User $user): bool
    {
        return $user && $user->hasAnyRole(['sysadmin', 'account_manager']) && self::can($user, 'companies.view');
    }

    public static function isTenant(?User $user): bool
    {
        return $user && $user->hasRole('tenant') && self::tenantIds($user) !== [];
    }

    /** The tenant records of this person (one per landlord company), whatever company scope is active. */
    public static function tenantIds(User $user): array
    {
        return CompanyAccess::bypass(fn () => Tenant::withoutGlobalScope('company')->where('user_id', $user->id)->pluck('id')
            ->map(fn ($id) => (int) $id)->all());
    }

    /**
     * The flat a tenant lives in: their active tenancy with the latest move-in. Gate passes they send straight to a
     * guest are for this unit only; for any other unit they hold they can only suggest a pass, and whoever lives
     * there approves it (FieldController::issue / act).
     */
    public static function homeUnitId(User $user): ?int
    {
        return CompanyAccess::bypass(function () use ($user) {
            $id = Tenancy::withoutGlobalScope('company')->whereIn('tenant_id', self::tenantIds($user) ?: [0])->where('status', 'active')
                ->orderByDesc('move_in_date')->orderByDesc('id')->value('unit_id');

            return $id ? (int) $id : null;
        });
    }

    /** A tenant acting as a tenant at the gate (not also a guard, manager, host or sysadmin). */
    public static function gateAsTenant(User $user): bool
    {
        return $user->hasRole('tenant') && ! $user->hasAnyRole(['sysadmin', 'bnb_host']) && ! Gate::staff($user, 'manage');
    }

    /** The landlord company whose label the person sees: their own company, or a tenant's landlord. */
    public static function companyFor(User $user): ?int
    {
        if ($user->company_id) {
            return (int) $user->company_id;
        }
        if (! $user->hasRole('tenant')) {
            return null;
        }

        return CompanyAccess::bypass(function () use ($user) {
            $tenancy = Tenancy::withoutGlobalScope('company')->with('unit.property')->whereIn('tenant_id', self::tenantIds($user) ?: [0])
                ->orderByRaw("status = 'active' desc")->latest('id')->first();

            return $tenancy ? RentRun::companyOf($tenancy) : null;
        });
    }

    /** The white label (company_brands, App\Support\Brand) or scharge's own. */
    /**
     * A company's white label shows in the app only while its scharge subscription is paid up: no pending or failed
     * subscription invoice. The same rule as the dashboard (Brand::paidUp), so web and app always agree; an unpaid
     * company (e.g. Danaff with an open bill) keeps scharge red for its staff and tenants.
     */
    public static function whiteLabelPaid(int $companyId): bool
    {
        return method_exists(Brand::class, 'paidUp') && Brand::paidUp($companyId);
    }

    public static function brand(User $user): array
    {
        // scharge staff always see scharge; everyone else only gets a company's colours once its white label is paid.
        $companyId = $user->hasAnyRole(['sysadmin', 'account_manager', 'sales']) ? null : self::companyFor($user);
        $brand = $companyId && self::whiteLabelPaid($companyId) && class_exists(\App\Support\Brand::class) ? \App\Support\Brand::forCompany($companyId) : null;
        $color = $brand && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $brand->primary_color) ? strtoupper($brand->primary_color) : '#DC2626'; // scharge red

        return [
            'white_label' => (bool) $brand,
            'name' => $brand?->display_name ?? 'scharge',
            'logo_url' => $brand?->logoUrl(),
            'logo_dark_url' => $brand?->logoDarkUrl(),
            'icon_url' => $brand?->iconUrl(),
            'primary_color' => $color,
            'tagline' => $brand?->tagline,
            'support_phone' => $brand?->support_phone,
            'support_email' => $brand?->support_email,
            'powered_by' => $brand ? (bool) $brand->show_powered_by : false,
        ];
    }

    /**
     * The screens (bottom tabs and home tiles) for this person, in the order the app shows them, with the actions
     * each one may offer. Keys are stable: the app maps them to its screens and ignores ones it does not know.
     *
     * @return array<int, array{key: string, title: string, actions: string[]}>
     */
    public static function screens(User $user): array
    {
        $can = fn (string $p) => self::can($user, $p);
        $out = [];
        $add = function (string $key, string $title, array $actions = []) use (&$out) {
            $out[] = ['key' => $key, 'title' => $title, 'actions' => array_values(array_keys(array_filter($actions)))];
        };
        $gate = Gate::allowed($user);
        $gateManage = $user->hasAnyRole(['sysadmin', 'bnb_host', 'tenant']) || Gate::staff($user, 'manage');

        if (self::isTenant($user) && ! self::isStaff($user)) {
            $can('invoices.view') && $add('tenant_bills', 'My bills', ['pay' => true]);
            $can('payments.view') && $add('tenant_payments', 'Receipts');
            $can('tenants.view') && $add('tenant_water', 'Water', ['query' => $can('maintenance.manage')]);
            $can('maintenance.view') && $add('tenant_repairs', 'Repairs', ['create' => $can('maintenance.manage')]);
            $gate && $add('gate', 'Visitors', ['issue' => $gateManage, 'cancel' => $gateManage, 'approve' => $gateManage]);
            $can('tenants.view') && $add('tenant_suggest', 'Suggestion box');

            return $out;
        }
        // scharge sales people open on the CRM (bottom tabs: My day, Pipeline, Follow-ups, Sales), then the rest.
        if (self::isCrm($user)) {
            $manage = $can('crm.manage');
            $add('crm_today', 'My day');
            $add('crm_pipeline', 'Pipeline', ['move_stage' => $manage, 'log' => $manage, 'schedule' => $manage, 'handle_reply' => $manage, 'send_email' => $manage]);
            $add('crm_follow_ups', 'Follow-ups', ['update' => $manage]);
            $add('crm_sales', 'Sales');
            $add('crm_requests', 'Requests', ['update' => $manage]);
            $add('crm_waitlist', 'Waitlist', ['update' => $manage]);
        }
        if (! self::isStaff($user)) {
            return $out;
        }

        $can('payments.view') && $add('rent', 'Rent', ['match_paybill' => $can('payments.manage')]);
        $can('invoices.view') && $add('bills', 'Unpaid bills', ['share_link' => true]);
        $can('payments.view') && $add('payments', 'Payments');
        $can('water.view') && $add('readings', 'Readings', ['record' => $can('water.manage')]);
        $can('maintenance.view') && $add('repairs', 'Repairs', ['update' => $can('maintenance.manage')]);
        $gate && $add('gate', $user->hasRole('security') ? 'Gate' : 'Visitors', ['verify' => $gate, 'issue' => $gateManage, 'check_in' => $gateManage, 'approve' => $gateManage]);
        if ($can('properties.view') && ($user->hasRole('bnb_host') || Unit::whereIn('stay_type', self::SHORT_STAY)->exists())) {
            $add('bookings', 'Bookings');
        }
        $can('tenants.view') && $add('tenants', 'Tenants');
        $can('tenants.manage') && $user->company_id && $add('suggestions', 'Suggestions');
        $can('properties.view') && $add('units', 'Units');
        self::seesCompanies($user) && $add('companies', 'Companies');

        return $out;
    }

    /** The permission names this person holds (for display; the server re-checks every call). */
    public static function permissions(User $user): array
    {
        return collect(Access::permissions())->filter(fn ($p) => self::can($user, $p))->values()->all();
    }
}
