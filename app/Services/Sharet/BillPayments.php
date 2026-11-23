<?php

namespace App\Services\Sharet;

use App\Models\BillingPayment;
use App\Models\SharetPaymentRequest;
use App\Services\Integrations;
use App\Support\BillingLock;
use App\Support\Modules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Companies paying scharge's own bills (subscription_invoices) from Billing. docs/BILLING_PAYMENTS.md.
 *
 *  - Sharet Pay (default): a pay link on scharge's own Sharet account (never the company's connected account or its own
 *    Daraja: this money is scharge's). The hosted page takes M-Pesa (phone prompt) or a Sharet balance.
 *  - Wallet: the company's scharge wallet is rent collected through scharge (rail mode) and not yet paid out, less
 *    scharge's fee. Paying from it moves no money: the bill is marked paid now and the amount is taken off the
 *    company's next payout (SharetPayouts::sweep). Whatever is left is still paid out to M-Pesa, paybill, till or bank.
 *
 * Either way a paid billing_payments row is the company's ledger entry, the bill turns paid, and BillingLock unlocks.
 */
class BillPayments
{
    public static function installed(): bool
    {
        static $memo = [];

        return $memo[spl_object_id(app())] ??= Schema::hasTable('billing_payments');
    }

    /** Can bills be paid online at all (table there and scharge's Sharet account connected)? */
    public static function available(): bool
    {
        return self::installed() && Integrations::sharet() !== null;
    }

    public static function environment(): ?string
    {
        $platform = Integrations::sharet();

        return $platform ? SharetClient::fromIntegration($platform)->environment : null;
    }

    /** Rail collections not yet paid out. */
    public static function unsweptCollections(int $companyId, string $environment): Builder
    {
        return SharetPaymentRequest::where('company_id', $companyId)->where('mode', 'rail')->where('status', 'paid')
            ->whereNull('sweep_id')->where('environment', $environment);
    }

    /** Bills paid from the wallet and not yet taken off a payout. */
    public static function unsweptDebits(int $companyId, string $environment): Builder
    {
        return BillingPayment::where('company_id', $companyId)->where('method', 'wallet')->where('status', 'paid')
            ->whereNull('sweep_id')->where('environment', $environment);
    }

    public static function feePercent(): float
    {
        return (float) (Integrations::sharet()?->get('fee_percent', config('sharet.fee_percent')) ?? config('sharet.fee_percent'));
    }

    /**
     * The company's scharge wallet: rent collected and not yet paid out, less scharge's fee, less bills already paid
     * from it. Whole shillings, like the payout.
     *
     * @return array{gross: float, fee: float, debits: float, available: float, fee_percent: float, environment: ?string}
     */
    public static function wallet(int $companyId): array
    {
        $env = self::environment();
        $feePercent = self::feePercent();
        if (! $env || ! self::installed()) {
            return ['gross' => 0.0, 'fee' => 0.0, 'debits' => 0.0, 'available' => 0.0, 'fee_percent' => $feePercent, 'environment' => $env];
        }
        $gross = (float) self::unsweptCollections($companyId, $env)->sum('amount');
        $debits = (float) self::unsweptDebits($companyId, $env)->sum('amount');

        return self::split($gross, $debits, $feePercent) + ['environment' => $env];
    }

    /** gross → fee, debits, available (the payout rule: round down to whole shillings, the cents stay with the fee). */
    public static function split(float $gross, float $debits, float $feePercent): array
    {
        $available = max(0.0, floor($gross - $gross * $feePercent / 100 - $debits));

        return ['gross' => $gross, 'fee' => round(max(0, $gross - $debits - $available), 2), 'debits' => $debits, 'available' => $available, 'fee_percent' => $feePercent];
    }

    /** An unpaid bill of this company, or null. */
    public static function bill(int $companyId, int $invoiceId): ?object
    {
        return DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->where('s.company_id', $companyId)->where('i.id', $invoiceId)
            ->first(['i.id', 'i.invoice_number', 'i.amount', 'i.currency', 'i.status', 'i.due_date']);
    }

