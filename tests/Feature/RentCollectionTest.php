<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Models\Invoice;
use App\Models\PaybillPayment;
use App\Models\Payment;
use App\Models\RentHold;
use App\Models\RentMessage;
use App\Models\User;
use App\Services\Rent\ArrearsReminders;
use App\Services\Rent\LatePenalties;
use App\Services\Rent\PaybillPayments;
use App\Services\Rent\PaybillRegistration;
use App\Services\Rent\RentRun;
use App\Services\Rent\RentSettings;
use App\Support\CompanyAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Paybill payments, late penalties (owner's switch), paused tenants and the Rent collection page. */
class RentCollectionTest extends RentSchemaTestCase
{
    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        $this->a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 8000]], settings: ['send_bill' => 'no']);
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'mpesa_daraja', 'enabled' => true,
            'credentials' => ['environment' => 'sandbox', 'consumer_key' => 'k', 'consumer_secret' => 's', 'shortcode' => '600111', 'passkey' => 'p', 'kind' => 'paybill']]);
        Cache::forget('paybill-shortcodes');
        $run = app(RentRun::class);
        $run->run($this->a['company'], $this->month('2026-11'));
        $run->run($this->a['company'], $this->month('2026-12'));
    }

    private function c2b(string $id, float $amount, string $ref, string $msisdn = '2547xxxxx'): array
    {
        return ['TransactionType' => 'Pay Bill', 'TransID' => $id, 'TransTime' => '20261206101500', 'TransAmount' => (string) $amount,
            'BusinessShortCode' => '600111', 'BillRefNumber' => $ref, 'MSISDN' => $msisdn, 'FirstName' => 'JANE', 'LastName' => 'WANJIKU'];
    }

    private function confirm(array $payload)
    {
        return $this->postJson('/webhooks/mpesa/c2b/'.PaybillRegistration::token().'/confirmation', $payload);
    }

    public function test_paybill_payment_with_the_unit_as_account_pays_the_oldest_bills_first_once(): void
    {
        $this->confirm($this->c2b('SKA1', 15000, ' a-1 '))->assertOk()->assertJson(['ResultCode' => '0']);
        $this->confirm($this->c2b('SKA1', 15000, 'A1'))->assertOk(); // Safaricom repeats: ignored

        $row = PaybillPayment::sole();
        $this->assertSame('applied', $row->status);
        $this->assertSame('account', $row->matched_by);
        $bills = Invoice::where('tenancy_id', $this->a['tenancies'][0])->orderBy('billing_month')->get();
        $this->assertSame(['paid', 'partial'], $bills->pluck('status')->all());
        $this->assertEquals(5000, (float) $bills[1]->total_paid);
        $this->assertSame(2, Payment::count());
        $this->assertSame(['SKA1', 'SKA1-2'], Payment::orderBy('id')->pluck('transaction_reference')->all());
    }

    public function test_overpayment_is_credit_and_bill_numbers_and_phones_also_match(): void
    {
        $nov = Invoice::where('tenancy_id', $this->a['tenancies'][1])->orderBy('billing_month')->first();
        $this->confirm($this->c2b('SKB1', 20000, 'INV-'.$nov->id))->assertOk();
        $this->assertSame('invoice', PaybillPayment::where('trans_id', 'SKB1')->value('matched_by'));
        $this->assertSame(0, Invoice::where('tenancy_id', $this->a['tenancies'][1])->where('status', '!=', 'paid')->count());
        $tenant = \App\Models\Tenant::find(DB::table('tenancies')->where('id', $this->a['tenancies'][1])->value('tenant_id'));
        $this->assertEquals(4000, (float) $tenant->balance); // 20,000 - 16,000

        $this->confirm($this->c2b('SKB2', 500, 'rent', '254712000001'))->assertOk(); // tenant 1's phone
        $this->assertSame('phone', PaybillPayment::where('trans_id', 'SKB2')->value('matched_by'));
    }

    public function test_unknown_account_waits_for_the_landlord_and_wrong_secret_is_refused(): void
    {
        $this->postJson('/webhooks/mpesa/c2b/wrong/confirmation', $this->c2b('SKX', 100, 'A1'))->assertNotFound();
        $this->confirm($this->c2b('SKC1', 3000, 'HOUSE 9'))->assertOk();
        $row = PaybillPayment::sole();
        $this->assertSame('unmatched', $row->status);

        $admin = $this->staff($this->a['company']);
        $this->actingAs($admin)->get('/rent')->assertOk()->assertSee('HOUSE 9')->assertSee('Choose tenant');
        $this->actingAs($admin)->post('/rent/paybill/'.$row->id.'/assign', ['tenancy_id' => $this->a['tenancies'][1]])->assertRedirect();
        $this->assertSame('applied', $row->fresh()->status);
        $this->assertSame('manual', $row->fresh()->matched_by);
        $this->actingAs($admin)->post('/rent/paybill/'.$row->id.'/assign', ['tenancy_id' => $this->a['tenancies'][1]])->assertStatus(422); // only once
    }

    public function test_another_company_cannot_see_or_assign_the_payment(): void
    {
        $this->confirm($this->c2b('SKD1', 3000, 'nothing'))->assertOk();
        $b = $this->company('Other', [['rent_amount' => 1000]], automation: false);
        $other = $this->staff($b['company']);

        $this->actingAs($other)->get('/rent')->assertOk()->assertDontSee('SKD1');
        $this->actingAs($other)->post('/rent/paybill/'.PaybillPayment::sole()->id.'/assign', ['tenancy_id' => $b['tenancies'][0]])->assertNotFound();
    }

    public function test_penalty_only_when_the_owner_switched_it_on_once_per_bill_and_never_when_paused(): void
    {
        $today = CarbonImmutable::parse('2026-12-12', RentSettings::TZ);
        $penalties = app(LatePenalties::class);
        $this->assertSame(0, $penalties->run(RentSettings::for($this->a['company']), $today)['added']); // off by default

        CompanyIntegration::where('provider', RentSettings::PROVIDER)->first()->forceFill(['credentials' => ['invoice_day' => '1', 'due_day' => '5',
            'send_bill' => 'no', 'reminder_days' => '1,7', 'channel' => 'sms', 'penalty' => 'percent', 'penalty_amount' => '10', 'penalty_after_days' => '5']])->save();
        RentHold::create(['company_id' => $this->a['company'], 'tenancy_id' => $this->a['tenancies'][1], 'reason' => 'Payment plan']);
        $settings = RentSettings::for($this->a['company']);

        $r = $penalties->run($settings, $today); // Nov (37 days late) and Dec (7 days late) of tenant 1; tenant 2 paused
        $this->assertSame(2, $r['added']);
        $this->assertSame(0, $penalties->run($settings, $today)['added']);
        $nov = Invoice::where('tenancy_id', $this->a['tenancies'][0])->orderBy('billing_month')->first();
        $this->assertEquals(11000, (float) $nov->total_amount);
        $this->assertSame('Late payment penalty (10% of KES 10,000 unpaid)', $nov->items()->where('item_type', 'other')->value('description'));
        $this->assertEquals(8000, (float) Invoice::where('tenancy_id', $this->a['tenancies'][1])->orderBy('billing_month')->first()->total_amount); // paused: no penalty

        // Paused tenant: no reminder either.
        $sent = app(ArrearsReminders::class)->run($settings, $today);
        $this->assertSame([(int) $this->a['tenancies'][0]], array_column($sent['due'], 'tenancy_id'));
    }

    public function test_failed_messages_can_be_sent_again_and_fresh_links_go_to_everyone_who_owes(): void
    {
        DB::table('users')->where('phone', '0712000002')->update(['phone' => null]);
        $admin = $this->staff($this->a['company']);
        $this->actingAs($admin)->post('/rent/resend-links')->assertRedirect()->assertSessionHas('success', 'Fresh bill links sent to 1 tenant. 1 could not be sent: see Messages.');
        $this->assertSame(2, RentMessage::where('kind', 'link')->count());
        $this->actingAs($admin)->post('/rent/resend-links'); // same day: not sent twice
        $this->assertSame(2, RentMessage::where('kind', 'link')->count());

        DB::table('users')->where('name', 'like', '%Sunrise 2')->update(['phone' => '0712000002']);
        $failed = RentMessage::where('status', 'no_phone')->sole();
        $this->actingAs($admin)->post('/rent/messages/'.$failed->id.'/retry')->assertRedirect();
        $this->assertSame('sent', $failed->fresh()->status);
        $this->actingAs($admin)->get('/rent?tab=messages')->assertOk()->assertSee('Bill link');
    }

    public function test_pause_and_resume_from_the_page(): void
    {
        $admin = $this->staff($this->a['company']);
        $this->actingAs($admin)->post('/rent/pause', ['tenancy_id' => $this->a['tenancies'][0], 'reason' => 'Paying weekly'])->assertRedirect();
        $hold = RentHold::sole();
        $this->assertSame([(int) $this->a['tenancies'][0]], RentHold::activeTenancyIds($this->a['company']));
        $this->actingAs($admin)->get('/rent?tab=paused')->assertOk()->assertSee('Paying weekly');
        $this->actingAs($admin)->post('/rent/paused/'.$hold->id.'/resume')->assertRedirect();
        $this->assertSame(0, RentHold::count());
    }

    private function staff(int $companyId): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Admin', 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        CompanyAccess::flush();

        return User::find($id);
    }
}
