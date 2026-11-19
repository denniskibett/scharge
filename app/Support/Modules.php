<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Paid modules: the one place that answers "is module X on for this company".
 *
 *  - Core modules are always on.
 *  - Otherwise the company's active plan decides (subscription_plans.features["modules"] = list of keys). A plan
 *    without that list, or a company without an active plan, gets every module, so existing customers keep what
 *    they use today.
 *  - A sysadmin override (company_modules) switches one module on or off for one company, optionally until a date.
 *  - scharge staff (sysadmin, account managers, sales) are never limited.
 *
 * Enforced by the CheckModule middleware (web pages), AuthenticateMobile (scharge app) and AuthenticateApiKey
 * (public API), all through deny(); the sidebar hides locked entries through lockedMenu(). See docs/MODULES.md.
 */
class Modules
{
    /**
     * key => label, blurb, core, paths (dashboard addresses, without the leading slash; "x" covers x and x/…, * is a
     * wildcard), menu (sidebar keys from Access::MENU).
     */
    public const CATALOGUE = [
        'properties' => ['label' => 'Properties & units', 'blurb' => 'Buildings, units, owners and occupancy.', 'core' => true,
            'paths' => [], 'menu' => []],
        'tenants' => ['label' => 'Tenants & tenancies', 'blurb' => 'Tenant records, leases, move-in and move-out.', 'core' => true,
            'paths' => [], 'menu' => []],
        'billing' => ['label' => 'Invoices & payments', 'blurb' => 'Bills, receipts, Sharet Pay and M-Pesa payments, tenant wallets.', 'core' => true,
            'paths' => [], 'menu' => []],
        'team' => ['label' => 'Team & access', 'blurb' => 'Staff accounts and roles.', 'core' => true,
            'paths' => [], 'menu' => []],

        'rent_automation' => ['label' => 'Rent collection automation', 'blurb' => 'Automatic monthly bills, reminders, late penalties and paybill matching.',
            'paths' => ['rent'], 'menu' => ['rent']],
        'water' => ['label' => 'Water & meters', 'blurb' => 'Meter readings, water bills and smart meters.',
            'paths' => ['water', 'meter-readings', 'api/units/with-water-readings', 'api/v1/app/meters', 'api/v1/app/tenant/water', 'api/v1/meters', 'api/v1/readings'],
            'menu' => ['water', 'water_readings', 'water_reports']],
        'maintenance' => ['label' => 'Repairs & maintenance', 'blurb' => 'Repair requests, work orders and cleaning schedules.',
            'paths' => ['maintenance', 'cleaning', 'api/v1/app/repairs', 'api/v1/app/tenant/repairs'], 'menu' => ['maintenance']],
        'gate' => ['label' => 'Gate & visitors', 'blurb' => 'Guest passes, the gate log and visitor checks.',
            'paths' => ['gate', 'security', 'api/v1/app/gate'], 'menu' => ['gate', 'security', 'security_logs']],
        'sms' => ['label' => 'Tenant SMS', 'blurb' => 'Bulk SMS, templates and message history.',
            'paths' => ['sms', 'api/sms'], 'menu' => ['sms', 'sms_send', 'sms_history', 'sms_templates', 'sms_settings']],
        'bookings' => ['label' => 'Bookings & short stay', 'blurb' => 'Short-stay bookings and channel calendars.',
            'paths' => ['bookings', 'api/v1/app/bookings'], 'menu' => ['bookings']],
        'listings' => ['label' => 'Open listings & enquiries', 'blurb' => 'Vacancy enquiries and renter demand.',
            'paths' => ['enquiries', 'demand'], 'menu' => ['enquiries', 'demand']],
        'website' => ['label' => 'Website & white label', 'blurb' => 'The company website, its own address and brand.',
            'paths' => ['settings/website', 'settings/white-label'], 'menu' => ['website', 'white_label']],
        'integrations' => ['label' => 'Integrations', 'blurb' => 'Accounting sync, bank statement import, webhooks, calendar feeds.',
            'paths' => ['integrations', 'bank-import'], 'menu' => ['integrations']],
        'api_keys' => ['label' => 'API keys', 'blurb' => 'The scharge API for the company\'s own systems.',
            'paths' => ['api-keys'], 'menu' => ['api_keys']], // and every call made with a key (AuthenticateApiKey)
        'expenses' => ['label' => 'Expenses & payees', 'blurb' => 'Property expenses, categories and payees.',
            'paths' => ['expenses', 'expense-categories', 'payees'], 'menu' => ['expenses', 'payees']],
        'reports' => ['label' => 'Reports', 'blurb' => 'Collection, arrears, occupancy and statement reports.',
            'paths' => ['reports'], 'menu' => ['reports', 'analytics']],
        'agents' => ['label' => 'Agents', 'blurb' => 'Give properties to letting agencies; the agency\'s agent dashboard.',
            'paths' => ['agent', 'properties/*/agents'], 'menu' => ['agent']],
        'mobile_app' => ['label' => 'scharge app', 'blurb' => 'The Android and iPhone app for staff and tenants.',
            'paths' => ['api/v1/app'], 'menu' => []],
    ];

