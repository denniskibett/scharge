<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;
use App\Services\Integrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Equity Bank through Jenga API v3 (Integrations › Equity Bank). Signs in with the merchant code + consumer secret,
 * reads the balance and the mini statement (signed with the company's RSA private key), and feeds money-in lines to
 * the same matching as an uploaded statement. `integrations:retry` pulls each connected company every morning.
 */
class Jenga
{
    public static function test(CompanyIntegration $row): array
    {
        $token = self::token($row);
        if (! $token) {
            return [false, 'Jenga rejected the merchant code, API key or consumer secret.'];
        }
        if (blank($row->get('private_key'))) {
            return [true, 'Jenga accepted the keys. Add your private key so scharge can read deposits.'];
        }
        $res = self::balance($row, $token);
        if (! $res->successful()) {
            return [false, 'Signed in, but Jenga refused the balance (HTTP '.$res->status().'): '.mb_substr((string) ($res->json('message') ?? ''), 0, 120).' Check the account number and that the private key matches the public key on JengaHQ.'];
        }
        $available = collect($res->json('data.balances') ?? [])->firstWhere('type', 'Available')['amount'] ?? null;

        return [true, 'Connected to Equity account '.$row->get('account_number').($available !== null ? '. Available: KES '.number_format((float) $available, 2) : '').'. Deposits are read every morning.'];
    }

    /** Pull the latest deposits into bank_statement_lines (returns lines added). */
    public static function pull(CompanyIntegration $row): int
    {
        if (blank($row->get('private_key')) || ! Hub::installed('bank_statement_lines') || ! ($token = self::token($row))) {
            return 0;
        }
        $country = 'KE';
        $account = (string) $row->get('account_number');
        $res = Http::withToken($token)->acceptJson()->timeout(30)
            ->withHeaders(['signature' => self::sign($row, $country.$account)])
            ->get(self::base($row).'/v3-apis/account-api/v3.0/accounts/miniStatement/'.$country.'/'.$account);
        if (! $res->successful()) {
            return 0;
        }
        $lines = collect($res->json('data.transactions') ?? [])
            ->filter(fn ($t) => strtolower((string) ($t['type'] ?? '')) === 'credit' && (float) ($t['amount'] ?? 0) > 0)
            ->map(fn ($t) => ['posted_on' => substr((string) ($t['date'] ?? now()->toDateString()), 0, 10), 'amount' => round((float) $t['amount'], 2),
                'description' => mb_substr((string) ($t['description'] ?? ''), 0, 500) ?: null, 'reference' => mb_substr((string) ($t['reference'] ?? ''), 0, 120) ?: null])
            ->values()->all();

        return BankStatements::import((int) $row->company_id, $lines, 'jenga', 'equity', null)['added'];
    }

    public static function pullAll(): int
    {
        if (! \App\Services\Integrations::installed()) {
            return 0;
        }

        return CompanyIntegration::where('provider', 'jenga')->where('enabled', true)->whereNotNull('company_id')->get()
            ->sum(fn ($row) => rescue(fn () => self::pull($row), 0));
    }

    private static function token(CompanyIntegration $row): ?string
    {
        $res = Http::withHeaders(['Api-Key' => (string) $row->get('api_key')])->acceptJson()->asJson()->timeout(20)
            ->post(self::base($row).'/authentication/api/v3/authenticate/merchant', [
                'merchantCode' => (string) $row->get('merchant_code'), 'consumerSecret' => (string) $row->get('consumer_secret'),
            ]);

        return $res->successful() ? ($res->json('accessToken') ?: null) : null;
    }

    private static function balance(CompanyIntegration $row, string $token)
    {
        $account = (string) $row->get('account_number');

        return Http::withToken($token)->acceptJson()->timeout(20)->withHeaders(['signature' => self::sign($row, 'KE'.$account)])
            ->get(self::base($row).'/v3-apis/account-api/v3.0/accounts/balances/KE/'.$account);
    }

    private static function sign(CompanyIntegration $row, string $data): string
    {
        $key = openssl_pkey_get_private((string) $row->get('private_key'));
        if (! $key || ! openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256)) {
            return '';
        }

        return base64_encode($sig);
    }

    private static function base(CompanyIntegration $row): string
    {
        return $row->get('environment') === 'production' ? 'https://api.finserve.africa' : 'https://uat.finserve.africa';
    }
}
