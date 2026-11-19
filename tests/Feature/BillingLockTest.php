<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Support\BillingLock;
use App\Support\CompanyAccess;
use App\Support\Modules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Unpaid scharge bill past due → the whole company is locked except Billing (App\Support\BillingLock). */
class BillingLockTest extends RentSchemaTestCase
{
    private int $co;

    private int $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        foreach (['company_modules', 'company_module_logs', 'company_subscriptions', 'subscription_plans', 'subscription_invoices'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('subscription_plans', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->text('features')->nullable(), $t->timestamps()]);
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->timestamps()]);
        Schema::create('subscription_invoices', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_subscription_id'), $t->string('invoice_number'), $t->decimal('amount', 10, 2),
            $t->string('currency')->default('KES'), $t->string('status')->default('pending'), $t->date('due_date'), $t->timestamp('paid_at')->nullable(), $t->timestamps()]);
        Schema::create('company_modules', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->boolean('enabled'),
            $t->timestamp('expires_at')->nullable(), $t->string('note')->nullable(), $t->unsignedBigInteger('changed_by')->nullable(), $t->timestamps(), $t->unique(['company_id', 'module'])]);
        Schema::create('company_module_logs', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->string('from_state')->nullable(),
            $t->string('to_state'), $t->timestamp('expires_at')->nullable(), $t->string('note')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->timestamp('created_at')->nullable()]);
        config(['billing.lock_enabled' => true, 'billing.lock_from' => null, 'billing.paybill' => '123456']);
        $this->co = $this->company('Danaff', [['rent_amount' => 25000]], automation: false)['company'];
        $plan = DB::table('subscription_plans')->insertGetId(['name' => 'Plan']);
        $sub = DB::table('company_subscriptions')->insertGetId(['company_id' => $this->co, 'plan_id' => $plan, 'status' => 'active']);
        $this->invoice = DB::table('subscription_invoices')->insertGetId(['company_subscription_id' => $sub, 'invoice_number' => 'SCH-0001',
            'amount' => 67600, 'due_date' => now()->subDay()->toDateString()]);
        $this->fresh();
        Route::middleware('web')->get('/rent/probe', fn () => 'rent page');
        Route::middleware('web')->get('/tenant-probe', fn () => 'tenant page');
    }

    private function fresh(): void
    {
        BillingLock::flush();
        Modules::flush();
    }

    private function user(?int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U '.$role, 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }

    public function test_overdue_locks_everyone_in_the_company_except_billing(): void
    {
        $admin = $this->user($this->co, 'admin');
        $manager = $this->user($this->co, 'property_manager');

        $this->actingAs($admin)->get('/rent/probe')->assertRedirect('/billing');
        $this->actingAs($manager)->get('/tenant-probe')->assertRedirect('/billing');
        $this->actingAs($admin)->getJson('/rent/probe')->assertStatus(403)->assertJsonPath('error', 'billing_locked');

        // Owner sees the amount and how to pay; others only that access is paused.
        $this->actingAs($admin)->get('/billing')->assertOk()->assertSee('KES 67,600')->assertSee('SCH-0001')->assertSee('123456');
        $this->actingAs($manager)->get('/billing')->assertOk()->assertSee('Contact your admin to restore it.')
            ->assertSee('U admin')->assertDontSee('67,600')->assertDontSee('SCH-0001');
    }

    public function test_paying_unlocks_at_once(): void
    {
        $admin = $this->user($this->co, 'admin');
        $this->actingAs($admin)->get('/rent/probe')->assertRedirect('/billing');
        DB::table('subscription_invoices')->where('id', $this->invoice)->update(['status' => 'paid', 'paid_at' => now()]);
        $this->fresh();
        $this->actingAs($admin)->get('/rent/probe')->assertOk()->assertSee('rent page');
    }

    public function test_staff_tenants_and_the_public_are_never_locked(): void
    {
        $this->actingAs($this->user($this->co, 'sysadmin'))->get('/rent/probe')->assertSee('rent page');
        $this->actingAs($this->user(null, 'account_manager'))->get('/rent/probe')->assertSee('rent page');
        $this->actingAs($this->user($this->co, 'tenant'))->get('/tenant-probe')->assertSee('tenant page');
        auth()->logout();
        $this->get('/tenant-probe')->assertSee('tenant page');
    }

    public function test_not_yet_due_grace_lock_from_and_lock_off_do_not_lock(): void
    {
        $admin = $this->user($this->co, 'admin');
        DB::table('subscription_invoices')->update(['due_date' => now()->toDateString()]); // due today: not past due
        $this->fresh();
        $this->actingAs($admin)->get('/rent/probe')->assertSee('rent page');

        DB::table('subscription_invoices')->update(['due_date' => '2026-07-14']);
        config(['billing.lock_from' => now()->subMonth()->toDateString()]); // older bills never lock
        $this->fresh();
        $this->actingAs($admin)->get('/rent/probe')->assertSee('rent page');

        config(['billing.lock_from' => null]);
        BillingLock::setGrace($this->co, now()->addWeek()->toDateString(), 'Paying Friday', null);
        $this->actingAs($admin)->get('/rent/probe')->assertSee('rent page');
        BillingLock::setGrace($this->co, null, null, null);
        $this->actingAs($admin)->get('/rent/probe')->assertRedirect('/billing');
        $this->assertSame(2, DB::table('company_module_logs')->where('module', 'billing_grace')->count());

        config(['billing.lock_enabled' => false]);
        $this->fresh();
        $this->actingAs($admin)->get('/rent/probe')->assertSee('rent page');
    }

    public function test_api_keys_and_sysadmin_grace_endpoint(): void
    {
        $key = new ApiKey(['name' => 'ERP']);
        $key->company_id = $this->co;
        $plain = $key->issue();
        $key->save();
        $this->withToken($plain)->getJson('/api/v1/me')->assertStatus(403)->assertJsonPath('error', 'billing_locked');

        $sys = $this->user(null, 'sysadmin');
        $this->actingAs($sys)->putJson("/admin/companies/{$this->co}/billing-grace", ['until' => now()->addDays(3)->toDateString(), 'note' => 'Promised'])
            ->assertOk()->assertJsonPath('billing.locked', false);
        $this->actingAs($this->user($this->co, 'admin'))->putJson("/admin/companies/{$this->co}/billing-grace", ['until' => null])->assertForbidden();
    }
}
