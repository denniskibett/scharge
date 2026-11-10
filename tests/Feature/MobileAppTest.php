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
        $this->assertSame(['tenant_bills', 'tenant_payments', 'tenant_repairs', 'gate', 'tenant_suggest'], array_column($res->json('screens'), 'key'));
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
        $this->assertSame(['rent', 'payments', 'companies'], $keys('account_manager', null));
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
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$mine)->assertOk()->assertJsonPath('balance', '1000.00');
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$this->billOf($this->a['tenancies'][1]))->assertNotFound(); // neighbour
        $this->api($token)->getJson('/api/v1/app/tenant/bills/'.$this->billOf($this->b['tenancies'][0]))->assertNotFound(); // other company
        $this->api($token)->getJson('/api/v1/app/tenant/summary')->assertOk()->assertJsonPath('balance', '1000.00')->assertJsonPath('unit', 'A1');

        // Tenants hold invoices.view, but the company screens are for staff only.
        $this->api($token)->getJson('/api/v1/app/bills')->assertForbidden();
        $this->api($token)->getJson('/api/v1/app/rent')->assertForbidden();
        $this->api($token)->getJson('/api/v1/app/tenants')->assertForbidden();
    }

    public function test_a_tenant_reports_a_repair_for_their_own_unit(): void
    {
        [, $token] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($token)->postJson('/api/v1/app/tenant/repairs', ['title' => 'Leaking tap', 'description' => 'Kitchen tap drips.', 'category' => 'plumbing'])
            ->assertCreated()->assertJsonPath('unit', 'A1')->assertJsonPath('status', 'open');
        $this->assertSame((int) $this->a['company'], (int) DB::table('maintenances')->value('company_id'));
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
        Schema::table('companies', fn (Blueprint $t) => $t->unsignedBigInteger('owner_id')->nullable());
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
        $this->api($staff)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'Danaff Homes')->assertJsonPath('brand.primary_color', '#0A7C55');
        [, $tenant] = $this->tenantOf($this->a['tenancies'][0]);
        $this->api($tenant)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'Danaff Homes');
        [, $other] = $this->person($this->b['company'], 'admin');
        $this->api($other)->getJson('/api/v1/app/me')->assertJsonPath('brand.name', 'scharge');
    }

    public function test_the_website_lists_the_app_as_in_development(): void
    {
        foreach (['/api', '/developers'] as $page) {
            $this->get($page)->assertOk()->assertSee('The scharge app, for every role')->assertSee('In development');
        }
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
}
