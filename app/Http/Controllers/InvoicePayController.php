<?php

namespace App\Http\Controllers;

use App\Models\BillingPayment;
use App\Services\Sharet\BillPayments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Pay a scharge invoice with Sharet Pay from its signed public link, no sign-in.
 *
 *  GET /invoice/{invoice}/pay          start (or reuse) a Sharet payment request, then go to its status page
 *  GET /invoice/{invoice}/pay/{ref}    the payment by its Sharet reference (SH…): who it pays (@handle), the amount,
 *                                      M-Pesa STK or Sharet balance on Sharet's page, and the live status
 *  GET /invoice/{invoice}/pay/{ref}/status   JSON for the page's polling
 *
 * Sharet's pay page has no return URL, so the Sharet reference lives in our own signed URL: the link keeps working and
 * always shows the real status (asked from Sharet while open; webhooks settle it too).
 */
class InvoicePayController extends Controller
{
    /** $toSharet: straight on to the Sharet Africa payment gateway (the invoice's Pay with Sharet Pay button and the PDF). */
    public static function startUrl(int $invoice, bool $toSharet = false): string
    {
        return URL::signedRoute('billing.invoice.pay', $toSharet ? ['invoice' => $invoice, 'to' => 'sharet'] : ['invoice' => $invoice]);
    }

    public static function statusUrl(int $invoice, string $ref): string
    {
        return URL::signedRoute('billing.invoice.pay.show', ['invoice' => $invoice, 'ref' => $ref]);
    }

    /** scharge's Sharet @handle: from Sharet's own answer on the latest bill payment, else config. */
    public static function payeeHandle(): ?string
    {
        return cache()->remember('sharet.payee_handle', 3600, function () {
            $raw = BillingPayment::whereNotNull('raw')->where('method', 'sharet_pay')->latest('id')->value('raw');
            $raw = is_string($raw) ? json_decode($raw, true) : $raw;
            $h = $raw['payee']['handle'] ?? config('billing.sharet_handle');

            return $h ? '@'.ltrim($h, '@') : null;
        });
    }

    public function start(int $invoice, BillPayments $bills)
    {
        $inv = $this->invoice($invoice);
        if ($inv->status === 'paid') {
            $paid = BillingPayment::where('subscription_invoice_id', $inv->id)->where('status', 'paid')->whereNotNull('sharet_id')->latest('id')->first();

            return $paid ? redirect()->to(self::statusUrl($inv->id, $paid->sharet_id)) : $this->page($inv, null, 'This invoice is already paid.');
        }
        try {
            $row = $bills->payLink((int) $inv->company_id, $inv->id);
        } catch (\RuntimeException $e) {
            return $this->page($inv, null, $e->getMessage());
        }

        // The invoice's Pay with Sharet Pay: on to the Sharet Africa payment gateway itself.
        if (request()->query('to') === 'sharet' && $row->isOpen() && $row->pay_url) {
            return redirect()->away($row->pay_url);
        }

        return redirect()->to(self::statusUrl($inv->id, $row->sharet_id));
    }

    public function show(int $invoice, string $ref, BillPayments $bills)
    {
        $inv = $this->invoice($invoice);
        $row = $this->payment($inv->id, $ref);

        return $this->page($inv, $bills->refresh($row));
    }

    public function status(int $invoice, string $ref, BillPayments $bills)
    {
        $inv = $this->invoice($invoice);
        $row = $bills->refresh($this->payment($inv->id, $ref));

        return response()->json(['status' => $row->status, 'paid_via' => $row->paid_via, 'paid_at' => $row->paid_at?->toIso8601String()])
            ->header('Cache-Control', 'no-store');
    }

    private function page(object $inv, ?BillingPayment $row, ?string $error = null)
    {
        $raw = $row?->raw ?? [];
        $handle = isset($raw['payee']['handle']) ? '@'.ltrim($raw['payee']['handle'], '@') : self::payeeHandle();

        return response()->view('billing.pay', [
            'inv' => $inv,
            'row' => $row,
            'error' => $error,
            'handle' => $handle,
            'payee' => 'scharge · Sharet Africa',
            'invoiceUrl' => BillingController::publicUrl($inv->id),
            'startUrl' => self::startUrl($inv->id),
            'statusJson' => $row ? URL::signedRoute('billing.invoice.pay.status', ['invoice' => $inv->id, 'ref' => $row->sharet_id]) : null,
            'stkNumber' => config('billing.stk_number'),
        ])->header('Cache-Control', 'no-store');
    }

    private function invoice(int $invoice): object
    {
        $inv = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->join('companies as c', 'c.id', '=', 's.company_id')->where('i.id', $invoice)
            ->first(['i.id', 'i.invoice_number', 'i.amount', 'i.currency', 'i.status', 'i.due_date', 'i.paid_at', 's.company_id', 'c.name as company_name']);
        abort_unless($inv, 404);

        return $inv;
    }

    private function payment(int $invoiceId, string $ref): BillingPayment
    {
        return BillingPayment::where('subscription_invoice_id', $invoiceId)->where('sharet_id', $ref)->firstOrFail();
    }
}
