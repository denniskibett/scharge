<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\FirstBill;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** New accounts' first bill: staff set the price, one month is billed once units are in, paying it opens 3 months. */
class FirstBillTest extends RentSchemaTestCase
{
    private int $co;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['company_subscriptions', 'subscription_plans', 'subscription_invoices', 'company_modules', 'company_module_logs'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('subscription_plans', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->text('features')->nullable(), $t->timestamps()]);
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'),
            $t->timestamp('starts_at')->nullable(), $t->timestamp('ends_at')->nullable(), $t->timestamps()]);
        Schema::create('subscription_invoices', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_subscription_id'), $t->string('invoice_number'), $t->decimal('amount', 10, 2),
            $t->string('currency')->default('KES'), $t->string('status')->default('pending'), $t->string('payment_method')->nullable(), $t->string('payment_id')->nullable(),
            $t->date('due_date'), $t->timestamp('paid_at')->nullable(), $t->longText('invoice_json')->nullable(), $t->timestamps()]);
        Schema::table('companies', fn (Blueprint $t) => [$t->decimal('price_per_unit', 10, 2)->nullable(), $t->unsignedBigInteger('price_set_by')->nullable(),
            $t->timestamp('price_set_at')->nullable(), $t->string('subscription_status')->nullable(), $t->timestamp('subscription_ends_at')->nullable()]);
        DB::table('subscription_plans')->insert(['name' => 'Plan']);
        config(['billing.first_bill_from' => '2026-11-23', 'billing.first_bill_due_days' => 7, 'billing.first_bill_months' => 3]);

        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(9, 0));
        $this->co = $this->company('Kilimani Heights', [[], [], []], automation: false)['company'];
        DB::table('companies')->where('id', $this->co)->update(['created_at' => now()]);
        DB::table('units')->update(['created_at' => now()]);
    }

    public function test_no_price_no_bill_then_one_month_billed_once_units_settle(): void
    {
        $this->artisan('billing:first-bills')->assertSuccessful();
        $this->assertSame(0, DB::table('subscription_invoices')->count(), 'no bill without a staff price');

        DB::table('companies')->where('id', $this->co)->update(['price_per_unit' => 150]);
        FirstBill::run();
        $this->assertSame(0, DB::table('subscription_invoices')->count(), 'units added under an hour ago: wait for the import to finish');

        $this->travel(61)->minutes();
        FirstBill::run();
        $bill = DB::table('subscription_invoices')->sole();
        $this->assertSame(450.0, (float) $bill->amount);                 // 3 units × KES 150, one month
        $this->assertSame('pending', $bill->status);
        $this->assertSame('2026-12-01', $bill->due_date);                // due in 7 days
        $json = json_decode($bill->invoice_json, true);
        $this->assertTrue($json['first_bill']);
        $this->assertSame(3, $json['covers_months']);

        FirstBill::run();
        $this->assertSame(1, DB::table('subscription_invoices')->count(), 'never issued twice');
        $this->assertSame('First bill issued, due 1 Dec 2026.', FirstBill::status($this->co)['note']);
    }

    public function test_paying_the_first_bill_opens_three_months(): void
    {
        DB::table('companies')->where('id', $this->co)->update(['price_per_unit' => 40]);
        DB::table('units')->update(['created_at' => now()->subHours(2)]);
        $id = FirstBill::issue($this->co);
        $this->assertNotNull($id);

        DB::table('subscription_invoices')->where('id', $id)->update(['status' => 'paid', 'paid_at' => '2026-11-26 10:00:00']);
        FirstBill::run();   // the hourly job also catches bills paid by any route

        $sub = DB::table('company_subscriptions')->sole();
        $this->assertSame('active', $sub->status);
        $this->assertSame('2027-02-26 10:00:00', $sub->ends_at);
        $company = DB::table('companies')->where('id', $this->co)->first();
        $this->assertSame('active', $company->subscription_status);
        $this->assertSame('2027-02-26 10:00:00', $company->subscription_ends_at);

        FirstBill::paid($id);
        $this->assertSame('2027-02-26 10:00:00', DB::table('company_subscriptions')->value('ends_at'), 'safe to call twice');
    }

    public function test_older_companies_and_companies_already_billed_are_left_alone(): void
    {
        DB::table('companies')->where('id', $this->co)->update(['price_per_unit' => 300, 'created_at' => '2026-10-01 08:00:00']);
        DB::table('units')->update(['created_at' => now()->subDay()]);
        $this->assertNull(FirstBill::issue($this->co));
        $this->assertSame('Created before the first-bill offer; bill it by hand.', FirstBill::status($this->co)['note']);

        $new = $this->company('Ruiru Court', [[]], automation: false)['company'];
        DB::table('companies')->where('id', $new)->update(['created_at' => now(), 'price_per_unit' => 40]);
        $sub = DB::table('company_subscriptions')->insertGetId(['company_id' => $new, 'plan_id' => 1, 'status' => 'active']);
        DB::table('subscription_invoices')->insert(['company_subscription_id' => $sub, 'invoice_number' => 'INV-OLD', 'amount' => 99, 'due_date' => '2026-12-01']);
        DB::table('units')->update(['created_at' => now()->subDay()]);
        $this->assertNull(FirstBill::issue($new));
        $this->assertSame(1, DB::table('subscription_invoices')->count());
    }

    public function test_sysadmin_sets_the_price_and_others_cannot(): void
    {
        DB::table('units')->update(['created_at' => now()->subHours(3)]);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $manager = $this->user('account_manager');
        $this->actingAs($manager)->putJson("/admin/companies/{$this->co}/billing-price", ['price' => 150])->assertForbidden();

        $admin = $this->user('sysadmin');
        $this->actingAs($admin)->putJson("/admin/companies/{$this->co}/billing-price", ['price' => 150])->assertOk()
            ->assertJsonPath('first_bill.price', 150)->assertJsonPath('first_bill.bill.amount', 450);
        $this->assertSame($admin->id, (int) DB::table('companies')->where('id', $this->co)->value('price_set_by'));
        $this->actingAs($admin)->putJson("/admin/companies/{$this->co}/billing-price", ['price' => 0])->assertUnprocessable();
    }

    private function user(string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Staff '.$role, 'email' => uniqid().'@example.test']);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }
}
