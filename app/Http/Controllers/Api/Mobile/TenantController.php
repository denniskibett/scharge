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
                'next_bill' => ($n = $open->sortBy('billing_month')->first()) ? $this->invoice($n->load('items'), true) + self::due($n) : null,
                'credit' => self::money(self::credit($ids)),
                'notices' => self::notices($ids, $tenancy),
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
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'image|max:6144',
            'caretaker_can_let_in' => 'nullable|boolean',
        ]);
        $ids = MobileAccess::tenantIds($request->user());
        $photos = collect($request->file('photos', []))->map(fn ($f) => $f->store('repair-photos/'.now()->format('Y/m'), 'public'))->values()->all();
        if ($request->boolean('caretaker_can_let_in')) {
            $data['description'] .= "\n\nThe caretaker can let the fundi in if I am out.";
        }

        return CompanyAccess::bypass(function () use ($ids, $data, $photos, $request) {
            // The flat they live in.
            $home = MobileAccess::homeUnitId($request->user());
            $tenancy = Tenancy::withoutGlobalScope('company')->with('unit.property')->whereIn('tenant_id', $ids ?: [0])->where('status', 'active')
                ->when($home, fn ($q) => $q->where('unit_id', $home))->latest('id')->first();
            abort_unless($tenancy?->unit, 422, 'You need an active tenancy to report a repair.');
            $repair = new Maintenance(['unit_id' => $tenancy->unit_id, 'tenant_id' => $tenancy->tenant_id, 'name' => $data['title'],
                'description' => $data['description'], 'category' => $data['category'] ?? 'other', 'priority' => $data['priority'] ?? 'medium', 'status' => 'open',
                'images' => $photos ?: null]);
            $repair->forceFill(['company_id' => \App\Services\Rent\RentRun::companyOf($tenancy)])->save();

            return response()->json($this->repair($repair->load('unit.property')), 201);
        });
    }

    /**
     * How this bill can be paid, for the Pay screen: M-Pesa (the bill link page sends the prompt), Sharet Pay,
     * the landlord's paybill (unit as the account), and bank standing order, each only when the landlord has it set up.
     */
    public function payOptions(Request $request, int $id)
    {
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(function () use ($ids, $id) {
            $invoice = $this->invoices($ids)->with('tenancy.unit.property', 'tenancy.tenant.user')->find($id);
            abort_unless($invoice, 404, 'No such bill.');
            $company = $invoice->owningCompanyId();
            $unit = $invoice->tenancy?->unit;
            $reference = $unit?->unit_number ?? ('INV'.$invoice->id);
            $daraja = self::setting($company, 'mpesa_daraja');
            $daraja = $daraja && filled($daraja->get('shortcode')) && filled($daraja->get('passkey')) ? $daraja : null;
            $bank = $company ? self::setting($company, 'bank_standing_order') : null;
            $methods = [
                ['key' => 'mpesa', 'title' => 'M-Pesa', 'subtitle' => 'Prompt on your phone', 'url' => \App\Support\InvoiceLink::url($invoice).'&from=app',
                    'phone' => ($ph = preg_replace('/\D/', '', (string) $invoice->tenancy?->tenant?->user?->phone)) ? '0'.substr($ph, -9, 3).' ••• '.substr($ph, -3) : null],
            ];
            if (filled(self::setting(null, 'sharet')?->get('api_key'))) {
                $methods[] = ['key' => 'sharet', 'title' => 'Sharet Pay', 'subtitle' => 'M-Pesa or your Sharet wallet'];
            }
            if ($daraja && ($daraja->get('kind', 'paybill') === 'paybill')) {
                $methods[] = ['key' => 'paybill', 'title' => 'Paybill '.$daraja->get('shortcode'), 'subtitle' => 'Account: '.strtoupper($reference),
                    'paybill' => (string) $daraja->get('shortcode'), 'account' => $reference];
            }
            if ($bank && filled($bank->get('account_number'))) {
                $methods[] = ['key' => 'bank', 'title' => 'Bank standing order', 'subtitle' => 'Set up once, every 1st',
                    'bank' => $bank->get('bank'), 'account_name' => $bank->get('account_name'), 'account_number' => $bank->get('account_number'),
                    'branch' => $bank->get('branch'), 'reference' => $reference, 'note' => $bank->get('note'),
                    'monthly' => $unit ? self::money($unit->total_monthly_charges ?? $unit->rent_amount) : null];
            }

            $credit = self::credit($ids);
            if ($credit > 0) {
                array_unshift($methods, ['key' => 'credit', 'title' => 'My scharge credit', 'subtitle' => 'KES '.number_format($credit, 2).' available', 'available' => self::money($credit)]);
            }

            return response()->json(['balance' => self::money(max(0, (float) $invoice->total_amount - (float) $invoice->total_paid)), 'credit' => self::money($credit),
                // Paying more than the bill (M-Pesa, Sharet or paybill) keeps the extra as credit for the next bill.
                'pay_ahead' => true, 'methods' => $methods]);
        });
    }

    /**
     * Pay a bill from the tenant's credit (money paid ahead). Confirmed with the app PIN (checked here, same lockout
     * as unlocking) or with the phone's own lock (confirmed_with=device, checked on the phone). WalletService moves it.
     */
    public function payFromCredit(Request $request, int $id, \App\Modules\Payments\Services\WalletService $wallets)
    {
        $data = $request->validate(['amount' => 'nullable|numeric|min:1', 'pin' => ['nullable', 'regex:/^(\d{4}|\d{6})$/'], 'confirmed_with' => 'nullable|in:pin,device']);
        $session = $request->attributes->get('mobile_session');
        if (($data['confirmed_with'] ?? 'pin') !== 'device' && ($fail = AuthController::checkPin($session, $data['pin'] ?? null))) {
            return $fail;
        }
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(function () use ($ids, $id, $data, $wallets, $session) {
            $invoice = $this->invoices($ids)->with('tenancy.tenant')->find($id);
            abort_unless($invoice, 404, 'No such bill.');
            $owed = max(0, (float) $invoice->total_amount - (float) $invoice->total_paid);
            $amount = min($owed, (float) ($data['amount'] ?? $owed));
            abort_if($amount <= 0, 422, 'Nothing left to pay on this bill.');
            $tenant = $invoice->tenancy?->tenant;
            abort_unless($tenant, 422, 'No tenant on this bill.');
            $r = $wallets->payInvoice($tenant, $invoice, round($amount, 2), ['payment_method' => 'wallet',
                'description' => 'Paid from credit in the scharge app', 'confirmed_with' => $data['confirmed_with'] ?? 'pin', 'mobile_session_id' => $session->id]);
            if (! ($r['success'] ?? false)) {
                return response()->json(['error' => 'credit_failed', 'message' => $r['error'] ?? 'Could not pay from credit.'], 422);
            }

            return response()->json(['paid' => self::money($amount), 'credit' => self::money(self::credit($ids)), 'bill' => $this->invoice($invoice->fresh())], 201);
        });
    }

    /**
     * Building notices on the home screen (canvas: "Building notices"), from live data: the lease ending soon, repairs
     * with a fundi on them or done, and guests waiting for this tenant's approval. alert = the dark bar.
     */
    private static function notices(array $tenantIds, $tenancy): array
    {
        $out = [];
        if ($tenancy?->move_out_date && $tenancy->move_out_date->isFuture() && $tenancy->move_out_date->lte(now()->addDays(60))) {
            $out[] = ['title' => 'Your lease ends '.$tenancy->move_out_date->format('j M'), 'text' => 'Talk to your manager about renewing before then.', 'alert' => false, 'screen' => null];
        }
        foreach (Maintenance::with('assignedStaff')->whereIn('tenant_id', $tenantIds ?: [0])->whereIn('status', ['open', 'in_progress', 'pending_parts', 'completed'])
            ->where('updated_at', '>=', now()->subDays(7))->latest('updated_at')->limit(2)->get() as $m) {
            $out[] = match (true) {
                $m->status === 'completed' => ['title' => $m->name.': done', 'text' => 'Marked done. Tell your manager if it is not fixed.', 'alert' => false, 'screen' => 'tenant_repairs'],
                $m->status === 'pending_parts' => ['title' => $m->name, 'text' => 'Waiting for parts.', 'alert' => false, 'screen' => 'tenant_repairs'],
                (bool) $m->assignedStaff => ['title' => $m->name, 'text' => 'Fundi assigned: '.$m->assignedStaff->name.'.', 'alert' => false, 'screen' => 'tenant_repairs'],
                default => ['title' => $m->name, 'text' => 'Reported. Your manager will assign a fundi.', 'alert' => false, 'screen' => 'tenant_repairs'],
            };
        }
        if ($tenancy?->unit_id) {
            $n = \App\Modules\Security\Models\GatePass::where('status', 'suggested')->where('unit_id', $tenancy->unit_id)->count();
            if ($n) {
                $out[] = ['title' => $n === 1 ? 'A guest is waiting for your OK' : "$n guests are waiting for your OK", 'text' => 'Suggested for your home. Approve or decline.', 'alert' => true, 'screen' => 'gate'];
            }
        }

        return $out;
    }

    /** When a bill is due (rent automation's due day, else the 5th) and how many days are left, for the home card. */
    private static function due($invoice): array
    {
        $month = $invoice->billing_month ? \Carbon\CarbonImmutable::parse($invoice->billing_month) : null;
        if (! $month) {
            return [];
        }
        $settings = ($c = $invoice->owningCompanyId()) ? \App\Services\Rent\RentSettings::for($c) : null;
        $due = $settings ? $settings->dueDate($month) : $month->startOfMonth()->setDay(5);
        $days = (int) now(\App\Services\Rent\RentSettings::TZ)->startOfDay()->diffInDays($due->startOfDay(), false);

        return ['due_date' => $due->toDateString(), 'due_label' => $due->format('j M'), 'days_left' => $days];
    }

    /**
     * The wallet: money paid ahead (credit), its history, and how to add money. Adding money = paying the landlord's
     * paybill with the unit as the account (credit when nothing is owed), or paying more than a bill.
     */
    public function wallet(Request $request)
    {
        $ids = MobileAccess::tenantIds($request->user());
        $home = MobileAccess::homeUnitId($request->user());

        return CompanyAccess::bypass(function () use ($ids, $home) {
            $tenants = \App\Models\Tenant::withoutGlobalScope('company')->whereIn('id', $ids ?: [0])->get();
            $tx = $tenants->flatMap(fn ($t) => rescue(fn () => $t->transactions()->where('confirmed', true)->latest('id')->limit(40)->get(), collect(), false))
                ->sortByDesc('id')->take(40)->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type, // deposit | withdraw
                    'amount' => self::money(abs((float) $t->amount)), // stored in shillings, like the balance
                    'description' => $t->meta['description'] ?? ($t->type === 'deposit' ? 'Money in' : 'Paid a bill'),
                    'at' => $t->created_at?->toIso8601String(),
                ])->values();
            $unit = $home ? \App\Models\Unit::withoutGlobalScope('company')->with('property')->find($home) : null;
            $company = $unit ? ($unit->company_id ?? $unit->property?->company_id) : null;
            $daraja = $company ? self::setting((int) $company, 'mpesa_daraja') : null;

            return response()->json([
                'balance' => self::money(self::credit($ids)),
                'transactions' => $tx,
                'add_money' => array_values(array_filter([
                    $daraja && filled($daraja->get('shortcode')) ? ['key' => 'paybill', 'title' => 'M-Pesa Paybill', 'paybill' => (string) $daraja->get('shortcode'),
                        'account' => $unit->unit_number, 'note' => 'Pay any amount. Anything not owed is kept here as credit.'] : null,
                    ['key' => 'overpay', 'title' => 'Pay more than your bill', 'note' => 'On the Pay screen, type more than you owe. The extra comes here.'],
                ])),
            ]);
        });
    }

    /** Money paid ahead: the tenant's wallet balances (one wallet per landlord company's tenant record). */
    private static function credit(array $tenantIds): float
    {
        return (float) \App\Models\Tenant::withoutGlobalScope('company')->whereIn('id', $tenantIds ?: [0])->get()
            ->sum(fn ($t) => rescue(fn () => (float) $t->balance, 0, false));
    }

    /** Sharet Pay: a Sharet payment page for this bill (or part of it). Money still settles through SharetCollections. */
    public function sharet(Request $request, int $id, \App\Services\Sharet\SharetCollections $collections)
    {
        $data = $request->validate(['amount' => 'nullable|numeric|min:1']);
        abort_unless(filled(self::setting(null, 'sharet')?->get('api_key')), 422, 'Sharet Pay is not switched on yet.');
        $ids = MobileAccess::tenantIds($request->user());

        return CompanyAccess::bypass(function () use ($ids, $id, $data, $request, $collections) {
            $invoice = $this->invoices($ids)->with('tenancy.tenant.user')->find($id);
            abort_unless($invoice, 404, 'No such bill.');
            try {
                $link = $collections->payLink($invoice, isset($data['amount']) ? (float) $data['amount'] : null, $request->user()->phone);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'sharet_failed', 'message' => $e->getMessage()], 422);
            }

            return response()->json(['url' => $link->pay_url, 'amount' => self::money($link->amount), 'expires_at' => $link->expires_at?->toIso8601String()]);
        });
    }

    /**
     * Water for the flat the tenant lives in: this month, the monthly history (the billing readings), the last meter
     * reading with who read it and the photo, and the rate. Only their home unit (MobileAccess::homeUnitId).
     */
    public function water(Request $request)
    {
        $unitId = MobileAccess::homeUnitId($request->user());
        abort_unless($unitId, 404, 'You need an active tenancy to see water.');

        return CompanyAccess::bypass(function () use ($unitId) {
            $unit = \App\Models\Unit::withoutGlobalScope('company')->with('property')->find($unitId);
            $meter = \App\Models\Meter::where('unit_id', $unitId)->where('utility', 'water')->where('status', 'active')->latest('id')->first();
            $rate = $meter?->rate() ?? (float) ($unit?->custom_water_rate ?? $unit?->property?->water_rate ?? 0);
            $history = \App\Models\WaterReading::where('unit_id', $unitId)->orderByDesc('reading_date')->limit(12)->get()
                ->map(fn ($w) => [
                    'month' => \Carbon\Carbon::parse($w->reading_date)->format('Y-m'),
                    'label' => \Carbon\Carbon::parse($w->reading_date)->format('M'),
                    'consumption' => round((float) $w->consumption, 2),
                    'reading' => round((float) $w->current_reading, 2),
                    'charge' => self::money($w->charge ?? ((float) $w->consumption * $rate)),
                ])->reverse()->values();
            $last = $meter ? \App\Models\MeterReading::where('meter_id', $meter->id)->where('status', '!=', 'rejected')->latest('read_at')->first() : null;
            $now = $history->last();
            $avg = $history->count() > 1 ? round($history->slice(0, -1)->avg('consumption'), 2) : null;

            return response()->json([
                'unit' => $unit?->unit_number,
                'property' => $unit?->property?->name,
                'billing' => $unit?->water_billing_type ?? 'consumption', // consumption | fixed
                'fixed_charge' => $unit && ($unit->water_billing_type ?? null) === 'fixed' ? self::money($unit->water_charge) : null,
                'rate' => $rate ? self::money($rate) : null,
                'this_month' => $now,
                'average' => $avg,
                'history' => $history,
                'meter' => $meter ? ['serial_number' => $meter->serial_number, 'unit_of_measure' => $meter->unit_of_measure] : null,
                'last_reading' => $last ? [
                    'id' => $last->id, 'reading' => $last->reading, 'previous' => $last->previous_reading, 'consumption' => $last->consumption,
                    'read_at' => $last->read_at?->toIso8601String(), 'status' => $last->status,
                    'read_by' => $last->recorded_by ? \App\Models\User::whereKey($last->recorded_by)->value('name') : null,
                    'photo_url' => $last->photo_path ? asset('storage/'.$last->photo_path) : null,
                ] : null,
            ]);
        });
    }

    /** "Query this reading": a ticket for the manager about the water reading (shows in their work orders). */
    public function waterQuery(Request $request)
    {
        $data = $request->validate(['note' => 'required|string|min:5|max:1000']);
        $ids = MobileAccess::tenantIds($request->user());
        $unitId = MobileAccess::homeUnitId($request->user());
        abort_unless($unitId, 422, 'You need an active tenancy.');

        return CompanyAccess::bypass(function () use ($ids, $unitId, $data) {
            $tenancy = Tenancy::withoutGlobalScope('company')->with('unit.property')->whereIn('tenant_id', $ids ?: [0])->where('unit_id', $unitId)->where('status', 'active')->latest('id')->first();
            $meter = \App\Models\Meter::where('unit_id', $unitId)->where('utility', 'water')->latest('id')->first();
            $last = $meter ? \App\Models\MeterReading::where('meter_id', $meter->id)->latest('read_at')->first() : null;
            $repair = new Maintenance(['unit_id' => $unitId, 'tenant_id' => $tenancy->tenant_id, 'name' => 'Water reading query', 'category' => 'plumbing', 'priority' => 'medium', 'status' => 'open',
                'description' => $data['note'].($last ? "\n\nLast reading: {$last->reading} (previous {$last->previous_reading}) on ".$last->read_at?->format('j M Y').'.' : '')]);
            $repair->forceFill(['company_id' => \App\Services\Rent\RentRun::companyOf($tenancy)])->save();

            return response()->json(['sent' => true, 'number' => $repair->request_number, 'message' => 'Sent to your manager. Follow it under Repairs.'], 201);
        });
    }

    /** A switched-on Integrations row (null company = scharge's own), read directly. */
    private static function setting(?int $companyId, string $provider): ?\App\Models\CompanyIntegration
    {
        return \App\Models\CompanyIntegration::where('provider', $provider)->where('enabled', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId), fn ($q) => $q->whereNull('company_id'))->latest('id')->first();
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
