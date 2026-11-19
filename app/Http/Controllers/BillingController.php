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
        $row = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->join('companies as c', 'c.id', '=', 's.company_id')->leftJoin('subscription_plans as p', 'p.id', '=', 's.plan_id')
            ->where('i.id', $invoice)->first(['i.*', 's.company_id', 'c.name as company_name', 'c.email as company_email', 'c.phone as company_phone', 'c.address as company_address', 'p.name as plan_name']);
        abort_unless($row, 404);
        $staff = $user->hasRole('sysadmin');
        abort_unless($staff || ($user->hasAnyRole(BillingLock::PAYERS) && (int) Modules::companyOf($user) === (int) $row->company_id), 403);
        $json = json_decode((string) $row->invoice_json, true) ?: [];

        // Lines: explicit items, else one per billed month, else the plan as one line.
        $items = collect($json['items'] ?? [])->map(fn ($i) => ['description' => $i['description'], 'detail' => $i['detail'] ?? null, 'amount' => (float) $i['amount']]);
        if ($items->isEmpty() && ! empty($json['lines'])) {
            $items = collect($json['lines'])->map(fn ($l) => ['description' => ($json['property'] ?? 'Units').' · '.$l['month'],
                'detail' => $l['units'].' units × KES '.number_format($l['rate']), 'amount' => (float) $l['amount']]);
        }
        if ($items->isEmpty()) {
            $items = collect([['description' => ($json['plan_name'] ?? $row->plan_name ?? 'scharge subscription'), 'detail' => null, 'amount' => (float) $row->amount]]);
        }

        return view('billing.invoice', ['inv' => $row, 'json' => $json, 'items' => $items, 'staff' => $staff]);
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
