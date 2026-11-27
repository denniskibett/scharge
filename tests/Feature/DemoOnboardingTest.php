<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Mail\AccountReadyEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\DemoOnboarding;
use App\Modules\CRM\Support\SetupMode;
use App\Modules\Users\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** After a demo, scharge sets the account up from the demo data; the client adds units, scharge does the rest. */
class DemoOnboardingTest extends RentSchemaTestCase
{
    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'phone' => 'string', 'crm_stage' => 'string', 'kind' => 'string', 'is_active' => 'boolean',
            'registration_number' => 'string', 'tax_id' => 'string', 'address' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        foreach (['service_charge', 'garbage_charge', 'security_charge'] as $col) {
            if (! Schema::hasColumn('properties', $col)) {
                Schema::table('properties', fn (Blueprint $t) => $t->decimal($col, 12, 2)->nullable());
            }
        }
        foreach (['email_verified_at' => 'timestamp', 'password' => 'string', 'company_id' => 'unsignedBigInteger', 'remember_token' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('users', $col)) {
                Schema::table('users', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', fn (Blueprint $t) => [$t->string('email')->primary(), $t->string('token'), $t->timestamp('created_at')->nullable()]);
        }
        foreach (['admin', 'account_manager', 'lead'] as $r) {
            DB::table('roles')->insertOrIgnore(['name' => $r, 'guard_name' => 'web']);
        }
        DB::table('users')->insert(['id' => 5, 'name' => 'Waithaka Kammau', 'email' => 'waithaka@example.test', 'phone' => '+254745907070']);
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'account_manager')->value('id'), 'model_type' => User::class, 'model_id' => 5]);
        DB::table('users')->insert(['id' => 9, 'name' => 'Emma Wanja', 'email' => 'emma@jourdan.test', 'phone' => '+254700000009']);
        $this->companyId = DB::table('companies')->insertGetId(['name' => 'Jourdan Management Ltd', 'lifecycle' => 'prospect', 'owner_id' => 5, 'crm_stage' => 'demo_booked',
            'email' => 'info@jourdan.test', 'kind' => 'manager',
            'crm_profile' => json_encode(['estimated_units' => 221, 'company_details' => ['kra_pin' => 'P051234567X', 'registration_number' => 'PVT-AB1234'],
                'prospect_properties' => [['id' => 'r0ojqtlm', 'name' => 'Blemerg', 'location' => 'Adlife Plaza', 'units' => 221, 'status' => 'open']]])]);
        CrmFollowUp::create(['company_id' => $this->companyId, 'user_id' => 9, 'type' => 'contact', 'status' => 'done', 'title' => 'Emma linked', 'details' => ['primary' => true]]);
        SetupMode::flush();
        $this->sms = [];
        $fake = \Mockery::mock(\App\Services\SmsService::class);
        $fake->shouldReceive('forCompany')->andReturnSelf();
        $fake->shouldReceive('send')->andReturnUsing(function ($phone, $text) { $this->sms[] = [$phone, $text]; return ['success' => true]; });
        $this->app->instance(\App\Services\SmsService::class, $fake);
    }

    private array $sms = [];

    public function test_the_account_is_set_up_from_the_demo_and_they_get_the_pdf(): void
    {
        Mail::fake();
        Storage::fake('local');
        $r = app(DemoOnboarding::class)->setUp(Company::withoutGlobalScopes()->find($this->companyId));

        $c = Company::withoutGlobalScopes()->find($this->companyId);
        $this->assertSame('customer', $c->lifecycle);
        $this->assertSame('demo_booked', $c->crm_stage, 'the stage is not Won: nothing is billed');
        $this->assertSame('P051234567X', $c->tax_id, 'company details from the demo form');
        $this->assertSame(9, $r['user']->id, 'the person who booked the demo gets the login');
        $this->assertSame($this->companyId, (int) $r['user']->company_id);
        $this->assertTrue($r['user']->hasRole('admin'));
        $this->assertSame(1, DB::table('properties')->where('company_id', $this->companyId)->where('name', 'Blemerg')->count());
        $this->assertTrue(SetupMode::on($this->companyId));
        Storage::disk('local')->assertExists($r['pdf']);
        $this->assertGreaterThanOrEqual(4, CrmFollowUp::where('company_id', $this->companyId)->where('details->onboarding', 'setup')->count(), 'the account manager\'s setup tasks');
        Mail::assertSent(AccountReadyEmail::class, fn ($m) => $m->hasTo('emma@jourdan.test') && $m->pdf === $r['pdf'] && str_contains($m->body, '/welcome/9'));

        $this->assertCount(1, $this->sms, 'the welcome link by SMS too');
        $this->assertSame('+254700000009', $this->sms[0][0], 'to the number they gave on the demo form');
        $this->assertStringContainsString('/welcome/9', $this->sms[0][1]);

        $p = DemoOnboarding::progress($c);
        $this->assertSame(221, $p['expected']);
        $this->assertTrue($p['steps'][0]['done']);
        $this->assertFalse($p['steps'][3]['done'], 'no units yet');
    }

    public function test_during_setup_they_can_add_units_but_nothing_else(): void
    {
        Mail::fake();
        Storage::fake('local');
        app(DemoOnboarding::class)->setUp(Company::withoutGlobalScopes()->find($this->companyId));
        $emma = User::find(9);

        $deny = fn (string $method, string $path) => SetupMode::deny(tap(\Illuminate\Http\Request::create($path, $method), fn ($r) => $r->setUserResolver(fn () => $emma)));
        $this->assertNull($deny('GET', '/units'));
        $this->assertNull($deny('POST', '/units'));
        $this->assertNull($deny('GET', '/properties'));
        $this->assertNotNull($deny('POST', '/properties'), 'properties are ours to set up');
        $this->assertNotNull($deny('GET', '/tenants'));
        $this->assertNotNull($deny('GET', '/integrations'));

        SetupMode::finish(Company::withoutGlobalScopes()->find($this->companyId), 5);
        $this->assertNull($deny('GET', '/tenants'), 'full access once setup is finished');
        $staff = User::find(5);
        $this->assertNull(SetupMode::deny(tap(\Illuminate\Http\Request::create('/tenants'), fn ($r) => $r->setUserResolver(fn () => $staff))));
    }

    public function test_the_welcome_link_gives_a_fresh_password_link_until_they_sign_in(): void
    {
        Mail::fake();
        Storage::fake('local');
        $r = app(DemoOnboarding::class)->setUp(Company::withoutGlobalScopes()->find($this->companyId));
        $url = DemoOnboarding::welcomeUrl($r['user']);

        $this->get($url)->assertRedirectContains('/reset-password/');
        DB::table('login_events')->insert(['user_id' => 9, 'success' => true, 'channel' => 'web', 'created_at' => now()]);
        $this->get($url)->assertRedirect(route('login'));
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    }
}
