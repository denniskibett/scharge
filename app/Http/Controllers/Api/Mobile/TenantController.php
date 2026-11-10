<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Support\CompanyAccess;
use App\Support\MobileAccess;
use Illuminate\Http\Request;

/**
 * Tenants in the scharge app: their own bills, receipts and repairs only. Everything is filtered by the person's own
 * tenant records (MobileAccess::tenantIds), never by a company, so one tenant never sees another's rows.
 */
class TenantController extends Controller
{
    use Presents;

    public function summary(Request $request)
    {
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(function () use ($ids) {
            $tenancy = Tenancy::withoutGlobalScope('company')->with('unit.property')->whereIn('tenant_id', $ids ?: [0])
                ->orderByRaw("status = 'active' desc")->latest('id')->first();
            $open = $this->invoices($ids)->whereIn('status', ['unpaid', 'partial', 'overdue'])->whereColumn('total_paid', '<', 'total_amount')->get();
            $last = Payment::withoutGlobalScope('company')->whereIn('tenant_id', $ids ?: [0])->where('status', 'completed')->latest('id')->first();

            return response()->json([
                'unit' => $tenancy?->unit?->unit_number,
                'property' => $tenancy?->unit?->property?->name,
                'rent' => $tenancy?->unit ? self::money($tenancy->unit->rent_amount) : null,
                'balance' => self::money($open->sum(fn ($i) => (float) $i->total_amount - (float) $i->total_paid)),
                'open_bills' => $open->count(),
                'next_bill' => ($n = $open->sortBy('billing_month')->first()) ? $this->invoice($n) : null,
                'last_payment' => $last ? $this->payment($last) : null,
            ]);
        });
    }

    public function bills(Request $request)
    {
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(fn () => response()->json(['data' => $this->invoices($ids)
            ->when($request->query('status') === 'open', fn ($q) => $q->whereIn('status', ['unpaid', 'partial', 'overdue']))
            ->with('tenancy.unit.property')->orderByDesc('billing_month')->orderByDesc('id')->limit(60)->get()
            ->map(fn ($i) => $this->invoice($i))->values()]));
    }

    public function bill(Request $request, int $id)
    {
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(function () use ($ids, $id) {
            $invoice = $this->invoices($ids)->with('items', 'tenancy.unit.property')->find($id);
            abort_unless($invoice, 404, 'No such bill.');

            return response()->json($this->invoice($invoice, true));
        });
    }

    public function payments(Request $request)
    {
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(fn () => response()->json(['data' => Payment::withoutGlobalScope('company')
            ->where(fn ($q) => $q->whereIn('tenant_id', $ids ?: [0])->orWhereIn('invoice_id', $this->invoices($ids)->select('invoices.id')))
            ->with(['tenant.user', 'invoice' => fn ($q) => $q->withoutGlobalScope('company'), 'invoice.tenancy' => fn ($q) => $q->withoutGlobalScope('company'),
                'invoice.tenancy.unit' => fn ($q) => $q->withoutGlobalScope('company'), 'invoice.tenancy.unit.property'])
            ->where('status', 'completed')->latest('id')->limit(60)->get()->map(fn ($p) => $this->payment($p))->values()]));
    }

    public function repairs(Request $request)
    {
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(fn () => response()->json(['data' => Maintenance::with('unit.property', 'assignedStaff')
            ->whereIn('tenant_id', $ids ?: [0])->latest('id')->limit(60)->get()->map(fn ($m) => $this->repair($m))->values()]));
    }

    public function reportRepair(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:2000',
            'category' => 'nullable|in:plumbing,electrical,hvac,appliance,structural,pest_control,cleaning,other',
            'priority' => 'nullable|in:low,medium,high,emergency',
        ]);
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(function () use ($ids, $data) {
            $tenancy = Tenancy::withoutGlobalScope('company')->with('unit.property')->whereIn('tenant_id', $ids ?: [0])->where('status', 'active')->latest('id')->first();
            abort_unless($tenancy?->unit, 422, 'You need an active tenancy to report a repair.');
            $repair = new Maintenance(['unit_id' => $tenancy->unit_id, 'tenant_id' => $tenancy->tenant_id, 'name' => $data['title'],
                'description' => $data['description'], 'category' => $data['category'] ?? 'other', 'priority' => $data['priority'] ?? 'medium', 'status' => 'open']);
            $repair->forceFill(['company_id' => \App\Services\Rent\RentRun::companyOf($tenancy)])->save();

            return response()->json($this->repair($repair->load('unit.property')), 201);
        });
    }

    /** Anonymous suggestion to the landlord: only the company and the text are saved (no tenant, unit or phone). */
    public function suggest(Request $request)
    {
        $data = $request->validate(['body' => 'required|string|min:5|max:2000',
            'topic' => 'nullable|in:'.implode(',', array_keys(\App\Models\TenantSuggestion::TOPICS))]);
        $company = MobileAccess::companyFor($request->user());
        abort_unless($company, 422, 'You need an active tenancy to send a suggestion.');
        \App\Models\TenantSuggestion::create(['company_id' => $company, 'topic' => $data['topic'] ?? 'general', 'body' => $data['body']]);

        return response()->json(['sent' => true, 'message' => 'Thank you. Your suggestion went to management without your name or unit.'], 201);
    }

    /** The person's own invoices, through their tenancies (no company scope: tenants have no company). */
    private function invoices(array $tenantIds)
    {
        return Invoice::withoutGlobalScope('company')->whereIn('tenancy_id',
            Tenancy::withoutGlobalScope('company')->whereIn('tenant_id', $tenantIds ?: [0])->select('id'));
    }
}