    /** scharge app calls that must keep working when the app is locked (signing in and out, crash reports). */
    private const ALWAYS_OPEN = ['api/v1/app/auth', 'api/v1/app/errors', 'api/v1/app/device'];

    /** Roles that work for scharge itself, never limited by a customer's plan. */
    public const STAFF_ROLES = ['sysadmin', 'account_manager', 'sales'];

    /** @var array<int, array<string, array>> per company, for this request */
    private static array $memo = [];

    private static ?bool $installed = null;

    private static ?int $memoApp = null;

    private static int $memoAt = 0;

    public static function flush(): void
    {
        self::$memo = [];
        self::$installed = null;
    }

    /** The memo lives for one app instance (request, test) and at most 30 seconds (queue workers, long scripts). */
    private static function fresh(): void
    {
        $app = spl_object_id(app());
        if (self::$memoApp !== $app || time() - self::$memoAt > 30) {
            self::flush();
            self::$memoApp = $app;
            self::$memoAt = time();
        }
    }

    public static function label(string $key): string
    {
        return self::CATALOGUE[$key]['label'] ?? Str::headline($key);
    }

    public static function isCore(string $key): bool
    {
        return (bool) (self::CATALOGUE[$key]['core'] ?? false);
    }

    /** @return string[] keys a plan or a sysadmin can switch */
    public static function paid(): array
    {
        return array_keys(array_filter(self::CATALOGUE, fn ($m) => empty($m['core'])));
    }

    public static function installed(): bool
    {
        self::fresh();
        if (self::$installed === null) {
            try {
                self::$installed = Schema::hasTable('company_modules');
            } catch (\Throwable) {
                self::$installed = false;
            }
        }

        return self::$installed;
    }

    /** Is the module on for this company? Unknown keys and companies are on (fails open: plans only ever remove). */
    public static function on(?int $companyId, string $key): bool
    {
        if (! $companyId || self::isCore($key) || ! isset(self::CATALOGUE[$key])) {
            return true;
        }

        return self::states($companyId)[$key]['on'] ?? true;
    }

    /** Is the module on for this person? scharge staff and people without a company always: yes. */
    public static function onFor($user, string $key): bool
    {
        return ! in_array($key, self::lockedFor($user), true);
    }

    /** @return string[] module keys locked for this person */
    public static function lockedFor($user): array
    {
        $companyId = self::companyOf($user);
        if (! $companyId) {
            return [];
        }

        return array_keys(array_filter(self::states($companyId), fn ($s) => ! $s['on']));
    }

    /** @return string[] sidebar keys to hide for this person */
    public static function lockedMenu($user): array
    {
        return array_values(array_unique(array_merge(...array_map(fn ($k) => self::CATALOGUE[$k]['menu'], self::lockedFor($user)) ?: [[]])));
    }

    /** The company whose plan limits this person, or null (scharge staff, or no company). */
    public static function companyOf($user): ?int
    {
        if (! $user || ! $user->company_id) {
            return null;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::STAFF_ROLES)) {
            return null;
        }

