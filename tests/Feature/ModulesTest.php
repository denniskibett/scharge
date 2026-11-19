<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\Modules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Paid modules per company (App\Support\Modules): plan defaults, sysadmin overrides, friendly lock page, API. */
class ModulesTest extends RentSchemaTestCase
{
    private int $co;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        foreach (['company_modules', 'company_module_logs', 'company_subscriptions', 'subscription_plans'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('subscription_plans', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->text('features')->nullable(), $t->timestamps()]);
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->timestamps()]);
        Schema::create('company_modules', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->boolean('enabled'),
            $t->timestamp('expires_at')->nullable(), $t->string('note')->nullable(), $t->unsignedBigInteger('changed_by')->nullable(), $t->timestamps(), $t->unique(['company_id', 'module'])]);
        Schema::create('company_module_logs', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->string('from_state')->nullable(),
            $t->string('to_state'), $t->timestamp('expires_at')->nullable(), $t->string('note')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->timestamp('created_at')->nullable()]);
        Modules::flush();
        $this->co = $this->company('Danaff', [['rent_amount' => 25000]], automation: false)['company'];
        Route::middleware('web')->get('/rent/probe', fn () => 'rent page');
        Route::middleware('web')->get('/water/probe', fn () => 'water page');
    }

    private function user(?int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U '.$role, 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }

    private function plan(?array $modules): void
    {
        $features = ['business_features' => []] + ($modules === null ? [] : ['modules' => $modules]);
        $planId = DB::table('subscription_plans')->insertGetId(['name' => 'Plan', 'features' => json_encode($features)]);
        DB::table('company_subscriptions')->insert(['company_id' => $this->co, 'plan_id' => $planId, 'status' => 'active']);
        Modules::flush();
    }

    public function test_without_a_plan_list_every_module_stays_on(): void
    {
        $this->assertSame([], Modules::lockedFor($this->user($this->co, 'admin')));
        $this->plan(null);
        $this->assertSame([], Modules::lockedFor($this->user($this->co, 'admin')));
    }

    public function test_the_plan_sets_the_defaults_and_core_is_always_on(): void
    {
        $this->plan(['water', 'sms']);
        $this->assertTrue(Modules::on($this->co, 'water'));
        $this->assertFalse(Modules::on($this->co, 'rent_automation'));
        $this->assertTrue(Modules::on($this->co, 'properties'));
        $this->assertContains('rent', Modules::lockedMenu($this->user($this->co, 'admin')));
        $this->assertNotContains('water', Modules::lockedMenu($this->user($this->co, 'admin')));
    }

    public function test_locked_page_is_friendly_and_switching_on_brings_it_back(): void
    {
        $admin = $this->user($this->co, 'admin');
        $sys = $this->user($this->co, 'sysadmin');

        $this->actingAs($sys)->putJson("/admin/companies/{$this->co}/modules/rent_automation", ['state' => 'off', 'note' => 'Not paid'])
            ->assertOk()->assertJsonPath('state.on', false)->assertJsonPath('state.source', 'override');
        Modules::flush();

        $this->actingAs($admin)->get('/rent/probe')->assertOk()->assertHeader('X-Module-Locked', 'rent_automation')
            ->assertSee("Rent collection automation isn't on your plan", false)->assertDontSee('rent page');
        $this->actingAs($admin)->getJson('/rent/probe')->assertStatus(403)->assertJsonPath('error', 'module_locked');
        $this->actingAs($admin)->get('/water/probe')->assertOk()->assertSee('water page');
        // scharge staff always see everything.
        $this->actingAs($sys)->get('/rent/probe')->assertSee('rent page');

        $this->actingAs($sys)->putJson("/admin/companies/{$this->co}/modules/rent_automation", ['state' => 'plan'])->assertOk()->assertJsonPath('state.on', true);
        Modules::flush();
        $this->actingAs($admin)->get('/rent/probe')->assertSee('rent page');

        $this->assertSame(['off', 'plan'], DB::table('company_module_logs')->orderBy('id')->pluck('to_state')->all());
        $this->assertSame((int) $sys->id, (int) DB::table('company_module_logs')->value('user_id'));
    }

    public function test_only_sysadmins_switch_modules(): void
    {
        $this->actingAs($this->user($this->co, 'admin'))->putJson("/admin/companies/{$this->co}/modules/water", ['state' => 'off'])->assertForbidden();
        $this->assertSame(0, DB::table('company_modules')->count());
    }

    public function test_an_override_ends_on_its_expiry_and_the_plan_applies_again(): void
    {
        $this->plan(['water']);
        DB::table('company_modules')->insert(['company_id' => $this->co, 'module' => 'rent_automation', 'enabled' => true, 'expires_at' => now()->subDay()]);
        Modules::flush();
        $this->assertFalse(Modules::on($this->co, 'rent_automation'));
        DB::table('company_modules')->update(['expires_at' => now()->addWeek()]);
        Modules::flush();
        $this->assertTrue(Modules::on($this->co, 'rent_automation'));
        DB::table('company_modules')->insert(['company_id' => $this->co, 'module' => 'water', 'enabled' => false, 'expires_at' => null]);
        Modules::flush();
        $this->assertFalse(Modules::on($this->co, 'water'));
    }

    public function test_api_keys_respect_the_same_answer(): void
    {
        $key = new ApiKey(['name' => 'ERP']);
        $key->company_id = $this->co;
        $plain = $key->issue();
        $key->save();

        $this->plan(['water']);
        $this->withToken($plain)->getJson('/api/v1/me')->assertStatus(403)->assertJsonPath('module', 'api_keys');

        $this->plan(null);
        $this->assertNotSame('module_locked', $this->withToken($plain)->getJson('/api/v1/me')->json('error'));
    }

    public function test_plan_modules_are_saved_from_the_plan_page(): void
    {
        $planId = DB::table('subscription_plans')->insertGetId(['name' => 'Starter', 'features' => json_encode(['business_features' => ['x']])]);
        $sys = $this->user(null, 'sysadmin');
        $this->actingAs($sys)->putJson("/admin/subscriptions/plans/{$planId}/modules", ['all' => false, 'modules' => ['sms', 'nope']])->assertOk();
        $features = json_decode(DB::table('subscription_plans')->where('id', $planId)->value('features'), true);
        $this->assertSame(['sms'], $features['modules']);
        $this->assertSame(['x'], $features['business_features']);
        $this->actingAs($sys)->putJson("/admin/subscriptions/plans/{$planId}/modules", ['all' => true])->assertOk();
        $this->assertNull(Modules::modulesFromFeatures(DB::table('subscription_plans')->where('id', $planId)->value('features')));
    }
}
