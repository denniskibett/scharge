<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Models\Invoice;
use App\Models\RentMessage;
use App\Services\Rent\ArrearsReminders;
use App\Services\Rent\RentRun;
use App\Services\Rent\RentSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Rent automation (docs/RENT_AUTOMATION.md). */
class RentAutomationTest extends RentSchemaTestCase
{
    public function test_bills_every_active_tenancy_once_with_all_fixed_charges_and_last_months_water(): void
    {
        $a = $this->company('Sunrise', [
            ['rent_amount' => 25000, 'service_charge' => 2000, 'garbage_charge' => 300, 'security_charge' => 500],
            ['rent_amount' => 15000, 'water_billing_type' => 'flat', 'water_charge' => 600],
            ['rent_amount' => 12000], // metered water
        ]);
        $meteredUnit = DB::table('tenancies')->where('id', $a['tenancies'][2])->value('unit_id');
        DB::table('water_readings')->insert(['unit_id' => $meteredUnit, 'previous_reading' => 100, 'current_reading' => 112, 'reading_date' => '2026-11-28']);
        DB::table('water_readings')->insert(['unit_id' => $meteredUnit, 'previous_reading' => 90, 'current_reading' => 100, 'reading_date' => '2026-10-28']); // older: never billed again

        $run = app(RentRun::class);
        $first = $run->run($a['company'], $this->month('2026-12'), notify: false);
        $second = $run->run($a['company'], $this->month('2026-12'), notify: false);

        $this->assertSame(3, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['skipped']);
        $this->assertSame(3, Invoice::count());

        $one = Invoice::where('tenancy_id', $a['tenancies'][0])->first();
        $this->assertEquals(27800, (float) $one->total_amount);
        $this->assertSame('unpaid', $one->status);
        $this->assertSame((int) $a['company'], (int) $one->company_id);
        $this->assertEqualsCanonicalizing(['rent', 'service', 'garbage', 'security'], $one->items()->pluck('item_type')->all());

        $this->assertEquals(15600, (float) Invoice::where('tenancy_id', $a['tenancies'][1])->value('total_amount'));
        $water = Invoice::where('tenancy_id', $a['tenancies'][2])->first()->items()->where('item_type', 'water')->first();
        $this->assertEquals(1200, (float) $water->amount); // 12 m³ × KES 100 (November reading)
        $this->assertEquals(12, (float) $water->water_units_used);
    }

