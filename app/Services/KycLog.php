<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * KYC attempt log: every email-code and sign-up attempt is written as one JSON object per line to
 * storage/logs/kyc/attempts-YYYY-MM-DD.jsonl (JSON Lines: each line is valid JSON, the file can only grow).
 * Codes are never written, right or wrong.
 *
 * Alongside the file, short-lived counters in the cache drive verdict(): whether this network / account is
 * guessing codes or flooding sign-ups and should be refused. Thresholds: config/kyc.php › guard.
 * Read the log with `php artisan kyc:report`.
 */
class KycLog
{
    /** Events that count as a failed guess. */
    private const WRONG = ['otp.wrong', 'otp.locked'];

    public static function path(?string $date = null): string
    {
        return rtrim(config('kyc.guard.log_dir') ?: storage_path('logs/kyc'), '/').'/attempts-'.($date ?? now()->format('Y-m-d')).'.jsonl';
    }

    /** Write one event. Never throws: logging must not break sign-in. */
    public static function record(string $event, $user = null, array $extra = []): void
    {
        try {
            $request = request();
            $ip = $request?->ip();
            $email = strtolower((string) ($extra['email'] ?? $user?->email ?? ''));
            unset($extra['email'], $extra['code']);

            $row = array_filter([
                'at' => now()->toIso8601String(),
                'event' => $event,
                'ip' => $ip,
                'user_id' => $user?->id,
                'email' => $email ?: null,
                'ua' => $request ? mb_substr((string) $request->userAgent(), 0, 200) : null,
                'session' => $request?->hasSession() ? substr(hash('sha256', $request->session()->getId()), 0, 12) : null,
                'path' => $request?->path(),
            ] + $extra, fn ($v) => $v !== null && $v !== '');

            File::ensureDirectoryExists(dirname(self::path()));
            File::append(self::path(), json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

            self::count($event, $ip, $user?->id, $email);
        } catch (\Throwable $e) {
            Log::warning('KYC log write failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Should this person be let through (sign up, or try another code)?
     *
     * @return array{allow: bool, reasons: list<string>}
     */
    public static function verdict($user = null, ?string $email = null): array
    {
        $g = config('kyc.guard');
        if (! ($g['enabled'] ?? true)) {
            return ['allow' => true, 'reasons' => []];
        }
        $ip = request()?->ip();
        if ($ip && in_array($ip, $g['trusted_ips'] ?? [], true)) {
            return ['allow' => true, 'reasons' => []];
        }
        $email = strtolower((string) ($email ?? $user?->email ?? ''));
        $reasons = [];

        if ($ip) {
            if (($n = self::hits("ip:{$ip}:wrong", 3600)) >= $g['ip_wrong_per_hour']) {
                $reasons[] = "{$n} wrong codes from this network in the last hour";
            }
            if (($n = count(self::accounts($ip))) >= $g['ip_accounts_per_hour']) {
                $reasons[] = "wrong codes for {$n} different accounts from this network in the last hour";
            }
            if (($n = self::hits("ip:{$ip}:locked", 86400)) >= $g['ip_lockouts_per_day']) {
                $reasons[] = "{$n} code lock-outs from this network today";
            }
            if (($n = self::hits("ip:{$ip}:signup", 3600)) >= $g['ip_signups_per_hour']) {
                $reasons[] = "{$n} sign-ups from this network in the last hour";
            }
        }
        if ($user && ($n = self::hits("user:{$user->id}:wrong", 86400)) >= $g['account_wrong_per_day']) {
            $reasons[] = "{$n} wrong codes on this account today";
        }
        if ($email && ($n = self::hits('email:'.sha1($email).':wrong', 86400)) >= $g['account_wrong_per_day']) {
            $reasons[] = "{$n} wrong codes for this email today";
        }

        return ['allow' => $reasons === [], 'reasons' => array_values(array_unique($reasons))];
    }

    /** Read the log back (newest last) for the last $hours. */
    public static function read(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $rows = [];
        for ($d = $since->copy()->startOfDay(); $d->lte(now()); $d->addDay()) {
            $file = self::path($d->format('Y-m-d'));
            if (! is_file($file)) {
                continue;
            }
            foreach (new \SplFileObject($file) as $line) {
                $row = json_decode(trim((string) $line), true);
                if (is_array($row) && isset($row['at']) && $row['at'] >= $since->toIso8601String()) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    // ---- counters ----------------------------------------------------------------------------------------

    private static function count(string $event, ?string $ip, $userId, string $email): void
    {
        $wrong = in_array($event, self::WRONG, true);
        if ($ip && $wrong) {
            self::hit("ip:{$ip}:wrong", 3600);
            if ($userId || $email) {
                $acc = self::accounts($ip);
                $acc[(string) ($userId ?: sha1($email))] = time();
                Cache::put("kyc:ip:{$ip}:accounts", $acc, 3600);
            }
        }
        if ($ip && $event === 'otp.locked') {
            self::hit("ip:{$ip}:locked", 86400);
        }
        if ($ip && $event === 'signup.ok') {
            self::hit("ip:{$ip}:signup", 3600);
        }
        if ($wrong && $userId) {
            self::hit("user:{$userId}:wrong", 86400);
        }
        if ($wrong && $email) {
            self::hit('email:'.sha1($email).':wrong', 86400);
        }
    }

    /** Accounts that got wrong codes from this IP within the hour. */
    private static function accounts(string $ip): array
    {
        return array_filter((array) Cache::get("kyc:ip:{$ip}:accounts", []), fn ($t) => $t > time() - 3600);
    }

    private static function hit(string $key, int $window): void
    {
        $times = self::times($key, $window);
        $times[] = time();
        Cache::put("kyc:{$key}", array_slice($times, -500), $window);
    }

    private static function hits(string $key, int $window): int
    {
        return count(self::times($key, $window));
    }

    private static function times(string $key, int $window): array
    {
        return array_values(array_filter((array) Cache::get("kyc:{$key}", []), fn ($t) => $t > time() - $window));
    }
}
