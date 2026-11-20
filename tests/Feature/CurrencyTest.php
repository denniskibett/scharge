<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use App\Support\Currency;
use App\Support\FxRates;
use App\Support\Money;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Display currency with Sharet's FX rates: conversion maths, the stale-rate fallback, stored amounts untouched. docs/CURRENCY.md */
class CurrencyTest extends RentSchemaTestCase
{
    private string $store;

    /** What the fake Sharet answers: an array body, or an int HTTP status for a failure. */
    private array|int $sharet = 500;

    private int $sharetCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        Schema::table('companies', fn (Blueprint $t) => $t->char('display_currency', 3)->nullable());
        Schema::dropIfExists('system');
        Schema::create('system', fn (Blueprint $t) => [$t->id(), $t->string('name')->nullable(), $t->string('timezone')->nullable(), $t->string('date_format')->nullable(),
            $t->string('time_format')->nullable(), $t->string('currency', 3)->nullable(), $t->string('currency_symbol')->nullable(), $t->string('primary_color')->nullable(),
            $t->string('secondary_color')->nullable(), $t->string('slogan')->nullable(), $t->integer('pagination_limit')->nullable(), $t->boolean('maintenance_mode')->default(false),
            $t->json('settings')->nullable(), $t->json('location')->nullable(), $t->timestamps()]);
        DB::table('system')->insert(['name' => 'scharge', 'currency' => 'KES', 'currency_symbol' => 'KSh']);