    public function test_move_in_month_ended_tenancies_and_other_companies_are_not_billed(): void
    {
        $a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 10000], ['rent_amount' => 10000]]);
        $b = $this->company('Other', [['rent_amount' => 99000]]);
        DB::table('invoices')->insert(['tenancy_id' => $a['tenancies'][0], 'invoice_type' => 'move_in', 'billing_month' => '2026-12-10', 'total_amount' => 20000, 'status' => 'unpaid']);
        DB::table('tenancies')->where('id', $a['tenancies'][1])->update(['status' => 'ended', 'move_out_date' => '2026-11-30']);
        DB::table('tenancies')->where('id', $a['tenancies'][2])->update(['move_in_date' => '2027-01-03']); // not in yet

        $r = app(RentRun::class)->run($a['company'], $this->month('2026-12'), notify: false);

        $this->assertSame(0, $r['created']);
        $this->assertSame(0, Invoice::where('tenancy_id', $b['tenancies'][0])->count());
    }

    public function test_command_waits_for_the_invoice_day_and_only_runs_companies_that_switched_it_on(): void
    {
        $on = $this->company('Sunrise', [['rent_amount' => 10000]], settings: ['invoice_day' => '3']);
        $off = $this->company('Manual', [['rent_amount' => 10000]], automation: false);

        $this->travelTo(CarbonImmutable::parse('2026-12-02 07:00', RentSettings::TZ));
        Artisan::call('rent:invoice');
        $this->assertSame(0, Invoice::count());

        $this->travelTo(CarbonImmutable::parse('2026-12-04 07:00', RentSettings::TZ)); // missed the 3rd: catches up
        Artisan::call('rent:invoice');
        Artisan::call('rent:invoice');
        $this->assertSame(1, Invoice::where('tenancy_id', $on['tenancies'][0])->count());
        $this->assertSame(0, Invoice::where('tenancy_id', $off['tenancies'][0])->count());

        // The bill went out once, by SMS, with a pay link.
        $bill = RentMessage::where('kind', 'bill')->sole();
        $this->assertSame('sent', $bill->status);
        $this->assertSame('sms', $bill->channel);
        $this->assertStringContainsString('December 2026 bill for A1, Sunrise Court is KES 10,000, due 5 Dec', $bill->message);
        $this->assertStringContainsString('/public/invoice/', $bill->message);
        $this->assertSame(1, $this->sent);
    }

    public function test_reminders_go_at_each_stage_once_with_the_total_owed(): void
    {
        $a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 8000]], settings: ['send_bill' => 'no']);
        $run = app(RentRun::class);
        $run->run($a['company'], $this->month('2026-11'));
        $run->run($a['company'], $this->month('2026-12'));
        // Tenant 2 paid everything.
        Invoice::where('tenancy_id', $a['tenancies'][1])->get()->each(fn ($i) => $i->forceFill(['total_paid' => $i->total_amount, 'status' => 'paid'])->saveQuietly()); // no receipt email in this test
        // Tenant 1 paid part of November.
        Invoice::where('tenancy_id', $a['tenancies'][0])->whereDate('billing_month', '2026-11-01')->first()->forceFill(['total_paid' => 4000, 'status' => 'partial'])->saveQuietly();
        $this->assertSame(0, RentMessage::count()); // send_bill = no

        $settings = RentSettings::for($a['company']);
        $reminders = app(ArrearsReminders::class);

        // 5 Dec: November (due 5 Nov) is 30 days late -> stage 14; December is due today, not late.
        $r = $reminders->run($settings, CarbonImmutable::parse('2026-12-05', RentSettings::TZ));
        $this->assertSame(1, $r['sent']);
        $msg = RentMessage::where('kind', 'reminder')->where('status', 'sent')->sole();
        $this->assertSame(14, $msg->stage);
        $this->assertStringContainsString('KES 16,000 for A1, Sunrise Court is 30 days late (due 5 Nov)', $msg->message);

        // Same day again: nothing new.
        $this->assertSame(0, $reminders->run($settings, CarbonImmutable::parse('2026-12-05', RentSettings::TZ))['sent']);
        $this->assertSame(1, $this->sent);
    }

    public function test_a_new_month_late_gets_its_first_stage_and_grouped_bills_are_not_reminded_twice(): void
    {
        $a = $this->company('Sunrise', [['rent_amount' => 10000]], settings: ['send_bill' => 'no', 'reminder_days' => '1,7']);
        app(RentRun::class)->run($a['company'], $this->month('2026-12'));
        $settings = RentSettings::for($a['company']);
        $reminders = app(ArrearsReminders::class);

        $this->assertSame([], $reminders->run($settings, CarbonImmutable::parse('2026-12-05', RentSettings::TZ))['due']); // due today, stage 0 not set
        $this->assertSame(1, $reminders->run($settings, CarbonImmutable::parse('2026-12-06', RentSettings::TZ))['sent']);
        $this->assertSame(0, $reminders->run($settings, CarbonImmutable::parse('2026-12-08', RentSettings::TZ))['sent']);
        $this->assertSame(1, $reminders->run($settings, CarbonImmutable::parse('2026-12-12', RentSettings::TZ))['sent']);
        $this->assertSame([1, 7], RentMessage::orderBy('stage')->pluck('stage')->all());
    }

    public function test_dry_run_sends_and_creates_nothing_and_a_tenant_without_a_phone_is_recorded(): void
    {
        $a = $this->company('Sunrise', [['rent_amount' => 10000]]);
        DB::table('users')->update(['phone' => null]);

        $this->travelTo(CarbonImmutable::parse('2026-12-02 07:00', RentSettings::TZ));
        Artisan::call('rent:invoice', ['--dry-run' => true]);
        $this->assertSame(0, Invoice::count());

        Artisan::call('rent:invoice');
        $this->assertSame('no_phone', RentMessage::sole()->status);
        $this->assertSame(0, $this->sent);
    }

    public function test_generate_all_button_bills_only_the_users_own_company(): void
    {
        $a = $this->company('Sunrise', [['rent_amount' => 10000]], automation: false);
        $b = $this->company('Other', [['rent_amount' => 20000]], automation: false);
        $request = \Illuminate\Http\Request::create('/invoices/generate/all', 'POST');
        $request->setUserResolver(fn () => (object) ['company_id' => $a['company']]);

        $res = app(\App\Http\Controllers\InvoiceController::class)->generateAllInvoices($request, app(RentRun::class));

        $this->assertSame(1, $res->getData(true)['generated_count']);
        $this->assertSame(1, Invoice::where('tenancy_id', $a['tenancies'][0])->count());
        $this->assertSame(0, Invoice::where('tenancy_id', $b['tenancies'][0])->count());
        $this->assertSame(0, RentMessage::count()); // automation off: invoices only, no SMS
    }

    public function test_settings_test_button_previews_without_creating_or_sending(): void
    {
        $a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 5000]]);
        $this->travelTo(CarbonImmutable::parse('2026-12-02 09:00', RentSettings::TZ));
        $row = CompanyIntegration::where('company_id', $a['company'])->sole();

        [$ok, $message] = \App\Services\Rent\RentPreview::test($row);

        $this->assertTrue($ok);
        $this->assertStringContainsString('December 2026: 2 invoices to create (KES 15,000)', $message);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, $this->sent);
    }
}
