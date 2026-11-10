<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CompanyIntegration;
use App\Models\Unit;
use App\Models\UnitChannel;
use App\Models\User;
use App\Services\Bookings\ChannelSync;
use App\Services\Bookings\Ical;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Short-stay bookings: no double bookings, channel calendars in and out, "Coming soon" until switched on. docs/BOOKINGS.md */
class BookingsTest extends RentSchemaTestCase
{
    private array $a;

    private int $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        $this->a = $this->company('Beach', [['rent_amount' => 0, 'stay_type' => 'bnb'], ['rent_amount' => 30000]], automation: false);
        $this->unit = (int) DB::table('tenancies')->where('id', $this->a['tenancies'][0])->value('unit_id');
    }

    private function staff(int $companyId): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Host', 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        CompanyAccess::flush();

        return User::find($id);
    }

    private function book(User $u, string $in, string $out, string $guest = 'Amina')
    {
        return $this->actingAs($u)->post('/bookings', ['unit_id' => $this->unit, 'check_in' => $in, 'check_out' => $out, 'guest_name' => $guest, 'amount' => 9000]);
    }

    public function test_a_night_is_never_booked_twice_but_back_to_back_stays_are_fine(): void
    {
        $u = $this->staff($this->a['company']);
        $this->book($u, '2026-12-20', '2026-12-23')->assertRedirect()->assertSessionHas('success', 'Booked A1, 20 Dec to 23 Dec (3 nights).');
        $this->book($u, '2026-12-22', '2026-12-25', 'Brian')->assertSessionHasErrors(['check_in' => 'Already booked 20 Dec to 23 Dec (Direct, Amina).']);
        $this->book($u, '2026-12-23', '2026-12-25', 'Brian')->assertSessionHasNoErrors(); // checks in the morning Amina leaves
        $this->assertSame(2, Booking::count());

        $this->actingAs($u)->get('/bookings')->assertOk()->assertSee('Amina')->assertSee('Brian')->assertSee('Coming soon');
    }

    public function test_long_stay_units_and_other_companies_units_cannot_be_booked(): void
    {
        $longStay = (int) DB::table('tenancies')->where('id', $this->a['tenancies'][1])->value('unit_id');
        $b = $this->company('Other', [['rent_amount' => 0, 'stay_type' => 'bnb']], automation: false);
        $u = $this->staff($b['company']);

        $this->book($u, '2026-12-20', '2026-12-21')->assertNotFound(); // A1 belongs to Beach
        $this->actingAs($this->staff($this->a['company']))->post('/bookings', ['unit_id' => $longStay, 'check_in' => '2026-12-20', 'check_out' => '2026-12-21', 'guest_name' => 'X'])->assertNotFound();
    }

    public function test_channel_connection_is_refused_while_sync_is_off(): void
    {
        $this->actingAs($this->staff($this->a['company']))
            ->post('/bookings/units/'.$this->unit.'/channels', ['channel' => 'airbnb', 'import_url' => 'https://www.airbnb.com/calendar/ical/1.ics?s=x'])
            ->assertStatus(422);
        $this->assertSame(0, UnitChannel::count());
    }

    public function test_imported_channel_stays_appear_cancellations_follow_and_clashes_are_flagged(): void
    {
        CompanyIntegration::create(['company_id' => null, 'provider' => 'channel_sync', 'enabled' => true, 'credentials' => []]);
        $this->assertTrue(ChannelSync::enabled());
        Booking::create(['unit_id' => $this->unit, 'company_id' => $this->a['company'], 'source' => 'direct', 'check_in' => '2027-01-10', 'check_out' => '2027-01-12', 'guest_name' => 'Direct guest']);

        $ics = fn (array $events) => Ical::build('Airbnb', $events);
        Http::fake(['www.airbnb.com/*' => Http::sequence()
            ->push($ics([['uid' => 'abc@airbnb', 'start' => '2027-01-02', 'end' => '2027-01-05', 'summary' => 'Reserved'],
                ['uid' => 'def@airbnb', 'start' => '2027-01-11', 'end' => '2027-01-13', 'summary' => 'Airbnb (Not available)']]))
            ->push($ics([['uid' => 'def@airbnb', 'start' => '2027-01-11', 'end' => '2027-01-13', 'summary' => 'Reserved']]))]);

        $this->actingAs($this->staff($this->a['company']))
            ->post('/bookings/units/'.$this->unit.'/channels', ['channel' => 'airbnb', 'import_url' => 'https://www.airbnb.com/calendar/ical/1.ics?s=x'])
            ->assertSessionHas('success', 'Airbnb connected: 2 stays imported.');
        $channel = UnitChannel::sole();
        $this->assertStringContainsString('1 stay overlaps', $channel->last_error);
        $this->assertNull(Booking::where('external_uid', 'abc@airbnb')->value('guest_name'));

        $this->artisan('bookings:sync')->assertSuccessful(); // Airbnb dropped abc: cancelled here
        $this->assertSame('cancelled', Booking::where('external_uid', 'abc@airbnb')->value('status'));
        $this->assertSame('confirmed', Booking::where('external_uid', 'def@airbnb')->value('status'));
    }

    public function test_export_link_lists_other_bookings_without_guest_details_and_needs_its_code(): void
    {
        Booking::create(['unit_id' => $this->unit, 'source' => 'direct', 'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(5)->toDateString(), 'guest_name' => 'Secret Name']);
        Booking::create(['unit_id' => $this->unit, 'source' => 'airbnb', 'external_uid' => 'x1', 'check_in' => now()->addDays(8)->toDateString(), 'check_out' => now()->addDays(9)->toDateString()]);

        $res = $this->get(ChannelSync::exportUrl($this->unit, 'airbnb'))->assertOk();
        $this->assertStringContainsString('text/calendar', $res->headers->get('Content-Type'));
        $events = Ical::parse($res->getContent());
        $this->assertCount(1, $events); // Airbnb's own stay is not sent back to Airbnb
        $this->assertStringNotContainsString('Secret Name', $res->getContent());
        $this->assertCount(2, Ical::parse($this->get(ChannelSync::exportUrl($this->unit))->getContent()));

        $this->get('/calendar/units/'.$this->unit.'/wrongtoken')->assertNotFound();
    }

    public function test_only_channel_calendar_links_are_fetched(): void
    {
        $this->assertTrue(ChannelSync::allowedUrl('airbnb', 'https://www.airbnb.com/calendar/ical/123.ics?s=abc'));
        $this->assertTrue(ChannelSync::allowedUrl('booking_com', 'https://admin.booking.com/hotel/hoteladmin/ical.html?t=x'));
        $this->assertFalse(ChannelSync::allowedUrl('airbnb', 'http://www.airbnb.com/calendar/ical/1.ics'));
        $this->assertFalse(ChannelSync::allowedUrl('airbnb', 'https://airbnb.com.evil.test/x.ics'));
        $this->assertFalse(ChannelSync::allowedUrl('booking_com', 'https://169.254.169.254/latest'));
    }
}
