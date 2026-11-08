<?php

namespace App\Services;

use App\Models\CompanyIntegration;
use App\Services\Sharet\SharetClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Reads provider settings saved on the Integrations page, and tests them. */
class Integrations
{
    /** Enabled settings for a company (or the platform when $companyId is null). */
    public static function for(?int $companyId, string $provider): ?CompanyIntegration
    {
        if (! self::installed()) {
            return null;
        }

        return CompanyIntegration::where('provider', $provider)->where('enabled', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
            ->latest('id')->first();
    }

    /** scharge's own Sharet account, if a key is saved and switched on. */
    public static function sharet(): ?CompanyIntegration
    {
        $row = self::for(null, 'sharet');

        return $row && filled($row->get('api_key')) ? $row : null;
    }

    /** A company's own Daraja app, if switched on and complete. */
    public static function daraja(?int $companyId): ?CompanyIntegration
    {
        if (! $companyId) {
            return null;
        }
        $row = self::for($companyId, 'mpesa_daraja');

        return $row && filled($row->get('consumer_key')) && filled($row->get('consumer_secret')) && filled($row->get('passkey')) ? $row : null;
    }

    public static function installed(): bool
    {
        static $installed;

        return $installed ??= Schema::hasTable('company_integrations');
    }

    /** @return array{0: bool, 1: string} */
    public static function test(CompanyIntegration $row): array
    {
        [$ok, $message] = match ($row->provider) {
            'sharet' => self::testSharet($row),
            'mpesa_daraja' => self::testDaraja($row),
            'sharet_payouts' => self::testPayouts($row),
            default => [false, 'No test available for this provider.'],
        };

        $row->forceFill(['last_tested_at' => now(), 'last_test_ok' => $ok, 'last_test_message' => mb_substr($message, 0, 250)])->save();

        return [$ok, $message];
    }

    private static function testSharet(CompanyIntegration $row): array
    {
        $client = SharetClient::fromIntegration($row);
        $me = $client->get('/accounts/me');
        if (! $me->successful()) {
            return [false, 'Sharet rejected the key: '.($me->json('detail') ?? $me->json('message') ?? $me->status())];
        }

        // Register our webhook once and keep its signing secret.
        if (blank($row->get('webhook_secret'))) {
            $hook = $client->post('/webhooks', ['url' => route('webhooks.sharet'), 'events' => SharetClient::WEBHOOK_EVENTS]);
            if ($hook->successful() && $hook->json('secret')) {
                $row->mergeCredentials(['webhook_secret' => $hook->json('secret')]);
                $row->save();
            }
        }

        $name = $me->json('name') ?? $me->json('business.name') ?? 'your account';

        return [true, "Connected to Sharet as {$name} ({$me->json('sharet_id')}), ".($client->environment === 'live' ? 'live' : 'sandbox').'.'
            .(filled($row->get('webhook_secret')) ? ' Webhook registered.' : ' Webhook not registered yet (needs a public HTTPS URL).')];
    }

    private static function testDaraja(CompanyIntegration $row): array
    {
        $base = $row->get('environment') === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
        $res = Http::withBasicAuth((string) $row->get('consumer_key'), (string) $row->get('consumer_secret'))
            ->timeout(20)->get($base.'/oauth/v1/generate?grant_type=client_credentials');

        return $res->successful() && $res->json('access_token')
            ? [true, 'Safaricom accepted the consumer key and secret.']
            : [false, 'Safaricom rejected the consumer key/secret (HTTP '.$res->status().').'];
    }

    private static function testPayouts(CompanyIntegration $row): array
    {
        if ($row->get('mode') === 'connected') {
            if (blank($row->get('sharet_account'))) {
                return [false, 'Connect your Sharet account first.'];
            }
            $platform = self::sharet();
            if (! $platform) {
                return [false, 'scharge has not connected its Sharet account yet.'];
            }
            $res = SharetClient::fromIntegration($platform)->asAccount($row->get('sharet_account'))->get('/accounts/me');

            return $res->successful()
                ? [true, 'Connected Sharet account '.$row->get('sharet_account').' is active.']
                : [false, 'Sharet says the connection is not active: '.($res->json('detail') ?? $res->status())];
        }

        $destination = \App\Services\Sharet\SharetPayouts::destination($row);

        return $destination
            ? [true, 'Payout destination looks complete. Sweeps run at 08:00, 12:00 and 16:00.']
            : [false, 'Fill in the details for where you want to be paid.'];
    }
}
