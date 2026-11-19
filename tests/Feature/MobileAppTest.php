<?php

namespace Tests\Feature;

use App\Models\MobileSession;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * The scharge app API (docs/MOBILE_APP.md): SMS-code sign-in, PIN, the screens each role gets, and that nobody sees
 * another company's (or another tenant's) records. Two landlord companies, A and B, each with tenants and bills.
 */
class MobileAppTest extends RentSchemaTestCase
{
    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extraSchema();
        $this->a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 12000]], automation: false);
        $this->b = $this->company('Other', [['rent_amount' => 50000]], automation: false);
        foreach (array_merge($this->a['tenancies'], $this->b['tenancies']) as $i => $tenancyId) {
            DB::table('invoices')->insert(['tenancy_id' => $tenancyId, 'invoice_type' => 'monthly', 'billing_month' => now()->startOfMonth()->toDateString(),
                'total_amount' => 1000 * ($i + 1), 'total_paid' => 0, 'status' => 'unpaid', 'company_id' => DB::table('tenancies')->where('id', $tenancyId)->value('company_id')]);
        }
        // An older row: company only through the unit (CompanyAccess / RentRun::ownedBy still find it).
        DB::table('invoices')->where('tenancy_id', $this->a['tenancies'][1])->update(['company_id' => null]);
        RateLimiter::clear('app-otp-send:712000001');
    }

    private function extraSchema(): void
    {
        foreach (['tenant_suggestions', 'mobile_sessions', 'maintenances', 'meters', 'meter_readings', 'gate_passes', 'visitors', 'visitor_places', 'security', 'company_brands'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->tinyInteger('status')->default(0));
        (require base_path('database/migrations/2026_11_11_000600_create_mobile_sessions.php'))->up();
        (require base_path('database/migrations/2026_11_11_001000_create_tenant_suggestions.php'))->up();
        if (file_exists($brands = base_path('database/migrations/2026_11_11_000300_create_company_brands.php'))) {
            (require $brands)->up();
        }
        Schema::create('maintenances', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id'), $t->unsignedBigInteger('tenant_id'),
            $t->unsignedBigInteger('assigned_to')->nullable(), $t->string('request_number'), $t->string('name'), $t->text('description'), $t->string('category')->default('other'),
            $t->string('priority')->default('medium'), $t->string('status')->default('open'), $t->text('admin_notes')->nullable(), $t->text('resolution_notes')->nullable(),
            $t->date('scheduled_date')->nullable(), $t->date('completed_date')->nullable(), $t->decimal('cost', 10, 2)->nullable(), $t->text('images')->nullable(), $t->timestamps()]);
        Schema::create('meters', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('utility'), $t->string('kind')->default('manual'), $t->string('billing')->default('postpaid'), $t->string('serial_number'), $t->string('make')->nullable(), $t->string('model')->nullable(),
            $t->string('unit_of_measure')->default('kWh'), $t->decimal('multiplier', 10, 4)->default(1), $t->decimal('rate_per_unit', 12, 2)->nullable(), $t->decimal('initial_reading', 14, 3)->default(0),
            $t->decimal('last_reading', 14, 3)->nullable(), $t->timestamp('last_read_at')->nullable(), $t->string('device_token_prefix')->nullable(), $t->string('device_token_hash')->nullable(),
            $t->string('status')->default('active'), $t->text('notes')->nullable(), $t->timestamps()]);
        Schema::create('meter_readings', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('meter_id'), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('utility'), $t->decimal('reading', 14, 3), $t->decimal('previous_reading', 14, 3)->nullable(), $t->decimal('consumption', 14, 3)->nullable(), $t->decimal('rate_applied', 12, 2)->nullable(),
            $t->decimal('charge', 12, 2)->nullable(), $t->timestamp('read_at'), $t->string('source'), $t->unsignedBigInteger('recorded_by')->nullable(), $t->string('photo_path')->nullable(),
            $t->string('status')->default('accepted'), $t->string('flag_reason')->nullable(), $t->string('client_ref')->nullable(), $t->unsignedBigInteger('water_reading_id')->nullable(),
            $t->string('note')->nullable(), $t->json('raw')->nullable(), $t->timestamps()]);
        Schema::create('gate_passes', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->string('token'), $t->unsignedBigInteger('visitor_id'), $t->unsignedBigInteger('unit_id'),
            $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('company_id')->nullable(), $t->string('kind')->default('visitor'), $t->string('status')->default('issued'),
            $t->string('guest_name'), $t->string('guest_phone')->nullable(), $t->string('guest_email')->nullable(), $t->unsignedSmallInteger('guests_count')->default(1), $t->string('vehicle_plate')->nullable(),
            $t->string('vehicle_desc')->nullable(), $t->timestamp('valid_from')->nullable(), $t->timestamp('valid_until')->nullable(), $t->timestamp('checked_in_at')->nullable(),
            $t->unsignedBigInteger('checked_in_by')->nullable(), $t->string('check_in_method')->nullable(), $t->timestamp('checked_out_at')->nullable(), $t->unsignedBigInteger('checked_out_by')->nullable(),
            $t->string('check_out_method')->nullable(), $t->boolean('is_inside')->default(false), $t->timestamp('sms_sent_at')->nullable(), $t->timestamp('email_sent_at')->nullable(),
            $t->string('notes')->nullable(), $t->unsignedBigInteger('created_by')->nullable(), $t->timestamps()]);
        Schema::create('visitors', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->unsignedBigInteger('tenant_id')->nullable(), $t->string('name'), $t->string('phone')->nullable(), $t->string('email')->nullable(), $t->string('id_number')->nullable(),
            $t->string('visitor_type')->default('one_time'), $t->text('vehicles')->nullable(), $t->boolean('is_registered')->default(false), $t->boolean('is_active')->default(true),
            $t->boolean('is_blacklisted')->default(false), $t->integer('visit_count')->default(0), $t->timestamp('last_visit_at')->nullable(), $t->text('notes')->nullable(), $t->timestamps()]);
        Schema::create('visitor_places', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('visitor_id'), $t->unsignedBigInteger('unit_id'), $t->unsignedBigInteger('property_id')->nullable(),
            $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedInteger('visit_count')->default(0), $t->timestamp('first_visit_at')->nullable(), $t->timestamp('last_check_in_at')->nullable(),
            $t->timestamp('last_check_out_at')->nullable(), $t->unsignedBigInteger('last_pass_id')->nullable(), $t->string('last_vehicle_plate')->nullable(), $t->boolean('is_blocked')->default(false),
            $t->string('notes')->nullable(), $t->timestamps()]);
        // App data (docs/APP_DATA.md) and renter demand (docs/RENTER_DEMAND.md), on top of the tables above.
        foreach (['payment_attempts', 'maintenance_visits', 'app_errors', 'listing_searches'] as $t) {
            Schema::dropIfExists($t);
        }
        (require base_path('database/migrations/2026_11_11_000800_app_push_payments_proof_errors.php'))->up();
        (require base_path('database/migrations/2026_11_11_000900_create_listing_demand.php'))->up();
    }

    /** A user with a role, signed in on a phone; returns [user, bearer token]. */
    private function person(?int $companyId, string $role, ?string $phone = null): array
    {
        $id = DB::table('users')->insertGetId(['name' => ucfirst($role).' '.uniqid(), 'email' => uniqid().'@example.test', 'company_id' => $companyId, 'phone' => $phone,
            'password' => bcrypt('secret-pass')]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        $session = new MobileSession(['user_id' => $id, 'device_name' => 'Test phone']);
        $token = $session->issue();
        $session->save();
        CompanyAccess::flush();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return [User::find($id), $token];
    }

    /** The tenant user of a tenancy, given the tenant role and a phone session. */
    private function tenantOf(int $tenancyId): array
    {
        $userId = DB::table('tenants')->where('id', DB::table('tenancies')->where('id', $tenancyId)->value('tenant_id'))->value('user_id');
        $roleId = DB::table('roles')->where('name', 'tenant')->value('id') ?? DB::table('roles')->insertGetId(['name' => 'tenant', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $userId]);
        $session = new MobileSession(['user_id' => $userId, 'device_name' => 'Tenant phone']);
        $token = $session->issue();
        $session->save();

        return [User::find($userId), $token];
    }

    private function api(string $token)
    {
        CompanyAccess::flush();
        auth()->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
    }

    private function billOf(int $tenancyId): int
    {
        return (int) DB::table('invoices')->where('tenancy_id', $tenancyId)->value('id');
    }

    private function sentCode(): string
    {
        $sms = collect(Http::recorded())->map(fn ($pair) => json_encode($pair[0]->data()))->last();
        preg_match('/code is (\d{6})/', (string) $sms, $m);

        return $m[1] ?? '';
    }

    // ---- Sign-in -------------------------------------------------------------------------------------------------

    public function test_a_tenant_registers_a_phone_with_an_sms_code_then_sets_a_pin(): void
    {
        $tenantUser = DB::table('tenants')->where('id', DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id'))->value('user_id');
        $this->tenantOf($this->a['tenancies'][0]); // gives the role
        DB::table('mobile_sessions')->delete();
        $phone = DB::table('users')->where('id', $tenantUser)->value('phone'); // 0712000001

        $this->postJson('/api/v1/app/auth/code', ['phone' => '+254 712 000 001'])->assertOk()->assertJson(['sent' => true]);
        $code = $this->sentCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->postJson('/api/v1/app/auth/verify', ['phone' => $phone, 'code' => $code === '000000' ? '111111' : '000000', 'device_name' => 'Pixel'])->assertStatus(422);
        // Company B's first tenant has the same number: the person picks which account this phone opens.
        $choices = $this->postJson('/api/v1/app/auth/verify', ['phone' => $phone, 'code' => $code, 'device_name' => 'Pixel'])->assertOk()->json('choose_account');
        $this->assertCount(2, $choices);
        $this->assertContains($tenantUser, array_column($choices, 'id'));
        $res = $this->postJson('/api/v1/app/auth/verify', ['phone' => $phone, 'code' => $code, 'device_name' => 'Pixel', 'account_id' => $tenantUser])->assertCreated()
            ->assertJsonPath('user.id', $tenantUser)->assertJsonPath('session.has_pin', false);
        $this->assertSame(['tenant_bills', 'tenant_payments', 'tenant_water', 'tenant_repairs', 'gate', 'tenant_suggest'], array_column($res->json('screens'), 'key'));
        $token = $res->json('token');

        // The code works once.
        $this->postJson('/api/v1/app/auth/verify', ['phone' => $phone, 'code' => $code, 'device_name' => 'Pixel'])->assertStatus(422);

        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '1234'])->assertStatus(422); // too easy
        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '4826'])->assertOk();
        $this->api($token)->postJson('/api/v1/app/auth/unlock', ['pin' => '4826'])->assertOk()->assertJsonPath('session.has_pin', true);
        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '5937'])->assertStatus(422); // changing needs the old PIN
    }

    public function test_an_unknown_number_gets_the_same_answer_and_no_sms(): void
    {
        $before = count(Http::recorded());
        $this->postJson('/api/v1/app/auth/code', ['phone' => '0799999999'])->assertOk()->assertJson(['sent' => true]);
        $this->assertSame($before, count(Http::recorded()));
    }

    public function test_five_wrong_pins_sign_the_phone_out(): void
    {
        [, $token] = $this->person($this->a['company'], 'admin');
        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '4826'])->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->api($token)->postJson('/api/v1/app/auth/unlock', ['pin' => '0001'])->assertStatus(422);
        }
        $this->api($token)->postJson('/api/v1/app/auth/unlock', ['pin' => '0001'])->assertStatus(401);
        $this->api($token)->getJson('/api/v1/app/me')->assertStatus(401);
    }

    public function test_switched_off_accounts_and_bad_tokens_are_refused(): void
    {
        [$user, $token] = $this->person($this->a['company'], 'admin');
        $this->api('mob_nonsense')->getJson('/api/v1/app/me')->assertStatus(401);
        DB::table('users')->where('id', $user->id)->update(['status' => 1]);
        $this->api($token)->getJson('/api/v1/app/me')->assertStatus(401);
    }

    public function test_password_sign_in_for_staff_without_a_phone(): void
    {
        [$user] = $this->person($this->a['company'], 'accountant');
        $this->postJson('/api/v1/app/auth/password', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'x'])->assertStatus(401);
        $this->postJson('/api/v1/app/auth/password', ['email' => $user->email, 'password' => 'secret-pass', 'device_name' => 'x'])->assertCreated()
            ->assertJsonPath('screens.0.key', 'rent');
        // The same password with the phone number instead of the email.
        DB::table('users')->where('id', $user->id)->update(['phone' => '0733000111']);
        $this->postJson('/api/v1/app/auth/password', ['login' => '+254 733 000 111', 'password' => 'secret-pass', 'device_name' => 'x'])->assertCreated();
        $this->postJson('/api/v1/app/auth/password', ['login' => $user->email, 'password' => 'secret-pass', 'device_name' => 'x'])->assertCreated();
    }

    // ---- Screens per role -----------------------------------------------------------------------------------------

    public function test_each_role_gets_its_own_screens(): void
    {
        $keys = fn (string $role, ?int $company) => array_column($this->api($this->person($company, $role)[1])->getJson('/api/v1/app/me')->assertOk()->json('screens'), 'key');
        $c = $this->a['company'];

        $this->assertSame(['rent', 'bills', 'payments', 'readings', 'repairs', 'gate', 'tenants', 'suggestions', 'units'], $keys('admin', $c));
        $this->assertSame(['rent', 'bills', 'payments', 'readings', 'tenants', 'units'], $keys('accountant', $c));
        $this->assertSame(['readings', 'units'], $keys('meter_reader', $c));
        $this->assertSame(['repairs', 'units'], $keys('maintenance', $c));
        $this->assertSame(['gate', 'units'], $keys('security', $c));
        $this->assertSame(['gate', 'bookings', 'units'], $keys('bnb_host', $c)); // guests at their units, as on the web
        // Account managers open on the CRM (as on the web: pipeline, follow-ups, sales tracker, requests, waitlist),
        // then the revenue and companies of their own book. The "sales" role works prospects only.
        $crm = ['crm_today', 'crm_pipeline', 'crm_follow_ups', 'crm_sales', 'crm_requests', 'crm_waitlist'];
        $this->assertSame([...$crm, 'rent', 'payments', 'companies'], $keys('account_manager', null));
        $this->assertSame($crm, $keys('sales', null)); // no companies: Admin › Companies is sysadmin + account managers
        $this->assertSame(['units'], $keys('agent', $c));
        $this->assertContains('companies', $keys('sysadmin', null));

        // A meter reader may record; an accountant may only look.
        $readings = collect($this->api($this->person($c, 'meter_reader')[1])->getJson('/api/v1/app/me')->json('screens'))->firstWhere('key', 'readings');
        $this->assertSame(['record'], $readings['actions']);
        $readings = collect($this->api($this->person($c, 'accountant')[1])->getJson('/api/v1/app/me')->json('screens'))->firstWhere('key', 'readings');
        $this->assertSame([], $readings['actions']);
    }

    public function test_roles_page_changes_reach_the_app_once_the_catalogue_is_installed(): void
    {
        Artisan::call('access:sync');
        [$user, $token] = $this->person($this->a['company'], 'accountant');
        $this->api($token)->getJson('/api/v1/app/bills')->assertOk();

        \Spatie\Permission\Models\Role::findByName('accountant')->revokePermissionTo('invoices.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->api($token)->getJson('/api/v1/app/bills')->assertStatus(403)->assertJsonPath('needs', 'invoices.view');
        $this->assertNotContains('bills', array_column($this->api($token)->getJson('/api/v1/app/me')->json('screens'), 'key'));
    }

    // ---- Isolation ------------------------------------------------------------------------------------------------

    public function test_a_tenant_sees_only_their_own_bills_and_never_company_lists(): void
    {
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $mine = $this->billOf($this->a['tenancies'][0]);

        $bills = $this->api($token)->getJson('/api/v1/app/tenant/bills')->assertOk()->json('data');
        $this->assertSame([$mine], array_column($bills, 'id'));
        $this->assertStringContainsString('?t=', $bills[0]['pay_url']);
        $this->assertSame('INV-'.str_pad((string) $mine, 4, '0', STR_PAD_LEFT), $bills[0]['number']); // one width: INV-0001
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$mine)->assertOk()->assertJsonPath('balance', '1000.00');
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$this->billOf($this->a['tenancies'][1]))->assertNotFound(); // neighbour
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$this->billOf($this->b['tenancies'][0]))->assertNotFound(); // other company
        $this->api($token)->getJson('/api/v1/app/tenant/summary')->assertOk()->assertJsonPath('balance', '1000.00')->assertJsonPath('unit', 'A1')
            ->assertJsonPath('next_bill.due_label', now()->startOfMonth()->setDay(5)->format('j M'))->assertJsonStructure(['next_bill' => ['items', 'days_left']]);

        // Tenants hold invoices.view, but the company screens are for staff only.
        $this->api($token)->getJson('/api/v1/app/bills')->assertForbidden();
        $this->api($token)->getJson('/api/v1/app/rent')->assertForbidden();
        $this->api($token)->getJson('/api/v1/app/tenants')->assertForbidden();
    }

    public function test_a_tenant_reports_a_repair_for_their_own_unit(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($token)->post('/api/v1/app/tenant/repairs', ['title' => 'Leaking tap', 'description' => 'Kitchen tap drips.', 'category' => 'plumbing',
            'photos' => [\Illuminate\Http\UploadedFile::fake()->image('sink.jpg')], 'caretaker_can_let_in' => 1])
            ->assertCreated()->assertJsonPath('unit', 'A1')->assertJsonPath('status', 'open')->assertJsonCount(1, 'photos');
        $this->assertSame((int) $this->a['company'], (int) DB::table('maintenances')->value('company_id'));
        $this->api($token)->getJson('/api/v1/app/tenant/summary')->assertJsonPath('notices.0.screen', 'tenant_repairs'); // shows under Building notices
        $this->assertCount(1, $this->api($token)->getJson('/api/v1/app/tenant/repairs')->json('data'));
    }

    public function test_company_staff_only_see_their_own_company(): void
    {
        [, $token] = $this->person($this->a['company'], 'admin');

        $ids = array_column($this->api($token)->getJson('/api/v1/app/bills')->assertOk()->json('data'), 'id');
        sort($ids);
        $this->assertSame([$this->billOf($this->a['tenancies'][0]), $this->billOf($this->a['tenancies'][1])], $ids); // incl. the one without company_id
        $this->api($token)->getJson('/api/v1/app/bills/'.$this->billOf($this->b['tenancies'][0]))->assertNotFound();
        $this->api($token)->getJson('/api/v1/app/rent')->assertOk()->assertJsonPath('outstanding', '3000.00')->assertJsonPath('units', 2);
        $this->assertSame(2, $this->api($token)->getJson('/api/v1/app/tenants')->json('total'));
        $this->assertSame(2, $this->api($token)->getJson('/api/v1/app/units')->json('total'));
        $this->api($token)->getJson('/api/v1/app/companies')->assertForbidden();

        // Company B sees only B.
        [, $other] = $this->person($this->b['company'], 'admin');
        $this->assertSame([$this->billOf($this->b['tenancies'][0])], array_column($this->api($other)->getJson('/api/v1/app/bills')->json('data'), 'id'));
        $this->api($other)->getJson('/api/v1/app/rent')->assertJsonPath('outstanding', '3000.00')->assertJsonPath('units', 1);
    }

    public function test_scharge_staff_see_what_their_role_allows(): void
    {
        [, $sys] = $this->person(null, 'sysadmin');
        $this->assertSame(3, count($this->api($sys)->getJson('/api/v1/app/bills')->json('data')));
        $this->assertSame(2, $this->api($sys)->getJson('/api/v1/app/companies')->json('total'));

        [$am, $token] = $this->person(null, 'account_manager');
        DB::table('companies')->insert(['name' => 'Managed Ltd']);
        DB::table('companies')->where('name', 'Managed Ltd')->update(['owner_id' => $am->id]);
        $this->assertSame(['Managed Ltd'], array_column($this->api($token)->getJson('/api/v1/app/companies')->json('data'), 'name'));
        $this->api($token)->getJson('/api/v1/app/bills')->assertForbidden(); // no invoices.view
        $this->assertSame('0.00', $this->api($token)->getJson('/api/v1/app/rent')->json('outstanding')); // none of A's or B's money
    }

    public function test_meter_readers_record_with_a_photo_on_their_own_company_meters_only(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $unitA = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
        $unitB = DB::table('tenancies')->where('id', $this->b['tenancies'][0])->value('unit_id');
        $meterA = DB::table('meters')->insertGetId(['company_id' => $this->a['company'], 'unit_id' => $unitA, 'utility' => 'electricity', 'serial_number' => 'E-1', 'created_at' => now()]);
        $meterB = DB::table('meters')->insertGetId(['company_id' => $this->b['company'], 'unit_id' => $unitB, 'utility' => 'electricity', 'serial_number' => 'E-2', 'created_at' => now()]);
        [, $token] = $this->person($this->a['company'], 'meter_reader');

        $this->assertSame([$meterA], array_column($this->api($token)->getJson('/api/v1/app/meters?due=1')->assertOk()->json('data'), 'id'));
        $res = $this->api($token)->post('/api/v1/app/meters/'.$meterA.'/readings', ['reading' => 120.5, 'client_ref' => 'r-1',
            'photo' => \Illuminate\Http\UploadedFile::fake()->image('meter.jpg')])->assertCreated();
        $this->assertNotNull($res->json('photo_url'));
        $this->api($token)->post('/api/v1/app/meters/'.$meterA.'/readings', ['reading' => 120.5, 'client_ref' => 'r-1'])->assertOk(); // retry: same row
        $this->assertSame(1, DB::table('meter_readings')->count());
        $this->api($token)->post('/api/v1/app/meters/'.$meterB.'/readings', ['reading' => 5])->assertNotFound();

        // Not the reader's job: bills.
        $this->api($token)->getJson('/api/v1/app/bills')->assertForbidden();
        [, $accountant] = $this->person($this->a['company'], 'accountant');
        $this->api($accountant)->post('/api/v1/app/meters/'.$meterA.'/readings', ['reading' => 130])->assertForbidden();
    }

    public function test_maintenance_staff_work_their_company_repairs_only(): void
    {
        $repair = fn (array $t) => DB::table('maintenances')->insertGetId(['unit_id' => DB::table('tenancies')->where('id', $t['tenancies'][0])->value('unit_id'),
            'tenant_id' => DB::table('tenancies')->where('id', $t['tenancies'][0])->value('tenant_id'), 'request_number' => uniqid(), 'name' => 'Fix', 'description' => 'x']);
        $mine = $repair($this->a);
        $theirs = $repair($this->b);
        [$user, $token] = $this->person($this->a['company'], 'maintenance');

        $this->assertSame([$mine], array_column($this->api($token)->getJson('/api/v1/app/repairs')->assertOk()->json('data'), 'id'));
        $this->api($token)->postJson('/api/v1/app/repairs/'.$mine, ['status' => 'in_progress'])->assertOk()->assertJsonPath('assigned_to', $user->name);
        $this->api($token)->postJson('/api/v1/app/repairs/'.$mine, ['status' => 'completed', 'resolution_notes' => 'New washer', 'cost' => 300])->assertOk()->assertJsonPath('cost', '300.00');
        $this->api($token)->postJson('/api/v1/app/repairs/'.$theirs, ['status' => 'completed'])->assertNotFound();

        [, $cleaner] = $this->person($this->a['company'], 'cleaning_staff');
        $this->api($cleaner)->postJson('/api/v1/app/repairs/'.$mine, ['status' => 'cancelled'])->assertForbidden(); // view only
    }

    public function test_the_gate_only_finds_passes_for_the_guards_own_company(): void
    {
        $pass = function (array $t, string $code) {
            $unit = DB::table('tenancies')->where('id', $t['tenancies'][0])->value('unit_id');
            $visitor = DB::table('visitors')->insertGetId(['name' => 'Guest']);

            return DB::table('gate_passes')->insertGetId(['code' => $code, 'token' => uniqid(), 'visitor_id' => $visitor, 'unit_id' => $unit, 'company_id' => $t['company'],
                'guest_name' => 'Guest '.$code, 'valid_from' => now()->subHour(), 'valid_until' => now()->addHours(5), 'status' => 'issued']);
        };
        $mine = $pass($this->a, '111222');
        $pass($this->b, '333444');
        [, $guard] = $this->person($this->a['company'], 'security');

        $this->api($guard)->postJson('/api/v1/app/gate/verify', ['code' => '111222'])->assertOk()->assertJsonPath('id', $mine);
        $this->api($guard)->postJson('/api/v1/app/gate/verify', ['code' => '333444'])->assertNotFound();
        $this->assertSame([$mine], array_column($this->api($guard)->getJson('/api/v1/app/gate/passes')->json('data'), 'id'));

        // A tenant sees passes for their own unit and cannot check guests in (the guard does).
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);
        $this->assertSame([$mine], array_column($this->api($tenant)->getJson('/api/v1/app/gate/passes')->json('data'), 'id'));
        $this->api($tenant)->postJson('/api/v1/app/gate/passes/'.$mine, ['action' => 'checkin'])->assertForbidden();
        [, $neighbour] = $this->tenantOf($this->a['tenancies'][1]);
        $this->assertSame([], $this->api($neighbour)->getJson('/api/v1/app/gate/passes')->json('data'));

        // A meter reader has no gate.
        [, $reader] = $this->person($this->a['company'], 'meter_reader');
        $this->api($reader)->getJson('/api/v1/app/gate/passes')->assertForbidden();
    }

    public function test_bnb_hosts_see_bookings_for_the_units_they_manage(): void
    {
        $unitB = DB::table('tenancies')->where('id', $this->b['tenancies'][0])->value('unit_id');
        DB::table('units')->where('id', $unitB)->update(['stay_type' => 'bnb']);
        $hostCompany = DB::table('companies')->insertGetId(['name' => 'Host (BnB host)']);
        DB::table('unit_managements')->insert(['unit_id' => $unitB, 'company_id' => $hostCompany, 'role' => 'bnb_host', 'status' => 'active']);
        DB::table('bookings')->insert(['company_id' => $hostCompany, 'unit_id' => $unitB, 'source' => 'direct', 'guest_name' => 'Wanjiku', 'guests' => 2,
            'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(), 'status' => 'confirmed', 'created_at' => now()]);
        [, $host] = $this->person($hostCompany, 'bnb_host');
        [, $otherHost] = $this->person(DB::table('companies')->insertGetId(['name' => 'Another host']), 'bnb_host');

        $this->api($host)->getJson('/api/v1/app/bookings')->assertOk()->assertJsonPath('data.0.guest', 'Wanjiku')->assertJsonPath('data.0.nights', 2);
        $this->assertSame([], $this->api($otherHost)->getJson('/api/v1/app/bookings')->json('data'));
        $this->api($host)->getJson('/api/v1/app/bills')->assertForbidden();
    }

    public function test_the_app_wears_the_landlords_white_label(): void
    {
        [, $staff] = $this->person($this->a['company'], 'admin');
        $this->api($staff)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'scharge')->assertJsonPath('brand.white_label', false);

        if (! class_exists(\App\Support\Brand::class) || ! Schema::hasTable('company_brands')) {
            $this->markTestSkipped('White label (company_brands, App\Support\Brand) is not merged into this branch yet.');
        }
        DB::table('company_brands')->insert(['company_id' => $this->a['company'], 'display_name' => 'Danaff Homes', 'primary_color' => '#0a7c55']);
        \App\Support\Brand::forget();
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);

        // Saved but not paid (no white-label module given): scharge red for the company's staff and its tenants.
        $this->api($staff)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'scharge')->assertJsonPath('brand.primary_color', '#DC2626');
        $this->api($tenant)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'scharge')->assertJsonPath('brand.white_label', false);

        // Paid: a sysadmin switches "Website & white label" on for the company (Companies › Modules).
        Schema::dropIfExists('company_modules');
        Schema::create('company_modules', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->boolean('enabled'),
            $t->timestamp('expires_at')->nullable(), $t->string('note')->nullable(), $t->unsignedBigInteger('changed_by')->nullable(), $t->timestamps()]);
        DB::table('company_modules')->insert(['company_id' => $this->a['company'], 'module' => 'website', 'enabled' => true]);
        \App\Support\Modules::flush();
        $this->api($staff)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'Danaff Homes')->assertJsonPath('brand.primary_color', '#0A7C55');
        $this->api($tenant)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'Danaff Homes');
        // scharge staff linked to that company still see scharge.
        [, $am] = $this->person($this->a['company'], 'account_manager');
        $this->api($am)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'scharge');
        [, $other] = $this->person($this->b['company'], 'admin');
        $this->api($other)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'scharge');
    }

    public function test_the_website_lists_the_app_as_in_development(): void
    {
        foreach (['/api', '/developers'] as $page) {
            $this->get($page)->assertOk()->assertSee('The scharge app, for every role')->assertSee('In development');
        }
    }

    // ---------------------------------------------------------------- app data (docs/APP_DATA.md)

    private function firebaseOn(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        \App\Models\CompanyIntegration::create(['company_id' => null, 'provider' => 'mobile_app', 'enabled' => true, 'credentials' => [
            'firebase_service_account' => json_encode(['project_id' => 'scharge-test', 'client_email' => 'push@scharge-test.iam.gserviceaccount.com', 'private_key' => $pem])]]);
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function rentAutomationOn(): void
    {
        \App\Models\CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'rent_automation', 'enabled' => true,
            'credentials' => ['invoice_day' => '1', 'due_day' => '5', 'send_bill' => 'yes', 'reminder_days' => '1', 'channel' => 'sms']]);
    }

    public function test_the_phone_registers_its_push_address_and_versions_and_old_apps_must_update(): void
    {
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($token)->postJson('/api/v1/app/device', ['push_token' => 'fcm-abc', 'app_version' => '1.0.0', 'os_version' => 'Android 14', 'device_model' => 'TECNO KI5'])->assertOk();
        $session = MobileSession::latest('id')->first();
        $this->assertSame(['fcm-abc', '1.0.0', 'Android 14', 'TECNO KI5'], [$session->push_token, $session->app_version, $session->os_version, $session->device_model]);

        [, $token2] = $this->tenantOf($this->a['tenancies'][0]); // the same phone signs in again: the address moves
        $this->api($token2)->postJson('/api/v1/app/device', ['push_token' => 'fcm-abc'])->assertOk();
        $this->assertSame(1, MobileSession::where('push_token', 'fcm-abc')->count());

        \App\Models\CompanyIntegration::create(['company_id' => null, 'provider' => 'mobile_app', 'enabled' => true, 'credentials' => ['min_version' => '1.2.0']]);
        $this->api($token2)->withHeaders(['X-App-Version' => '1.1.9'])->getJson('/api/v1/app/me')->assertStatus(426)->assertJson(['error' => 'update_required']);
        $this->api($token2)->withHeaders(['X-App-Version' => '1.2.0'])->getJson('/api/v1/app/me')->assertOk();
    }

    public function test_bills_go_by_push_instead_of_sms_when_the_tenant_has_the_app(): void
    {
        $this->firebaseOn();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'g-token']), 'fcm.googleapis.com/*' => Http::response(['name' => 'msg-1'])]);
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($token)->postJson('/api/v1/app/device', ['push_token' => 'fcm-tenant'])->assertOk()->assertJson(['push' => true]);
        $this->rentAutomationOn();

        $message = app(\App\Services\Rent\BillNotifier::class)->billReady(\App\Models\Invoice::find($this->billOf($this->a['tenancies'][0])));

        $this->assertSame(['push', 'sent'], [$message->channel, $message->status]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/scharge-test/messages:send') && $r['message']['token'] === 'fcm-tenant');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/sms/send'));
    }

    public function test_a_dead_push_address_falls_back_to_sms(): void
    {
        $this->firebaseOn();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'g-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404), '*' => Http::response(['data' => ['message_id' => 'm1']])]);
        [$tenant, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($token)->postJson('/api/v1/app/device', ['push_token' => 'fcm-gone'])->assertOk();
        DB::table('users')->where('id', $tenant->id)->update(['phone' => '0712999888']);
        $this->rentAutomationOn();

        $message = app(\App\Services\Rent\BillNotifier::class)->billReady(\App\Models\Invoice::find($this->billOf($this->a['tenancies'][0])));

        $this->assertSame('sms', $message->channel);
        $this->assertNull(MobileSession::where('push_token', 'fcm-gone')->first()); // removed
    }

    public function test_pay_presses_are_tracked_to_their_mpesa_result(): void
    {
        $invoice = \App\Models\Invoice::find($this->billOf($this->a['tenancies'][0]));
        $attempts = app(\App\Services\Payments\PaymentAttempts::class);
        $attempts->around($invoice, 1000, 'app', fn () => response()->json(['success' => true, 'checkout_request_id' => 'ws_CO_1']));
        $attempts->around($invoice, 1000, 'web', fn () => response()->json(['success' => false, 'message' => 'Safaricom is busy']));
        \App\Services\Payments\PaymentAttempts::stkResult('ws_CO_1', '2001', 'The initiator information is invalid.');

        $rows = DB::table('payment_attempts')->orderBy('id')->get();
        $this->assertSame([['app', 'stk', 'wrong_pin'], ['web', null, 'failed']], $rows->map(fn ($r) => [$r->channel, $r->method, $r->status])->all());
        $this->assertSame((int) $this->a['company'], (int) $rows[0]->company_id);

        $this->bareLayout();
        $this->actingAs($this->person($this->a['company'], 'admin')[0])->get('/rent?tab=attempts')->assertOk()->assertSee('Wrong M-Pesa PIN')->assertSee('Could not start');
    }

    public function test_readings_and_repair_visits_carry_the_phones_position(): void
    {
        [, $token] = $this->person($this->a['company'], 'admin');
        $unit = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
        $meter = DB::table('meters')->insertGetId(['company_id' => $this->a['company'], 'unit_id' => $unit, 'utility' => 'electricity', 'serial_number' => 'GPS-1', 'status' => 'active']);
        $this->api($token)->postJson('/api/v1/app/meters/'.$meter.'/readings', ['reading' => 120, 'lat' => -1.2921, 'lng' => 36.8219, 'accuracy_m' => 12,
            'captured_at' => now()->toIso8601String()])->assertCreated()->assertJsonPath('location.lat', -1.2921);

        $repair = DB::table('maintenances')->insertGetId(['company_id' => $this->a['company'], 'unit_id' => $unit,
            'tenant_id' => DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id'), 'request_number' => 'MR-GPS-1', 'name' => 'Leak', 'description' => 'Kitchen tap leaking', 'category' => 'plumbing', 'status' => 'open', 'priority' => 'medium']);
        $this->api($token)->postJson('/api/v1/app/repairs/'.$repair.'/visit', ['kind' => 'checkin', 'lat' => -1.29, 'lng' => 36.82])->assertCreated()->assertJson(['on_site' => true]);
        $this->travel(45)->minutes();
        $this->api($token)->postJson('/api/v1/app/repairs/'.$repair.'/visit', ['kind' => 'checkout'])->assertCreated()->assertJson(['on_site' => false, 'minutes_on_site' => 45]);
        $this->assertSame('in_progress', DB::table('maintenances')->where('id', $repair)->value('status'));
    }

    public function test_crash_reports_are_grouped_and_shown_to_staff(): void
    {
        foreach ([1, 2] as $n) {
            $this->postJson('/api/v1/app/errors', ['message' => "Null check operator used on a null value (line {$n})", 'stack' => "#0 BillsScreen.build (package:scharge/bills.dart:4{$n}:7)\n#1 ...",
                'screen' => 'Bills', 'app_version' => '1.0.0', 'os_version' => 'Android 13', 'device_model' => 'itel A70'])->assertStatus(202);
        }
        $this->assertSame([1, 2], [DB::table('app_errors')->count(), (int) DB::table('app_errors')->value('count')]);

        $this->bareLayout();
        $this->actingAs($this->person(null, 'sysadmin')[0])->get('/admin/app')->assertOk()->assertSee('Null check operator')->assertSee('itel A70');
    }

    // ---------------------------------------------------------------- renter demand (docs/RENTER_DEMAND.md)

    public function test_searches_alerts_and_viewings_feed_the_demand_heatmap(): void
    {
        // Website analytics on (the beacon ignores everything without its tables, as on a fresh install).
        foreach (['site_page_views', 'site_events', 'site_clicks'] as $t) {
            Schema::dropIfExists($t);
            Schema::create($t, fn (Blueprint $b) => [$b->id(), $b->timestamps()]);
        }
        config(['analytics.first_party' => true]);
        \Illuminate\Support\Facades\Cache::forget('site-analytics-ready');
        foreach ([['Kilimani', 30000, 0], ['Kilimani', 30000, 0], ['Rongai', 15000, 2]] as $i => [$area, $max, $results]) {
            $this->withServerVariables(['REMOTE_ADDR' => '41.90.1.'.$i, 'HTTP_USER_AGENT' => 'Mozilla/5.0 Test '.$i])->call('POST', '/t', [], [], [], ['CONTENT_TYPE' => 'text/plain'],
                json_encode(['type' => 'search', 'path' => '/listings', 'kind' => 'Homes to rent', 'area' => $area, 'max' => $max, 'results' => $results]))->assertNoContent();
        }
        app(\App\Services\Listings\ListingDemand::class)->record(request(), 'viewing', 'Homes to rent', 'Kilimani', 28000, null);
        $this->assertSame(4, DB::table('listing_searches')->count());

        $r = app(\App\Services\Listings\DemandReport::class)->build(30, 'Homes to rent');
        $this->assertSame('Kilimani', $r['areas'][0]); // 1 + 1 + 5 (viewing) beats Rongai's 1
        $this->assertSame(7, $r['grid']['Kilimani']['20k–35k']);
        $this->assertSame(['Kilimani' => 2], $r['unmet']->all()); // searched, nothing found

        $this->bareLayout();
        $this->actingAs($this->person($this->a['company'], 'admin')[0])->get('/demand')->assertOk()->assertSee('Heatmap')->assertSee('Kilimani');
        $this->actingAs($this->person(null, 'sysadmin')[0])->get('/admin/demand')->assertOk()->assertSee('Rongai');
    }

    public function test_a_tenant_sends_passes_for_the_flat_they_live_in_and_only_suggests_for_others(): void
    {
        // Tenant 1 of A lives in A1 and also rents A2 (moved into A1 later, so A1 is home). Tenant 2 lives in A2.
        [$owner, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $tenant1 = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id');
        $a1 = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
        $a2 = DB::table('tenancies')->where('id', $this->a['tenancies'][1])->value('unit_id');
        DB::table('tenancies')->where('id', $this->a['tenancies'][0])->update(['move_in_date' => '2026-06-01']);
        DB::table('tenancies')->insert(['company_id' => $this->a['company'], 'tenant_id' => $tenant1, 'unit_id' => $a2, 'move_in_date' => '2026-02-01', 'status' => 'active']);
        [, $resident] = $this->tenantOf($this->a['tenancies'][1]);
        [, $guard] = $this->person($this->a['company'], 'security');
        $guest = ['kind' => 'visitor', 'guest_name' => 'Mercy Wambui', 'guest_phone' => '0712000099', 'guest_email' => 'mercy@example.test'];

        $home = $this->api($token)->postJson('/api/v1/app/gate/passes', $guest + ['unit_id' => $a1])->assertCreated()
            ->assertJsonPath('status', 'issued')->assertJsonPath('needs_approval', false);
        $other = $this->api($token)->postJson('/api/v1/app/gate/passes', $guest + ['unit_id' => $a2])->assertCreated()
            ->assertJsonPath('status', 'suggested')->assertJsonPath('needs_approval', true);

        // The guard can use the home pass, not the suggestion.
        $this->api($guard)->postJson('/api/v1/app/gate/verify', ['code' => $home->json('code')])->assertOk();
        $this->api($guard)->postJson('/api/v1/app/gate/verify', ['code' => $other->json('code')])->assertNotFound();

        // The one who suggested it cannot approve it; the resident of A2 sees it and approves.
        $this->api($token)->postJson('/api/v1/app/gate/passes/'.$other->json('id'), ['action' => 'approve'])->assertForbidden();
        $this->assertSame([$other->json('id')], array_column($this->api($resident)->getJson('/api/v1/app/gate/passes?tab=approve')->json('data'), 'id'));
        $this->assertSame([], $this->api($token)->getJson('/api/v1/app/gate/passes?tab=approve')->json('data'));
        $this->api($resident)->postJson('/api/v1/app/gate/passes/'.$other->json('id'), ['action' => 'approve'])->assertOk()->assertJsonPath('status', 'issued');
        $this->api($guard)->postJson('/api/v1/app/gate/verify', ['code' => $other->json('code')])->assertOk();
    }

    public function test_guest_codes_go_by_email_by_default_and_by_sms_when_asked(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $a1 = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
        $smsBefore = $this->sent;

        $this->api($token)->postJson('/api/v1/app/gate/passes', ['unit_id' => $a1, 'kind' => 'visitor', 'guest_name' => 'Email Only', 'guest_email' => 'guest@example.test'])
            ->assertCreated()->assertJsonPath('guest_email', 'guest@example.test');
        $this->assertSame($smsBefore, $this->sent); // email only, no SMS

        $this->api($token)->postJson('/api/v1/app/gate/passes', ['unit_id' => $a1, 'kind' => 'visitor', 'guest_name' => 'Both', 'guest_email' => 'both@example.test',
            'guest_phone' => '0712000098', 'send_sms' => true])->assertCreated();
        $this->assertSame($smsBefore + 1, $this->sent);

        $this->api($token)->postJson('/api/v1/app/gate/passes', ['unit_id' => $a1, 'kind' => 'visitor', 'guest_name' => 'Nothing'])->assertStatus(422);
    }

    public function test_the_bell_counts_only_what_this_person_should_act_on(): void
    {
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($tenant)->getJson('/api/v1/app/notifications')->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('data.0.screen', 'tenant_bills');

        DB::table('invoices')->update(['total_paid' => DB::raw('total_amount'), 'status' => 'paid']);
        $this->api($tenant)->getJson('/api/v1/app/notifications')->assertJsonPath('count', 0); // nothing: the bell hides

        DB::table('paybill_payments')->insert(['trans_id' => 'QX1', 'company_id' => $this->b['company'], 'amount' => 500, 'status' => 'unmatched', 'paid_at' => now()]);
        [, $adminA] = $this->person($this->a['company'], 'admin');
        [, $adminB] = $this->person($this->b['company'], 'admin');
        $this->api($adminA)->getJson('/api/v1/app/notifications')->assertJsonPath('count', 0); // B's payment is not A's business
        $this->api($adminB)->getJson('/api/v1/app/notifications')->assertJsonPath('data.0.screen', 'rent');
    }

    public function test_the_suggestion_box_is_anonymous_and_reaches_only_the_landlords_admins(): void
    {
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($tenant)->postJson('/api/v1/app/tenant/suggestions', ['topic' => 'security', 'body' => 'Please fix the light at the back gate.'])->assertCreated();

        $row = (array) DB::table('tenant_suggestions')->first();
        $this->assertSame((int) $this->a['company'], (int) $row['company_id']);
        $this->assertSame(['id', 'company_id', 'topic', 'body', 'read_at', 'read_by', 'created_at', 'updated_at'], array_keys($row)); // no tenant, unit or phone

        [, $adminA] = $this->person($this->a['company'], 'admin');
        [, $adminB] = $this->person($this->b['company'], 'admin');
        [, $reader] = $this->person($this->a['company'], 'meter_reader');
        $this->api($adminA)->getJson('/api/v1/app/notifications')->assertJsonPath('data.0.screen', 'suggestions');
        $this->api($adminA)->getJson('/api/v1/app/suggestions')->assertOk()->assertJsonPath('data.0.body', 'Please fix the light at the back gate.')
            ->assertJsonPath('data.0.new', true)->assertJsonMissingPath('data.0.unit');
        $this->api($adminA)->getJson('/api/v1/app/notifications')->assertJsonPath('count', 0); // read now
        $this->assertSame([], $this->api($adminB)->getJson('/api/v1/app/suggestions')->json('data'));
        $this->api($reader)->getJson('/api/v1/app/suggestions')->assertForbidden();
        $this->api($tenant)->getJson('/api/v1/app/suggestions')->assertForbidden();
    }

    public function test_receipts_carry_what_a_shared_receipt_needs_and_stay_private(): void
    {
        $mine = $this->billOf($this->a['tenancies'][0]);
        $tenantId = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id');
        DB::table('payments')->insert(['invoice_id' => $mine, 'tenant_id' => $tenantId, 'amount' => 600, 'payment_method' => 'mpesa', 'external_reference' => 'SJK3H2X1PQ', 'status' => 'completed', 'created_at' => now()]);
        DB::table('payments')->insert(['invoice_id' => $this->billOf($this->b['tenancies'][0]), 'amount' => 900, 'status' => 'completed', 'created_at' => now()]);
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);

        $this->api($tenant)->getJson('/api/v1/app/tenant/payments')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'SJK3H2X1PQ')->assertJsonPath('data.0.unit', 'A1')
            ->assertJsonPath('data.0.issued_by', 'Sunrise')->assertJsonPath('data.0.receipt_no', fn ($v) => str_starts_with($v, 'RCT-'));
        [, $adminB] = $this->person($this->b['company'], 'admin');
        $this->assertSame(['900.00'], array_column($this->api($adminB)->getJson('/api/v1/app/payments')->json('data'), 'amount'));
    }

    public function test_pay_options_show_only_what_the_landlord_has_set_up(): void
    {
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $mine = $this->billOf($this->a['tenancies'][0]);

        $this->assertSame(['mpesa'], array_column($this->api($token)->getJson("/api/v1/app/tenant/bills/$mine/pay")->assertOk()->json('methods'), 'key'));
        $this->api($token)->postJson("/api/v1/app/tenant/bills/$mine/sharet")->assertStatus(422); // Sharet not switched on

        \App\Models\CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'bank_standing_order', 'enabled' => true,
            'credentials' => ['bank' => 'Equity Bank', 'account_name' => 'Sunrise Ltd', 'account_number' => '0123456789']]);
        \App\Models\CompanyIntegration::create(['provider' => 'sharet', 'enabled' => true, 'credentials' => ['api_key' => 'sk_test_x']]);
        \Illuminate\Support\Facades\Cache::flush();
        $res = $this->api($token)->getJson("/api/v1/app/tenant/bills/$mine/pay")->assertOk();
        $this->assertSame(['mpesa', 'sharet', 'bank'], array_column($res->json('methods'), 'key'));
        $this->assertSame('A1', $res->json('methods.2.reference'));
        $this->assertStringContainsString('from=app', $res->json('methods.0.url'));

        // Another tenant's or company's bill: nothing.
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$this->billOf($this->a['tenancies'][1]).'/pay')->assertNotFound();
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$this->billOf($this->b['tenancies'][0]).'/pay')->assertNotFound();
    }

    public function test_the_water_dashboard_shows_only_the_tenants_home_and_queries_reach_the_manager(): void
    {
        $a1 = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
        $a2 = DB::table('tenancies')->where('id', $this->a['tenancies'][1])->value('unit_id');
        foreach ([['2026-08-28', 4], ['2026-09-28', 6], ['2026-10-28', 9]] as [$d, $c]) {
            DB::table('water_readings')->insert(['unit_id' => $a1, 'previous_reading' => 100, 'current_reading' => 100 + $c, 'consumption' => $c, 'reading_date' => $d]);
        }
        DB::table('water_readings')->insert(['unit_id' => $a2, 'previous_reading' => 0, 'current_reading' => 50, 'consumption' => 50, 'reading_date' => '2026-10-28']);
        $meter = DB::table('meters')->insertGetId(['company_id' => $this->a['company'], 'unit_id' => $a1, 'utility' => 'water', 'serial_number' => 'W-1', 'unit_of_measure' => 'm3', 'created_at' => now()]);
        [$reader] = $this->person($this->a['company'], 'meter_reader');
        DB::table('meter_readings')->insert(['meter_id' => $meter, 'company_id' => $this->a['company'], 'unit_id' => $a1, 'utility' => 'water', 'reading' => 109, 'previous_reading' => 106,
            'consumption' => 3, 'read_at' => now(), 'source' => 'reader_app', 'recorded_by' => $reader->id, 'status' => 'accepted']);
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);

        $w = $this->api($token)->getJson('/api/v1/app/tenant/water')->assertOk();
        $this->assertSame([4, 6, 9], array_map('intval', array_column($w->json('history'), 'consumption'))); // only A1, oldest first
        $w->assertJsonPath('rate', '100.00')->assertJsonPath('this_month.charge', '900.00')->assertJsonPath('last_reading.read_by', $reader->name)->assertJsonPath('unit', 'A1');

        $this->api($token)->postJson('/api/v1/app/tenant/water/query', ['note' => 'My bill looks too high this month.'])->assertCreated();
        $this->assertSame('Water reading query', DB::table('maintenances')->value('name'));
        [, $maint] = $this->person($this->a['company'], 'maintenance');
        $this->assertCount(1, $this->api($maint)->getJson('/api/v1/app/repairs')->json('data'));
        [, $staff] = $this->person($this->a['company'], 'admin');
        $this->api($staff)->getJson('/api/v1/app/tenant/water')->assertForbidden();
    }

    public function test_six_digit_pins_and_paying_a_bill_from_credit_paid_ahead(): void
    {
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '111111'])->assertStatus(422); // too easy
        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '48261'])->assertStatus(422); // 5 digits
        $this->api($token)->postJson('/api/v1/app/auth/pin', ['pin' => '482617'])->assertOk()->assertJsonPath('pin_length', 6);
        $this->api($token)->postJson('/api/v1/app/auth/unlock', ['pin' => '482617'])->assertOk();

        $tenant = \App\Models\Tenant::withoutGlobalScope('company')->find(DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id'));
        $tenant->deposit(1500); // paid ahead
        $bill = $this->billOf($this->a['tenancies'][0]); // KES 1,000 owed
        $this->api($token)->getJson('/api/v1/app/tenant/summary')->assertJsonPath('credit', '1500.00');
        $this->assertSame('credit', $this->api($token)->getJson("/api/v1/app/tenant/bills/$bill/pay")->json('methods.0.key'));

        $this->api($token)->postJson("/api/v1/app/tenant/bills/$bill/pay-credit", ['pin' => '000001'])->assertStatus(422)->assertJsonPath('error', 'wrong_pin');
        $this->api($token)->postJson("/api/v1/app/tenant/bills/$bill/pay-credit", ['pin' => '482617'])->assertCreated()
            ->assertJsonPath('paid', '1000.00')->assertJsonPath('credit', '500.00')->assertJsonPath('bill.status', 'paid');
        $this->api($token)->postJson("/api/v1/app/tenant/bills/$bill/pay-credit", ['confirmed_with' => 'device'])->assertStatus(422); // nothing left

        // Not someone else's bill.
        $this->api($token)->postJson('/api/v1/app/tenant/bills/'.$this->billOf($this->a['tenancies'][1]).'/pay-credit', ['confirmed_with' => 'device'])->assertNotFound();
    }

    public function test_a_tenant_creates_their_account_with_the_sms_code_and_one_password(): void
    {
        [$tenant] = $this->tenantOf($this->a['tenancies'][0]); // added by the landlord with 0712000001
        DB::table('mobile_sessions')->delete();
        Schema::table('tenants', fn (Blueprint $t) => $t->string('id_number')->nullable());
        $this->postJson('/api/v1/app/auth/code', ['phone' => '0712000001'])->assertOk();
        $code = $this->sentCode();
        $form = ['phone' => '0712000001', 'code' => $code, 'name' => 'Achieng Otieno', 'email' => 'achieng@example.test', 'id_number' => '12345678',
            'password' => 'nyumba-2026', 'password_confirmation' => 'nyumba-2026', 'device_name' => 'Pixel', 'account_id' => $tenant->id];

        $this->postJson('/api/v1/app/auth/register', ['code' => '000000'] + $form)->assertStatus(422);
        $this->postJson('/api/v1/app/auth/register', $form)->assertCreated()->assertJsonPath('user.name', 'Achieng Otieno');
        $this->assertSame('12345678', DB::table('tenants')->where('user_id', $tenant->id)->value('id_number'));

        // One password, phone or email.
        $this->postJson('/api/v1/app/auth/password', ['login' => 'achieng@example.test', 'password' => 'nyumba-2026', 'device_name' => 'x'])->assertCreated();
        $this->postJson('/api/v1/app/auth/password', ['login' => '0712000001', 'password' => 'nyumba-2026', 'device_name' => 'x'])->assertCreated();

        // A number no landlord has added.
        RateLimiter::clear('app-otp-send:799999999');
        $this->postJson('/api/v1/app/auth/register', ['phone' => '0799999999'] + $form)->assertStatus(422); // no code was ever sent
    }

    public function test_the_wallet_shows_credit_and_its_history(): void
    {
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $tenant = \App\Models\Tenant::withoutGlobalScope('company')->find(DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id'));
        $tenant->deposit(2500, ['description' => 'M-Pesa paybill payment QX1 received (credit)']);
        $w = $this->api($token)->getJson('/api/v1/app/tenant/wallet')->assertOk()->assertJsonPath('balance', '2500.00');
        $this->assertSame('2500.00', $w->json('transactions.0.amount'));
        $this->assertSame('deposit', $w->json('transactions.0.type'));
        [, $other] = $this->tenantOf($this->a['tenancies'][1]);
        $this->api($other)->getJson('/api/v1/app/tenant/wallet')->assertJsonPath('balance', '0.00')->assertJsonCount(0, 'transactions');
    }

    public function test_payment_references_are_short_and_readable(): void
    {
        $bill = $this->billOf($this->a['tenancies'][0]);
        $tenantId = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id');
        DB::table('payments')->insert([
            ['invoice_id' => $bill, 'tenant_id' => $tenantId, 'amount' => 100, 'payment_method' => 'sharet', 'source' => 'sharet_rail', 'transaction_reference' => 'SHD1CCFAF6',
                'external_reference' => 'pr_9f8e7d6c5b4a39281706f5e4', 'meta' => json_encode(['sharet_transaction' => 'SHD1CCFAF6']), 'status' => 'completed', 'created_at' => now()],
            ['invoice_id' => $bill, 'tenant_id' => $tenantId, 'amount' => 100, 'payment_method' => 'mpesa', 'source' => 'stk', 'transaction_reference' => null,
                'external_reference' => 'SJK3H2X1PQ', 'meta' => null, 'status' => 'completed', 'created_at' => now()],
            ['invoice_id' => $bill, 'tenant_id' => $tenantId, 'amount' => 100, 'payment_method' => 'wallet', 'source' => 'web', 'transaction_reference' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
                'external_reference' => null, 'meta' => null, 'status' => 'completed', 'created_at' => now()],
        ]);
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $refs = array_column($this->api($token)->getJson('/api/v1/app/tenant/payments')->json('data'), 'reference');
        sort($refs);
        $this->assertSame(['7C9E6679', 'SHD1CCFAF6', 'SJK3H2X1PQ'], $refs);
    }

    public function test_tenant_sign_up_never_sets_a_password_on_a_staff_account(): void
    {
        [$admin] = $this->person($this->a['company'], 'admin', '0712555444');
        $before = DB::table('users')->where('id', $admin->id)->value('password');
        \Illuminate\Support\Facades\Cache::put('app-otp:712555444', ['hash' => \Illuminate\Support\Facades\Hash::make('123456'), 'tries' => 0], now()->addMinutes(10));

        $this->postJson('/api/v1/app/auth/register', ['phone' => '0712555444', 'code' => '123456', 'name' => 'Intruder', 'password' => 'new-pass-123',
            'password_confirmation' => 'new-pass-123', 'device_name' => 'Phone'])->assertStatus(422)->assertJson(['error' => 'staff_account']);
        $this->assertSame($before, DB::table('users')->where('id', $admin->id)->value('password'));
        $this->assertNotSame('Intruder', DB::table('users')->where('id', $admin->id)->value('name'));
    }

    // ---- CRM for account managers / sales ---------------------------------------------------------------------------

    private function crmSchema(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            foreach (['crm_stage' => 'string', 'email' => 'string', 'phone' => 'string', 'kind' => 'string'] as $col => $type) {
                if (! Schema::hasColumn('companies', $col)) {
                    $t->{$type}($col)->nullable();
                }
            }
            if (! Schema::hasColumn('companies', 'tier')) {
                $t->unsignedTinyInteger('tier')->nullable();
            }
            if (! Schema::hasColumn('companies', 'crm_profile')) {
                $t->json('crm_profile')->nullable();
            }
            if (! Schema::hasColumn('companies', 'reply_pending')) {
                $t->boolean('reply_pending')->default(false); // a stored generated column in MySQL (2026_11_19_crm_speed.sql)
            }
            if (! Schema::hasColumn('companies', 'is_active')) {
                $t->boolean('is_active')->default(true);
            }
        });
        // CRM › Sales tracker commission (CommissionService): settings on `system`, paid platform bills.
        foreach (['system', 'company_subscriptions', 'subscription_invoices'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('system', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->json('settings')->nullable(), $t->timestamps()]);
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->timestamps()]);
        Schema::create('subscription_invoices', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_subscription_id'), $t->string('invoice_number')->nullable(),
            $t->string('status'), $t->decimal('amount', 12, 2), $t->timestamp('paid_at')->nullable(), $t->json('invoice_json')->nullable(), $t->timestamps()]);
        Schema::dropIfExists('waitlist_entries');
        Schema::create('waitlist_entries', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('user_id')->nullable(), $t->unsignedBigInteger('company_id')->nullable(),
            $t->string('country_code', 2)->nullable(), $t->string('status')->default('waiting'), $t->json('details')->nullable(), $t->timestamps()]);
    }

    private function prospect(string $name, ?int $owner, string $stage = 'new', array $profile = []): int
    {
        return DB::table('companies')->insertGetId(['name' => $name, 'lifecycle' => 'prospect', 'crm_stage' => $stage, 'tier' => 2, 'owner_id' => $owner,
            'crm_profile' => json_encode($profile), 'reply_pending' => ! empty($profile['reply_pending'])]);
    }

    public function test_account_managers_work_their_own_crm_book_in_the_app(): void
    {
        $this->crmSchema();
        [$am, $token] = $this->person(null, 'account_manager');
        [$other] = $this->person(null, 'account_manager');
        $mine = $this->prospect('Mine Homes', $am->id, 'contacted', ['reply_pending' => true, 'reply' => ['from' => 'a@b.test', 'name' => 'Asha', 'snippet' => 'Call me', 'at' => now()->toDateTimeString()]]);
        $free = $this->prospect('Unassigned Lets', null);
        $theirs = $this->prospect('Their Estates', $other->id);
        DB::table('crm_follow_ups')->insert(['company_id' => $mine, 'assigned_to' => $am->id, 'type' => 'call', 'status' => 'open', 'title' => 'Call back', 'due_at' => now()->subDay()]);
        $theirTask = DB::table('crm_follow_ups')->insertGetId(['company_id' => $theirs, 'assigned_to' => $other->id, 'type' => 'call', 'status' => 'open', 'title' => 'Theirs', 'due_at' => now()->subDay()]);

        // Pipeline: own book plus unassigned leads, replies first; never another account manager's prospects.
        $names = array_column($this->api($token)->getJson('/api/v1/app/crm/prospects')->assertOk()->json('data'), 'name');
        $this->assertSame(['Mine Homes', 'Unassigned Lets'], $names);
        $this->api($token)->getJson('/api/v1/app/crm/prospects/'.$theirs)->assertForbidden();
        $this->api($token)->postJson('/api/v1/app/crm/prospects/'.$theirs.'/stage', ['stage' => 'qualified'])->assertForbidden();
        $this->api($token)->postJson('/api/v1/app/crm/follow-ups/'.$theirTask, ['status' => 'done', 'outcome' => 'x'])->assertForbidden();

        $day = $this->api($token)->getJson('/api/v1/app/crm/summary')->assertOk();
        $this->assertSame(1, $day->json('stats.replied'));
        $this->assertSame(1, $day->json('stats.overdue'));
        $this->assertSame('Asha', $day->json('replies.0.reply.from'));
        $this->assertContains('crm_today', array_column($this->api($token)->getJson('/api/v1/app/notifications')->json('data'), 'screen'));

        // Work a prospect: log a call (new -> contacted), move the stage, clear the reply alert, close the follow-up.
        $this->api($token)->postJson('/api/v1/app/crm/prospects/'.$free.'/follow-ups', ['mode' => 'log', 'type' => 'call', 'title' => 'Intro call'])->assertCreated();
        $this->assertSame('contacted', DB::table('companies')->where('id', $free)->value('crm_stage'));
        $this->api($token)->postJson('/api/v1/app/crm/prospects/'.$mine.'/stage', ['stage' => 'lost'])->assertStatus(422); // lost needs a reason
        $this->api($token)->postJson('/api/v1/app/crm/prospects/'.$mine.'/stage', ['stage' => 'proposal'])->assertOk()->assertJsonPath('stage', 'proposal');
        $this->api($token)->postJson('/api/v1/app/crm/prospects/'.$mine.'/reply/handled', ['note' => 'Called her'])->assertOk()->assertJsonPath('reply', null);
        $task = $this->api($token)->getJson('/api/v1/app/crm/follow-ups?view=overdue')->assertOk()->json('data.0.id');
        $this->api($token)->postJson('/api/v1/app/crm/follow-ups/'.$task, ['status' => 'done', 'outcome' => 'Sent proposal', 'next_title' => 'Chase', 'next_due_at' => now()->addDays(2)->toDateTimeString()])->assertOk();
        $this->assertSame(1, DB::table('crm_follow_ups')->where('company_id', $mine)->where('title', 'Chase')->count());

        // Sales tracker: their own book only; commission on the paid platform bills of companies they own (20% default).
        DB::table('companies')->where('id', $this->a['company'])->update(['owner_id' => $am->id]);
        DB::table('units')->whereIn('id', DB::table('tenancies')->whereIn('id', $this->a['tenancies'])->pluck('unit_id'))->update(['company_id' => $this->a['company']]);
        $sub = DB::table('company_subscriptions')->insertGetId(['company_id' => $this->a['company']]);
        DB::table('subscription_invoices')->insert(['company_subscription_id' => $sub, 'invoice_number' => 'SUB-1', 'status' => 'paid', 'amount' => 1000, 'paid_at' => now(), 'invoice_json' => '{}']);
        $sales = $this->api($token)->getJson('/api/v1/app/crm/sales')->assertOk();
        $this->assertSame('mine', $sales->json('scope'));
        $this->assertSame('1000.00', $sales->json('totals.fees'));
        $this->assertSame('200.00', $sales->json('totals.commission'));
        $this->assertSame(2, $sales->json('totals.units'));
        $this->assertSame(['Mine Homes'], array_column($sales->json('closing'), 'name'));
    }

    public function test_sysadmins_see_the_whole_pipeline_and_sales_cannot_reach_company_data(): void
    {
        $this->crmSchema();
        [$am] = $this->person(null, 'account_manager');
        $this->prospect('Mine Homes', $am->id);
        $this->prospect('Unassigned Lets', null);
        [, $sys] = $this->person(null, 'sysadmin');
        $this->assertSame(2, $this->api($sys)->getJson('/api/v1/app/crm/prospects')->json('total'));
        $this->assertSame('team', $this->api($sys)->getJson('/api/v1/app/crm/sales')->assertOk()->json('scope'));

        [, $sales] = $this->person(null, 'sales');
        $this->assertSame(['Unassigned Lets'], array_column($this->api($sales)->getJson('/api/v1/app/crm/prospects')->assertOk()->json('data'), 'name'));
        foreach (['/companies', '/rent', '/bills', '/tenants', '/units', '/payments'] as $path) {
            $this->api($sales)->getJson('/api/v1/app'.$path)->assertForbidden();
        }

        // Landlord staff and tenants never reach the CRM.
        [, $admin] = $this->person($this->a['company'], 'admin');
        $this->api($admin)->getJson('/api/v1/app/crm/prospects')->assertForbidden();
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($tenant)->getJson('/api/v1/app/crm/summary')->assertForbidden();
    }

    public function test_repair_photos_never_turn_an_empty_list_into_a_storage_url(): void
    {
        $unit = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
        $tenantId = DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('tenant_id');
        foreach (['"[]"', '[]', null, '["repair-photos/a.jpg"]', '"[\"repair-photos/b.jpg\"]"'] as $i => $images) {
            DB::table('maintenances')->insert(['company_id' => $this->a['company'], 'unit_id' => $unit, 'tenant_id' => $tenantId, 'request_number' => 'R'.$i,
                'name' => 'Leak '.$i, 'description' => 'x', 'images' => $images]);
        }
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $photos = collect($this->api($token)->getJson('/api/v1/app/tenant/repairs')->assertOk()->json('data'))->pluck('photos', 'title');
        $this->assertSame([], $photos['Leak 0']);
        $this->assertSame([], $photos['Leak 1']);
        $this->assertSame([], $photos['Leak 2']);
        $this->assertStringEndsWith('/storage/repair-photos/a.jpg', $photos['Leak 3'][0]);
        $this->assertStringEndsWith('/storage/repair-photos/b.jpg', $photos['Leak 4'][0]);
    }
}
