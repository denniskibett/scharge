<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Tenancy;
use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\InvoiceLink;
use Illuminate\Support\Facades\DB;

/**
 * One landlord company never sees another's invoices or tenancies (CompanyAccess scope), and public bill links need
 * their secret code (InvoiceLink). Uses the hand-built schema (RentSchemaTestCase).
 */
class CompanyIsolationTest extends RentSchemaTestCase
{
    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 12000]], automation: false);
        $this->b = $this->company('Other', [['rent_amount' => 50000]], automation: false);
        foreach (array_merge($this->a['tenancies'], $this->b['tenancies']) as $tenancyId) {
            DB::table('invoices')->insert(['tenancy_id' => $tenancyId, 'invoice_type' => 'monthly', 'billing_month' => '2026-12-01', 'total_amount' => 1000, 'status' => 'unpaid']);
        }
        // Older rows: no company_id on the invoice or tenancy, only on the unit / property.
        DB::table('invoices')->update(['company_id' => null]);
        DB::table('tenancies')->where('id', $this->a['tenancies'][1])->update(['company_id' => null]);
    }

    private function user(?int $companyId, ?string $role = null): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Staff', 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        if ($role) {
            $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        }
        CompanyAccess::flush();

        return User::find($id);
    }

    public function test_a_company_user_only_finds_their_own_invoices_and_tenancies(): void
    {
        $this->actingAs($this->user($this->a['company']));

        $this->assertSame(2, Invoice::count());
        $this->assertSame(2, Tenancy::count()); // including the one linked only through its unit
        $other = Invoice::withoutGlobalScope('company')->where('tenancy_id', $this->b['tenancies'][0])->value('id');
        $this->assertNull(Invoice::find($other));
        $this->assertNull(Tenancy::find($this->b['tenancies'][0]));
    }

    public function test_the_other_company_sees_only_theirs_and_sysadmins_and_the_scheduler_see_all(): void
    {
        $this->actingAs($this->user($this->b['company']));
        $this->assertSame(1, Invoice::count());

        $this->actingAs($this->user(null, 'sysadmin'));
        $this->assertSame(3, Invoice::count());

        auth()->logout();
        $this->assertSame(3, Invoice::count());
        $this->assertSame(3, Tenancy::count());
    }

    public function test_public_bill_page_needs_the_secret_code(): void
    {
        $id = Invoice::where('tenancy_id', $this->a['tenancies'][0])->value('id');

        $this->get('/public/invoice/'.$id)->assertStatus(403)->assertSee('This link is not valid');
        $this->get('/public/invoice/'.$id.'?t='.InvoiceLink::token($id + 1))->assertStatus(403);
        $this->postJson('/public/invoice/'.$id.'/pay', ['phone' => '0712000001', 'amount' => 100])->assertStatus(403);
        $this->getJson('/public/invoice/'.$id.'/status/ws_CO_1')->assertStatus(403);

        $this->get(InvoiceLink::url($id))->assertOk()->assertSee('A1');
        $this->assertStringContainsString('?t='.InvoiceLink::token($id), InvoiceLink::url($id));
    }

    public function test_a_signed_in_landlord_of_another_company_can_still_open_a_tenants_bill_link(): void
    {
        $id = Invoice::where('tenancy_id', $this->a['tenancies'][0])->value('id');
        $this->actingAs($this->user($this->b['company']));

        $this->get(InvoiceLink::url($id))->assertOk();
    }

    public function test_payments_tenants_and_units_follow_the_same_rule(): void
    {
        foreach (Invoice::withoutGlobalScope('company')->get() as $invoice) {
            DB::table('payments')->insert(['invoice_id' => $invoice->id, 'amount' => 500, 'status' => 'completed']);
        }
        DB::table('tenants')->where('id', DB::table('tenancies')->where('id', $this->a['tenancies'][1])->value('tenant_id'))->update(['company_id' => null]);
        $bUnit = DB::table('tenancies')->where('id', $this->b['tenancies'][0])->value('unit_id');

        $this->actingAs($this->user($this->a['company']));
        $this->assertSame(2, Payment::count());
        $this->assertSame(2, Tenant::count()); // one only through its tenancy
        $this->assertSame(2, Unit::count());
        $this->assertNull(Unit::find($bUnit));

        // A unit A manages in B's building (a BnB host unit) is visible to A.
        DB::table('unit_managements')->insert(['unit_id' => $bUnit, 'company_id' => $this->a['company']]);
        $this->assertNotNull(Unit::find($bUnit));

        // System work (payment settlement, host units) sees everything, whoever is signed in.
        $this->assertSame(3, CompanyAccess::bypass(fn () => Payment::count()));
        $this->assertSame(3, CompanyAccess::bypass(fn () => Invoice::with('tenancy.tenant')->get()->filter(fn ($i) => $i->tenancy?->tenant)->count()));
        $this->assertSame(2, Payment::count()); // and the filter is back afterwards
    }

    public function test_a_tenant_added_by_a_company_user_stays_in_their_list(): void
    {
        $this->actingAs($this->user($this->a['company']));
        $userId = DB::table('users')->insertGetId(['name' => 'New Tenant', 'email' => 'new@example.test']);

        $tenant = Tenant::create(['user_id' => $userId]);

        $this->assertSame((int) $this->a['company'], (int) $tenant->company_id);
        $this->assertNotNull(Tenant::find($tenant->id));
    }

    // ---- Every company-scoped model -------------------------------------------------------------------------------

    /**
     * Model => [kind, extra columns]. kind: direct (company_id), via (company_id NULL, found through the parent
     * column in extra), shared (own rows plus company_id NULL defaults). Extra values name a parent of the company:
     * unit, tenancy, invoice, property, meter.
     */
    private const SCOPED = [
        \App\Models\Invoice::class => ['via', ['tenancy_id' => 'tenancy', 'invoice_type' => 'monthly', 'total_amount' => 1]],
        \App\Modules\Payments\Models\InvoiceItem::class => ['via', ['invoice_id' => 'invoice']],
        \App\Models\Payment::class => ['parent', ['invoice_id' => 'invoice']],
        \App\Models\Tenancy::class => ['via', ['unit_id' => 'unit', 'tenant_id' => 1, 'move_in_date' => '2026-01-01']],
        \App\Models\Unit::class => ['via', ['property_id' => 'property', 'unit_number' => 'X', 'rent_amount' => 1]],
        \App\Models\Property::class => ['direct', ['name' => 'Court']],
        \App\Models\WaterReading::class => ['via', ['unit_id' => 'unit']],
        \App\Models\Meter::class => ['direct', ['unit_id' => 'unit']],
        \App\Models\MeterReading::class => ['via', ['meter_id' => 'meter']],
        \App\Models\Maintenance::class => ['via', ['unit_id' => 'unit']],
        \App\Models\Expense::class => ['direct', []],
        \App\Modules\Expenses\Models\ExpenseCategory::class => ['shared', []],
        \App\Modules\Expenses\Models\Payee::class => ['shared', []],
        \App\Models\SmsTemplate::class => ['shared', []],
        \App\Modules\SMS\Models\SmsTemplate::class => ['shared', []],
        \App\Models\CalendarEvent::class => ['shared', []],
        \App\Models\Booking::class => ['direct', ['unit_id' => 'unit']],
        \App\Models\UnitChannel::class => ['direct', ['unit_id' => 'unit']],
        \App\Models\UnitPhoto::class => ['direct', ['unit_id' => 'unit']],
        \App\Models\RentHold::class => ['direct', ['tenancy_id' => 'tenancy']],
        \App\Models\RentMessage::class => ['direct', ['tenancy_id' => 'tenancy']],
        \App\Models\PaybillPayment::class => ['direct', []],
        \App\Models\TenantSuggestion::class => ['direct', []],
        \App\Modules\Security\Models\GatePass::class => ['via', ['unit_id' => 'unit']],
        \App\Models\SecurityLog::class => ['via', ['unit_id' => 'unit']],
        \App\Modules\Security\Models\VisitorPlace::class => ['via', ['unit_id' => 'unit']],
        \App\Modules\Properties\Models\TenancyCharge::class => ['parent', ['tenancy_id' => 'tenancy']],
        \App\Modules\Properties\Models\LeaseAgreement::class => ['parent', ['tenancy_id' => 'tenancy']],
        \App\Modules\Properties\Models\HouseChecklist::class => ['parent', ['tenancy_id' => 'tenancy']],
        \App\Models\ApiKey::class => ['direct', []],
        \App\Models\CompanyIntegration::class => ['direct', []],
        \App\Models\CompanyKyc::class => ['direct', []],
        \App\Models\KycDocument::class => ['direct', []],
        \App\Models\CompanyPage::class => ['direct', []],
        \App\Models\CompanyBrand::class => ['direct', []],
        \App\Models\CompanyAgreement::class => ['direct', []],
        \App\Modules\Payments\Models\CompanyPaymentMethod::class => ['direct', []],
        \App\Modules\Subscriptions\Models\CompanyPaymentMethod::class => ['direct', []],
        \App\Modules\Subscriptions\Models\CompanySubscription::class => ['direct', []],
        \App\Models\SharetSweep::class => ['direct', []],
        \App\Models\SharetPaymentRequest::class => ['direct', []],
        \App\Models\PropertyClaim::class => ['direct', ['property_id' => 'property']],
    ];

    /**
     * Models with a company_id that are deliberately not company-scoped, and why. A new model with company_id must be
     * added to SCOPED (with the CompanyScoped trait) or here.
     */
    private const UNSCOPED = [
        \App\Models\User::class => 'signing in loads the user before CompanyAccess can know the company; each screen filters people',
        \App\Modules\Users\Models\User::class => 'as App\Models\User',
        \App\Models\Company::class => 'the companies themselves; staff screens filter by owner_id, company users only open their own',
        \App\Modules\CRM\Models\CrmFollowUp::class => 'scharge sales CRM: company_id is the prospect, guarded by CrmAccess',
        \App\Modules\CRM\Models\WaitlistEntry::class => 'scharge sales CRM, guarded by CrmAccess',
        \App\Models\UnitManagement::class => 'the link between a building owner and a BnB host: both companies read it (used inside the unit scope)',
        \App\Models\Visitor::class => 'one person across many gates; what a company sees of them is VisitorPlace / GatePass (scoped)',
        \App\Modules\Security\Models\Visitor::class => 'as App\Models\Visitor',
    ];

    /** @return array<string, int> parents of a company for SCOPED extra columns */
    private function parentsOf(array $c): array
    {
        $tenancy = $c['tenancies'][0];
        $unit = (int) DB::table('tenancies')->where('id', $tenancy)->value('unit_id');

        return ['tenancy' => $tenancy, 'unit' => $unit, 'property' => (int) DB::table('units')->where('id', $unit)->value('property_id'),
            'invoice' => (int) DB::table('invoices')->where('tenancy_id', $tenancy)->value('id'), 'meter' => $c['meter']];
    }

    /** A row with every NOT NULL column filled; tables the hand-built schema lacks are made with just what the scope reads. */
    private function row(string $table, array $values): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            \Illuminate\Support\Facades\Schema::create($table, function (\Illuminate\Database\Schema\Blueprint $t) use ($values) {
                $t->id();
                foreach (array_keys($values) as $col) {
                    is_int($values[$col]) || $values[$col] === null ? $t->unsignedBigInteger($col)->nullable() : $t->string($col)->nullable();
                }
                $t->timestamps();
            });
        }
        foreach (array_keys($values) as $col) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                \Illuminate\Support\Facades\Schema::table($table, fn ($t) => $t->unsignedBigInteger($col)->nullable());
            }
        }
        foreach (DB::select("PRAGMA table_info($table)") as $col) {
            if ($col->notnull && $col->dflt_value === null && ! $col->pk && ! array_key_exists($col->name, $values)) {
                $type = strtolower($col->type);
                $values[$col->name] = match (true) {
                    str_contains($type, 'int') => 1, str_contains($type, 'numeric') || str_contains($type, 'decimal') => 0,
                    str_contains($type, 'date') || str_contains($type, 'time') => now()->toDateTimeString(),
                    $col->name === 'meta' || str_contains($type, 'json') => '{}', default => uniqid($col->name),
                };
            }
        }

        return DB::table($table)->insertGetId($values);
    }

    /** One row for A, one for B (and a shared default) of every scoped model; returns [class => [a ids, b ids, shared ids]]. */
    private function seedScoped(): array
    {
        foreach (['a', 'b'] as $k) {
            $unit = (int) DB::table('tenancies')->where('id', $this->{$k}['tenancies'][0])->value('unit_id');
            $this->{$k}['meter'] = $this->row('meters', ['company_id' => $this->{$k}['company'], 'unit_id' => $unit]);
        }
        $made = [];
        foreach (self::SCOPED as $class => [$kind, $extra]) {
            $table = (new $class)->getTable();
            $made[$class] = [[], [], []];
            foreach (['a' => 0, 'b' => 1] as $k => $slot) {
                $p = $this->parentsOf($this->{$k});
                $values = array_map(fn ($v) => is_string($v) && isset($p[$v]) ? $p[$v] : $v, $extra);
                if ($kind !== 'parent') {
                    $values['company_id'] = $kind === 'via' ? null : $this->{$k}['company'];
                }
                $made[$class][$slot][] = $this->row($table, $values);
            }
            if ($kind === 'shared') {
                $made[$class][2][] = $this->row($table, ['company_id' => null]);
            }
        }

        return $made;
    }

    public function test_every_company_scoped_model_hides_the_other_companys_rows(): void
    {
        $made = $this->seedScoped();
        $owner = $this->user(null, 'account_manager');
        DB::table('companies')->where('id', $this->a['company'])->update(['owner_id' => $owner->id]);

        foreach ([[$this->user($this->a['company']), 0, 1], [$this->user($this->b['company']), 1, 0], [$owner, 0, 1]] as [$user, $mine, $theirs]) {
            $this->actingAs($user);
            CompanyAccess::flush();
            foreach ($made as $class => $ids) {
                $seen = $class::pluck((new $class)->getQualifiedKeyName())->map(fn ($id) => (int) $id)->all();
                foreach (array_merge($ids[$mine], $ids[2]) as $id) {
                    $this->assertContains($id, $seen, "$class: {$user->name} should see their own row $id");
                }
                foreach ($ids[$theirs] as $id) {
                    $this->assertNotContains($id, $seen, "$class: {$user->name} sees the other company's row $id");
                    $this->assertNull($class::find($id), "$class: find() opens the other company's row $id");
                }
            }
        }

        // scharge staff, the scheduler and system work (CompanyAccess::bypass) see every row.
        foreach ([$this->user(null, 'sysadmin'), null] as $who) {
            $who ? $this->actingAs($who) : auth()->logout();
            CompanyAccess::flush();
            foreach ($made as $class => $ids) {
                $this->assertSame(count(array_merge(...$ids)), $class::whereIn((new $class)->getQualifiedKeyName(), array_merge(...$ids))->count(), "$class for ".($who ? 'sysadmin' : 'the scheduler'));
            }
        }
        $this->actingAs($this->user($this->a['company']));
        foreach ($made as $class => $ids) {
            $this->assertSame(count(array_merge(...$ids)), CompanyAccess::bypass(fn () => $class::whereIn((new $class)->getQualifiedKeyName(), array_merge(...$ids))->count()), "$class under bypass");
        }
    }

    public function test_a_building_is_seen_by_the_host_and_the_agency_that_work_in_it(): void
    {
        $bUnit = (int) DB::table('tenancies')->where('id', $this->b['tenancies'][0])->value('unit_id');
        $bProperty = (int) DB::table('units')->where('id', $bUnit)->value('property_id');
        $this->actingAs($this->user($this->a['company']));
        $this->assertNull(\App\Models\Property::find($bProperty));

        DB::table('unit_managements')->insert(['unit_id' => $bUnit, 'company_id' => $this->a['company']]);
        CompanyAccess::flush();
        $this->assertNotNull(\App\Models\Property::find($bProperty), 'a BnB host sees the building of a unit it manages');
        $this->assertNotNull(\App\Models\Property::withoutGlobalScope('company')->find($bProperty));
    }

    public function test_every_model_with_a_company_is_scoped_or_listed_as_deliberately_unscoped(): void
    {
        $files = array_merge(glob(app_path('Models/*.php')), glob(app_path('Modules/*/Models/*.php')));
        $missing = [];
        foreach ($files as $file) {
            $class = str_replace(['/', '.php'], ['\\', ''], 'App/'.substr($file, strlen(app_path()) + 1));
            $source = file_get_contents($file);
            // app/Models/InvoiceItem.php is a stray copy that declares the module's class; it is never loaded.
            if (! str_contains($source, 'namespace '.substr($class, 0, strrpos($class, '\\')).';')) {
                continue;
            }
            // Only models that name a company (or extend a module model that may); some unused files do not load.
            if (! str_contains($source, 'company_id') && ! str_contains($source, 'extends \\App\\Modules\\')) {
                continue;
            }
            if (! class_exists($class) || ! is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $parent = get_parent_class($class);
            $mentions = str_contains($source, 'company_id') || ($parent !== \Illuminate\Database\Eloquent\Model::class && str_contains(file_get_contents((new \ReflectionClass($parent))->getFileName()), 'company_id'));
            new $class; // global scopes are added when the model boots
            if ($mentions && ! $class::hasGlobalScope('company') && ! isset(self::UNSCOPED[$class])) {
                $missing[] = $class;
            }
        }
        $this->assertSame([], $missing, 'Models with company_id but no company scope: add CompanyScoped or list them in UNSCOPED with the reason.');
        foreach (array_keys(self::SCOPED) as $class) {
            new $class;
            $this->assertTrue($class::hasGlobalScope('company'), "$class has no company scope");
        }
    }

    public function test_new_bills_and_their_lines_carry_the_company_without_the_fallback(): void
    {
        $this->actingAs($this->user($this->a['company']));
        // The second tenancy has no company_id: the company comes from its unit's property.
        $invoice = Invoice::create(['tenancy_id' => $this->a['tenancies'][1], 'invoice_type' => 'monthly', 'total_amount' => 900, 'status' => 'unpaid']);
        $item = $invoice->items()->create(['item_type' => 'rent', 'description' => 'Rent', 'amount' => 900]);

        $this->assertSame((int) $this->a['company'], (int) DB::table('invoices')->where('id', $invoice->id)->value('company_id'));
        $this->assertSame((int) $this->a['company'], (int) DB::table('invoice_items')->where('id', $item->id)->value('company_id'));
    }

    // ---- Through the web and the app ------------------------------------------------------------------------------

    public function test_web_pages_do_not_open_another_companys_records(): void
    {
        $made = $this->seedScoped();
        $admin = $this->user($this->a['company'], 'admin');
        $b = fn (string $class) => $made[$class][1][0];

        foreach ([
            ['get', '/invoices/'.$this->billOfB().'/details'],
            ['post', '/rent/paybill/'.$b(\App\Models\PaybillPayment::class).'/ignore'],
            ['post', '/rent/messages/'.$b(\App\Models\RentMessage::class).'/retry'],
            ['post', '/rent/paused/'.$b(\App\Models\RentHold::class).'/resume'],
            ['post', '/bookings/'.$b(\App\Models\Booking::class).'/cancel'],
            ['post', '/bookings/channels/'.$b(\App\Models\UnitChannel::class).'/remove'],
            ['get', '/settings/verify/documents/'.$b(\App\Models\KycDocument::class)],
            ['get', '/payments/'.Payment::withoutGlobalScope('company')->where('invoice_id', $this->billOfB())->value('id')],
        ] as [$method, $url]) {
            CompanyAccess::flush();
            $this->actingAs($admin)->{$method}($url)->assertNotFound();
        }

        // An account manager reviewing websites only finds the companies they look after.
        $owner = $this->user(null, 'account_manager');
        DB::table('companies')->where('id', $this->a['company'])->update(['owner_id' => $owner->id]);
        CompanyAccess::flush();
        $this->actingAs($owner)->post('/admin/website-approvals/'.$b(\App\Models\CompanyPage::class), ['decision' => 'approve'])->assertNotFound();
    }

    public function test_the_app_api_only_returns_the_companys_own_records(): void
    {
        $this->appSchema();
        $made = $this->seedScoped();
        foreach (Invoice::withoutGlobalScope('company')->get() as $invoice) {
            DB::table('payments')->insert(['invoice_id' => $invoice->id, 'amount' => 500, 'status' => 'completed']);
        }
        $token = $this->appToken($this->a['company'], 'admin');
        $api = function () use ($token) {
            CompanyAccess::flush();
            auth()->forgetGuards();

            return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
        };
        $ids = fn (string $url) => array_map('intval', array_column($api()->getJson($url)->assertOk()->json('data'), 'id'));

        $api()->getJson('/api/v1/app/bills/'.$this->billOfB())->assertNotFound();
        $this->assertNotContains($this->billOfB(), $ids('/api/v1/app/bills'));
        $bPayment = (int) Payment::withoutGlobalScope('company')->where('invoice_id', $this->billOfB())->value('id');
        $this->assertNotContains($bPayment, $ids('/api/v1/app/payments'));
        $this->assertContains((int) Payment::withoutGlobalScope('company')->where('invoice_id', $this->parentsOf($this->a)['invoice'])->value('id'), $ids('/api/v1/app/payments'));
        $aUnits = DB::table('units')->where('property_id', $this->parentsOf($this->a)['property'])->pluck('id');
        $this->assertSame($aUnits->count(), $api()->getJson('/api/v1/app/units')->json('total'));
        $this->assertSame(DB::table('tenancies')->whereIn('unit_id', $aUnits)->where('status', 'active')->count(), $api()->getJson('/api/v1/app/tenants')->json('total'));
        $meters = $ids('/api/v1/app/meters');
        sort($meters);
        $this->assertSame(array_merge([$this->a['meter']], $made[\App\Models\Meter::class][0]), $meters);
        $this->assertSame($made[\App\Models\Maintenance::class][0], $ids('/api/v1/app/repairs'));
        $this->assertNotContains($made[\App\Modules\Security\Models\GatePass::class][1][0], $ids('/api/v1/app/gate/passes'));
        $api()->getJson('/api/v1/app/meters/'.$this->b['meter'].'/readings')->assertNotFound();
        $api()->postJson('/api/v1/app/repairs/'.$made[\App\Models\Maintenance::class][1][0], ['status' => 'completed'])->assertNotFound();
    }

    private function billOfB(): int
    {
        return (int) Invoice::withoutGlobalScope('company')->where('tenancy_id', $this->b['tenancies'][0])->value('id');
    }

    /** The app's sign-in tables and the field tables with the columns its screens read (as in MobileAppTest). */
    private function appSchema(): void
    {
        $S = \Illuminate\Support\Facades\Schema::class;
        foreach (['tenant_suggestions', 'mobile_sessions', 'maintenances', 'meters', 'meter_readings', 'gate_passes', 'visitors', 'visitor_places', 'payment_attempts', 'maintenance_visits', 'app_errors', 'listing_searches'] as $t) {
            $S::dropIfExists($t);
        }
        $S::table('users', fn ($t) => $t->tinyInteger('status')->default(0));
        (require base_path('database/migrations/2026_11_11_000600_create_mobile_sessions.php'))->up();
        (require base_path('database/migrations/2026_11_11_001000_create_tenant_suggestions.php'))->up();
        (require base_path('database/migrations/2026_11_11_000800_app_push_payments_proof_errors.php'))->up();
        (require base_path('database/migrations/2026_11_11_000900_create_listing_demand.php'))->up();
        $S::create('maintenances', fn ($t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(), $t->unsignedBigInteger('tenant_id')->nullable(),
            $t->unsignedBigInteger('assigned_to')->nullable(), $t->string('request_number')->nullable(), $t->string('name')->nullable(), $t->text('description')->nullable(), $t->string('category')->default('other'),
            $t->string('priority')->default('medium'), $t->string('status')->default('open'), $t->text('admin_notes')->nullable(), $t->text('resolution_notes')->nullable(),
            $t->date('scheduled_date')->nullable(), $t->date('completed_date')->nullable(), $t->decimal('cost', 10, 2)->nullable(), $t->text('images')->nullable(), $t->timestamps()]);
        $S::create('meters', fn ($t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('utility')->default('water'), $t->string('kind')->default('manual'), $t->string('billing')->default('postpaid'), $t->string('serial_number')->default('M1'), $t->string('make')->nullable(), $t->string('model')->nullable(),
            $t->string('unit_of_measure')->default('m3'), $t->decimal('multiplier', 10, 4)->default(1), $t->decimal('rate_per_unit', 12, 2)->nullable(), $t->decimal('initial_reading', 14, 3)->default(0),
            $t->decimal('last_reading', 14, 3)->nullable(), $t->timestamp('last_read_at')->nullable(), $t->string('device_token_prefix')->nullable(), $t->string('device_token_hash')->nullable(),
            $t->string('status')->default('active'), $t->text('notes')->nullable(), $t->timestamps()]);
        $S::create('meter_readings', fn ($t) => [$t->id(), $t->unsignedBigInteger('meter_id'), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('utility')->default('water'), $t->decimal('reading', 14, 3)->default(0), $t->decimal('previous_reading', 14, 3)->nullable(), $t->decimal('consumption', 14, 3)->nullable(), $t->decimal('rate_applied', 12, 2)->nullable(),
            $t->decimal('charge', 12, 2)->nullable(), $t->timestamp('read_at')->nullable(), $t->string('source')->default('app'), $t->unsignedBigInteger('recorded_by')->nullable(), $t->string('photo_path')->nullable(),
            $t->string('status')->default('accepted'), $t->string('flag_reason')->nullable(), $t->string('client_ref')->nullable(), $t->unsignedBigInteger('water_reading_id')->nullable(),
            $t->string('note')->nullable(), $t->json('raw')->nullable(), $t->timestamps()]);
        $S::create('gate_passes', fn ($t) => [$t->id(), $t->string('code')->nullable(), $t->string('token')->nullable(), $t->unsignedBigInteger('visitor_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('company_id')->nullable(), $t->string('kind')->default('visitor'), $t->string('status')->default('issued'),
            $t->string('guest_name')->default('Guest'), $t->string('guest_phone')->nullable(), $t->string('guest_email')->nullable(), $t->unsignedSmallInteger('guests_count')->default(1), $t->string('vehicle_plate')->nullable(),
            $t->string('vehicle_desc')->nullable(), $t->timestamp('valid_from')->nullable(), $t->timestamp('valid_until')->nullable(), $t->timestamp('checked_in_at')->nullable(),
            $t->unsignedBigInteger('checked_in_by')->nullable(), $t->string('check_in_method')->nullable(), $t->timestamp('checked_out_at')->nullable(), $t->unsignedBigInteger('checked_out_by')->nullable(),
            $t->string('check_out_method')->nullable(), $t->boolean('is_inside')->default(false), $t->timestamp('sms_sent_at')->nullable(), $t->timestamp('email_sent_at')->nullable(),
            $t->string('notes')->nullable(), $t->unsignedBigInteger('created_by')->nullable(), $t->timestamps()]);
        $S::create('visitors', fn ($t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->unsignedBigInteger('tenant_id')->nullable(), $t->string('name')->default('Guest'), $t->string('phone')->nullable(), $t->timestamps()]);
        $S::create('visitor_places', fn ($t) => [$t->id(), $t->unsignedBigInteger('visitor_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(), $t->unsignedBigInteger('property_id')->nullable(),
            $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedInteger('visit_count')->default(0), $t->timestamps()]);
    }

    private function appToken(int $companyId, string $role): string
    {
        $user = $this->user($companyId, $role);
        $session = new \App\Models\MobileSession(['user_id' => $user->id, 'device_name' => 'Test phone']);
        $token = $session->issue();
        $session->save();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $token;
    }
}
