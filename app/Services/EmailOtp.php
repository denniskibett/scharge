<?php

namespace App\Services;

use App\Mail\EmailOtpCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Email one-time codes (KYC): proves each person owns the email on their account.
 * The code is kept hashed in the cache for a few minutes; a confirmed email is stamped on users.email_confirmed_at
 * (database/schema/2026_11_09_email_otp.sql). Settings: config/kyc.php.
 */
class EmailOtp
{
    /** On when switched on, the column exists and mail really goes out (not "log"/"array"). */
    public static function active(): bool
    {
        static $hasColumn;
        $hasColumn ??= Schema::hasColumn('users', 'email_confirmed_at');

        return (bool) config('kyc.email_otp.enabled') && $hasColumn && self::mailWorks();
    }

    public static function mailWorks(): bool
    {
        Integrations::applyToConfig(); // Integrations › Email (SMTP) overrides .env when switched on

        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }

    public static function needs($user): bool
    {
        return $user && filled($user->email) && empty($user->email_confirmed_at) && self::active();
    }

    private static function key($user): string
    {
        return 'email-otp:'.$user->id;
    }

    /** Seconds until another code may be sent (0 = now). */
    public static function wait($user): int
    {
        $s = Cache::get(self::key($user));

        return $s ? max(0, (int) config('kyc.email_otp.resend_seconds') - (time() - $s['sent_at'])) : 0;
    }

    /** @return array{ok: bool, message: string} */
    public static function send($user, bool $force = false): array
    {
        if (! $force && self::wait($user) > 0) {
            return ['ok' => false, 'message' => 'A code was just sent. You can ask for another in '.self::wait($user).' seconds.'];
        }
        $digits = (int) config('kyc.email_otp.digits', 6);
        $code = str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
        $minutes = (int) config('kyc.email_otp.expires_minutes', 10);

        try {
            Mail::to($user->email)->send(new EmailOtpCode($user, $code, $minutes));
        } catch (\Throwable $e) {
            Log::error('Email code could not be sent', ['user' => $user->id, 'error' => $e->getMessage()]);
            KycLog::record('otp.send_failed', $user);

            return ['ok' => false, 'message' => 'We could not send the email just now. Try again in a minute.'];
        }

        KycLog::record('otp.sent', $user, ['forced' => $force ?: null]);
        Cache::put(self::key($user), ['hash' => Hash::make($code), 'email' => $user->email, 'expires' => time() + $minutes * 60, 'attempts' => 0, 'sent_at' => time()], $minutes * 60);

        return ['ok' => true, 'message' => "We sent a {$digits}-digit code to {$user->email}. It works for {$minutes} minutes."];
    }

    /** @return array{ok: bool, message: string} */
    public static function verify($user, string $code): array
    {
        $guard = KycLog::verdict($user);
        if (! $guard['allow']) {
            KycLog::record('otp.blocked', $user, ['reasons' => $guard['reasons']]);

            return ['ok' => false, 'message' => 'Too many wrong codes. For your safety, wait an hour and try again, or contact support.'];
        }
        $s = Cache::get(self::key($user));
        if (! $s || $s['expires'] < time() || $s['email'] !== $user->email) {
            KycLog::record('otp.expired', $user);

            return ['ok' => false, 'message' => 'That code has expired. Send a new one.'];
        }
        if ($s['attempts'] >= (int) config('kyc.email_otp.max_attempts', 5)) {
            Cache::forget(self::key($user));
            KycLog::record('otp.after_lock', $user);

            return ['ok' => false, 'message' => 'Too many wrong tries. Send a new code.'];
        }
        if (! Hash::check(preg_replace('/\D/', '', $code), $s['hash'])) {
            $s['attempts']++;
            Cache::put(self::key($user), $s, max(1, $s['expires'] - time()));
            $left = (int) config('kyc.email_otp.max_attempts', 5) - $s['attempts'];
            KycLog::record($left > 0 ? 'otp.wrong' : 'otp.locked', $user, ['attempt' => $s['attempts'], 'seconds_since_sent' => time() - $s['sent_at']]);

            return ['ok' => false, 'message' => "That code is not right. {$left} ".($left === 1 ? 'try' : 'tries').' left.'];
        }

        Cache::forget(self::key($user));
        $user->forceFill(['email_confirmed_at' => now(), 'email_verified_at' => $user->email_verified_at ?? now()])->save(); // the code also verifies the email
        KycLog::record('otp.ok', $user, ['attempt' => $s['attempts'] + 1, 'seconds_since_sent' => time() - $s['sent_at']]);

        return ['ok' => true, 'message' => 'Email confirmed. Thank you.'];
    }
}
