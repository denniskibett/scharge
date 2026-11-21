<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * The company manual: what scharge offers and how to do each thing, as numbered chapters with buttons to the real
 * pages and ticks from the company's own data. Shown on the admin / property manager dashboard while a company is
 * setting up, and always at /guide.
 *
 * State (hidden from the dashboard, chapters marked "not for us" or "read") lives in companies.crm_profile.guide,
 * which every CRM writer merges into rather than replaces. No schema change.
 *
 * Facts must stay true to the product: prices come from welcome.blade.php #pricing-data (also in App\Support\Guides),
 * and every button is a route that exists (a missing route drops the button).
 */
class OnboardingGuide
{
    public function __construct(private Company $company, private ?User $user = null) {}

    public static function for(User $user): ?self
    {
        $company = $user->company_id ? Company::withoutGlobalScopes()->find($user->company_id) : null;

        return $company ? new self($company, $user) : null;
    }

    public function company(): Company
    {
        return $this->company;
    }

    /** The manual opens itself on the dashboard until it is complete or hidden. */
    public function showOnDashboard(): bool
    {
        return empty($this->state()['hidden_at']) && $this->progress()['done'] < $this->progress()['total'];
    }

    public function state(): array
    {
        return (array) (($this->company->crm_profile ?? [])['guide'] ?? []);
    }

