<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Money doesn't sleep": a company with a scharge invoice (subscription_invoices, pending or failed) past its due date
 * is locked at once, for everyone in it, until the invoice is paid. Open while locked: the Billing page (owner and
 * accountant see the amounts and how to pay, everyone else is told to contact their admin), sign-out, profile and
 * password, the email-code screen. Never locked: scharge staff, tenants (they still pay rent), people without a
 * company, the public site and payment callbacks (no signed-in company user).
 *
 * A sysadmin can give a dated grace period (company_modules row "billing_grace", logged in company_module_logs).
 * Nothing is cached across requests longer than Modules' 30-second memo, so paying unlocks within the minute.
 * Enforced through Modules::deny() (web, scharge app, API keys). Off until config billing.lock_enabled.
 */
class BillingLock
{
    public const GRACE = 'billing_grace';

    /** Roles that see the amounts and pay. */
    public const PAYERS = ['admin', 'accountant'];

    /** Addresses that stay open while locked (prefixes, without the leading slash). */
    private const OPEN = ['billing', 'logout', 'profile', 'password', 'confirm-password', 'confirm-email', 'reset-password',
        'forgot-password', 'login', 'api/v1/app/auth', 'api/v1/app/errors', 'api/v1/app/device', 'up'];

    private static array $memo = [];

    public static function enabled(): bool
    {
        return (bool) config('billing.lock_enabled');
    }

    /** The company whose bills can lock this person (not staff, not tenants), or null. */
    public static function companyOf($user): ?int
    {
        $companyId = Modules::companyOf($user);
        if (! $companyId) {
            return null;
        }
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            if ($roles->contains('tenant') && $roles->diff(['tenant', 'guest'])->isEmpty()) {
                return null;
            }
        }

        return $companyId;
    }

    /**
     * Status for a company: overdue invoices (unpaid, past due), grace, and whether it is locked now.
     *
     * @return array{locked: bool, overdue: array, unpaid: array, total_due: float, currency: string, grace_until: ?string, grace_note: ?string}
     */
    public static function status(int $companyId): array
    {
        $key = spl_object_id(app()).':'.$companyId;
        if (isset(self::$memo[$key]) && self::$memo[$key]['at'] > time() - 30) {
            return self::$memo[$key]['status'];
        }
        $unpaid = [];
        try {
            $q = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
                ->where('s.company_id', $companyId)->whereIn('i.status', ['pending', 'failed'])->orderBy('i.due_date');
            $unpaid = $q->get(['i.id', 'i.invoice_number', 'i.amount', 'i.currency', 'i.due_date', 'i.status'])->map(fn ($r) => (array) $r)->all();
        } catch (\Throwable) {
        }
        $from = config('billing.lock_from') ? Carbon::parse(config('billing.lock_from'))->startOfDay() : null;
        $today = now()->startOfDay();
        $overdue = array_values(array_filter($unpaid, fn ($i) => Carbon::parse($i['due_date'])->lt($today) && (! $from || Carbon::parse($i['due_date'])->gte($from))));

        $grace = null;
        try {
            $grace = Modules::installed() ? DB::table('company_modules')->where(['company_id' => $companyId, 'module' => self::GRACE])
                ->where('expires_at', '>', now())->first() : null;
        } catch (\Throwable) {
        }
        $status = [
            'locked' => self::enabled() && $overdue && ! $grace,
            'overdue' => $overdue,
            'unpaid' => $unpaid,
            'total_due' => (float) array_sum(array_column($unpaid, 'amount')),
            'currency' => $unpaid[0]['currency'] ?? 'KES',
            'grace_until' => $grace?->expires_at,
            'grace_note' => $grace?->note,
        ];
        self::$memo[$key] = ['at' => time(), 'status' => $status];

        return $status;
    }

    /** Companies with an overdue scharge bill and no grace now (one query, for lists and the dry-run report). */
    public static function overdueCompanyIds(): array
    {
        try {
            $q = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
                ->whereIn('i.status', ['pending', 'failed'])->where('i.due_date', '<', now()->toDateString());
            if (config('billing.lock_from')) {
                $q->where('i.due_date', '>=', Carbon::parse(config('billing.lock_from'))->toDateString());
            }
            $ids = $q->distinct()->pluck('s.company_id')->map(fn ($id) => (int) $id)->all();
            $grace = Modules::installed() ? DB::table('company_modules')->where('module', self::GRACE)->where('expires_at', '>', now())->pluck('company_id')->map(fn ($id) => (int) $id)->all() : [];

            return array_values(array_diff($ids, $grace));
        } catch (\Throwable) {
            return [];
        }
    }

    public static function lockedFor($user): bool
    {
        $companyId = self::companyOf($user);

        return $companyId !== null && self::enabled() && self::status($companyId)['locked'];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }

    /** The lock answer for this request, or null to carry on. */
    public static function deny(Request $request, ?int $apiKeyCompany = null): ?\Symfony\Component\HttpFoundation\Response
    {
        if (! self::enabled()) {
            return null;
        }
        $path = trim($request->path(), '/');
        foreach (self::OPEN as $open) {
            if ($path === $open || str_starts_with($path, $open.'/')) {
                return null;
            }
        }
        $companyId = $apiKeyCompany ?? self::companyOf($request->user());
        if (! $companyId || ! self::status($companyId)['locked']) {
            return null;
        }
        $message = 'Your company\'s scharge access is paused until the scharge bill is paid.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'billing_locked', 'message' => $message, 'billing_url' => url('/billing')], 403);
        }

        return redirect('/billing');
    }

    /** Sysadmin: grace until a date (null = end the grace now). Logged like module changes. */
    public static function setGrace(int $companyId, ?string $until, ?string $note, ?int $userId): void
    {
        $until = $until ? Carbon::parse($until)->endOfDay() : null;
        DB::transaction(function () use ($companyId, $until, $note, $userId) {
            $had = DB::table('company_modules')->where(['company_id' => $companyId, 'module' => self::GRACE])->where('expires_at', '>', now())->exists();
            if ($until) {
                DB::table('company_modules')->updateOrInsert(['company_id' => $companyId, 'module' => self::GRACE],
                    ['enabled' => true, 'expires_at' => $until, 'note' => $note, 'changed_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
            } else {
                DB::table('company_modules')->where(['company_id' => $companyId, 'module' => self::GRACE])->delete();
            }
            DB::table('company_module_logs')->insert(['company_id' => $companyId, 'module' => self::GRACE, 'from_state' => $had ? 'on' : 'off',
                'to_state' => $until ? 'on' : 'off', 'expires_at' => $until, 'note' => $note, 'user_id' => $userId, 'created_at' => now()]);
        });
        self::flush();
        Modules::flush();
    }
}
