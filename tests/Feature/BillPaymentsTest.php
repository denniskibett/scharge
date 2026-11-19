<?php

namespace Tests\Feature;

use App\Models\BillingPayment;
use App\Models\CompanyIntegration;
use App\Models\User;
use App\Services\Integrations;
use App\Services\Sharet\BillPayments;
use App\Services\Sharet\SharetPayouts;
use App\Support\BillingLock;
use App\Support\CompanyAccess;
use App\Support\Modules;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Companies pay scharge's bills from Billing: Sharet Pay or their scharge wallet (App\Services\Sharet\BillPayments). */
class BillPaymentsTest extends RentSchemaTestCase
{
    private int $co;

    private int $invoice;

    private array $sharetCalls = [];

    private bool $failPayouts = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        foreach (['company_modules', 'company_module_logs', 'company_subscriptions', 'subscription_plans', 'subscription_invoices', 'billing_payments', 'sharet_payment_requests', 'sharet_sweeps', 'sharet_webhook_events'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('subscription_plans', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->text('features')->nullable(), $t->timestamps()]);
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'), $t->timestamps()]);
        Schema::create('subscription_invoices', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_subscription_id'), $t->string('invoice_number'), $t->decimal('amount', 10, 2),
            $t->string('currency')->default('KES'), $t->string('status')->default('pending'), $t->string('payment_method')->nullable(), $t->string('payment_id')->nullable(),
            $t->date('due_date'), $t->timestamp('paid_at')->nullable(), $t->timestamps()]);
        Schema::create('company_modules', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->boolean('enabled'),
            $t->timestamp('expires_at')->nullable(), $t->string('note')->nullable(), $t->unsignedBigInteger('changed_by')->nullable(), $t->timestamps()]);
        Schema::create('company_module_logs', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('module'), $t->string('to_state'), $t->timestamp('created_at')->nullable()]);
        Schema::create('sharet_payment_requests', fn (Blueprint $t) => [$t->id(), $t->string('sharet_id')->unique(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('invoice_id')->nullable(),
            $t->unsignedBigInteger('tenant_id')->nullable(), $t->string('environment')->default('test'), $t->string('mode')->default('rail'), $t->string('sharet_account')->nullable(),
            $t->decimal('amount', 12, 2), $t->string('status')->default('open'), $t->string('pay_url')->nullable(), $t->string('reference')->nullable(), $t->string('paid_via')->nullable(),
            $t->timestamp('paid_at')->nullable(), $t->timestamp('expires_at')->nullable(), $t->unsignedBigInteger('settled_payment_id')->nullable(), $t->unsignedBigInteger('sweep_id')->nullable(),
            $t->json('raw')->nullable(), $t->timestamps()]);
        Schema::create('sharet_sweeps', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('slot'), $t->unsignedInteger('requests_count')->default(0),
            $t->decimal('gross', 12, 2)->default(0), $t->decimal('fee', 12, 2)->default(0), $t->decimal('net', 12, 2)->default(0), $t->json('destination')->nullable(),
            $t->string('status')->default('pending'), $t->string('sharet_payment_id')->nullable(), $t->string('failure')->nullable(), $t->timestamps(), $t->unique(['company_id', 'slot'])]);
        Schema::create('sharet_webhook_events', fn (Blueprint $t) => [$t->id(), $t->string('event_id')->unique(), $t->string('type'), $t->longText('payload')->nullable(), $t->timestamp('processed_at')->nullable(), $t->timestamps()]);
        // Same columns as database/schema/2026_11_20_billing_payments.sql.
        Schema::create('billing_payments', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('subscription_invoice_id'), $t->string('method'),
            $t->decimal('amount', 12, 2), $t->string('currency')->default('KES'), $t->string('status')->default('open'), $t->string('environment')->default('test'),
            $t->string('sharet_id')->nullable()->unique(), $t->string('sharet_transaction')->nullable(), $t->string('pay_url')->nullable(), $t->string('paid_via')->nullable(),
            $t->unsignedBigInteger('sweep_id')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->timestamp('paid_at')->nullable(), $t->timestamp('expires_at')->nullable(),
            $t->json('raw')->nullable(), $t->timestamps()]);

        config(['billing.lock_enabled' => true, 'billing.lock_from' => null]);
        CompanyIntegration::create(['company_id' => null, 'provider' => 'sharet', 'enabled' => true,
            'credentials' => ['api_key' => 'sk_test_abc', 'base_url' => 'https://sharet.example.test/api/v1', 'webhook_secret' => 'whsec_test', 'fee_percent' => 2.5, 'sweep_minimum' => 100]]);
        Integrations::forgetInstalled();
        Integrations::forget(null, 'sharet');

        $this->co = $this->company('Danaff', [['rent_amount' => 25000]], automation: false)['company'];
        $plan = DB::table('subscription_plans')->insertGetId(['name' => 'Plan']);
        $sub = DB::table('company_subscriptions')->insertGetId(['company_id' => $this->co, 'plan_id' => $plan, 'status' => 'active']);
        $this->invoice = DB::table('subscription_invoices')->insertGetId(['company_subscription_id' => $sub, 'invoice_number' => 'SCH-0001',
            'amount' => 6760, 'due_date' => now()->subDay()->toDateString()]);

        Http::fake(['sharet.example.test/*' => function ($request) {
            $this->sharetCalls[] = $request;
            if (str_ends_with($request->url(), '/payment-requests')) {
                return Http::response(['id' => 'pr_'.count($this->sharetCalls), 'status' => 'open', 'environment' => 'test',
                    'pay_url' => 'https://sharet.example.test/pay/pr_'.count($this->sharetCalls), 'expires_at' => now()->addDay()->toIso8601String()], 201);
            }
            if (str_ends_with($request->url(), '/payments')) {
                return $this->failPayouts ? Http::response(['title' => 'Insufficient float'], 422) : Http::response(['id' => 'pay_1', 'status' => 'paid']);
            }

            return Http::response(['status' => 'cancelled']);
        }]);
        Route::middleware('web')->get('/rent/probe', fn () => 'rent page');
        $this->fresh();
    }

    private function fresh(): void
    {
        BillingLock::flush();
        Modules::flush();
    }

    private function user(?int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U '.$role, 'email' => uniqid().'@example.test', 'phone' => '0712345678', 'company_id' => $companyId]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }

    /** Rent paid into scharge's Sharet wallet for this company (rail mode), not yet paid out. */
    private function collected(float $amount, ?int $companyId = null): void
    {
        DB::table('sharet_payment_requests')->insert(['sharet_id' => uniqid('pr_rent_'), 'company_id' => $companyId ?? $this->co, 'environment' => 'test', 'mode' => 'rail',
            'amount' => $amount, 'status' => 'paid', 'reference' => 'Invoice #1', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function webhook(array $event)
    {
        auth()->logout();   // Sharet calls with no session
        $raw = json_encode($event);
        $t = time();

        return $this->call('POST', '/webhooks/sharet', [], [], [], ['HTTP_SHARET_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$raw, 'whsec_test'), 'CONTENT_TYPE' => 'application/json'], $raw);
    }

    public function test_owner_pays_with_sharet_pay_and_the_company_unlocks(): void
    {
        $admin = $this->user($this->co, 'admin');
        $this->actingAs($admin)->get('/rent/probe')->assertRedirect('/billing');
        $this->actingAs($admin)->get('/billing')->assertOk()->assertSee('Pay with Sharet Pay')->assertDontSee('Cloud9');

        $this->actingAs($admin)->post("/billing/{$this->invoice}/pay/sharet", ['phone' => '0722000111'])->assertRedirect('https://sharet.example.test/pay/pr_1');
        $body = $this->sharetCalls[0]->data();
        $this->assertSame('6760.00', $body['amount']);
        $this->assertSame((string) $this->invoice, $body['metadata']['scharge_bill']);
        $this->assertSame('0722000111', $body['payer']['phone']);
        $this->assertFalse($this->sharetCalls[0]->hasHeader('Sharet-Account'));   // scharge's own account
        $this->assertNotEmpty($this->sharetCalls[0]->header('Idempotency-Key'));

        // Clicking again reuses the open link.
        $this->actingAs($admin)->post("/billing/{$this->invoice}/pay/sharet")->assertRedirect('https://sharet.example.test/pay/pr_1');
        $this->assertCount(1, $this->sharetCalls);

        $paid = ['id' => 'pr_1', 'status' => 'paid', 'paid_via' => 'mpesa', 'paid_at' => now()->toIso8601String(), 'transaction_id' => 'SHD1CCFAF6'];
        $this->webhook(['id' => 'evt_1', 'type' => 'payment_request.paid', 'data' => $paid])->assertOk();
        $this->webhook(['id' => 'evt_2', 'type' => 'payment_request.paid', 'data' => $paid])->assertOk();   // a second event changes nothing

        $bill = DB::table('subscription_invoices')->find($this->invoice);
        $this->assertSame('paid', $bill->status);
        $this->assertNotNull($bill->paid_at);
        $this->assertSame('sharet_pay', $bill->payment_method);
        $this->assertSame('SHD1CCFAF6', $bill->payment_id);
        $this->assertSame(1, BillingPayment::where('status', 'paid')->count());

        $this->fresh();
        $this->actingAs($admin)->get('/rent/probe')->assertOk()->assertSee('rent page');
        $this->actingAs($admin)->get('/billing')->assertSee('scharge bill SCH-0001 paid with Sharet Pay');
    }

    public function test_owner_pays_from_the_wallet_less_fees_and_the_payout_takes_the_rest(): void
    {
        $this->collected(10000);
        $accountant = $this->user($this->co, 'accountant');
        // 10,000 collected, 2.5% fee = 250 → 9,750 available.
        $this->assertSame(9750.0, BillPayments::wallet($this->co)['available']);
        $this->actingAs($accountant)->get('/billing')->assertSee('/pay/wallet')->assertSee('KES 9,750');

        $this->actingAs($accountant)->post("/billing/{$this->invoice}/pay/wallet")->assertRedirect('/billing');
        $this->assertSame('paid', DB::table('subscription_invoices')->where('id', $this->invoice)->value('status'));
        $this->assertSame('wallet', DB::table('subscription_invoices')->where('id', $this->invoice)->value('payment_method'));
        $this->assertSame(2990.0, BillPayments::wallet($this->co)['available']);
        $this->fresh();
        $this->actingAs($accountant)->get('/rent/probe')->assertOk();
        $this->actingAs($accountant)->get('/billing')->assertSee('scharge bill SCH-0001 paid from wallet')->assertSee('Rent collected');

        // The payout sends what is left (10,000 − 250 fee − 6,760 bill) to the company's M-Pesa.
        CompanyIntegration::create(['company_id' => $this->co, 'provider' => 'sharet_payouts', 'enabled' => true, 'credentials' => ['mode' => 'rail', 'payout_type' => 'mpesa', 'phone' => '0712345678']]);
        Integrations::forget($this->co, 'sharet_payouts');
        $result = app(SharetPayouts::class)->sweep('2026-11-20T08');
        $this->assertSame('paid', $result[0]['status']);
        $this->assertSame('2990.00', collect($this->sharetCalls)->last()->data()['amount']);
        $this->assertNotNull(BillingPayment::first()->sweep_id);
        $this->assertSame(0.0, BillPayments::wallet($this->co)['available']);
    }

    public function test_wallet_needs_enough_balance_and_only_payers_can_pay(): void
    {
        $this->collected(5000);
        $admin = $this->user($this->co, 'admin');
        $this->actingAs($admin)->get('/billing')->assertSee('/pay/sharet')->assertDontSee('/pay/wallet');
        $this->actingAs($admin)->from('/billing')->post("/billing/{$this->invoice}/pay/wallet")->assertRedirect('/billing')->assertSessionHas('error');
        $this->assertSame('pending', DB::table('subscription_invoices')->where('id', $this->invoice)->value('status'));

        $this->actingAs($this->user($this->co, 'property_manager'))->post("/billing/{$this->invoice}/pay/sharet")->assertForbidden();

        // Another company's bill is not found.
        $other = $this->company('Other', [['rent_amount' => 1000]], automation: false)['company'];
        $this->collected(50000, $other);
        $this->actingAs($this->user($other, 'admin'))->from('/billing')->post("/billing/{$this->invoice}/pay/wallet")->assertSessionHas('error', 'Bill not found.');
        $this->assertSame('pending', DB::table('subscription_invoices')->where('id', $this->invoice)->value('status'));
    }

    public function test_a_failed_payout_gives_the_wallet_payment_back_to_the_next_sweep(): void
    {
        $this->collected(10000);
        app(BillPayments::class)->payFromWallet($this->co, $this->invoice);
        CompanyIntegration::create(['company_id' => $this->co, 'provider' => 'sharet_payouts', 'enabled' => true, 'credentials' => ['mode' => 'rail', 'payout_type' => 'mpesa', 'phone' => '0712345678']]);
        Integrations::forget($this->co, 'sharet_payouts');
        $this->failPayouts = true;
        app(SharetPayouts::class)->sweep('2026-11-20T08');
        $this->assertNull(BillingPayment::first()->sweep_id);
        $this->assertSame(2990.0, BillPayments::wallet($this->co)['available']);
    }
}
