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
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'device_name' => 'required|string|max:60']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password) || (int) $user->status === 1) {
            return response()->json(['error' => 'invalid_credentials', 'message' => 'Wrong email or password.'], 401);
        }

        return $this->signIn($request, $user, $data['device_name'], 'password');
    }

    public function me(Request $request)
    {
        return response()->json($this->profile($request->user(), $request->attributes->get('mobile_session')));
    }

    /** Create or change the app PIN (4 digits). Changing it needs the old PIN. */
    public function setPin(Request $request)
    {
        $session = $request->attributes->get('mobile_session');
        $data = $request->validate(['pin' => 'required|digits:4', 'current_pin' => 'nullable|digits:4']);
        if ($session->pin_hash && ! Hash::check((string) ($data['current_pin'] ?? ''), $session->pin_hash)) {
            return response()->json(['error' => 'wrong_pin', 'message' => 'Your current PIN is wrong.'], 422);
        }
        if (in_array($data['pin'], ['0000', '1111', '1234', '4321', '2222', '9999'], true)) {
            return response()->json(['error' => 'weak_pin', 'message' => 'Pick a PIN that is harder to guess.'], 422);
        }
        $session->forceFill(['pin_hash' => Hash::make($data['pin']), 'pin_failures' => 0])->save();

        return response()->json(['pin_set' => true]);
    }

    /** Opening the app with the PIN (fingerprint / phone lock is checked on the phone). Five wrong PINs sign the phone out. */
    public function unlock(Request $request)
    {
        $session = $request->attributes->get('mobile_session');
        $data = $request->validate(['pin' => 'required|digits:4']);
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
