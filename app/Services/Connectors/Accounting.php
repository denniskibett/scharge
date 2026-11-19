<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Services\Integrations;
use App\Support\CompanyAccess;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Accounting sync (Integrations › QuickBooks Online / Xero / Zoho Books). A company clicks Connect (OAuth through
 * scharge's own developer app, set on the platform cards); after that each tenant becomes a customer, each bill an
 * invoice and each payment a payment against it, as they happen. Progress is kept per record in integration_links
 * (also used by KRA eTIMS), and failures are retried by `integrations:retry`.
 */
class Accounting
{
    public const PROVIDERS = ['quickbooks' => 'quickbooks_app', 'xero' => 'xero_app', 'zoho_books' => 'zoho_app'];

    private const BACKOFF_MINUTES = [5, 30, 120, 720, 1440];

    // ── OAuth ───────────────────────────────────────────────────────────────────────────────────────────────

    public static function app(string $provider): ?CompanyIntegration
    {
        $app = Integrations::for(null, self::PROVIDERS[$provider] ?? '');

        return $app && filled($app->get('client_id')) && filled($app->get('client_secret')) ? $app : null;
    }

    public static function redirectUri(string $provider): string
    {
        return route('integrations.oauth.callback', ['provider' => $provider]);
    }

    public static function authorizeUrl(string $provider, string $state): string
    {
        $app = self::app($provider);
        $q = fn (array $p) => http_build_query($p + ['client_id' => $app->get('client_id'), 'redirect_uri' => self::redirectUri($provider), 'response_type' => 'code', 'state' => $state]);

        return match ($provider) {
            'quickbooks' => 'https://appcenter.intuit.com/connect/oauth2?'.$q(['scope' => 'com.intuit.quickbooks.accounting']),
            'xero' => 'https://login.xero.com/identity/connect/authorize?'.$q(['scope' => $app->get('scopes') ?: 'offline_access accounting.transactions accounting.contacts accounting.settings.read']),
            'zoho_books' => 'https://accounts.zoho.'.$app->get('region', 'com').'/oauth/v2/auth?'.$q(['scope' => 'ZohoBooks.fullaccess.all', 'access_type' => 'offline', 'prompt' => 'consent']),
        };
    }

    /** Exchange the code from the callback and save the tokens on the company's card. */
    public static function connect(string $provider, CompanyIntegration $row, string $code, array $query): array
    {
        $app = self::app($provider);
        $form = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirectUri($provider)];
        $tokenUrl = self::tokenUrl($provider, $app, $query['accounts-server'] ?? null);
        $res = $provider === 'zoho_books'
            ? Http::asForm()->acceptJson()->timeout(20)->post($tokenUrl, $form + ['client_id' => $app->get('client_id'), 'client_secret' => $app->get('client_secret')])
            : Http::asForm()->acceptJson()->timeout(20)->withBasicAuth((string) $app->get('client_id'), (string) $app->get('client_secret'))->post($tokenUrl, $form);
        if (! $res->successful() || ! $res->json('access_token')) {
            return [false, 'The connection was refused: '.($res->json('error_description') ?? $res->json('error') ?? 'HTTP '.$res->status())];
        }
        $row->mergeCredentials(self::tokens($res) + ['connected_at' => now()->toIso8601String(), 'accounts_server' => $query['accounts-server'] ?? null]);
        if ($provider === 'quickbooks') {
            $row->mergeCredentials(['realm_id' => $query['realmId'] ?? null]);
        }
        if ($provider === 'xero') {
            $tenant = Http::withToken((string) $row->get('access_token'))->acceptJson()->timeout(20)->get('https://api.xero.com/connections')->json('0') ?? [];
            $row->mergeCredentials(['tenant_id' => $tenant['tenantId'] ?? null, 'connected_name' => $tenant['tenantName'] ?? null]);
        }
        if ($provider === 'zoho_books') {
            $row->mergeCredentials(['api_domain' => $res->json('api_domain') ?: 'https://www.zohoapis.'.$app->get('region', 'com')]);
            $org = self::client($row)->get(self::zoho($row, '/organizations'))->json('organizations.0') ?? [];
            $row->mergeCredentials(['org_id' => $org['organization_id'] ?? null, 'connected_name' => $org['name'] ?? null]);
        }
        $row->enabled = true;
        $row->save();

        return self::test($row);
    }

    public static function test(CompanyIntegration $row): array
    {
        if (blank($row->get('refresh_token'))) {
            return [false, 'Click Connect first, and approve scharge in '.self::label($row->provider).'.'];
        }
        if (! self::app($row->provider)) {
            return [false, 'scharge has not set up its '.self::label($row->provider).' app yet. Ask scharge support.'];
        }
        try {
            $name = match ($row->provider) {
                'quickbooks' => self::qb($row, 'get', '/companyinfo/'.$row->get('realm_id'))->json('CompanyInfo.CompanyName'),
                'xero' => self::client($row)->get('https://api.xero.com/api.xro/2.0/Organisation')->json('Organisations.0.Name'),
                'zoho_books' => self::client($row)->get(self::zoho($row, '/organizations/'.$row->get('org_id')))->json('organization.name'),
            };
        } catch (\Throwable $e) {
            return [false, 'Could not reach '.self::label($row->provider).': '.mb_substr($e->getMessage(), 0, 150)];
        }
        if (! $name) {
            return [false, self::label($row->provider).' did not accept the connection. Click Connect again.'];
        }
        $row->mergeCredentials(['connected_name' => $name]);
        $row->save();
        $from = $row->get('from_date') ?: substr((string) $row->get('connected_at'), 0, 10);

        return [true, "Connected to {$name}. Bills and payments from {$from} are copied as they happen."];
    }

    // ── Queue and push ──────────────────────────────────────────────────────────────────────────────────────

    /** A bill or payment just happened: copy it to each connected accounting system. */
    public static function queue(int $companyId, string $type, int $id): void
    {
        if (! Hub::installed('integration_links')) {
            return;
        }
        foreach (array_keys(self::PROVIDERS) as $provider) {
            $row = Integrations::for($companyId, $provider);
            if (! $row || blank($row->get('refresh_token'))) {
                continue;
            }
            $link = self::link($companyId, $provider, $type, $id);
            if ($link->status !== 'synced') {
                rescue(fn () => self::push($row, $link->id));
            }
        }
    }

    public static function link(int $companyId, string $provider, string $type, int $id): object
    {
        DB::table('integration_links')->insertOrIgnore(['company_id' => $companyId, 'provider' => $provider, 'object_type' => $type, 'object_id' => $id,
            'status' => 'pending', 'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('integration_links')->where(['company_id' => $companyId, 'provider' => $provider, 'object_type' => $type, 'object_id' => $id])->first();
    }

    public static function done(int $linkId, bool $ok, ?string $error = null, ?string $remoteId = null, bool $final = false): void
    {
        $link = DB::table('integration_links')->find($linkId);
        $attempts = ($link->attempts ?? 0) + 1;
        DB::table('integration_links')->where('id', $linkId)->update(array_filter([
            'status' => $ok ? 'synced' : ($final || $attempts > count(self::BACKOFF_MINUTES) ? 'failed' : 'pending'),
            'attempts' => $attempts, 'error' => $ok ? null : mb_substr((string) $error, 0, 500), 'remote_id' => $remoteId,
            'next_attempt_at' => $ok ? null : now()->addMinutes(self::BACKOFF_MINUTES[$attempts - 1] ?? 1440),
            'synced_at' => $ok ? now() : null, 'updated_at' => now(),
        ], fn ($v, $k) => $v !== null || in_array($k, ['error', 'next_attempt_at', 'synced_at'], true), ARRAY_FILTER_USE_BOTH));
    }

    /** Push one record (integration_links row). */
    public static function push(CompanyIntegration $row, int $linkId): bool
    {
        $link = DB::table('integration_links')->find($linkId);
        if (! $link || $link->status === 'synced') {
            return true;
        }
        try {
            $remote = match ($link->object_type) {
                'invoice' => self::pushInvoice($row, (int) $link->object_id),
                'payment' => self::pushPayment($row, (int) $link->object_id),
            };
            self::done($linkId, true, null, $remote);

            return true;
        } catch (\DomainException $e) {
            self::done($linkId, false, $e->getMessage(), final: true);

            return false;
        } catch (\Throwable $e) {
            self::done($linkId, false, $e->getMessage());

            return false;
        }
    }

    /** Pending links whose retry time has come (eTIMS and accounting). */
    public static function retryDue(): int
    {
        if (! Hub::installed('integration_links')) {
            return 0;
        }
        $due = DB::table('integration_links')->where('status', 'pending')->where('attempts', '>', 0)->where('next_attempt_at', '<=', now())
            ->whereIn('object_type', ['invoice', 'payment'])->orderBy('id')->limit(100)->get();
        foreach ($due as $link) {
            $row = Integrations::for((int) $link->company_id, $link->provider);
            if (! $row) {
                continue;
            }
            $link->provider === 'etims' ? Etims::send($row, (int) $link->id) : self::push($row, (int) $link->id);
        }

        return $due->count();
    }

    private static function pushInvoice(CompanyIntegration $row, int $invoiceId): string
    {
        $invoice = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->with('items', 'tenancy.tenant.user', 'tenancy.unit.property')->findOrFail($invoiceId));
        self::skipOld($row, $invoice->created_at);
        $customer = self::customer($row, $invoice);
        $lines = $invoice->items->filter(fn ($i) => (float) $i->amount > 0)
            ->map(fn ($i) => ['desc' => (string) ($i->description ?: ucfirst((string) $i->item_type)), 'amount' => round((float) $i->amount, 2)])->values();
        if ($lines->isEmpty()) {
            $lines = collect([['desc' => 'Rent '.$invoice->billing_month?->format('M Y'), 'amount' => round((float) $invoice->total_amount, 2)]]);
        }
        $date = ($invoice->created_at ?? now())->toDateString();
        $number = mb_substr(Payloads::number($invoice), 0, 21);

        return match ($row->provider) {
            'quickbooks' => (string) self::qb($row, 'post', '/invoice', ['CustomerRef' => ['value' => $customer], 'DocNumber' => $number, 'TxnDate' => $date,
                'Line' => $lines->map(fn ($l) => ['Amount' => $l['amount'], 'Description' => $l['desc'], 'DetailType' => 'SalesItemLineDetail',
                    'SalesItemLineDetail' => ['ItemRef' => ['value' => self::qbItem($row)], 'Qty' => 1, 'UnitPrice' => $l['amount']]])->all()])->json('Invoice.Id'),
            'xero' => (string) self::ok(self::client($row)->post('https://api.xero.com/api.xro/2.0/Invoices', ['Invoices' => [[
                'Type' => 'ACCREC', 'Contact' => ['ContactID' => $customer], 'Date' => $date, 'DueDate' => $date, 'InvoiceNumber' => $number,
                'Reference' => $invoice->tenancy?->unit?->unit_number, 'Status' => 'AUTHORISED', 'LineAmountTypes' => 'NoTax',
                'LineItems' => $lines->map(fn ($l) => ['Description' => $l['desc'], 'Quantity' => 1, 'UnitAmount' => $l['amount'], 'AccountCode' => $row->get('income_account') ?: '200'])->all(),
            ]]]))->json('Invoices.0.InvoiceID'),
            'zoho_books' => tap((string) self::ok(self::client($row)->post(self::zoho($row, '/invoices', ['ignore_auto_number_generation' => 'true']), [
                'customer_id' => $customer, 'invoice_number' => $number, 'date' => $date, 'reference_number' => $invoice->tenancy?->unit?->unit_number,
                'line_items' => $lines->map(fn ($l) => ['name' => $row->get('item_name') ?: 'Rent', 'description' => $l['desc'], 'rate' => $l['amount'], 'quantity' => 1])->all(),
            ]))->json('invoice.invoice_id'), fn ($id) => self::client($row)->post(self::zoho($row, "/invoices/$id/status/sent"))),
        } ?: throw new \RuntimeException(self::label($row->provider).' did not return an invoice id.');
    }

    private static function pushPayment(CompanyIntegration $row, int $paymentId): string
    {
        $payment = CompanyAccess::bypass(fn () => Payment::withoutGlobalScopes()->findOrFail($paymentId));
        self::skipOld($row, $payment->created_at);
        $invoiceLink = self::link((int) $row->company_id, $row->provider, 'invoice', (int) $payment->invoice_id);
        if ($invoiceLink->status !== 'synced' && ! self::push($row, $invoiceLink->id)) {
            throw new \RuntimeException('Waiting for the bill to be copied first.');
        }
        $invoiceRemote = DB::table('integration_links')->where('id', $invoiceLink->id)->value('remote_id');
        $invoice = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->with('tenancy.tenant.user', 'tenancy.unit')->findOrFail($payment->invoice_id));
        $customer = self::customer($row, $invoice);
        $amount = round((float) $payment->amount, 2);
        $date = ($payment->created_at ?? now())->toDateString();
        $ref = mb_substr((string) ($payment->external_reference ?: $payment->transaction_reference ?: 'scharge '.$payment->id), 0, 21);

        return match ($row->provider) {
            'quickbooks' => (string) self::qb($row, 'post', '/payment', array_filter(['CustomerRef' => ['value' => $customer], 'TotalAmt' => $amount, 'TxnDate' => $date,
                'PaymentRefNum' => $ref, 'DepositToAccountRef' => $row->get('deposit_account') ? ['value' => $row->get('deposit_account')] : null,
                'Line' => [['Amount' => $amount, 'LinkedTxn' => [['TxnId' => $invoiceRemote, 'TxnType' => 'Invoice']]]]]))->json('Payment.Id'),
            'xero' => (string) self::ok(self::client($row)->put('https://api.xero.com/api.xro/2.0/Payments', ['Payments' => [[
                'Invoice' => ['InvoiceID' => $invoiceRemote], 'Account' => ['Code' => $row->get('bank_account') ?: '090'], 'Amount' => $amount, 'Date' => $date, 'Reference' => $ref,
            ]]]))->json('Payments.0.PaymentID'),
            'zoho_books' => (string) self::ok(self::client($row)->post(self::zoho($row, '/customerpayments'), array_filter([
                'customer_id' => $customer, 'payment_mode' => str_contains((string) $payment->payment_method, 'mpesa') ? 'M-Pesa' : 'Bank Transfer',
                'amount' => $amount, 'date' => $date, 'reference_number' => $ref, 'account_id' => $row->get('deposit_account'),
                'invoices' => [['invoice_id' => $invoiceRemote, 'amount_applied' => $amount]],
            ])))->json('payment.payment_id'),
        } ?: throw new \RuntimeException(self::label($row->provider).' did not return a payment id.');
    }

    /** The tenant as a customer/contact (created once, remembered as a "customer" link keyed by tenant id). */
    private static function customer(CompanyIntegration $row, Invoice $invoice): string
    {
        $tenant = $invoice->tenancy?->tenant ?? throw new \RuntimeException('The bill has no tenant.');
        $link = self::link((int) $row->company_id, $row->provider, 'customer', (int) $tenant->id);
        if ($link->status === 'synced' && $link->remote_id) {
            return $link->remote_id;
        }
        $user = $tenant->user;
        $name = mb_substr(trim(($user?->name ?: 'Tenant '.$tenant->id).($invoice->tenancy?->unit ? ' ('.$invoice->tenancy->unit->unit_number.')' : '')), 0, 100);
        $email = filter_var($user?->email, FILTER_VALIDATE_EMAIL) ? $user->email : null;
        $id = match ($row->provider) {
            'quickbooks' => self::qbQuery($row, "select Id from Customer where DisplayName = '".str_replace("'", "\\'", $name)."'", 'Customer')
                ?? self::qb($row, 'post', '/customer', array_filter(['DisplayName' => $name, 'PrimaryPhone' => $user?->phone ? ['FreeFormNumber' => $user->phone] : null,
                    'PrimaryEmailAddr' => $email ? ['Address' => $email] : null]))->json('Customer.Id'),
            'xero' => self::client($row)->get('https://api.xero.com/api.xro/2.0/Contacts', ['where' => 'Name=="'.str_replace('"', '', $name).'"'])->json('Contacts.0.ContactID')
                ?? self::ok(self::client($row)->post('https://api.xero.com/api.xro/2.0/Contacts', ['Contacts' => [array_filter(['Name' => $name, 'EmailAddress' => $email,
                    'Phones' => $user?->phone ? [['PhoneType' => 'MOBILE', 'PhoneNumber' => $user->phone]] : null])]]))->json('Contacts.0.ContactID'),
            'zoho_books' => self::client($row)->get(self::zoho($row, '/contacts', ['contact_name' => $name]))->json('contacts.0.contact_id')
                ?? self::ok(self::client($row)->post(self::zoho($row, '/contacts'), array_filter(['contact_name' => $name, 'contact_type' => 'customer',
                    'contact_persons' => $email || $user?->phone ? [array_filter(['first_name' => $user?->name, 'email' => $email, 'mobile' => $user?->phone, 'is_primary_contact' => true])] : null])))
                    ->json('contact.contact_id'),
        };
        if (! $id) {
            throw new \RuntimeException('Could not create the customer in '.self::label($row->provider).'.');
        }
        self::done($link->id, true, null, (string) $id);

        return (string) $id;
    }

    // ── HTTP helpers ────────────────────────────────────────────────────────────────────────────────────────

    /** A request with a fresh access token (refreshed and saved when it is about to expire). */
    private static function client(CompanyIntegration $row): PendingRequest
    {
        if ((int) $row->get('expires_at', 0) < time() + 60) {
            self::refresh($row);
        }
        $headers = match ($row->provider) {
            'xero' => ['xero-tenant-id' => (string) $row->get('tenant_id')],
            default => [],
        };
        $auth = $row->provider === 'zoho_books' ? ['Authorization' => 'Zoho-oauthtoken '.$row->get('access_token')] : [];

        return Http::acceptJson()->asJson()->timeout(30)->withHeaders($headers + $auth)
            ->when($row->provider !== 'zoho_books', fn ($h) => $h->withToken((string) $row->get('access_token')));
    }

    private static function refresh(CompanyIntegration $row): void
    {
        $app = self::app($row->provider) ?? throw new \RuntimeException('scharge\'s '.self::label($row->provider).' app is not set up.');
        $form = ['grant_type' => 'refresh_token', 'refresh_token' => (string) $row->get('refresh_token')];
        $url = self::tokenUrl($row->provider, $app, $row->get('accounts_server'));
        $res = $row->provider === 'zoho_books'
            ? Http::asForm()->acceptJson()->timeout(20)->post($url, $form + ['client_id' => $app->get('client_id'), 'client_secret' => $app->get('client_secret')])
            : Http::asForm()->acceptJson()->timeout(20)->withBasicAuth((string) $app->get('client_id'), (string) $app->get('client_secret'))->post($url, $form);
        if (! $res->successful() || ! $res->json('access_token')) {
            throw new \RuntimeException(self::label($row->provider).' ended the connection. Click Connect again.');
        }
        $row->mergeCredentials(self::tokens($res, (string) $row->get('refresh_token')));
        $row->save();
    }

    private static function tokens(Response $res, ?string $oldRefresh = null): array
    {
        return ['access_token' => $res->json('access_token'), 'refresh_token' => $res->json('refresh_token') ?: $oldRefresh,
            'expires_at' => time() + (int) ($res->json('expires_in') ?: 3600)];
    }

    private static function tokenUrl(string $provider, CompanyIntegration $app, ?string $accountsServer): string
    {
        return match ($provider) {
            'quickbooks' => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
            'xero' => 'https://identity.xero.com/connect/token',
            'zoho_books' => (filled($accountsServer) && preg_match('#^https://accounts\.zoho\.[a-z.]+$#', $accountsServer) ? $accountsServer : 'https://accounts.zoho.'.$app->get('region', 'com')).'/oauth/v2/token',
        };
    }

    private static function qb(CompanyIntegration $row, string $method, string $path, array $body = []): Response
    {
        $base = self::app('quickbooks')?->get('environment') === 'production' ? 'https://quickbooks.api.intuit.com' : 'https://sandbox-quickbooks.api.intuit.com';
        $url = $base.'/v3/company/'.$row->get('realm_id').$path.(str_contains($path, '?') ? '&' : '?').'minorversion=75';

        return self::ok($method === 'get' ? self::client($row)->get($url) : self::client($row)->post($url, $body));
    }

    private static function qbQuery(CompanyIntegration $row, string $sql, string $entity): ?string
    {
        return self::qb($row, 'get', '/query?query='.rawurlencode($sql))->json("QueryResponse.$entity.0.Id");
    }

    private static function qbItem(CompanyIntegration $row): string
    {
        $name = str_replace("'", "\\'", (string) ($row->get('item_name') ?: 'Rent'));

        return self::qbQuery($row, "select Id from Item where Name = '$name'", 'Item')
            ?? self::qbQuery($row, "select Id from Item where Type = 'Service' maxresults 1", 'Item')
            ?? throw new \RuntimeException("Add a Service item named \"$name\" in QuickBooks (Sales › Products and services).");
    }

    private static function zoho(CompanyIntegration $row, string $path, array $query = []): string
    {
        return rtrim((string) $row->get('api_domain'), '/').'/books/v3'.$path.'?'.http_build_query(array_filter(['organization_id' => $row->get('org_id')]) + $query);
    }

    private static function ok(Response $res): Response
    {
        if (! $res->successful()) {
            $msg = $res->json('Fault.Error.0.Detail') ?? $res->json('Elements.0.ValidationErrors.0.Message') ?? $res->json('Message') ?? $res->json('message') ?? $res->body();
            throw new \RuntimeException('HTTP '.$res->status().': '.mb_substr((string) $msg, 0, 300));
        }

        return $res;
    }

    private static function skipOld(CompanyIntegration $row, $createdAt): void
    {
        $from = $row->get('from_date') ?: substr((string) $row->get('connected_at'), 0, 10);
        if ($from && $createdAt && $createdAt->toDateString() < $from) {
            throw new \DomainException("Older than {$from}; not copied.");
        }
    }

    public static function label(string $provider): string
    {
        return ['quickbooks' => 'QuickBooks', 'xero' => 'Xero', 'zoho_books' => 'Zoho Books'][$provider] ?? $provider;
    }
}
