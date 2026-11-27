<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Done-for-you setup. After a demo, scharge sets the account up from what the client shared (DemoOnboarding).
 * While setup runs, the client's own logins can only see their properties and add or edit units; scharge staff
 * do everything else (properties, tenants, payments, KYC, branding). The account manager opens full access when
 * setup is finished (finish()).
 *
 * State: companies.crm_profile.setup = {mode: managed, started_at, by, finished_at?, finished_by?}.
 * Enforced in App\Support\Modules::deny, so it covers the web, the scharge app and the API in one place.
 */
class SetupMode
{
    /** Pages a client may open during setup (GET), by path prefix. '' is the home page. */
    public const VIEW = ['', 'dashboard', 'units', 'properties', 'guide', 'setup', 'profile', 'notifications', 'search',
        'logout', 'login', 'password', 'reset-password', 'forgot-password', 'confirm-password', 'verify-email', 'email', 'account', 'api/v1/app/me'];

    /** What a client may change during setup, by path prefix: units (and their photos and readings), their own profile and password. */
    public const CHANGE = ['units', 'profile', 'logout', 'password', 'reset-password', 'forgot-password', 'confirm-password', 'email', 'account', 'guide/tenant-list'];

    private static array $cache = [];

    public static function on(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }
        if (! array_key_exists($companyId, self::$cache)) {
            $profile = json_decode((string) DB::table('companies')->where('id', $companyId)->value('crm_profile'), true) ?: [];
            $setup = $profile['setup'] ?? [];
            self::$cache[$companyId] = ($setup['mode'] ?? null) === 'managed' && empty($setup['finished_at']);
        }

        return self::$cache[$companyId];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function start(Company $company, ?int $by): void
    {
        $profile = $company->crm_profile ?? [];
        $profile['setup'] = ['mode' => 'managed', 'started_at' => now()->toDateTimeString(), 'by' => $by];
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        self::flush();
    }

    /** Setup is done: the client's logins get their full access back. */
    public static function finish(Company $company, ?int $by): void
    {
        $profile = $company->crm_profile ?? [];
        $profile['setup'] = array_merge($profile['setup'] ?? ['mode' => 'managed'], ['finished_at' => now()->toDateTimeString(), 'finished_by' => $by]);
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        self::flush();
    }

    /** The answer when a client opens something scharge is still setting up for them; null to carry on. */
    public static function deny(Request $request): ?\Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        if (! $user || ! $user->company_id || self::isStaff($user) || ! self::on((int) $user->company_id)) {
            return null;
        }
        $path = trim($request->path(), '/');
        $allowed = $request->isMethod('GET') || $request->isMethod('HEAD') ? self::VIEW : self::CHANGE;
        foreach ($allowed as $open) {
            if ($path === $open || ($open !== '' && str_starts_with($path, $open.'/'))) {
                return null;
            }
        }
        $message = 'scharge is setting up your account for you. For now you can add and edit your units; everything else opens as soon as setup is finished.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'setup_in_progress', 'message' => $message], 403);
        }

        return redirect('/units')->with('info', $message);
    }

    private static function isStaff($user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['sysadmin', 'account_manager', 'sales']);
    }
}
