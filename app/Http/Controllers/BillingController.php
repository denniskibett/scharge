<?php

namespace App\Http\Controllers;

use App\Services\Sharet\BillPayments;
use App\Support\BillingLock;
use App\Support\CompanyLedger;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Billing: the company's scharge bills. The one page open while the company is locked for an unpaid bill
 * (App\Support\BillingLock). The owner (admin) and accountant see the amounts and how to pay; everyone else only
 * sees that access is paused and who their admin is.
 */
class BillingController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $companyId = Modules::companyOf($user);
        $status = $companyId ? BillingLock::status($companyId) : null;
        $payer = $user->hasAnyRole(BillingLock::PAYERS);
        $admins = $companyId ? DB::table('users')->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'admin')->where('users.company_id', $companyId)
            ->orderBy('users.id')->limit(3)->get(['users.name', 'users.email', 'users.phone']) : collect();
        $paid = $companyId && $payer ? DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->where('s.company_id', $companyId)->where('i.status', 'paid')->orderByDesc('i.paid_at')->limit(6)
            ->get(['i.invoice_number', 'i.amount', 'i.currency', 'i.paid_at']) : collect();

        return view('billing.index', [
            'status' => $status,
            'locked' => $status['locked'] ?? false,
            'payer' => $payer,
            'admins' => $admins,
            'paid' => $paid,
            'staff' => ! $companyId,
            'online' => $companyId && $payer && BillPayments::available(),
            'wallet' => $companyId && $payer ? BillPayments::wallet($companyId) : null,
            'ledger' => $companyId && $payer ? CompanyLedger::entries($companyId) : [],
            'phone' => $user->phone ?? null,
            'paybill' => config('billing.paybill'),
            'accountHint' => config('billing.account_hint'),
            'contactEmail' => config('billing.contact_email'),
            'contactPhone' => config('billing.contact_phone'),
        ]);
    }

    /** Sharet Pay: open the hosted pay page (M-Pesa prompt or Sharet balance) for one bill. */
    public function paySharet(Request $request, int $invoice, BillPayments $bills)
    {
        $companyId = $this->payerCompany();
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ]{9,20}$/']]);
        try {
            $row = $bills->payLink($companyId, $invoice, auth()->id(), $data['phone'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($row->pay_url);
    }

    /** Pay one bill from the company's scharge wallet (rent collected through scharge, less fees). */
    public function payWallet(int $invoice, BillPayments $bills)
    {
        $companyId = $this->payerCompany();
        try {
            $row = $bills->payFromWallet($companyId, $invoice, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $number = DB::table('subscription_invoices')->where('id', $row->subscription_invoice_id)->value('invoice_number');

        return redirect()->route('billing.index')->with('success', "Bill {$number} paid from your scharge wallet: KES ".number_format((float) $row->amount).'. Thank you.');
    }

    /** Only the owner (admin) and accountant of a company pay. */
    /** One scharge bill as a printable invoice with its lines (owner / accountant of that company, or scharge staff). */
    public function show(int $invoice)
    {
        $user = auth()->user();
        $row = $this->invoiceRow($invoice);
        $staff = $user->hasRole('sysadmin');
        abort_unless($staff || ($user->hasAnyRole(BillingLock::PAYERS) && (int) Modules::companyOf($user) === (int) $row->company_id), 403);

        return $this->renderInvoice($row, $staff, false);
    }

    /** The invoice as a PDF download (same access as show). */
    public function pdf(int $invoice)
    {
        $user = auth()->user();
        $row = $this->invoiceRow($invoice);
        abort_unless($user->hasRole('sysadmin') || ($user->hasAnyRole(BillingLock::PAYERS) && (int) Modules::companyOf($user) === (int) $row->company_id), 403);

        return $this->renderInvoice($row, false, false, true);
    }

    /** PDF through the signed public link. */
    public function publicPdf(int $invoice)
    {
        return $this->renderInvoice($this->invoiceRow($invoice), false, true, true);
    }

    /** The same invoice through its signed public link (no sign-in): for sharing with whoever pays. */
    public function publicShow(int $invoice)
    {
        return $this->renderInvoice($this->invoiceRow($invoice), false, true);
    }

    /** Signed public link to an invoice (valid until the APP_KEY changes). */
    public static function publicUrl(int $invoice): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('billing.invoice.public', ['invoice' => $invoice]);
    }

    /** VAT on a scharge invoice: 'none' (non-VAT, the default) or 'exclusive' (line prices are net, VAT added on top). */
    public static function vatOf(array $json, float $amount): array
    {
        $mode = ($json['vat']['mode'] ?? 'none') === 'exclusive' ? 'exclusive' : 'none';
        $rate = (float) ($json['vat']['rate'] ?? config('billing.vat_rate', 16));
        $net = (float) ($json['vat']['net'] ?? $amount);
        $tax = $mode === 'exclusive' ? round($net * $rate / 100, 2) : 0.0;

        return ['mode' => $mode, 'rate' => $rate, 'net' => $net, 'tax' => $tax, 'total' => round($net + $tax, 2)];
    }

    /** Sysadmin: switch an unpaid invoice between non-VAT and VAT exclusive; the amount due follows. */
    public function toggleVat(Request $request, int $invoice)
    {
        abort_unless($request->user()->hasRole('sysadmin'), 403);
        $row = DB::table('subscription_invoices')->where('id', $invoice)->first();
        abort_unless($row, 404);
        abort_if($row->status === 'paid', 422, 'A paid invoice cannot change its VAT.');
        $json = json_decode((string) $row->invoice_json, true) ?: [];
        $current = self::vatOf($json, (float) $row->amount);
        $mode = $request->input('mode') === 'exclusive' ? 'exclusive' : 'none';
        $json['vat'] = ['mode' => $mode, 'rate' => (float) config('billing.vat_rate', 16), 'net' => $current['net'], 'changed_by' => $request->user()->id, 'changed_at' => now()->toIso8601String()];
        $vat = self::vatOf($json, $current['net']);
        DB::table('subscription_invoices')->where('id', $invoice)->update(['amount' => $vat['total'], 'invoice_json' => json_encode($json), 'updated_at' => now()]);

        return back()->with('success', $mode === 'exclusive' ? 'VAT added: '.number_format($vat['tax'], 2).' on top.' : 'Invoice is non-VAT.');
    }

    private function invoiceRow(int $invoice): object
    {
        $row = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->join('companies as c', 'c.id', '=', 's.company_id')->leftJoin('subscription_plans as p', 'p.id', '=', 's.plan_id')
            ->where('i.id', $invoice)->first(['i.*', 's.company_id', 'c.name as company_name', 'c.email as company_email', 'c.phone as company_phone', 'c.address as company_address', 'c.tax_id as company_tax_id', 'p.name as plan_name']);
        abort_unless($row, 404);

        return $row;
    }

    private function renderInvoice(object $row, bool $staff, bool $public, bool $asPdf = false)
    {
        $json = json_decode((string) $row->invoice_json, true) ?: [];

        // Lines: explicit items, else one per billed month, else the plan as one line.
        // Units and rate in their own columns: from the item, else read from an older "276 units × KES 60" detail.
        $items = collect($json['items'] ?? [])->map(function ($i) {
            $units = $i['units'] ?? null;
            $rate = $i['rate'] ?? null;
            if (($units === null || $rate === null) && preg_match('/([\d,]+)\s*units?\s*×\s*KES\s*([\d,.]+)/u', (string) ($i['detail'] ?? ''), $m)) {
                $units ??= (int) str_replace(',', '', $m[1]);
                $rate ??= (float) str_replace(',', '', $m[2]);
            }

            return ['description' => $i['description'], 'detail' => ($units !== null ? null : ($i['detail'] ?? null)), 'units' => $units, 'rate' => $rate, 'amount' => (float) $i['amount']];
        });
        if ($items->isEmpty() && ! empty($json['lines'])) {
            $items = collect($json['lines'])->map(fn ($l) => ['description' => ($json['property'] ?? 'Units').' · '.$l['month'],
                'detail' => null, 'units' => (int) $l['units'], 'rate' => (float) $l['rate'], 'amount' => (float) $l['amount']]);
        }
        if ($items->isEmpty()) {
            $items = collect([['description' => ($json['plan_name'] ?? $row->plan_name ?? 'scharge subscription'), 'detail' => null, 'units' => null, 'rate' => null, 'amount' => (float) $row->amount]]);
        }

        $vat = self::vatOf($json, (float) $row->amount);

        $data = ['inv' => $row, 'json' => $json, 'items' => $items, 'staff' => $staff, 'public' => $public, 'vat' => $vat, 'pay' => config('billing'),
            'payUrl' => $row->status === 'paid' ? null : InvoicePayController::startUrl((int) $row->id, true),
            'handle' => InvoicePayController::payeeHandle()];
        if ($asPdf) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('billing.invoice-pdf', $data)->setPaper('a4', 'portrait')
                ->setOption(['chroot' => [public_path(), resource_path('fonts')], 'isRemoteEnabled' => false])
                ->stream($row->invoice_number.'.pdf');
        }

        return view('billing.invoice', $data + ['publicUrl' => self::publicUrl((int) $row->id), 'privateUrl' => route('billing.invoice', $row->id),
            'pdfUrl' => $public ? \Illuminate\Support\Facades\URL::signedRoute('billing.invoice.public.pdf', ['invoice' => $row->id]) : route('billing.invoice.pdf', $row->id)]);
    }

    private function payerCompany(): int
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(BillingLock::PAYERS), 403);
        $companyId = Modules::companyOf($user);
        abort_unless($companyId, 404);

        return $companyId;
    }
}