        $this->store = sys_get_temp_dir().'/scharge-fx-'.uniqid().'.json';
        config(['currency.rates.url' => 'https://sharet.test/api/v1/fx/rates', 'currency.rates.store' => $this->store]);
        Http::fake(['sharet.test/*' => function () {
            $this->sharetCalls++;

            return is_int($this->sharet) ? Http::response(['error' => 'down'], $this->sharet) : Http::response($this->sharet);
        }]);
        Currency::flush();
        FxRates::forget();
    }

    protected function tearDown(): void
    {
        @unlink($this->store);
        Currency::flush();
        parent::tearDown();
    }

    /** 1 KES = 0.008 USD (125 KES a dollar) and 28.65 UGX, as Sharet publishes them. */
    private function sharetUp(string $date = '2026-11-20'): void
    {
        $this->sharet = ['base' => 'KES', 'date' => $date, 'source' => 'safaricom', 'rates' => ['USD' => '0.008', 'UGX' => '28.65']];
    }

    private function displayIn(?string $platform = null, ?int $company = null, ?string $companyCode = null): void
    {
        if ($platform) {
            DB::table('system')->update(['currency' => $platform]);
            Cache::forget('system_settings');
        }
        if ($company) {
            DB::table('companies')->where('id', $company)->update(['display_currency' => $companyCode]);
        }
        Currency::flush();
    }

    public function test_conversion_maths_and_rounding(): void
    {
        $this->sharetUp();

        $this->assertSame('12.00', Money::convert('1500', 'KES', 'USD'));
        $this->assertSame('9.88', Money::convert('1234.56', 'KES', 'USD'));       // 9.87648 rounds up
        $this->assertSame('3125.00', Money::convert(25, 'USD', 'KES'));
        $this->assertSame('42975', Money::convert(1500, 'KES', 'UGX'));            // UGX has no decimals
        $this->assertSame('35813', Money::convert(10, 'USD', 'UGX'));              // 35812.5 crossed through KES, half rounds up
        $this->assertSame('0.30', Money::convert(0.1 + 0.2, 'KES', 'KES'));        // floats go in as exact decimals
        $this->assertSame('-12.00', Money::convert('-1500', 'KES', 'USD'));
        $this->assertNull(Money::convert(100, 'KES', 'EUR'));                       // Sharet has no EUR rate: no guessing

        $this->assertSame('0.01', Money::round('0.005', 2));
        $this->assertSame('-0.01', Money::round('-0.005', 2));
        $this->assertSame('0.00', Money::round('-0.004', 2));                      // never "-0.00"
        $this->assertSame('3', Money::round('2.5', 0));

        $this->assertSame('USD 12.00', Money::show(1500, 'KES', 'USD'));
        $this->assertSame('KES 100.00', Money::show(100, 'KES', 'EUR'));            // no rate: stays in its own currency
        $this->assertSame('KES 1,500', Money::format(1500, 'KES', 0));
        $this->assertSame('AUD 10.00', Money::format(10, 'aud'));                    // a stored code is never relabelled

        $total = Money::total([[1500, 'KES'], [10, 'USD'], ['5', 'EUR']], 'KES');
        $this->assertSame(['amount' => '2750.00', 'currency' => 'KES', 'missing' => ['EUR']], $total);

        $this->assertStringContainsString("Shown in USD at Sharet's rate of 20 Nov 2026 (1 KES = 0.008 USD)", Money::note('KES', 'USD'));
        $this->assertNull(Money::note('KES', 'KES'));
        $this->assertSame(1, $this->sharetCalls, 'rates are cached, not fetched per figure');
    }

    public function test_sharets_pair_form_is_read_too(): void
    {
        $this->sharet = ['data' => ['date' => '2026-11-19', 'pairs' => ['KES/USD' => '0.008', 'TZS/KES' => '0.05', 'USD/EUR' => '0.9']]];

        $snap = FxRates::snapshot();
        $this->assertSame('2026-11-19', $snap['date']);
        $this->assertSame('0.008', $snap['rates']['USD']);
        $this->assertSame('20', rtrim(rtrim($snap['rates']['TZS'], '0'), '.'));   // TZS/KES inverted: 20 TZS per KES
        $this->assertArrayNotHasKey('EUR', $snap['rates']);                          // pairs not through KES are ignored
        $this->assertSame('2000', Money::convert(100, 'KES', 'TZS'));
    }

    public function test_the_last_known_rate_is_used_when_sharet_cannot_be_reached(): void
    {
        $this->sharetUp('2026-11-18');
        $this->assertSame('12.00', Money::convert(1500, 'KES', 'USD'));
        $this->assertFileExists($this->store);

        // The cache expires (or is cleared) and Sharet is down
        $this->sharet = 503;
        FxRates::forget();
        $this->assertSame('12.00', Money::convert(1500, 'KES', 'USD'));
        $snap = FxRates::snapshot();
        $this->assertTrue($snap['stale']);
        $this->assertSame('2026-11-18', $snap['date']);
        $this->assertStringContainsString('18 Nov 2026', Money::note('KES', 'USD'));
        $this->assertStringContainsString('last known rate', Money::note('KES', 'USD'));

        // Sharet is not asked again on every figure while it is down
        $calls = $this->sharetCalls;
        Money::convert(10, 'KES', 'USD');
        FxRates::snapshot();
        $this->assertSame($calls, $this->sharetCalls);

        // Back up: the scheduled refresh picks up the new rate and it is no longer stale
        $this->sharetUp('2026-11-20');
        $this->sharet['rates']['USD'] = '0.0075';
        $this->artisan('fx:rates')->assertSuccessful();
        $this->assertFalse(FxRates::snapshot()['stale']);
        $this->assertSame('11.25', Money::convert(1500, 'KES', 'USD'));
    }

    public function test_with_no_rate_ever_amounts_stay_in_kes(): void
    {
        $this->sharet = 500;
        $this->displayIn('USD');

        $this->assertSame('USD', Currency::display());
        $this->assertNull(Money::convert(1500));
        $this->assertSame('KES 1,500.00', Money::show(1500));
        $this->assertSame(['code' => 'KES', 'rate' => 1, 'decimals' => 2], array_slice(Money::js(), 0, 3));
        $this->assertStringContainsString('No Sharet rate for KES to USD', Money::note());
        $this->artisan('fx:rates')->assertFailed();
    }

    public function test_display_currency_follows_the_company_then_the_platform(): void
    {
        $this->sharetUp();
        $a = $this->company('Danaff', [['rent_amount' => 10000]], automation: false)['company'];
        $b = $this->company('Other', [['rent_amount' => 10000]], automation: false)['company'];

        $this->assertSame('KES', Currency::platform());
        $this->displayIn('USD');
        $this->assertSame('USD', Currency::forCompany($a));
        $this->displayIn(null, $b, 'UGX');
        $this->assertSame('UGX', Currency::forCompany($b));
        $this->assertSame('USD', Currency::forCompany($a));

        // A leftover platform value Sharet doesn't quote (AUD on old installs) falls back to KES
        $this->displayIn('AUD');
        $this->assertSame('KES', Currency::platform());

        $this->actingAs($this->user($b, 'admin'));
        $this->assertSame('UGX', Currency::display());
        $this->assertSame('UGX 286,500', Money::show(10000));
    }

    public function test_stored_amounts_never_change_when_the_display_currency_does(): void
    {
        $this->sharetUp();
        $co = $this->company('Danaff', [['rent_amount' => 10000], ['rent_amount' => 12500]], automation: false)['company'];
        app(RentRun::class)->run($co, $this->month('2026-12'), notify: false);
        DB::table('payments')->insert(['invoice_id' => DB::table('invoices')->value('id'), 'amount' => 4000, 'status' => 'completed']);
        Schema::create('subscription_invoices', fn (Blueprint $t) => [$t->id(), $t->decimal('amount', 10, 2), $t->string('currency')->default('KES'), $t->timestamps()]);
        DB::table('subscription_invoices')->insert([['amount' => 1500, 'currency' => 'KES'], ['amount' => 20, 'currency' => 'USD']]);

        $before = $this->money();
        $this->assertNotEmpty($before['invoices']);

        $admin = $this->user($co, 'admin');
        $this->actingAs($admin)->get('/settings/white-label')->assertOk()->assertSee('Display currency');
        $this->actingAs($admin)->post('/settings/currency', ['display_currency' => 'USD'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('USD', DB::table('companies')->where('id', $co)->value('display_currency'));
        $this->actingAs($admin)->post('/settings/currency', ['display_currency' => 'XBT'])->assertSessionHasErrors('display_currency');
        $this->displayIn('UGX');
        Currency::flush();

        // Figures on screen convert...
        $this->assertSame('USD', Currency::display());
        $rent = (string) DB::table('invoices')->value('total_amount');
        $this->assertStringStartsWith('USD ', Money::show($rent));
        // ...but every stored amount and currency is exactly as it was, and is still charged as stored
        $this->assertSame($before, $this->money());
        $this->assertSame('KES 1,500.00', Money::format(DB::table('subscription_invoices')->where('currency', 'KES')->value('amount'), 'KES'));
        $this->assertSame('USD 20.00', Money::format(20, DB::table('subscription_invoices')->where('currency', 'USD')->value('currency')));

        // Back to the platform default
        $this->actingAs($admin)->post('/settings/currency', ['display_currency' => ''])->assertRedirect();
        $this->assertNull(DB::table('companies')->where('id', $co)->value('display_currency'));
        $this->assertSame('UGX', Currency::forCompany($co));
        $this->assertSame($before, $this->money());
    }

    private function money(): array
    {
        $rows = fn (string $table) => DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return ['invoices' => $rows('invoices'), 'invoice_items' => $rows('invoice_items'), 'payments' => $rows('payments'), 'subscription_invoices' => $rows('subscription_invoices')];
    }

    private function user(int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Admin', 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }
}
