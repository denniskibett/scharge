<?php

namespace App\Support;

use App\Models\BillingPayment;
use App\Models\SharetPaymentRequest;
use App\Models\SharetSweep;
use App\Services\Sharet\BillPayments;
use Illuminate\Support\Carbon;

/**
 * A company's scharge wallet ledger, read from the records themselves (one source of truth, nothing copied):
 * rent collected through scharge (sharet_payment_requests, rail), payouts and scharge's fee (sharet_sweeps), and
 * scharge bills paid (billing_payments). Wallet lines move the balance; a Sharet Pay bill payment is listed but
 * does not (that money came from outside the wallet).
 */
class CompanyLedger
{
    /** @return array<int, array{at: Carbon, label: string, detail: ?string, amount: float, direction: string, wallet: bool}> */
    public static function entries(int $companyId, int $limit = 25): array
    {
        $rows = [];
        try {
            SharetPaymentRequest::where('company_id', $companyId)->where('mode', 'rail')->where('status', 'paid')
                ->latest('paid_at')->limit($limit)->get()
                ->each(function ($r) use (&$rows) {
                    $rows[] = ['at' => $r->paid_at ?? $r->updated_at, 'label' => 'Rent collected', 'detail' => $r->reference, 'amount' => (float) $r->amount, 'direction' => 'in', 'wallet' => true];
                });
            SharetSweep::where('company_id', $companyId)->whereIn('status', ['paid', 'paying'])->latest('id')->limit($limit)->get()
                ->each(function ($s) use (&$rows) {
                    $to = $s->destination['type'] ?? 'payout';
                    if ((float) $s->fee > 0) {
                        $rows[] = ['at' => $s->created_at, 'label' => 'scharge fee', 'detail' => null, 'amount' => (float) $s->fee, 'direction' => 'out', 'wallet' => true];
                    }
                    $rows[] = ['at' => $s->created_at, 'label' => 'Paid out to '.self::destination($s->destination ?? []), 'detail' => $s->status === 'paying' ? 'on its way' : null,
                        'amount' => (float) $s->net, 'direction' => 'out', 'wallet' => true];
                });
        } catch (\Throwable) {
        }
        if (BillPayments::installed()) {
            BillingPayment::where('company_id', $companyId)->where('status', 'paid')->latest('paid_at')->limit($limit)->get()
                ->each(function ($p) use (&$rows) {
                    $number = \DB::table('subscription_invoices')->where('id', $p->subscription_invoice_id)->value('invoice_number');
                    $rows[] = $p->method === 'wallet'
                        ? ['at' => $p->paid_at, 'label' => 'scharge bill '.$number.' paid from wallet', 'detail' => null, 'amount' => (float) $p->amount, 'direction' => 'out', 'wallet' => true]
                        : ['at' => $p->paid_at, 'label' => 'scharge bill '.$number.' paid with Sharet Pay', 'detail' => $p->sharet_transaction ?: ($p->paid_via ? 'via '.$p->paid_via : null), 'amount' => (float) $p->amount, 'direction' => 'out', 'wallet' => false];
                });
        }

        usort($rows, fn ($a, $b) => Carbon::parse($b['at'])->timestamp <=> Carbon::parse($a['at'])->timestamp);

        return array_slice($rows, 0, $limit);
    }

    private static function destination(array $to): string
    {
        return match ($to['type'] ?? null) {
            'mpesa' => 'M-Pesa '.($to['phone'] ?? ''),
            'paybill' => 'paybill '.($to['paybill'] ?? ''),
            'till' => 'till '.($to['till'] ?? ''),
            'bank' => strtoupper((string) ($to['bank'] ?? 'bank')).' account',
            default => 'your account',
        };
    }
}