    /** @param array<string, mixed> $changes */
    public function save(array $changes): void
    {
        DB::transaction(function () use ($changes) {
            $company = Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->company->id);
            $profile = $company->crm_profile ?? [];
            $guide = array_merge((array) ($profile['guide'] ?? []), $changes, ['updated_by' => $this->user?->id]);
            $profile['guide'] = array_filter($guide, fn ($v) => $v !== null && $v !== []);
            $company->forceFill(['crm_profile' => $profile])->saveQuietly();
            $this->company = $company;
        });
    }

    public function mark(string $chapter, bool $on): void
    {
        $marked = array_values(array_diff((array) ($this->state()['marked'] ?? []), [$chapter]));
        if ($on) {
            $marked[] = $chapter;
        }
        $this->save(['marked' => $marked]);
    }

    /** @return array{done: int, total: int, percent: int} */
    public function progress(): array
    {
        $chapters = $this->chapters();
        $done = count(array_filter($chapters, fn ($c) => $c['done']));

        return ['done' => $done, 'total' => count($chapters), 'percent' => (int) round(100 * $done / max(1, count($chapters)))];
    }

    /** Monthly prices, from the public pricing table. */
    public static function pricing(): array
    {
        static $p;
        if ($p !== null) {
            return $p;
        }
        $p = ['apartments' => [500, 300, 150, 40], 'short_stays' => [1500, 1000, 700, 400], 'offices' => [2000, 1500, 1000, 600], 'minimum' => 1500];
        $html = @file_get_contents(resource_path('views/welcome.blade.php'));
        if ($html && preg_match('#<script type="application/json" id="pricing-data">(.*?)</script>#s', $html, $m) && ($d = json_decode($m[1], true))) {
            $rates = collect($d['types'] ?? [])->mapWithKeys(fn ($t) => [$t['name'] => $t['rates']]);
            $p = [
                'apartments' => $rates['Apartments'] ?? $p['apartments'],
                'short_stays' => $rates['Airbnb & serviced'] ?? $p['short_stays'],
                'offices' => $rates['Offices'] ?? $p['offices'],
                'minimum' => $d['minimum'] ?? $p['minimum'],
            ];
        }

        return $p;
    }

    /** @return array<int, array> */
    public function chapters(): array
    {
        return $this->chapters ??= $this->build();
    }

    private ?array $chapters = null;

    private function build(): array
    {
        $c = $this->company->id;
        $marked = (array) ($this->state()['marked'] ?? []);
        $n = fn (string $table, ?callable $q = null) => Schema::hasTable($table)
            ? (int) tap(DB::table($table), fn ($b) => $q ? $q($b) : $b->where('company_id', $c))->count() : 0;

        $propertyIds = DB::table('properties')->where('company_id', $c)->pluck('id');
        $properties = $propertyIds->count();
        $pinned = Schema::hasColumn('properties', 'lat') ? DB::table('properties')->where('company_id', $c)->whereNotNull('lat')->count() : 0;
        $listed = Schema::hasColumn('properties', 'listed_publicly') ? DB::table('properties')->where('company_id', $c)->where('listed_publicly', 1)->count() : 0;
        $units = fn () => DB::table('units')->where(fn ($q) => $q->where('company_id', $c)->orWhereIn('property_id', $propertyIds));
        $unitCount = $units()->count();
        $priced = $units()->where('rent_amount', '>', 0)->count();
        $vacant = $units()->where('status', '!=', 'occupied')->count();
        $tenancies = $n('tenancies', fn ($q) => $q->where('company_id', $c)->where('status', 'active'));
        $invoices = $n('invoices');
        $payments = Schema::hasTable('payments') ? DB::table('payments')->join('invoices', 'invoices.id', '=', 'payments.invoice_id')->where('invoices.company_id', $c)->count() : 0;
        $mpesa = $n('company_integrations', fn ($q) => $q->where('company_id', $c)->whereIn('provider', ['mpesa_daraja', 'mpesa'])->where('enabled', 1));
        $auto = $n('company_integrations', fn ($q) => $q->where('company_id', $c)->where('provider', 'rent_automation')->where('enabled', 1));
        $meters = $n('meters');
        $readings = $n('water_readings') + $n('meter_readings');
        $repairs = $n('maintenances');
        $passes = $n('gate_passes');
        $team = $n('users');
        $kyc = Schema::hasTable('company_kyc') ? DB::table('company_kyc')->where('company_id', $c)->value('status') : null;
        $brand = $n('company_brands');
        $keys = $n('api_keys');
        $agents = $n('property_agents', fn ($q) => $q->whereIn('property_id', $propertyIds)->where('status', 'active'));
        $price = self::pricing();
        $kes = fn ($v) => 'KES '.number_format($v);

        $chapters = [
            [
                'key' => 'property', 'title' => 'Add your property',
                'what' => 'Each building (or house, or block) is one property. scharge checks that it is not already on scharge, so an owner, a manager and a host never end up with two copies of the same building.',
                'why' => 'Everything else (units, tenants, invoices, water, listings) hangs off the property.',
                'steps' => [
                    'Click <b>Add property</b>, type its name and area, and set the default water rate and service charge for its units.',
                    'Pin it: stand at the gate and tap <b>Use my location</b>, or drag the pin onto the roof on the map. The pin tells apart two buildings with the same name.',
                    'Several buildings or many units? Use <b>Import CSV</b>: one row per unit, checked before anything is saved.',
                ],
                'progress' => $properties ? "{$properties} ".str('property')->plural($properties).($pinned < $properties ? " · {$pinned} pinned" : ' · all pinned') : 'No property yet',
                'done' => $properties > 0,
                'actions' => [['Add property', 'properties.index'], ['Import CSV', 'properties.onboard'], ['CSV template', 'properties.onboard.template']],
            ],
            [
                'key' => 'units', 'title' => 'Add units with their rent',
                'what' => 'A unit is a house, flat, shop or room: its number (A1, B4…), type, rent, deposit and charges.',
                'why' => 'The unit number is what tenants type as the M-Pesa account number, and the rent is what gets invoiced.',
                'steps' => [
                    'Open <b>Units</b> and add each unit with its rent and deposit, or fill the CSV template (bedrooms, rent, deposit, service charge, meter numbers) and import it.',
                    'Mark each unit vacant or occupied.',
                    'Vacant units are billed at half price on your scharge plan.',
                ],
                'progress' => $unitCount ? "{$unitCount} units · {$priced} with rent".($vacant ? " · {$vacant} vacant" : '') : 'No units yet',
                'done' => $priced > 0,
                'actions' => [['Units', 'units.index'], ['Import CSV', 'properties.onboard']],
            ],
            [
                'key' => 'tenants', 'title' => 'Add tenants and their tenancies',
                'what' => 'A tenant is the person; a tenancy puts them in a unit from a move-in date, with the rent and charges they pay.',
                'why' => 'Only units with an active tenancy get invoices, receipts and reminders.',
                'steps' => [
                    'Add the tenant (name and phone; the phone is where bills and receipts go by SMS).',
                    'Create the tenancy: the unit and the move-in date. Its rent and charges come from the unit.',
                    'Moving from Excel or another system is free: send us the sheet and we set up units, tenants, rents and balances.',
                ],
                'progress' => $tenancies ? "{$tenancies} active ".str('tenancy')->plural($tenancies) : 'No tenancies yet',
                'done' => $tenancies > 0,
                'actions' => [['Add tenant', 'tenants.index'], ['New tenancy', 'tenancies.create']],
            ],
            [
                'key' => 'payments', 'title' => 'Get paid: M-Pesa, invoices, receipts, reminders',
                'what' => 'Tenants pay into your own Paybill or Till (or by an M-Pesa prompt on their phone). The money goes straight to you; scharge never holds it.',
                'why' => 'Each payment is matched to the unit by its account number, the invoice is marked paid and the tenant gets an SMS receipt, so nobody reconciles M-Pesa statements by hand.',
                'steps' => [
                    'Connect your Paybill or Till in <b>Integrations</b> (M-Pesa). Tell tenants to use their house number as the account number.',
                    'Switch on <b>Rent automation</b> in Integrations and pick the invoice day and due day: each month rent, water and service charge go out on one bill by SMS.',
                    'Overdue tenants are reminded on the days you choose; late penalties apply only if you switch them on.',
                    'A Paybill payment with a wrong account number waits in <b>Rent collection</b> for you to match to the right unit.',
                ],
                'progress' => ($mpesa ? 'M-Pesa connected' : 'M-Pesa not connected').' · rent automation '.($auto ? 'on' : 'off')." · {$invoices} invoices · {$payments} payments",
                'done' => $mpesa > 0 && $invoices > 0,
                'actions' => [['Connect M-Pesa', 'integrations.index'], ['Rent collection', 'rent.index'], ['Invoices', 'invoices.index'], ['Payments', 'payments.index']],
            ],
            [
                'key' => 'water', 'title' => 'Water meters and readings', 'optional' => true,
                'what' => 'Bill water by meter: a reading each month, times your rate per m³, added to the tenant\'s invoice. Electricity token meters can be recorded too.',
                'why' => 'Most sub-metered buildings lose money on water that is never billed or is collected in cash.',
                'steps' => [
                    'Set the water rate (KES per m³) on the property; a unit can have its own rate.',
                    'Put each unit\'s meter number in the CSV import (water_meter_no), with today\'s reading as the starting point.',
                    'Each month your caretaker or a <b>meter reader</b> enters the readings from a phone; the charge lands on the next invoice.',
                ],
                'progress' => $meters || $readings ? "{$meters} meters · {$readings} readings" : 'No meters or readings yet',
                'done' => $readings > 0,
                'actions' => [['Water', 'water.index'], ['Meter readings', 'meter-readings.index']],
            ],
            [
                'key' => 'repairs', 'title' => 'Repairs, gate and visitors', 'optional' => true,
                'what' => 'Tenants report repairs from their phone with photos; you assign them to a caretaker or fundi and track them to done. At the gate, residents send visitors a pass and the guard checks it.',
                'why' => 'Fewer calls to the landlord, a record of every job and its cost, and a gate that knows who is expected.',
                'steps' => [
                    'Log a repair yourself, or let tenants report from their account.',
                    'Assign it to a staff member with the <b>maintenance</b> role; they see their jobs and close them with notes and cost.',
                    'For the gate, add guards with the <b>security</b> role; they check visitor passes at the gate.',
                ],
                'progress' => "{$repairs} repairs · {$passes} gate passes",
                'done' => $repairs > 0 || $passes > 0,
                'actions' => [['Repairs', 'maintenance.index'], ['Gate', 'gate.index']],
            ],
            [
                'key' => 'listings', 'title' => 'Fill vacant units: Open listings', 'optional' => true,
                'what' => 'Switch a property onto scharge\'s public Open listings and its vacant, priced units show to renters. They book a viewing or a stay, and you get an SMS.',
                'why' => 'Vacancies fill from real renter demand, and requests come to you, not to a middleman.',
                'steps' => [
                    'Give each vacant unit its rent (and photos on the unit page).',
                    'On the property page, switch <b>Open listings</b> on. Every property starts off.',
                    'Answer requests in <b>Enquiries</b>: New → Contacted → Viewing booked → Let.',
                    'Verify your business (ID, KRA PIN, registration) to get the <b>Verified</b> badge on your listings.',
                ],
                'progress' => "{$listed} of {$properties} properties listed · KYC ".($kyc ? str_replace('_', ' ', $kyc) : 'not started'),
                'done' => $listed > 0,
                'actions' => [['Properties', 'properties.index'], ['Enquiries', 'enquiries.index'], ['Verify your business', 'kyc.show'], ['See Open listings', 'home.listings']],
            ],
            [
                'key' => 'team', 'title' => 'Your team and what each role can do',
                'what' => 'Add your staff with a role: property manager, accountant, caretaker (maintenance), meter reader, security guard. Each sees only what their role allows.',
                'why' => 'Staff work in their own accounts, so every payment, reading and repair shows who did it.',
                'steps' => [
                    'Invite each person on <b>Team</b> with their role.',
                    'Change what a role can see or do on <b>Roles</b>.',
                    'Using a letting agency? On the property page, give it the property: its agents see vacant homes and enquiries, never tenants or money.',
                ],
                'progress' => $team.' '.str('person')->plural($team).' on the team'.($agents ? " · {$agents} agency ".str('grant')->plural($agents) : ''),
                'done' => $team > 1 || $agents > 0,
                'actions' => [['Team', 'team.index'], ['Roles', 'roles.index']],
            ],
            [
                'key' => 'reports', 'title' => 'Reports and statements', 'optional' => true, 'readable' => true,
                'what' => 'Financial summary, invoice and payment reports, a water statement per unit, and exports for your accountant or the owner.',
                'why' => 'Month-end takes minutes, and owners see exactly what was collected.',
                'steps' => [
                    'Open <b>Reports</b> for the financial summary, invoices and payments.',
                    'Export any report for your accountant or the owner.',
                ],
                'progress' => $payments ? 'Your reports have data' : 'Reports fill as payments come in',
                'done' => false,
                'actions' => [['Reports', 'reports.index'], ['Financial report', 'reports.financial']],
            ],
            [
                'key' => 'brand', 'title' => 'Your brand, your website, the API', 'optional' => true, 'readable' => true,
                'what' => 'White label: your logo, colours, domain and SMS sender name, with scharge\'s branding hidden. Your own company website. API keys and webhooks for your developers.',
                'why' => 'Tenants and owners see your company, not ours. Agencies and developers can resell scharge to their clients for a revenue share of 20% to 30% of what those clients pay, every month they stay.',
                'steps' => [
                    'Set your logo and colours on <b>White label</b> (white label is quoted per portfolio).',
                    'Set up your company website on <b>Website</b>.',
                    'Create <b>API keys</b> for your developers; the REST API, listings widget and webhooks are included with any plan, and sandbox keys are free.',
                ],
                'progress' => ($brand ? 'Brand set' : 'No brand yet').' · '.$keys.' API '.str('key')->plural($keys),
                'done' => $brand > 0 || $keys > 0,
                'actions' => [['White label', 'brand.edit'], ['Website', 'website.index'], ['API keys', 'api-keys.index'], ['API docs', 'home.api']],
            ],
        ];

        $chapters[] = $this->planChapter();

        foreach ($chapters as $i => &$ch) {
            $ch['number'] = $i + 1;
            $ch['optional'] ??= false;
            $ch['readable'] ??= false;
            $ch['marked'] = in_array($ch['key'], $marked, true);
            $ch['data_done'] = $ch['done'];
            $ch['done'] = $ch['done'] || $ch['marked'];
            $ch['actions'] = array_values(array_filter(array_map(
                fn ($a) => Route::has($a[1]) ? ['label' => $a[0], 'url' => route($a[1])] : null, $ch['actions'])));
        }

        return $chapters;
    }

    /**
     * "Your plan & payments": the billing policy set by scharge (pay one month, get three months; unpaid after the due
     * date locks the whole company except the payment page). The lock itself is enforced elsewhere; this only explains
     * it, with the next due date and amount from the subscription tables when there are any. Amounts are shown only to
     * the owner (admin) and the accountant, as on the payment page.
     */
    private function planChapter(): array
    {
        $c = $this->company->id;
        $sub = Schema::hasTable('company_subscriptions')
            ? DB::table('company_subscriptions')->where('company_id', $c)->orderByDesc('ends_at')->orderByDesc('id')->first() : null;
        $unpaid = $sub && Schema::hasTable('subscription_invoices')
            ? DB::table('subscription_invoices')->whereIn('company_subscription_id', DB::table('company_subscriptions')->where('company_id', $c)->select('id'))
                ->whereIn('status', ['pending', 'failed'])->orderBy('due_date')->first() : null;
        $paid = $sub && Schema::hasTable('subscription_invoices')
            ? DB::table('subscription_invoices')->whereIn('company_subscription_id', DB::table('company_subscriptions')->where('company_id', $c)->select('id'))
                ->where('status', 'paid')->count() : 0;
        $paidUntil = $sub?->ends_at ?? $this->company->subscription_ends_at ?? null;
        $seesMoney = ! $this->user || $this->user->hasAnyRole(['admin', 'accountant']);
        $date = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('j M Y');

        $progress = match (true) {
            (bool) $unpaid => 'Next payment due '.$date($unpaid->due_date).($seesMoney ? ' · '.($unpaid->currency ?: 'KES').' '.number_format((float) $unpaid->amount) : ''),
            (bool) $paidUntil => 'Paid up to '.$date($paidUntil),
            default => 'No bill yet',
        };
        $pay = collect(['billing.index', 'billing', 'subscription.pay', 'subscriptions.pay', 'account.billing'])->first(fn ($r) => Route::has($r));

        return [
            'key' => 'plan', 'title' => 'Your plan and payments', 'readable' => true,
            'what' => 'You pay for one month and get three months of access. Your monthly amount comes from your units and their area (see What it costs).',
            'why' => 'The account must be paid by its due date. If a payment has not arrived when it is due, the whole company is locked at once: every page closes except the payment page.',
            'steps' => [
                'Pay before the due date so nobody on your team is locked out.',
                'If the account is locked, the owner (admin) and the accountant still open the payment page, see the exact amount due and pay it there.',
                'Everyone else on the team sees a message to contact their admin for access until the payment is in.',
            ],
            'progress' => $progress,
            'done' => $paid > 0 && (! $unpaid || $unpaid->due_date >= now()->toDateString()),   // only an overdue bill un-ticks it
            'actions' => $pay && $seesMoney ? [['Pay now', $pay]] : [],
        ];
    }
}
