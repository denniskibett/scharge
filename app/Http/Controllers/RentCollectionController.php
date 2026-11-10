<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaybillPayment;
use App\Models\RentHold;
use App\Models\RentMessage;
use App\Models\Tenancy;
use App\Services\Rent\BillNotifier;
use App\Services\Rent\PaybillPayments;
use App\Services\Rent\RentRun;
use App\Services\Rent\RentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Money › Rent collection: one place for a landlord to see and steer rent automation (docs/RENT_AUTOMATION.md).
 * Tabs: Paybill payments (match the ones scharge could not), Messages (bills, reminders, penalties; resend failed ones),
 * Paused (tenants with reminders and penalties paused). Action: send fresh bill links to everyone who owes.
 * Company users see only their company; sysadmins see everything.
 */
class RentCollectionController extends Controller
{
    public function index(Request $request): View
    {
        $companyIds = $this->companyIds($request);
        $tab = in_array($request->query('tab'), ['paybill', 'messages', 'paused', 'attempts'], true) ? $request->query('tab') : 'paybill';
        $scope = fn ($q) => $companyIds === null ? $q : $q->whereIn('company_id', $companyIds ?: [0]);

        $paybill = $scope(PaybillPayment::query()->with('tenancy.tenant.user', 'tenancy.unit.property'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->orderByRaw("status = 'unmatched' desc"))
            ->latest('paid_at')->paginate(25, ['*'], 'page')->withQueryString();
        $messages = $scope(RentMessage::query()->with('tenancy.tenant.user', 'tenancy.unit.property'))
            ->where('status', '!=', 'grouped')
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->query('failed'), fn ($q) => $q->whereIn('status', ['failed', 'no_phone']))
            ->latest('id')->paginate(25, ['*'], 'page')->withQueryString();
        $holds = $scope(RentHold::query()->with('tenancy.tenant.user', 'tenancy.unit.property'))->latest('id')->get();
        $company = $companyIds && count($companyIds) === 1 ? $companyIds[0] : null;

        // Payment attempts (docs/APP_DATA.md): every Pay press in the last 30 days and how it ended.
        $attemptsQ = fn () => $scope(\Illuminate\Support\Facades\DB::table('payment_attempts'))->where('created_at', '>=', now()->subDays(30));
        $attempts = \App\Services\Payments\PaymentAttempts::ready() ? [
            'by_status' => $attemptsQ()->selectRaw('status, COUNT(*) n, SUM(amount) amount')->groupBy('status')->get()->keyBy('status'),
            'by_channel' => $attemptsQ()->selectRaw('channel, COUNT(*) n, SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) paid')->groupBy('channel')->get()->keyBy('channel'),
            'recent' => $tab === 'attempts' ? $attemptsQ()->orderByDesc('id')->limit(40)->get() : collect(),
        ] : null;

        return view('rent.index', [
            'tab' => $tab,
            'attempts' => $attempts,
            'paybill' => $paybill,
            'messages' => $messages,
            'holds' => $holds,
            'counts' => [
                'unmatched' => $scope(PaybillPayment::where('status', 'unmatched'))->count(),
                'failed' => $scope(RentMessage::whereIn('status', ['failed', 'no_phone']))->count(),
                'paused' => $holds->count(),
                'owing' => $company ? $this->owing($company)->count() : null,
            ],
            'settings' => $company ? RentSettings::for($company) : null,
            'company' => $company,
            // Active tenancies to pick from when matching a payment or pausing reminders.
            'tenancies' => $company ? RentRun::ownedByQuery($company)->where('status', 'active')->with('tenant.user', 'unit.property')->get()
                ->sortBy(fn ($t) => ($t->unit?->property?->name ?? '').' '.str_pad((string) $t->unit?->unit_number, 8, '0', STR_PAD_LEFT))->values() : collect(),
        ]);
    }

