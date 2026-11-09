<?php

namespace App\Support;

/**
 * What each role can do, area by area (pattern from Brevemaster's RoleSeeder).
 * Permissions are "<area>.<action>": view (open pages, read), manage (create & edit), delete.
 * `php artisan access:sync` writes this into Spatie's tables; the Roles page can then fine-tune
 * any role, and routes check the permission (middleware `access:<area>`).
 */
class Access
{
    public const ACTIONS = ['view', 'manage', 'delete'];

    /** area => label, in the order of the sidebar's business processes. */
    public const AREAS = [
        'properties' => 'Properties & units',
        'tenants' => 'Tenants & tenancies',
        'water' => 'Meter readings',
        'maintenance' => 'Repairs',
        'security' => 'Gate & visitors',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'expenses' => 'Expenses & payees',
        'reports' => 'Reports',
        'sms' => 'Tenant messages (SMS)',
        'crm' => 'Sales CRM',
        'companies' => 'Companies & claims',
        'team' => 'Team & access',
        'settings' => 'Integrations & API keys',
        'system' => 'System settings & roles',
    ];

    /**
     * Role => grants. '*' = everything; "area.*" = all actions in an area.
     * Company roles act inside their own company; tenants and hosts only see their own records
     * (that scoping is done by the controllers, these grants decide which areas they may open).
     */
    public const ROLES = [
        'sysadmin' => '*',
        'account_manager' => ['crm.*', 'companies.view', 'companies.manage', 'reports.view', 'payments.view'],
        'admin' => ['properties.*', 'tenants.*', 'water.*', 'maintenance.*', 'security.*', 'invoices.*', 'payments.*', 'expenses.*',
            'reports.view', 'sms.*', 'team.*', 'settings.*'],
        'property_manager' => ['properties.view', 'properties.manage', 'tenants.view', 'tenants.manage', 'water.view', 'water.manage',
            'maintenance.*', 'security.view', 'invoices.view', 'invoices.manage', 'payments.view', 'reports.view', 'sms.view', 'sms.manage'],
        'accountant' => ['properties.view', 'tenants.view', 'invoices.*', 'payments.*', 'expenses.*', 'reports.view', 'water.view'],
        'meter_reader' => ['properties.view', 'water.view', 'water.manage'],
        'maintenance' => ['properties.view', 'maintenance.view', 'maintenance.manage'],
        'cleaning_staff' => ['properties.view', 'maintenance.view'],
        'security' => ['properties.view', 'security.view', 'security.manage'],
        'tenant' => ['tenants.view', 'invoices.view', 'payments.view', 'maintenance.view', 'maintenance.manage', 'security.view'],
        'bnb_host' => ['properties.view', 'properties.manage'],
        'guest' => [],
    ];

    /** Roles a company admin may give their own people (scharge staff roles excluded). */
    public const COMPANY_ROLES = [
        'admin' => 'Admin: everything in the company',
        'property_manager' => 'Property manager: properties, tenants, readings, repairs, billing',
        'accountant' => 'Accountant: invoices, payments, expenses, reports',
        'meter_reader' => 'Meter reader: record water and electricity readings',
        'maintenance' => 'Maintenance: repairs and work orders',
        'cleaning_staff' => 'Cleaning: turnover and cleaning tasks',
        'security' => 'Security: gate and visitor log',
    ];

    /** Built-in roles: code checks their names, so they cannot be renamed or deleted. */
    public const BUILT_IN = ['sysadmin', 'account_manager', 'admin', 'property_manager', 'accountant', 'meter_reader',
        'maintenance', 'cleaning_staff', 'security', 'tenant', 'bnb_host', 'guest'];

    /** @return string[] every permission name */
    public static function permissions(): array
    {
        $out = [];
        foreach (array_keys(self::AREAS) as $area) {
            foreach (self::ACTIONS as $action) {
                $out[] = "{$area}.{$action}";
            }
        }

        return $out;
    }

    /** @return string[] the permission names a role gets by default */
    public static function grantsFor(string $role): array
    {
        $grants = self::ROLES[$role] ?? [];
        if ($grants === '*') {
            return self::permissions();
        }

        return array_values(array_filter(self::permissions(), function ($p) use ($grants) {
            [$area] = explode('.', $p);

            return in_array($p, $grants, true) || in_array("{$area}.*", $grants, true);
        }));
    }

    /** Which action an HTTP method needs. */
    public static function actionFor(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => 'view',
            'DELETE' => 'delete',
            default => 'manage',
        };
    }

    /** Sidebar entry keys => the permission that shows them (see partials/sidebar.blade.php). */
    public const MENU = [
        'property' => 'properties.view', 'properties' => 'properties.view', 'units' => 'properties.view',
        'tenants' => 'tenants.view', 'tenancies' => 'tenants.view',
        'water' => 'water.view', 'water_readings' => 'water.view', 'water_reports' => 'water.view',
        'maintenance' => 'maintenance.view',
        'security' => 'security.view', 'security_logs' => 'security.view',
        'finance' => ['invoices.view', 'payments.view'], 'invoices' => 'invoices.view', 'payments' => 'payments.view',
        'expenses' => 'expenses.view', 'payees' => 'expenses.view',
        'sms' => 'sms.view', 'sms_send' => 'sms.manage', 'sms_history' => 'sms.view', 'sms_templates' => 'sms.manage', 'sms_settings' => 'settings.manage',
        'crm' => 'crm.view', 'crm_pipeline' => 'crm.view', 'crm_follow_ups' => 'crm.view', 'crm_demos' => 'crm.view', 'crm_waitlist' => 'crm.view', 'crm_feedback' => 'crm.view',
        'companies' => 'companies.view', 'property_claims' => 'companies.manage',
        'settings' => ['settings.view', 'team.view', 'system.view'], 'integrations' => 'settings.view', 'api_keys' => 'settings.view',
        'users' => 'team.view', 'staff' => 'team.view', 'team' => 'team.view', 'roles' => 'system.view', 'system_settings' => 'system.view',
        'analytics' => 'reports.view',
    ];

    /**
     * Sidebar keys this user may see, from their real permissions. Null = catalogue not installed yet
     * (the sidebar then falls back to its built-in role list). Tenants keep their fixed, personal menu.
     */
    public static function menuFor($user): ?array
    {
        if (! \App\Http\Middleware\CheckAccess::catalogueInstalled() || $user->hasRole('tenant') && ! $user->hasAnyRole(['admin', 'property_manager', 'sysadmin'])) {
            return null;
        }
        $keys = ['dashboard', 'calendar', 'profile'];
        if ($user->hasRole('bnb_host')) {
            $keys[] = 'host';
        }
        foreach (self::MENU as $key => $needs) {
            if ($user->hasRole('sysadmin') || collect((array) $needs)->contains(fn ($perm) => $user->can($perm))) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