    /** Sharet Pay link for a bill (an open one for the same amount is reused). */
    public function payLink(int $companyId, int $invoiceId, ?int $userId = null, ?string $phone = null): BillingPayment
    {
        $platform = Integrations::sharet() ?? throw new \RuntimeException('Online payment is not set up yet.');
        $bill = self::bill($companyId, $invoiceId) ?? throw new \RuntimeException('Bill not found.');
        if (! in_array($bill->status, ['pending', 'failed'], true)) {
            throw new \RuntimeException('This bill is already paid.');
        }
        if (strtoupper((string) $bill->currency) !== 'KES') {
            throw new \RuntimeException('Sharet Pay takes KES; contact scharge to pay this bill.');
        }
        $amount = round((float) $bill->amount, 2);

        $open = BillingPayment::where('subscription_invoice_id', $bill->id)->where('method', 'sharet_pay')->where('status', 'open')
            ->where('amount', $amount)->latest('id')->first();
        if ($open?->isOpen() && $open->pay_url) {
            return $open;
        }

        $client = SharetClient::fromIntegration($platform);   // scharge's own account: no Sharet-Account header
        $res = $client->post('/payment-requests', array_filter([
            'amount' => number_format($amount, 2, '.', ''),
            'channels' => ['mpesa', 'sharet'],
            'reference' => 'scharge invoice '.\App\Support\InvoiceNo::label($bill->id),
            'payer' => $phone ? ['phone' => $phone] : null,
            'expires_in_minutes' => 60 * 24,
            'metadata' => ['scharge_bill' => (string) $bill->id, 'scharge_company' => (string) $companyId],
        ]), "scharge-bill-{$bill->id}-".str_replace('.', '', (string) $amount).'-'.now()->format('YmdHi'));

        if (! $res->successful()) {
            Log::error('Sharet bill pay link failed', ['invoice_id' => $bill->id, 'status' => $res->status(), 'body' => $res->json()]);
            throw new \RuntimeException('Sharet Pay could not start the payment. Try again in a minute.');
        }

        return BillingPayment::create([
            'company_id' => $companyId, 'subscription_invoice_id' => $bill->id, 'method' => 'sharet_pay', 'amount' => $amount,
            'currency' => 'KES', 'status' => $res->json('status') ?? 'open', 'environment' => $res->json('environment') ?? $client->environment,
            'sharet_id' => $res->json('id'), 'pay_url' => $res->json('pay_url'), 'user_id' => $userId,
            'expires_at' => $res->json('expires_at'), 'raw' => $res->json(),
        ]);
    }

    /** Pay a bill from the company's scharge wallet. */
    public function payFromWallet(int $companyId, int $invoiceId, ?int $userId = null): BillingPayment
    {
        $env = self::environment() ?? throw new \RuntimeException('Online payment is not set up yet.');

        $row = DB::transaction(function () use ($companyId, $invoiceId, $userId, $env) {
            $bill = DB::table('subscription_invoices')->where('id', $invoiceId)->lockForUpdate()->first();
            if (! $bill || ! self::bill($companyId, $invoiceId)) {
                throw new \RuntimeException('Bill not found.');
            }
            if (! in_array($bill->status, ['pending', 'failed'], true)) {
                throw new \RuntimeException('This bill is already paid.');
            }
            if (strtoupper((string) $bill->currency) !== 'KES') {
                throw new \RuntimeException('Your wallet is in KES; contact scharge to pay this bill.');
            }
            // Same rows the payout locks, so a payout and a wallet payment never both spend the same rent.
            $ids = self::unsweptCollections($companyId, $env)->lockForUpdate()->pluck('id');
            $debitIds = self::unsweptDebits($companyId, $env)->lockForUpdate()->pluck('id');
            $wallet = self::split((float) SharetPaymentRequest::whereIn('id', $ids)->sum('amount'),
                (float) BillingPayment::whereIn('id', $debitIds)->sum('amount'), self::feePercent());
            $amount = round((float) $bill->amount, 2);
            if ($wallet['available'] < $amount) {
                throw new \RuntimeException('Your wallet has KES '.number_format($wallet['available']).' available, less than this bill. Pay with Sharet Pay instead.');
            }

            $row = BillingPayment::create([
                'company_id' => $companyId, 'subscription_invoice_id' => $bill->id, 'method' => 'wallet', 'amount' => $amount,
                'currency' => 'KES', 'status' => 'paid', 'environment' => $env, 'user_id' => $userId, 'paid_at' => now(),
                'raw' => ['wallet_before' => $wallet['available'], 'wallet_after' => $wallet['available'] - $amount],
            ]);
            $this->settle($row);

            return $row;
        });

        $this->cancelOpenLinks($row->subscription_invoice_id);
        self::flush();

        return $row;
    }

