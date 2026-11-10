<?php

namespace App\Services\Rent;

use App\Models\CompanyIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Tells Safaricom where to send payments made straight to a company's paybill (C2B register URL, v2). Run from
 * Integrations › M-Pesa Daraja › Test, and `php artisan mpesa:register-paybill`. Needs a public https APP_URL.
 * The Daraja app must have the "M-Pesa Sandbox"/"C2B" product enabled on developer.safaricom.co.ke.
 */
class PaybillRegistration
{
    /** Secret part of the callback URLs (stable for the life of APP_KEY). */
    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'mpesa-c2b', (string) config('app.key')), 0, 32);
    }

    public static function urls(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'confirmation' => $base.'/webhooks/mpesa/c2b/'.self::token().'/confirmation',
            'validation' => $base.'/webhooks/mpesa/c2b/'.self::token().'/validation',
        ];
    }

    /** @return array{0: bool, 1: string} */
    public static function register(CompanyIntegration $row, string $accessToken): array
    {
        if ($row->get('kind', 'paybill') !== 'paybill') {
            return [true, 'Till numbers do not send direct payments to scharge; only paybills do.'];
        }
        $urls = self::urls();
        if (! str_starts_with($urls['confirmation'], 'https://')) {
            return [false, 'Paybill payments need the site on https (APP_URL is '.config('app.url').').'];
        }
        $base = $row->get('environment') === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
        $res = Http::withToken($accessToken)->acceptJson()->timeout(20)->post($base.'/mpesa/c2b/v2/registerurl', [
            'ShortCode' => (string) $row->get('shortcode'),
            'ResponseType' => 'Completed', // if scharge is down, Safaricom completes the payment anyway
            'ConfirmationURL' => $urls['confirmation'],
            'ValidationURL' => $urls['validation'],
        ]);
        \Illuminate\Support\Facades\Cache::forget('paybill-shortcodes');

        if ($res->successful() && (string) $res->json('ResponseCode') === '0') {
            return [true, 'Payments made straight to paybill '.$row->get('shortcode').' will now reach scharge.'];
        }
        $why = (string) ($res->json('errorMessage') ?? $res->json('ResponseDescription') ?? 'HTTP '.$res->status());
        if (str_contains(strtolower($why), 'already registered')) {
            return [true, 'Paybill '.$row->get('shortcode').' was already registered with scharge\'s address.'];
        }

        return [false, 'Safaricom did not register the paybill address: '.$why];
    }
}
