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

        // Read on most requests (mail, SMS, M-Pesa): cache briefly; forget() runs on every change.
        $id = \Illuminate\Support\Facades\Cache::remember(self::cacheKey($companyId, $provider), 60, fn () => CompanyIntegration::where('provider', $provider)->where('enabled', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))
            ->latest('id')->value('id') ?? 0);

        return $id ? CompanyIntegration::find($id) : null;
    }

    public static function forget(?int $companyId, string $provider): void
    {
        \Illuminate\Support\Facades\Cache::forget(self::cacheKey($companyId, $provider));
    }

    private static function cacheKey(?int $companyId, string $provider): string
    {
        return 'integration:'.($companyId ?? 'platform').':'.$provider;
    }

    /** scharge's own Daraja app from Integrations, if switched on and complete. */
    public static function platformMpesa(): ?CompanyIntegration
    {
        $row = self::for(null, 'mpesa');

        return $row && filled($row->get('consumer_key')) && filled($row->get('consumer_secret')) && filled($row->get('passkey')) ? $row : null;
    }

    /** Apply saved SMTP / KenyaSMS settings over .env for this request (called from AppServiceProvider). */
    public static function applyToConfig(): void
    {
        if (! self::installed()) {
            return;
        }
        if ($smtp = self::for(null, 'smtp')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $smtp->get('host'),
                'mail.mailers.smtp.port' => (int) $smtp->get('port', 587),
                'mail.mailers.smtp.scheme' => $smtp->get('encryption') === 'ssl' ? 'smtps' : null,
                'mail.mailers.smtp.username' => $smtp->get('username'),
                'mail.mailers.smtp.password' => $smtp->get('password'),
                'mail.from.address' => $smtp->get('from_address'),
                'mail.from.name' => $smtp->get('from_name', 'scharge'),
            ]);
        }
        if (($sms = self::for(null, 'kenyasms')) && filled($sms->get('api_key'))) {
            config([
                'sms.kenyasms.api_key' => $sms->get('api_key'),
                'sms.kenyasms.sender_id' => $sms->get('sender_id', config('sms.kenyasms.sender_id')),
                'sms.kenyasms.base_url' => $sms->get('base_url', config('sms.kenyasms.base_url')),
                'sms.kenyasms.sandbox' => $sms->get('mode', 'sandbox') !== 'live',
                'sms.sandbox' => $sms->get('mode', 'sandbox') !== 'live',
                // app/Services/SmsService reads these:
                'sms.api_key' => $sms->get('api_key'),
                'sms.sender_id' => $sms->get('sender_id', config('sms.sender_id')),
                'sms.base_url' => $sms->get('base_url', config('sms.base_url', config('sms.kenyasms.base_url'))),
            ]);
        }
    }

    /**
     * Which KenyaSMS key and sender to use for a company's messages. A company's own "SMS sender ID" card
     * (when switched on) gives the sender name, and its own API key if it has one; otherwise scharge's.
     * $companyId defaults to the signed-in user's company; null means scharge itself.
     *
     * @return array{api_key: ?string, sender_id: ?string, own_account: bool}
     */
    public static function smsSender(?int $companyId = -1): array
    {
        if ($companyId === -1) {
            $companyId = auth()->user()?->company_id;
        }
        $out = ['api_key' => config('sms.kenyasms.api_key') ?: config('sms.api_key'), 'sender_id' => config('sms.kenyasms.sender_id') ?: config('sms.sender_id'), 'own_account' => false];
        $row = $companyId ? self::for((int) $companyId, 'kenyasms_sender') : null;
        if ($row && filled($row->get('sender_id'))) {
            $out['sender_id'] = $row->get('sender_id');
            if (filled($row->get('api_key'))) {
                $out['api_key'] = $row->get('api_key');
                $out['own_account'] = true;
            }
        }

        return $out;
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
            'mpesa_daraja', 'mpesa' => self::testDaraja($row),
            'airtel_money' => self::testAirtel($row),
            'kenyasms' => self::testKenyaSms($row),
            'kenyasms_sender' => self::testSmsSender($row),
            'whatsapp', 'whatsapp_business' => WhatsApp::test($row),
            'africastalking' => self::testAfricasTalking($row),
            'smtp' => self::testSmtp($row),
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

    private static function testAirtel(CompanyIntegration $row): array
    {
        $base = $row->get('environment') === 'production' ? 'https://openapi.airtel.africa' : 'https://openapiuat.airtel.africa';
        $res = Http::acceptJson()->asJson()->timeout(20)->post($base.'/auth/oauth2/token', [
            'client_id' => $row->get('client_id'), 'client_secret' => $row->get('client_secret'), 'grant_type' => 'client_credentials',
        ]);

        return $res->successful() && $res->json('access_token')
            ? [true, 'Airtel accepted the client ID and secret ('.($row->get('environment') === 'production' ? 'live' : 'staging').').']
            : [false, 'Airtel rejected the credentials (HTTP '.$res->status().').'];
    }

    private static function testSmsSender(CompanyIntegration $row): array
    {
        $sender = (string) $row->get('sender_id');
        if (! preg_match('/^[A-Za-z0-9 ._-]{1,11}$/', $sender)) {
            return [false, 'A sender ID is 1 to 11 letters or numbers ("'.$sender.'" is not).'];
        }
        $own = filled($row->get('api_key'));
        $key = $own ? (string) $row->get('api_key') : (string) (config('sms.kenyasms.api_key') ?: config('sms.api_key'));
        if (blank($key)) {
            return [false, 'scharge\'s KenyaSMS account is not set up yet, so there is nothing to send through. Add your own API key or ask scharge.'];
        }
        $base = rtrim((string) config('sms.kenyasms.base_url', 'https://kenyasms.com/api/v1'), '/');
        $res = Http::withToken($key)->acceptJson()->timeout(20)->get($base.'/campaigns', ['page' => 1, 'limit' => 1]);
        if (! $res->successful()) {
            return [false, ($own ? 'KenyaSMS rejected your API key' : 'scharge\'s KenyaSMS account did not answer').' (HTTP '.$res->status().').'];
        }

        return [true, $own
            ? "Your KenyaSMS account works. Messages to your tenants will come from {$sender}."
            : "Messages to your tenants will come from {$sender} through scharge's KenyaSMS account, once KenyaSMS approves the name there."];
    }

    private static function testKenyaSms(CompanyIntegration $row): array
    {
        $base = rtrim((string) $row->get('base_url', 'https://kenyasms.com/api/v1'), '/');
        $res = Http::withToken((string) $row->get('api_key'))->acceptJson()->timeout(20)->get($base.'/campaigns', ['page' => 1, 'limit' => 1]);

        return $res->successful()
            ? [true, 'KenyaSMS accepted the API key. Sender ID: '.$row->get('sender_id').'; mode: '.$row->get('mode', 'sandbox').'.']
            : [false, 'KenyaSMS rejected the API key (HTTP '.$res->status().').'];
    }

    private static function testAfricasTalking(CompanyIntegration $row): array
    {
        $sandbox = $row->get('username') === 'sandbox';
        $res = Http::withHeaders(['apiKey' => (string) $row->get('api_key')])->acceptJson()->timeout(20)
            ->get(($sandbox ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com').'/version1/user', ['username' => $row->get('username')]);

        return $res->successful()
            ? [true, "Africa's Talking accepted the key. Balance: ".($res->json('UserData.balance') ?? 'n/a').'.']
            : [false, "Africa's Talking rejected the username/key (HTTP ".$res->status().').'];
    }

    private static function testSmtp(CompanyIntegration $row): array
    {
        try {
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                (string) $row->get('host'), (int) $row->get('port', 587), $row->get('encryption') === 'ssl'
            );
            if (filled($row->get('username'))) {
                $transport->setUsername((string) $row->get('username'));
                $transport->setPassword((string) $row->get('password'));
            }
            $transport->start();
            $transport->stop();

            return [true, 'Connected and signed in to '.$row->get('host').'. Emails will come from '.$row->get('from_address').'.'];
        } catch (\Throwable $e) {
            return [false, 'Could not sign in to the mail server: '.mb_substr($e->getMessage(), 0, 160)];
        }
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
