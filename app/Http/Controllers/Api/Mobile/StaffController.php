<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaybillPayment;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\Rent\PaybillPayments;
use App\Support\CompanyAccess;
use App\Support\MobileAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Owners, landlord staff and scharge staff in the scharge app: money, tenants, units, bookings, companies.
 * Every query goes through the "company" global scopes (CompanyAccess): a company user only ever finds their own
 * company's rows; sysadmins see all; account managers see the companies they own. Routes check the permission first.
 */
class StaffController extends Controller
{
    use Presents;

    private const OPEN = ['unpaid', 'partial', 'overdue'];

    public function rent(Request $request)
    {
        $month = CarbonImmutable::now('Africa/Nairobi')->startOfMonth();
        $billed = Invoice::whereDate('billing_month', '>=', $month->toDateString())->whereDate('billing_month', '<=', $month->endOfMonth()->toDateString());
        $open = Invoice::whereIn('status', self::OPEN)->whereColumn('total_paid', '<', 'total_amount');
        $ids = CompanyAccess::ids();

        return response()->json([
            'month' => $month->format('F Y'),
            'billed' => self::money((clone $billed)->sum('total_amount')),
            'paid_of_billed' => self::money((clone $billed)->sum('total_paid')),
            'collected' => self::money(Payment::where('status', 'completed')->where('created_at', '>=', $month->utc())->sum('amount')),
            'outstanding' => self::money((clone $open)->selectRaw('COALESCE(SUM(total_amount - total_paid), 0) as owed')->value('owed')),
            'unpaid_bills' => (clone $open)->count(),
            'unmatched_paybill' => PaybillPayment::where('status', 'unmatched')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))->count(),
            'occupied_units' => Unit::where('status', 'occupied')->count(),
            'units' => Unit::count(),
        ]);
    }

    public function bills(Request $request)
    {
        $status = $request->query('status', 'open');
        $s = trim((string) $request->query('q'));
        $q = Invoice::with('tenancy.tenant.user', 'tenancy.unit.property')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', self::OPEN)->whereColumn('total_paid', '<', 'total_amount'))
            ->when(in_array($status, ['paid', 'unpaid', 'partial', 'overdue'], true), fn ($q) => $q->where('status', $status))
            ->when($s !== '', fn ($q) => $q->whereHas('tenancy', fn ($t) => $t->whereHas('tenant.user', fn ($u) => $u->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('unit', fn ($u) => $u->where('unit_number', 'like', "%$s%"))))
            ->orderBy('billing_month')->orderBy('id');

        return response()->json($this->page($q, fn ($i) => $this->invoice($i), $request));
    }

    public function bill(int $id)
    {
        $invoice = Invoice::with('items', 'tenancy.tenant.user', 'tenancy.unit.property', 'payments')->find($id);
        abort_unless($invoice, 404, 'No such bill.');

        return response()->json($this->invoice($invoice, true) + ['payments' => $invoice->payments->map(fn ($p) => $this->payment($p))->values()]);
    }

    public function payments(Request $request)
    {
        $q = Payment::with('tenant.user', 'user', 'invoice.tenancy.unit.property')->where('status', 'completed')->latest('id');

        return response()->json($this->page($q, fn ($p) => $this->payment($p), $request));
    }

    public function tenants(Request $request)
    {
        $s = trim((string) $request->query('q'));
        $q = Tenancy::with('tenant.user', 'unit.property')->where('status', 'active')
            ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w->whereHas('tenant.user', fn ($u) => $u->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('unit', fn ($u) => $u->where('unit_number', 'like', "%$s%"))))
            ->orderBy('unit_id');

        // Balances for the whole page in one grouped query (was one query per tenant).
        $p = $q->paginate(min(50, max(10, (int) $request->query('per_page', 25))));
        $owed = Invoice::whereIn('tenancy_id', collect($p->items())->pluck('id')->all() ?: [0])->whereIn('status', self::OPEN)
            ->selectRaw('tenancy_id, COALESCE(SUM(total_amount - total_paid), 0) as owed')->groupBy('tenancy_id')->pluck('owed', 'tenancy_id');

        return response()->json(['data' => collect($p->items())->map(fn ($t) => [
            'tenancy_id' => $t->id, 'tenant_id' => $t->tenant_id, 'name' => $t->tenant?->user?->name, 'phone' => $t->tenant?->user?->phone,
            'unit' => $t->unit?->unit_number, 'property' => $t->unit?->property?->name, 'moved_in' => $t->move_in_date?->format('Y-m-d'),
            'balance' => self::money($owed[$t->id] ?? 0),
        ])->values(), 'page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]);
    }

    public function units(Request $request)
    {
        $s = trim((string) $request->query('q'));
        $q = Unit::with('property:id,name')->when($request->query('status'), fn ($q, $st) => $q->where('status', $st))
            ->when($s !== '', fn ($q) => $q->where('unit_number', 'like', "%$s%"))->orderBy('property_id')->orderBy('unit_number');

        return response()->json($this->page($q, fn ($u) => ['id' => $u->id, 'number' => $u->unit_number, 'property' => $u->property?->name,
            'type' => $u->unit_type, 'status' => $u->status, 'stay_type' => $u->stay_type, 'rent' => self::money($u->rent_amount)], $request));
    }

    public function bookings(Request $request)
    {
        $units = Unit::with('property:id,name')->whereIn('stay_type', MobileAccess::SHORT_STAY)->orderBy('unit_number')->get();
        // Agencies see landlords' units given to them (property_agents), but the money stays the landlord's.
        $ids = \App\Support\CompanyAccess::ids();
        $granted = \App\Services\Agents\AgentAccess::propertyIds($request->user());
        $hideAmount = $units->filter(fn ($u) => $ids !== null && in_array((int) $u->property_id, $granted, true) && ! in_array((int) $u->company_id, $ids, true))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $bookings = Booking::with('unit:id,unit_number')->whereIn('unit_id', $units->pluck('id')->all() ?: [0])->where('status', 'confirmed')
            ->whereDate('check_out', '>=', now()->toDateString())->orderBy('check_in')->limit(100)->get();

        return response()->json([
            'units' => $units->map(fn ($u) => ['id' => $u->id, 'number' => $u->unit_number, 'property' => $u->property?->name, 'status' => $u->status])->values(),
            'data' => $bookings->map(fn ($b) => ['id' => $b->id, 'unit' => $b->unit?->unit_number, 'guest' => $b->guest_name, 'phone' => $b->guest_phone,
                'guests' => $b->guests, 'check_in' => $b->check_in->format('Y-m-d'), 'check_out' => $b->check_out->format('Y-m-d'), 'nights' => $b->nights,
                'source' => Booking::SOURCES[$b->source] ?? $b->source, 'amount' => $b->amount !== null && ! in_array((int) $b->unit_id, $hideAmount, true) ? self::money($b->amount) : null])->values(),
        ]);
    }

    public function companies(Request $request)
    {
        $ids = CompanyAccess::ids();
        abort_unless(MobileAccess::seesCompanies($request->user()), 403, 'Companies are for scharge admins and account managers.');
        $q = Company::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where('name', 'like', "%$s%"))->orderBy('name');

        return response()->json($this->page($q, fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'email' => $c->email,
            'active' => (bool) $c->is_active, 'lifecycle' => $c->lifecycle], $request));
    }

    /** Paybill payments scharge could not match to a tenant (Rent collection's first tab). */
    public function unmatched(Request $request)
    {
        $ids = CompanyAccess::ids();
        $rows = PaybillPayment::where('status', 'unmatched')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))->latest('paid_at')->limit(50)->get();

        return response()->json(['data' => $rows->map(fn ($r) => ['id' => $r->id, 'amount' => self::money($r->amount), 'reference' => $r->trans_id,
            'account' => $r->bill_ref, 'payer' => $r->payer_name, 'phone' => $r->msisdn, 'paid_at' => $r->paid_at?->toIso8601String()])->values()]);
    }

    /** Give an unmatched paybill payment to a tenant: money moves only through PaybillPayments. */
    public function assign(Request $request, int $id, PaybillPayments $paybill)
    {
        $data = $request->validate(['tenancy_id' => 'required|integer']);
        $ids = CompanyAccess::ids();
        $row = PaybillPayment::where('status', 'unmatched')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))->find($id);
        abort_unless($row, 404, 'No such payment.');
        $tenancy = Tenancy::find($data['tenancy_id']); // company scope: only the user's own tenancies
        abort_unless($tenancy, 422, 'Pick one of your tenants.');
        $paybill->assign($row, $tenancy, $request->user()->id);

        return response()->json(['assigned' => true, 'status' => $row->fresh()->status]);
    }

    /** The suggestion box for this company's admins. Opening the list marks them read. */
    public function suggestions(Request $request)
    {
        $ids = CompanyAccess::ids();
        $q = \App\Models\TenantSuggestion::query()->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]))->latest('id');
        $out = $this->page($q, fn ($s) => ['id' => $s->id, 'topic' => \App\Models\TenantSuggestion::TOPICS[$s->topic] ?? $s->topic,
            'body' => $s->body, 'new' => $s->read_at === null, 'sent_at' => $s->created_at?->toIso8601String()], $request);
        (clone $q)->whereNull('read_at')->update(['read_at' => now(), 'read_by' => $request->user()->id]);

        return response()->json($out);
    }

    private function page($query, callable $map, Request $request): array
    {
        $p = $query->paginate(min(50, max(10, (int) $request->query('per_page', 25))));

        return ['data' => collect($p->items())->map($map)->values(), 'page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()];
    }
}
