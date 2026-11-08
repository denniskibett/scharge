<?php

namespace App\Services\Sharet;

use App\Models\CompanyIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Sharet Africa API v1 (https://sharet.africa/docs).
 * Auth: "Authorization: Bearer sk_…". Create calls send an Idempotency-Key.
 * asAccount('SH-…') acts for a landlord's connected Sharet account.
 */
class SharetClient
{
    public const WEBHOOK_EVENTS = [
        'payment_request.paid', 'payment_request.expired',
        'payment.paid', 'payment.failed', 'payment.refunded',
        'settlement.completed', 'settlement.failed',
        'account.connected', 'account.disconnected',
    ];

    public string $environment;

    private ?string $account = null;

    public function __construct(private string $apiKey, private string $baseUrl, ?string $environment = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->environment = $environment ?? (str_starts_with($apiKey, 'sk_live_') ? 'live' : 'test');
    }

    public static function fromIntegration(CompanyIntegration $row): self
    {
        return new self(
            (string) $row->get('api_key'),
            (string) $row->get('base_url', config('sharet.base_url')),
            str_starts_with((string) $row->get('api_key'), 'sk_live_') ? 'live' : 'test',
        );
    }

    /** Act for a connected account (Sharet-Account header). */
    public function asAccount(?string $sharetId): self
    {
        $clone = clone $this;
        $clone->account = $sharetId ?: null;

        return $clone;
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->http()->get($this->baseUrl.$path, $query);
    }

    public function post(string $path, array $body = [], ?string $idempotencyKey = null): Response
    {
        $http = $this->http();
        if ($idempotencyKey) {
            $http = $http->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        return $http->post($this->baseUrl.$path, $body);
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->when($this->account, fn ($h) => $h->withHeaders(['Sharet-Account' => $this->account]));
    }

    /**
     * Verify a webhook: header "Sharet-Signature: t=<unix>,v1=<hex>" where
     * v1 = HMAC-SHA256(secret, "<t>.<raw body>"), within $tolerance seconds.
     */
    public static function verifySignature(?string $header, string $payload, ?string $secret, int $tolerance = 300): bool
    {
        if (! $header || ! $secret) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, null);
            if ($k !== null && $v !== null) {
                $parts[$k][] = $v;
            }
        }
        $t = (int) ($parts['t'][0] ?? 0);
        if (! $t || abs(time() - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