        return (int) $user->company_id;
    }

    /** Active plan's module list for a company: null = the plan does not limit modules (or no active plan). */
    public static function planModules(int $companyId): ?array
    {
        try {
            $planId = DB::table('company_subscriptions')->where('company_id', $companyId)->whereIn('status', ['active', 'trial'])
                ->orderByDesc('id')->value('plan_id');
            $features = $planId ? DB::table('subscription_plans')->where('id', $planId)->value('features') : null;
        } catch (\Throwable) {
            return null;
        }

        return self::modulesFromFeatures($features);
    }

    /** subscription_plans.features may be an array, JSON, or JSON inside a JSON string (older saves). */
    public static function modulesFromFeatures($features): ?array
    {
        for ($i = 0; is_string($features) && $i < 2; $i++) {
            $features = json_decode($features, true);
        }
        if (! is_array($features) || ! array_key_exists('modules', $features) || ! is_array($features['modules'])) {
            return null;
        }

        return array_values(array_intersect(self::paid(), $features['modules']));
    }

    /**
     * Every paid module for a company: on, source (plan | override), the plan's default, the override's expiry, who
     * changed it and when.
     *
     * @return array<string, array{on: bool, source: string, plan: bool, override: ?bool, expires_at: ?string, changed_by: ?int, changed_at: ?string, note: ?string}>
     */
    public static function states(int $companyId): array
    {
        self::fresh();
        if (isset(self::$memo[$companyId])) {
            return self::$memo[$companyId];
        }
        $plan = self::planModules($companyId);
        try {
            $overrides = self::installed() ? DB::table('company_modules')->where('company_id', $companyId)->get()->keyBy('module') : collect();
        } catch (\Throwable) {
            $overrides = collect();
        }
        $out = [];
        foreach (self::paid() as $key) {
            $planOn = $plan === null || in_array($key, $plan, true);
            $row = $overrides[$key] ?? null;
            $live = $row && (! $row->expires_at || now()->lt($row->expires_at));
            $out[$key] = [
                'on' => $live ? (bool) $row->enabled : $planOn,
                'source' => $live ? 'override' : 'plan',
                'plan' => $planOn,
                'override' => $row ? (bool) $row->enabled : null,
                'expired' => $row && ! $live,
                'expires_at' => $row?->expires_at,
                'changed_by' => $row?->changed_by,
                'changed_at' => $row?->updated_at,
                'note' => $row?->note,
            ];
        }

        return self::$memo[$companyId] = $out;
    }

    /**
     * Sysadmin switch: $state "on" / "off" (override, optionally until $expires) or "plan" (remove the override).
     * Logs the change. Returns the new state of that module.
     */
    public static function set(int $companyId, string $key, string $state, ?string $expires, ?string $note, ?int $userId): array
    {
        abort_unless(in_array($key, self::paid(), true) && in_array($state, ['plan', 'on', 'off'], true), 422, 'Unknown module or state.');
        $before = self::states($companyId)[$key];
        $from = $before['source'] === 'override' ? ($before['on'] ? 'on' : 'off') : 'plan';
        $expires = $state === 'plan' || ! $expires ? null : \Illuminate\Support\Carbon::parse($expires)->endOfDay();

        DB::transaction(function () use ($companyId, $key, $state, $expires, $note, $userId, $from) {
            if ($state === 'plan') {
                DB::table('company_modules')->where(['company_id' => $companyId, 'module' => $key])->delete();
            } else {
                DB::table('company_modules')->updateOrInsert(['company_id' => $companyId, 'module' => $key],
                    ['enabled' => $state === 'on', 'expires_at' => $expires, 'note' => $note, 'changed_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
            }
            DB::table('company_module_logs')->insert(['company_id' => $companyId, 'module' => $key, 'from_state' => $from, 'to_state' => $state,
                'expires_at' => $expires, 'note' => $note, 'user_id' => $userId, 'created_at' => now()]);
        });
        unset(self::$memo[$companyId]);

        return self::states($companyId)[$key];
    }

    /** Module keys an address belongs to (more than one for e.g. the app's water screens: app + water). */
    public static function forPath(string $path): array
    {
        $path = trim($path, '/');
        foreach (self::ALWAYS_OPEN as $open) {
            if ($path === $open || str_starts_with($path, $open.'/')) {
                return [];
            }
        }
        $hits = [];
        foreach (self::CATALOGUE as $key => $module) {
            foreach ($module['paths'] as $pattern) {
                if ($path === $pattern || str_starts_with($path, $pattern.'/') || (str_contains($pattern, '*') && (Str::is($pattern, $path) || Str::is($pattern.'/*', $path)))) {
                    $hits[] = $key;
                    break;
                }
            }
        }

        return $hits;
    }

    /**
     * The friendly answer when this request opens a locked module, or null to carry on. The company comes from the
     * signed-in person (web, scharge app) or the API key.
     */
    public static function deny(Request $request): ?\Symfony\Component\HttpFoundation\Response
    {
        $apiKey = $request->attributes->get('api_key');
        // An unpaid scharge bill locks the whole company first (App\Support\BillingLock).
        if ($locked = BillingLock::deny($request, $apiKey ? (int) $apiKey->company_id : null)) {
            return $locked;
        }
        $keys = array_merge(self::forPath($request->path()), $apiKey ? ['api_keys'] : []);
        if (! $keys) {
            return null;
        }
        $companyId = $apiKey ? (int) $apiKey->company_id : self::companyOf($request->user());
        if (! $companyId) {
            return null;
        }
        $locked = array_values(array_filter($keys, fn ($k) => ! self::on($companyId, $k)));
        if (! $locked) {
            return null;
        }
        $module = $locked[0];
        $message = self::label($module).' isn\'t on your plan. Contact scharge to add it.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'module_locked', 'module' => $module, 'message' => $message,
                'contact' => self::contact()], 403);
        }
        return response()->view('modules.locked', ['module' => $module, 'label' => self::label($module),
            'blurb' => self::CATALOGUE[$module]['blurb'] ?? '', 'contact' => self::contact()], 200)->header('X-Module-Locked', $module);
    }

    /** Who to contact to add a module: the company's account manager, else scharge support. */
    public static function contact(): array
    {
        $user = auth()->user();
        $manager = null;
        try {
            $ownerId = $user?->company_id ? DB::table('companies')->where('id', $user->company_id)->value('owner_id') : null;
            $manager = $ownerId ? DB::table('users')->where('id', $ownerId)->first(['name', 'email', 'phone']) : null;
        } catch (\Throwable) {
        }

        return [
            'name' => $manager->name ?? 'scharge support',
            'email' => $manager->email ?? 'sales@sharet.africa',
            'phone' => $manager->phone ?? null,
        ];
    }
}
