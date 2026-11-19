<?php

namespace App\Services\Sharet;

use App\Models\BillingPayment;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send the M-Pesa STK prompt for a Sharet payment request from scharge, so the payer never leaves scharge.
 *
 * Sharet's API has no "send prompt" call yet; its hosted pay page (/r/{token}) has a public form that does it for anyone
 * with the link ("M-PESA works for anyone with the link"). This submits that same form server-side: GET the page for its
 * session and CSRF token, then POST the phone to /r/{token}/mpesa. Sharet's own validation message comes back from the
 * page's phone-error. Replace with an API call once Sharet has one (POST /payment-requests/{id}/mpesa).
 */
class HostedPay
{
    /** Kenyan mobile number to 07XXXXXXXX / 01XXXXXXXX, or null. */
    public static function phone(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (preg_match('/^(?:254|0)?([71]\d{8})$/', $d, $m)) {
            return '0'.$m[1];
        }

        return null;
    }

    /** @return array{ok: bool, message: string} */
    public function mpesa(BillingPayment $row, string $phone): array
    {
        if ($row->status !== 'open' || ! $row->pay_url || ! preg_match('#^https://[^/]+/r/[A-Za-z0-9]{40}$#', rtrim($row->pay_url, '/'))) {
            return ['ok' => false, 'message' => 'This payment link is no longer open. Start a new payment.'];
        }
        $url = rtrim($row->pay_url, '/');
        $jar = new CookieJar;
        $http = fn () => Http::withOptions(['cookies' => $jar])->timeout(25)->withHeaders(['User-Agent' => 'scharge-billing/1.0', 'Accept' => 'text/html']);

        try {
            $page = $http()->get($url);
            if (! $page->successful() || ! preg_match('/name="_token"\s+value="([^"]+)"/', $page->body(), $m)) {
                Log::warning('Sharet pay page unreadable for STK', ['billing_payment_id' => $row->id, 'status' => $page->status()]);

                return ['ok' => false, 'message' => 'Sharet could not be reached. Try again in a minute.'];
            }
            $res = $http()->withHeaders(['Referer' => $url, 'Origin' => parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST)])
                ->asForm()->post($url.'/mpesa', ['_token' => $m[1], 'phone' => $phone]);
        } catch (\Throwable $e) {
            Log::warning('Sharet STK request failed', ['billing_payment_id' => $row->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Sharet could not be reached. Try again in a minute.'];
        }

        if ($res->status() === 429) {
            return ['ok' => false, 'message' => 'Too many prompts just now. Wait a minute and try again.'];
        }
        if (! $res->successful()) {
            Log::warning('Sharet STK form answered '.$res->status(), ['billing_payment_id' => $row->id]);

            return ['ok' => false, 'message' => 'Sharet could not send the prompt. Try again in a minute.'];
        }
        if (preg_match('/id="phone-error"[^>]*>(.*?)<\/p>/s', $res->body(), $err)) {
            return ['ok' => false, 'message' => trim(html_entity_decode(strip_tags($err[1]), ENT_QUOTES))];
        }

        return ['ok' => true, 'message' => 'Prompt sent to '.$phone.'. Enter your M-Pesa PIN on the phone to pay.'];
    }
}
