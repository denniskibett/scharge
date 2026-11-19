<?php

namespace App\Http\Controllers;

use App\Support\BillingLock;
use App\Support\Modules;
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
            'paybill' => config('billing.paybill'),
            'accountHint' => config('billing.account_hint'),
            'contactEmail' => config('billing.contact_email'),
            'contactPhone' => config('billing.contact_phone'),
        ]);
    }
}
