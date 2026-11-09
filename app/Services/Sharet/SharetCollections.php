<?php

namespace App\Services\Sharet;

use App\Modules\Payments\Models\Invoice;
use App\Models\SharetPaymentRequest;
use App\Services\Integrations;
use App\Services\InvoicePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tenant pay links through Sharet.
 *  - rail mode (default): the request is created on scharge's own Sharet account; money lands in
 *    scharge's wallet and is swept to the landlord at 08:00 / 12:00 / 16:00 (SharetPayouts).
 *  - connected mode: the landlord connected their own Sharet account, so the request is created
 *    on it (Sharet-Account header) and the money is theirs straight away.
 */
class SharetCollections
{
    /** Is Sharet set up at all (platform key saved and on)? */
    public static function available(): bool
    {
        return Integrations::sharet() !== null;
    }

    /** An open pay link for this invoice (reused if one for the same amount is still open). */
    public function payLink(Invoice $invoice, ?float $amount = null, ?string $phone = null): SharetPaymentRequest
    {
        $platform = Integrations::sharet() ?? throw new \RuntimeException('Sharet is not configured.');
        $amount = round($amount ?? max(0, (float) $invoice->total_amount - (float) $invoice->total_paid), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Nothing left to pay on this invoice.');
        }

        $existing = SharetPaymentRequest::where('invoice_id', $invoice->id)->where('status', 'open')
            ->where('amount', $amount)->latest('id')->first();
        if ($existing?->isOpen()) {
            return $existing;
        }

        $companyId = $invoice->owningCompanyId();
        [$mode, $account] = $this->modeFor($companyId);
        $client = SharetClient::fromIntegration($platform)->asAccount($mode === 'connected' ? $account : null);
        $tenant = $invoice->tenancy?->tenant;
        $month = $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : null;
        $reference = mb_substr('Invoice #'.($invoice->invoice_number ?? $invoice->id).($month ? ' · '.$month : ''), 0, 140);

        $body = array_filter([
            'amount' => number_format($amount, 2, '.', ''),
            'channels' => ['mpesa', 'sharet'],
            'reference' => $reference,
            'payer' => $phone ? ['phone' => $phone] : null,
            'expires_in_minutes' => config('sharet.pay_link_minutes'),
            'metadata' => ['scharge_invoice' => (string) $invoice->id, 'scharge_company' => (string) $companyId, 'mode' => $mode],
        ]);

        $res = $client->post('/payment-requests', $body, "scharge-inv-{$invoice->id}-".str_replace('.', '', (string) $amount).'-'.now()->format('YmdHi'));
        if (! $res->successful()) {
            Log::error('Sharet payment request failed', ['invoice_id' => $invoice->id, 'status' => $res->status(), 'body' => $res->json()]);
            throw new \RuntimeException('Sharet could not create the pay link: '.($res->json('detail') ?? $res->json('message') ?? $res->status()));
        }

        return SharetPaymentRequest::create([
            'sharet_id' => $res->json('id'),
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant?->id,
            'environment' => $res->json('environment') ?? $client->environment,
            'mode' => $mode,
            'sharet_account' => $mode === 'connected' ? $account : null,
            'amount' => $amount,
            'status' => $res->json('status') ?? 'open',
            'pay_url' => $res->json('pay_url'),
            'reference' => $reference,
            'expires_at' => $res->json('expires_at'),
            'raw' => $res->json(),
        ]);
    }

    /** ['rail'|'connected', ?sharetAccount] for a company. */
    public function modeFor(?int $companyId): array
    {
        $settings = $companyId ? Integrations::for($companyId, 'sharet_payouts') : null;
        if ($settings && $settings->get('mode') === 'connected' && filled($settings->get('sharet_account'))) {
            return ['connected', $settings->get('sharet_account')];
        }

        return ['rail', null];
    }

    /** Apply a Sharet payment-request object (from a webhook or a poll) to our records. */
    public function apply(array $object): ?SharetPaymentRequest
    {
        $row = SharetPaymentRequest::where('sharet_id', $object['id'] ?? '')->first();
        if (! $row) {
            return null;
        }

        $status = $object['status'] ?? $row->status;
        if ($status !== 'paid') {
            if ($status !== $row->status) {
                $row->update(['status' => $status, 'raw' => $object]);
            }

            return $row;
        }

        return DB::transaction(function () use ($row, $object) {
            $row = SharetPaymentRequest::whereKey($row->id)->lockForUpdate()->first();
            if ($row->settled_payment_id) {
                return $row;   // already applied (duplicate webhook or poll)
            }

            $row->fill(['status' => 'paid', 'paid_via' => $object['paid_via'] ?? null, 'paid_at' => $object['paid_at'] ?? now(), 'raw' => $object]);
            $invoice = $row->invoice_id ? Invoice::whereKey($row->invoice_id)->lockForUpdate()->first() : null;

            if ($invoice?->tenancy?->tenant) {
                $payment = app(InvoicePaymentService::class)->apply($invoice, (float) $row->amount, [
                    'method' => 'sharet',
                    'source' => $row->mode === 'connected' ? 'sharet_connected' : 'sharet_rail',
                    // Sharet's own transaction ID (e.g. SHD1CCFAF6) is what staff and landlords see; the pay-link
                    // ID stays as the external reference for matching. Older Sharet versions send no transaction_id.
                    'transaction_reference' => $object['transaction_id'] ?? $row->sharet_id,
                    'external_reference' => $row->sharet_id,
                    'label' => 'Sharet '.($row->paid_via ?? 'payment'),
                    'meta' => ['sharet_request' => $row->sharet_id, 'sharet_transaction' => $object['transaction_id'] ?? null, 'mode' => $row->mode, 'paid_via' => $row->paid_via, 'environment' => $row->environment],
                ]);
                $row->settled_payment_id = $payment->id;
            } else {
                Log::warning('Sharet payment for an invoice that no longer has a tenant', ['sharet_id' => $row->sharet_id]);
            }
            $row->save();

            return $row;
        });
    }

    /** Poll Sharet for open requests (catches connected-mode payments, whose webhooks go to the landlord). */
    public function syncOpen(int $limit = 200): int
    {
        $platform = Integrations::sharet();
        if (! $platform) {
            return 0;
        }
        $client = SharetClient::fromIntegration($platform);
        $changed = 0;

        SharetPaymentRequest::where('status', 'open')->where('created_at', '>=', now()->subDays(45))
            ->orderBy('id')->limit($limit)->get()
            ->each(function (SharetPaymentRequest $row) use ($client, &$changed) {
                $res = $client->asAccount($row->mode === 'connected' ? $row->sharet_account : null)->get('/payment-requests/'.$row->sharet_id);
                if ($res->successful() && ($res->json('status') ?? 'open') !== 'open') {
                    $this->apply($res->json());
                    $changed++;
                }
            });

        return $changed;
    }
}
