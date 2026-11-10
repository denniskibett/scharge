<?php

namespace App\Services\App;

use App\Models\MobileSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push notifications to the scharge app through Firebase Cloud Messaging (HTTP v1), free per message, so bills,
 * reminders and receipts reach app users without paying for SMS (docs/APP_DATA.md). Needs Integrations › scharge app:
 * the Firebase project's service-account JSON. Phones register their push address with POST /api/v1/app/device.
 */
class PushService
{
    public static function enabled(): bool
    {
        $json = self::account();

        return $json !== null && filled($json['project_id'] ?? null) && filled($json['private_key'] ?? null) && filled($json['client_email'] ?? null);
    }

    /** Phones of a person that can get a push: a push address, used in the last 30 days. */
    public static function devices(int $userId)
    {
        return MobileSession::where('user_id', $userId)->whereNotNull('push_token')->where('last_used_at', '>=', now()->subDays(30))->get();
    }

    public static function reachable(int $userId): bool
    {
        return self::enabled() && self::devices($userId)->isNotEmpty();
    }

    /** Send to every phone of a person. Returns how many got it. Dead addresses are removed. */
    public function toUser(int $userId, string $title, string $body, array $data = []): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $sent = 0;
        foreach (self::devices($userId) as $session) {
            $sent += $this->toToken($session, $title, $body, $data) ? 1 : 0;
        }

        return $sent;
    }

    private function toToken(MobileSession $session, string $title, string $body, array $data): bool
    {
        $account = self::account();
        try {
            $res = Http::withToken($this->accessToken($account))->acceptJson()->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/'.$account['project_id'].'/messages:send', ['message' => [
                    'token' => $session->push_token,
                    'notification' => ['title' => mb_substr($title, 0, 100), 'body' => mb_substr($body, 0, 400)],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high'],
                ]]);
        } catch (\Throwable $e) {
            Log::warning('Push failed', ['error' => $e->getMessage()]);

            return false;
        }
        if ($res->successful()) {
            return true;
        }
        if (in_array($res->json('error.status'), ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true) || $res->status() === 404) {
            $session->forceFill(['push_token' => null, 'push_token_at' => null])->saveQuietly(); // the app was removed or the address changed
        }

        return false;
    }

    /** OAuth access token for FCM: a JWT signed with the service account's key, exchanged at Google (cached 50 minutes). */
    private function accessToken(array $account): string
    {
        return Cache::remember('fcm-token:'.md5($account['client_email']), 3000, function () use ($account) {
            $b64 = fn ($v) => rtrim(strtr(base64_encode(is_string($v) ? $v : json_encode($v)), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$b64(['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]);
            openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256);
            $res = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$b64($signature)]);
            if (! $res->successful() || ! $res->json('access_token')) {
                throw new \RuntimeException('Google refused the Firebase service account (HTTP '.$res->status().').');
            }

            return $res->json('access_token');
        });
    }

    private static function account(): ?array
    {
        $raw = AppSettings::row()?->get('firebase_service_account');
        $json = $raw ? json_decode($raw, true) : null;

        return is_array($json) ? $json : null;
    }

    /** Integrations › Test. @return array{0: bool, 1: string} */
    public static function test(): array
    {
        if (! self::enabled()) {
            return [true, 'Saved. Paste the Firebase service-account JSON to send push notifications.'];
        }
        try {
            (new self)->accessToken(self::account());

            return [true, 'Firebase accepted the service account. App users will get bills and reminders as push notifications.'];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }
}
