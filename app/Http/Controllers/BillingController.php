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
    private function payerCompany(): int
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(BillingLock::PAYERS), 403);
        $companyId = Modules::companyOf($user);
        abort_unless($companyId, 404);

        return $companyId;
    }
}
