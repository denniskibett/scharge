<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileSession;
use App\Models\User;
use App\Modules\SMS\Services\KenyaSMS;
use App\Support\CompanyAccess;
use App\Support\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * scharge app sign-in (docs/MOBILE_APP.md), the JAZZA pattern:
 *   1. a new phone: phone number -> SMS code -> create a 4-digit PIN (this registers the phone to the account);
 *   2. every day after: fingerprint / face / phone lock on the phone, or the PIN (POST unlock);
 *   3. forgot PIN or 5 wrong PINs: the phone is signed out, so it is an SMS code again.
 * Email + password stays for staff accounts without a phone number.
 */
class AuthController extends Controller
{
    private const CODE_MINUTES = 10;

    private const CODE_TRIES = 5;

    public function sendCode(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|min:9|max:20']);
        $key = self::phoneKey($data['phone']);
        abort_unless($key, 422, 'Enter your mobile number, like 0712 345 678.');

        if (RateLimiter::tooManyAttempts('app-otp-send:'.$key, 3)) {
            return response()->json(['error' => 'too_many', 'message' => 'Too many codes asked for. Try again in '.ceil(RateLimiter::availableIn('app-otp-send:'.$key) / 60).' minutes.'], 429);
        }
        RateLimiter::hit('app-otp-send:'.$key, 600);

        $users = $this->accountsFor($key);
        if ($users->isNotEmpty()) {
            $code = (string) random_int(100000, 999999);
            Cache::put('app-otp:'.$key, ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::CODE_MINUTES));
            $user = $users->first();
            $brand = MobileAccess::brand($user)['name'];
            try {
                app(KenyaSMS::class)->forCompany(MobileAccess::companyFor($user))
                    ->sendOne($user->phone, "Your {$brand} app code is {$code}. It expires in ".self::CODE_MINUTES.' minutes. Never share it.');
            } catch (\Throwable $e) {
                Log::warning('App sign-in code not sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        // The same answer either way, so the form cannot be used to find out who has an account.
        return response()->json(['sent' => true, 'expires_in' => self::CODE_MINUTES * 60,
            'message' => 'If this number has an account, we have sent it a 6-digit code.']);
    }

    /** Checks (and counts) an SMS code; true when right. The code is used up by the caller on success. */
    private function codeOk(string $key, string $code): bool
    {
        $entry = Cache::get('app-otp:'.$key);
        if (! $entry || $entry['tries'] >= self::CODE_TRIES) {
            return false;
        }
        if (! Hash::check($code, $entry['hash'])) {
            $entry['tries']++;
            Cache::put('app-otp:'.$key, $entry, now()->addMinutes(self::CODE_MINUTES));

            return false;
        }

        return true;
    }

    /**
     * Create my account (tenants): the landlord has already added them with this phone number. With the SMS code
     * they set their name, email, ID number and one password (for signing in by phone or email), then a PIN.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|digits:6',
            'name' => 'required|string|min:3|max:120',
            'email' => 'nullable|email|max:191',
            'id_number' => 'nullable|string|min:5|max:20',
            'password' => 'required|string|min:8|confirmed',
            'device_name' => 'required|string|max:60',
            'account_id' => 'nullable|integer',
        ]);
        $key = self::phoneKey($data['phone']);
        if (! $key || ! $this->codeOk($key, $data['code'])) {
            return response()->json(['error' => 'wrong_code', 'message' => 'That code is wrong or has expired. Ask for a new one.'], 422);
        }
        // Tenant accounts only: sign-up sets a password that also works on the website, so it must never reach a
        // landlord's staff or a scharge account that shares the number (they sign in with their own password).
        $all = $this->accountsFor($key);
        $users = $all->filter(fn ($u) => $u->hasRole('tenant') && ! $u->hasAnyRole(['sysadmin', 'account_manager', 'admin', 'property_manager',
            'accountant', 'meter_reader', 'maintenance', 'cleaning_staff', 'security', 'bnb_host']))->values();
        if ($users->isEmpty()) {
            return $all->isNotEmpty()
                ? response()->json(['error' => 'staff_account', 'message' => 'This number belongs to a staff account. Sign in with your password or an SMS code instead.'], 422)
                : response()->json(['error' => 'not_added', 'message' => 'Your landlord has not added this number yet. Ask them to add you, then try again.'], 404);
        }
        if ($users->count() > 1 && empty($data['account_id'])) {
            return response()->json(['choose_account' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name,
                'role' => $u->getRoleNames()->first(), 'company' => MobileAccess::brand($u)['name']])->values()]);
        }
        $user = empty($data['account_id']) ? $users->first() : $users->firstWhere('id', (int) $data['account_id']);
        abort_unless($user, 422, 'Pick one of your accounts.');
        if (! empty($data['email']) && User::where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
            return response()->json(['error' => 'email_taken', 'message' => 'That email belongs to another account.', 'errors' => ['email' => ['That email belongs to another account.']]], 422);
        }
        Cache::forget('app-otp:'.$key);

        $user->forceFill(['name' => $data['name'], 'password' => Hash::make($data['password'])] + (! empty($data['email']) ? ['email' => $data['email']] : []))->save();
        if (! empty($data['id_number'])) {
            CompanyAccess::bypass(fn () => \App\Models\Tenant::withoutGlobalScope('company')->where('user_id', $user->id)->update(['id_number' => $data['id_number']]));
        }

        return $this->signIn($request, $user->fresh(), $data['device_name'], 'otp');
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:20', 'code' => 'required|digits:6',
            'device_name' => 'required|string|max:60', 'account_id' => 'nullable|integer']);
        $key = self::phoneKey($data['phone']);
        $entry = $key ? Cache::get('app-otp:'.$key) : null;
        $wrong = response()->json(['error' => 'wrong_code', 'message' => 'That code is wrong or has expired. Ask for a new one.'], 422);

        if (! $entry || $entry['tries'] >= self::CODE_TRIES) {
            return $wrong;
        }
        if (! Hash::check($data['code'], $entry['hash'])) {
            $entry['tries']++;
            Cache::put('app-otp:'.$key, $entry, now()->addMinutes(self::CODE_MINUTES));

            return $wrong;
        }

        $users = $this->accountsFor($key);
        if ($users->count() > 1 && empty($data['account_id'])) {
            // One number, several accounts (e.g. a tenant who is also a landlord's staff): the person picks one.
            return response()->json(['choose_account' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name,
                'role' => $u->getRoleNames()->first(), 'company' => MobileAccess::brand($u)['name']])->values()]);
        }
        $user = empty($data['account_id']) ? $users->first() : $users->firstWhere('id', (int) $data['account_id']);
        if (! $user) {
            return $wrong;
        }
        Cache::forget('app-otp:'.$key);

        return $this->signIn($request, $user, $data['device_name'], 'otp');
    }

    public function password(Request $request)
    {
        // One password, either identifier: the phone number or the email.
        $data = $request->validate(['login' => 'required_without:email|nullable|string|max:191', 'email' => 'nullable|string|max:191',
            'password' => 'required|string', 'device_name' => 'required|string|max:60']);
        $login = trim((string) ($data['login'] ?? $data['email']));
        $user = str_contains($login, '@') ? User::where('email', $login)->first()
            : (($key = self::phoneKey($login)) ? $this->accountsFor($key)->first() : null);
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || (int) $user->status === 1) {
            app(\App\Services\Analytics\SignIns::class)->record($user, $request, 'app', 'password', false);

            return response()->json(['error' => 'invalid_credentials', 'message' => 'Wrong phone, email or password.'], 401);
        }

        return $this->signIn($request, $user, $data['device_name'], 'password');
    }

    public function me(Request $request)
    {
        return response()->json($this->profile($request->user(), $request->attributes->get('mobile_session')));
    }

    /**
     * What needs this person's attention now (the bell): worked out from live data, nothing stored. Each item names
     * the screen the app opens. Only for screens the person has, so it follows the same access rules.
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $keys = array_column(MobileAccess::screens($user), null, 'key');
        $items = [];
        $add = function (string $screen, string $title, string $text, int $count = 1) use (&$items, $keys) {
            if (isset($keys[$screen]) && $count > 0) {
                $items[] = ['screen' => $screen, 'title' => $title, 'text' => $text, 'count' => $count];
            }
        };
        $home = MobileAccess::gateAsTenant($user) ? MobileAccess::homeUnitId($user) : null;
        $gateIds = \App\Services\Gate\Gate::allowed($user) ? \App\Services\Gate\Gate::scopeUnitIds($user) : [];
        $toApprove = \App\Modules\Security\Models\GatePass::where('status', 'suggested')
            ->when($home !== null, fn ($q) => $q->where('unit_id', $home)->where('created_by', '!=', $user->id))
            ->when($home === null && $gateIds !== null, fn ($q) => $q->whereIn('unit_id', $gateIds ?: [0]))->count();
        $add('gate', 'Gate passes to approve', $toApprove === 1 ? 'Someone suggested a guest for your unit.' : "$toApprove guests are waiting for your OK.", $toApprove);

        if (isset($keys['tenant_bills'])) {
            $ids = MobileAccess::tenantIds($user);
            $open = CompanyAccess::bypass(fn () => \App\Models\Invoice::withoutGlobalScope('company')->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->whereColumn('total_paid', '<', 'total_amount')
                ->whereIn('tenancy_id', \App\Models\Tenancy::withoutGlobalScope('company')->whereIn('tenant_id', $ids ?: [0])->select('id'))->count());
            $add('tenant_bills', 'Bill to pay', $open === 1 ? 'You have a bill to pay.' : "You have $open bills to pay.", $open);
            $fixed = CompanyAccess::bypass(fn () => \App\Models\Maintenance::whereIn('tenant_id', $ids ?: [0])->where('status', 'completed')
                ->where('updated_at', '>=', now()->subDays(3))->count());
            $add('tenant_repairs', 'Repair done', 'A repair you reported was marked done.', $fixed);
        }
        if (isset($keys['rent'])) {
            $ids = CompanyAccess::ids();
            $n = \App\Models\PaybillPayment::where('status', 'unmatched')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))->count();
            $add('rent', 'Paybill payments to match', "$n paid without a unit we know.", $n);
        }
        if (isset($keys['readings']) && MobileAccess::can($user, 'water.manage')) {
            $ids = CompanyAccess::ids();
            $n = \App\Models\MeterReading::where('status', 'flagged')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))
                ->where('created_at', '>=', now()->subDays(30))->count();
            $add('readings', 'Unusual readings', "$n readings wait for a check before billing.", $n);
        }
        if (isset($keys['repairs'])) {
            $n = \App\Models\Maintenance::whereHas('unit')->where('status', 'open')
                ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', $user->id))->count();
            $add('repairs', 'Open repairs', "$n repairs need someone.", $n);
        }

        if (isset($keys['suggestions'])) {
            $ids = CompanyAccess::ids();
            $n = \App\Models\TenantSuggestion::whereNull('read_at')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))->count();
            $add('suggestions', 'New suggestions', "$n new from tenants (anonymous).", $n);
        }

        return response()->json(['count' => array_sum(array_column($items, 'count')), 'data' => $items]);
    }

    /** Create or change the app PIN (4 digits). Changing it needs the old PIN. */
    public function setPin(Request $request)
    {
        $session = $request->attributes->get('mobile_session');
        $data = $request->validate(['pin' => ['required', 'regex:/^(\d{4}|\d{6})$/'], 'current_pin' => ['nullable', 'regex:/^(\d{4}|\d{6})$/']],
            ['pin.regex' => 'Use 4 or 6 digits.']);
        if ($session->pin_hash && ! Hash::check((string) ($data['current_pin'] ?? ''), $session->pin_hash)) {
            return response()->json(['error' => 'wrong_pin', 'message' => 'Your current PIN is wrong.'], 422);
        }
        if (preg_match('/^(\d)\1+$/', $data['pin']) || in_array($data['pin'], ['1234', '4321', '123456', '654321', '121212'], true)) {
            return response()->json(['error' => 'weak_pin', 'message' => 'Pick a PIN that is harder to guess.'], 422);
        }
        $session->forceFill(['pin_hash' => Hash::make($data['pin']), 'pin_failures' => 0])->save();

        return response()->json(['pin_set' => true, 'pin_length' => strlen($data['pin'])]);
    }

    /** Opening the app with the PIN (fingerprint / phone lock is checked on the phone). Five wrong PINs sign the phone out. */
    public function unlock(Request $request)
    {
        $session = $request->attributes->get('mobile_session');
        $data = $request->validate(['pin' => ['required', 'regex:/^(\d{4}|\d{6})$/']]);
        if (! $session->pin_hash) {
            return response()->json(['error' => 'no_pin', 'message' => 'Create a PIN first.'], 422);
        }
        if (! Hash::check($data['pin'], $session->pin_hash)) {
            $session->increment('pin_failures');
            $left = MobileSession::MAX_PIN_FAILURES - $session->pin_failures;
            if ($left <= 0) {
                $session->delete();

                return response()->json(['error' => 'signed_out', 'message' => 'Too many wrong PINs. Sign in again with an SMS code.'], 401);
            }

            return response()->json(['error' => 'wrong_pin', 'attempts_left' => $left,
                'message' => "Wrong PIN. {$left} ".($left === 1 ? 'try' : 'tries').' left.'], 422);
        }
        $session->forceFill(['pin_failures' => 0])->save();

        return response()->json(['unlocked' => true] + $this->profile($request->user(), $session));
    }

    /**
     * Check the session's PIN for a payment. Wrong PINs count towards the same 5-try lockout as unlocking.
     * Returns null when right, else the error response.
     */
    public static function checkPin(MobileSession $session, ?string $pin)
    {
        if (! $session->pin_hash || ! $pin || ! Hash::check($pin, $session->pin_hash)) {
            $session->increment('pin_failures');
            $left = MobileSession::MAX_PIN_FAILURES - $session->pin_failures;
            if ($left <= 0) {
                $session->delete();

                return response()->json(['error' => 'signed_out', 'message' => 'Too many wrong PINs. Sign in again with an SMS code.'], 401);
            }

            return response()->json(['error' => 'wrong_pin', 'attempts_left' => $left, 'message' => "Wrong PIN. {$left} ".($left === 1 ? 'try' : 'tries').' left.'], 422);
        }
        $session->forceFill(['pin_failures' => 0])->save();

        return null;
    }

    public function logout(Request $request)
    {
        $request->attributes->get('mobile_session')->delete();

        return response()->json(['signed_out' => true]);
    }

    private function signIn(Request $request, User $user, string $device, string $via)
    {
        Auth::setUser($user);
        CompanyAccess::flush();
        if (MobileAccess::screens($user) === []) {
            return response()->json(['error' => 'no_app_access', 'message' => 'Your account has nothing to show in the app yet. Use the website.'], 403);
        }
        $session = new MobileSession(['user_id' => $user->id, 'device_name' => mb_substr($device, 0, 80),
            'platform' => in_array($request->input('platform'), ['android', 'ios'], true) ? $request->input('platform') : 'android', 'signed_in_with' => $via]);
        $token = $session->issue();
        $session->forceFill(['last_used_at' => now(), 'last_ip' => $request->ip()])->save();
        app(\App\Services\Analytics\SignIns::class)->record($user, $request, 'app', $via);

        return response()->json(['token' => $token] + $this->profile($user, $session), 201);
    }

    private function profile(User $user, ?MobileSession $session): array
    {
        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                'roles' => $user->getRoleNames()->values()->all(), 'company_id' => MobileAccess::companyFor($user)],
            'brand' => MobileAccess::brand($user),
            'screens' => MobileAccess::screens($user),
            'permissions' => MobileAccess::permissions($user),
            'session' => $session ? ['id' => $session->id, 'device' => $session->device_name, 'has_pin' => (bool) $session->pin_hash] : null,
        ];
    }

    /** Active accounts with this mobile number (stored as 07…, 7…, 2547… or +2547…). */
    private function accountsFor(string $key)
    {
        return User::where('phone', 'like', '%'.$key)->get()
            ->filter(fn ($u) => self::phoneKey((string) $u->phone) === $key && (int) $u->status !== 1)
            ->sortBy('id')->values();
    }

    /** The last 9 digits of a Kenyan mobile number (712345678), or null. */
    public static function phoneKey(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }
}
