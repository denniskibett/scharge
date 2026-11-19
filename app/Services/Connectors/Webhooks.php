<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;
use App\Services\Integrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Webhooks out (Integrations › Webhooks): a signed JSON POST to the company's URL when something happens.
 *
 *   Headers: X-Scharge-Event, X-Scharge-Event-Id (dedupe on it), X-Scharge-Signature: t=<unix>,v1=<hex>
 *   v1 = HMAC-SHA256(signing secret, "<t>.<raw body>"). Reject if t is more than 5 minutes old.
 *
 * Every delivery is stored (webhook_deliveries); a failed one is retried after 1, 5, 30 minutes, 2 and 12 hours.
 */
class Webhooks
{
    public const EVENTS = ['payment.received', 'invoice.issued', 'invoice.paid', 'tenancy.started', 'tenancy.ended', 'booking.created'];

    private const BACKOFF_MINUTES = [1, 5, 30, 120, 720];

    public static function fire(int $companyId, string $event, array $data): void
    {
        $row = Integrations::for($companyId, 'webhooks');
        if (! $row || blank($row->get('url')) || ! self::wants($row, $event) || ! Hub::installed('webhook_deliveries')) {
            return;
        }
        $eventId = 'evt_'.Str::lower(Str::random(24));
        $id = DB::table('webhook_deliveries')->insertGetId([
            'company_id' => $companyId, 'event' => $event, 'event_id' => $eventId, 'status' => 'pending',
            'payload' => json_encode(self::envelope($eventId, $event, $data)), 'next_attempt_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        self::deliver($id, $row);
    }

    /** Send one stored delivery. Returns [ok, http code]. */
    public static function deliver(int $id, ?CompanyIntegration $row = null): array
    {
        $d = DB::table('webhook_deliveries')->find($id);
        if (! $d || $d->status === 'sent') {
            return [true, null];
        }
        $row ??= Integrations::for((int) $d->company_id, 'webhooks');
        if (! $row || blank($row->get('url'))) {
            DB::table('webhook_deliveries')->where('id', $id)->update(['status' => 'failed', 'response_body' => 'Webhooks were turned off or removed.', 'updated_at' => now()]);

            return [false, null];
        }

        [$ok, $code, $body] = self::post($row, $d->event, $d->event_id, $d->payload);
        $attempts = $d->attempts + 1;
        DB::table('webhook_deliveries')->where('id', $id)->update([
            'attempts' => $attempts, 'response_code' => $code, 'response_body' => mb_substr($body, 0, 250),
            'status' => $ok ? 'sent' : ($attempts > count(self::BACKOFF_MINUTES) ? 'failed' : 'pending'),
            'next_attempt_at' => $ok ? null : now()->addMinutes(self::BACKOFF_MINUTES[$attempts - 1] ?? 0),
            'sent_at' => $ok ? now() : null, 'updated_at' => now(),
        ]);

        return [$ok, $code];
    }

    /** Deliveries whose retry time has come (run by `integrations:retry`). */
    public static function retryDue(): int
    {
        if (! Hub::installed('webhook_deliveries')) {
            return 0;
        }
        $ids = DB::table('webhook_deliveries')->where('status', 'pending')->where('attempts', '>', 0)
            ->where('next_attempt_at', '<=', now())->orderBy('id')->limit(200)->pluck('id');
        $ids->each(fn ($id) => self::deliver((int) $id));

        return $ids->count();
    }

    /** Test button: a signed "ping" to the URL, not stored. */
    public static function test(CompanyIntegration $row): array
    {
        $url = (string) $row->get('url');
        if (! self::safeUrl($url)) {
            return [false, 'The URL must be a public https:// address.'];
        }
        $eventId = 'evt_test_'.Str::lower(Str::random(16));
        [$ok, $code, $body] = self::post($row, 'ping', $eventId, json_encode(self::envelope($eventId, 'ping', ['message' => 'Test from scharge. Check the signature, then answer 2xx.'])));

        if ($ok) {
            return [true, "Your endpoint answered HTTP {$code}. Events: ".implode(', ', self::chosen($row)).'.'];
        }

        return [false, $code ? "Your endpoint answered HTTP {$code}".($body ? ': '.mb_substr($body, 0, 120) : '.').' It must answer 2xx.' : 'Could not reach the URL: '.mb_substr($body, 0, 150)];
    }

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** @return array{0: bool, 1: ?int, 2: string} */
    private static function post(CompanyIntegration $row, string $event, string $eventId, string $body): array
    {
        $url = (string) $row->get('url');
        if (! self::safeUrl($url)) {
            return [false, null, 'URL is not a public https address.'];
        }
        $t = time();
        try {
            $res = Http::withHeaders([
                'Content-Type' => 'application/json', 'User-Agent' => 'scharge-webhooks/1.0',
                'X-Scharge-Event' => $event, 'X-Scharge-Event-Id' => $eventId,
                'X-Scharge-Signature' => self::sign((string) $row->get('secret'), $t, $body),
            ])->withBody($body, 'application/json')->timeout(10)->withoutRedirecting()->post($url);

            return [$res->successful(), $res->status(), (string) $res->body()];
        } catch (\Throwable $e) {
            return [false, null, $e->getMessage()];
        }
    }

    private static function envelope(string $eventId, string $event, array $data): array
    {
        return ['id' => $eventId, 'event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data];
    }

    private static function wants(CompanyIntegration $row, string $event): bool
    {
        return in_array($event, self::chosen($row), true);
    }

    /** @return string[] the events this company chose (blank = all) */
    public static function chosen(CompanyIntegration $row): array
    {
        $list = array_values(array_intersect(self::EVENTS, array_map('trim', explode(',', (string) $row->get('events')))));

        return $list ?: self::EVENTS;
    }

    /** https only, and never to this server's own network (no SSRF into localhost / private ranges). */
    public static function safeUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }
        if (app()->runningUnitTests()) {
            return true;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        return $ips && collect($ips)->every(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
    }
}