    public function assign(Request $request, PaybillPayment $payment, PaybillPayments $paybill): RedirectResponse
    {
        $this->authorizeCompany($request, $payment->company_id);
        $data = $request->validate(['tenancy_id' => ['required', 'integer']]);
        $tenancy = Tenancy::findOrFail($data['tenancy_id']); // company scope: only the user's own tenancies
        if ($payment->company_id === null) {
            $this->authorizeCompany($request, RentRun::companyOf($tenancy));
        }
        $paybill->assign($payment, $tenancy, $request->user()->id);

        return back()->with('success', 'KES '.number_format($payment->amount).' recorded for '.($tenancy->tenant?->user?->name ?? 'the tenant').'.');
    }

    public function ignore(Request $request, PaybillPayment $payment, PaybillPayments $paybill): RedirectResponse
    {
        $this->authorizeCompany($request, $payment->company_id);
        $paybill->ignore($payment, $request->user()->id, $request->input('note'));

        return back()->with('success', 'Marked as not rent.');
    }

    public function retry(Request $request, RentMessage $message, BillNotifier $notifier): RedirectResponse
    {
        $this->authorizeCompany($request, $message->company_id);
        $message = $notifier->retry($message);

        return back()->with($message->status === 'sent' ? 'success' : 'error', $message->status === 'sent' ? 'Sent.' : 'Still not sent: '.$message->error);
    }

    public function pause(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenancy_id' => ['required', 'integer'], 'until' => ['nullable', 'date', 'after_or_equal:today'], 'reason' => ['nullable', 'string', 'max:255']]);
        $tenancy = Tenancy::findOrFail($data['tenancy_id']);
        $companyId = RentRun::companyOf($tenancy);
        $this->authorizeCompany($request, $companyId);
        RentHold::updateOrCreate(['tenancy_id' => $tenancy->id], ['company_id' => $companyId, 'until' => $data['until'] ?? null,
            'reason' => $data['reason'] ?? null, 'created_by' => $request->user()->id]);

        return redirect()->route('rent.index', ['tab' => 'paused'])->with('success', 'Reminders and penalties paused for '.($tenancy->tenant?->user?->name ?? 'this tenant').'.');
    }

    public function resume(Request $request, RentHold $hold): RedirectResponse
    {
        $this->authorizeCompany($request, $hold->company_id);
        $hold->delete();

        return back()->with('success', 'Reminders are on again for this tenant.');
    }

    /** Send every tenant who owes a fresh link to their oldest unpaid bill (at most once a day each). */
    public function resendLinks(Request $request, BillNotifier $notifier): RedirectResponse
    {
        $companyIds = $this->companyIds($request);
        abort_unless($companyIds && count($companyIds) === 1, 422, 'Open this page as a company user to send its bill links.');
        $sent = $failed = 0;
        foreach ($this->owing($companyIds[0])->get()->groupBy('tenancy_id') as $invoices) {
            $row = $notifier->resendLink($invoices->first());
            if ($row) {
                $row->status === 'sent' ? $sent++ : $failed++;
            }
        }

        return back()->with('success', "Fresh bill links sent to {$sent} ".($sent === 1 ? 'tenant' : 'tenants').'.'.($failed ? " {$failed} could not be sent: see Messages." : ''));
    }

    /** Unpaid bills of active tenancies, oldest first. */
    private function owing(int $companyId)
    {
        return Invoice::with('tenancy.tenant.user', 'tenancy.unit.property')->whereIn('status', ['unpaid', 'partial'])
            ->whereColumn('total_paid', '<', 'total_amount')
            ->whereHas('tenancy', fn ($t) => $t->where('status', 'active')->where(fn ($q) => RentRun::ownedBy($q, $companyId)))
            ->orderBy('billing_month')->orderBy('id');
    }

    /** null = every company (sysadmin). */
    private function companyIds(Request $request): ?array
    {
        $user = $request->user();
        abort_if($user->hasRole('tenant') && ! $user->hasAnyRole(['sysadmin', 'admin', 'property_manager', 'accountant']), 403);
        if ($user->hasRole('sysadmin')) {
            return $user->company_id ? [(int) $user->company_id] : null;
        }

        return $user->company_id ? [(int) $user->company_id] : [];
    }

    private function authorizeCompany(Request $request, $companyId): void
    {
        $ids = $this->companyIds($request);
        abort_unless($ids === null || ($companyId && in_array((int) $companyId, $ids, true)) || ($companyId === null && $request->user()->hasRole('sysadmin')), 404);
    }
}