    /** Apply a Sharet payment-request object (webhook or poll). Null when it is not a bill payment. */
    public function apply(array $object): ?BillingPayment
    {
        if (! self::installed()) {
            return null;
        }
        $row = BillingPayment::where('sharet_id', $object['id'] ?? '')->first();
        if (! $row) {
            return null;
        }
        $status = $object['status'] ?? $row->status;
        if ($status !== 'paid') {
            if ($status !== $row->status && $row->status === 'open') {
                $row->update(['status' => $status, 'raw' => $object]);
            }

            return $row;
        }

        $row = DB::transaction(function () use ($row, $object) {
            $row = BillingPayment::whereKey($row->id)->lockForUpdate()->first();
            if ($row->status === 'paid') {
                return $row;   // duplicate webhook or a later poll
            }
            $row->fill(['status' => 'paid', 'paid_via' => $object['paid_via'] ?? null, 'paid_at' => $object['paid_at'] ?? now(),
                'sharet_transaction' => $object['transaction_id'] ?? null, 'raw' => $object])->save();
            $this->settle($row);

            return $row;
        });
        self::flush();

        return $row;
    }

    /** Mark the bill paid. A second payment for a bill that is already paid is kept on the ledger and logged for scharge. */
    private function settle(BillingPayment $row): void
    {
        $updated = DB::table('subscription_invoices')->where('id', $row->subscription_invoice_id)->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'paid', 'paid_at' => $row->paid_at ?? now(), 'payment_method' => $row->method,
                'payment_id' => $row->sharet_transaction ?? $row->sharet_id ?? 'BP-'.$row->id, 'updated_at' => now()]);
        if (! $updated) {
            Log::warning('scharge bill paid again; refund or credit it', ['billing_payment_id' => $row->id, 'invoice_id' => $row->subscription_invoice_id]);
        } else {
            \App\Support\FirstBill::paid((int) $row->subscription_invoice_id);   // a first bill opens its three months
        }
    }

    /** Close other Sharet Pay links for a bill that is now paid (best effort). */
    private function cancelOpenLinks(int $invoiceId): void
    {
        $platform = Integrations::sharet();
        BillingPayment::where('subscription_invoice_id', $invoiceId)->where('method', 'sharet_pay')->where('status', 'open')->get()
            ->each(function (BillingPayment $open) use ($platform) {
                try {
                    if ($platform && $open->sharet_id) {
                        SharetClient::fromIntegration($platform)->post('/payment-requests/'.$open->sharet_id.'/cancel');
                    }
                } catch (\Throwable) {
                }
                $open->update(['status' => 'cancelled']);
            });
    }

    /** Ask Sharet for one open bill payment now (the public status page). Returns the row, updated when Sharet moved it. */
    public function refresh(BillingPayment $row): BillingPayment
    {
        $platform = Integrations::sharet();
        if (! $platform || $row->method !== 'sharet_pay' || $row->status !== 'open' || ! $row->sharet_id) {
            return $row;
        }
        try {
            $res = SharetClient::fromIntegration($platform)->get('/payment-requests/'.$row->sharet_id);
            if ($res->successful()) {
                return $this->apply($res->json()) ?? $row;
            }
        } catch (\Throwable $e) {
            Log::warning('Sharet bill status check failed', ['billing_payment_id' => $row->id, 'error' => $e->getMessage()]);
        }

        return $row;
    }

    /** Poll Sharet for open bill links (a safety net for missed webhooks). */
    public function syncOpen(int $limit = 100): int
    {
        $platform = Integrations::sharet();
        if (! $platform || ! self::installed()) {
            return 0;
        }
        $client = SharetClient::fromIntegration($platform);
        $changed = 0;
        BillingPayment::where('method', 'sharet_pay')->where('status', 'open')->where('created_at', '>=', now()->subDays(10))
            ->orderBy('id')->limit($limit)->get()
            ->each(function (BillingPayment $row) use ($client, &$changed) {
                $res = $client->get('/payment-requests/'.$row->sharet_id);
                if ($res->successful() && ($res->json('status') ?? 'open') !== 'open') {
                    $this->apply($res->json());
                    $changed++;
                }
            });

        return $changed;
    }

    private static function flush(): void
    {
        BillingLock::flush();
        Modules::flush();
    }
}
