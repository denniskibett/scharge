<?php

namespace Tests\Feature;

use App\Services\ListingCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Open listings: a listing that is one house (a host's flat) shows that house's own photos; a neighbour of the same
 * type never borrows them, and a listing that stands for several identical houses keeps to the shared photos.
 */
class ListingOwnPhotosTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('unit_photos', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id'), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('unit_type')->nullable(), $t->string('applies_to'), $t->string('path'), $t->string('caption')->nullable(), $t->integer('sort')->default(0), $t->unsignedBigInteger('uploaded_by')->nullable(), $t->timestamps()]);
        ListingCatalog::forget();
    }

    private function unit(int $property, string $number, float $rate, string $type = 'One Bedroom'): int
    {
        return DB::table('units')->insertGetId(['property_id' => $property, 'unit_number' => $number, 'unit_type' => $type, 'rent_amount' => 0,
            'status' => 'vacant', 'stay_type' => 'bnb', 'bnb_nightly_rate' => $rate, 'is_active' => 1]);
    }

    private function photo(int $property, ?int $unit, string $scope, string $file, int $sort, string $type = 'One Bedroom'): void
    {
        DB::table('unit_photos')->insert(['property_id' => $property, 'unit_id' => $unit, 'unit_type' => $type, 'applies_to' => $scope, 'path' => "unit-photos/{$property}/{$file}", 'sort' => $sort]);
    }

    public function test_a_one_house_listing_shows_its_own_photos_and_its_neighbour_does_not(): void
    {
        $p = DB::table('properties')->insertGetId(['name' => 'Pinecrest View', 'location' => 'Kilimani']);
        $c412 = $this->unit($p, 'C412', 4500);
        $this->unit($p, 'C411', 5000);
        $this->photo($p, $c412, 'unit', 'living.webp', 1);
        $this->photo($p, $c412, 'unit', 'balcony.webp', 2);
        $this->photo($p, null, 'property', 'gate.webp', 3);

        $host = DB::table('companies')->insertGetId(['name' => 'Urban Nest Airbnbs']);
        DB::table('unit_managements')->insert(['unit_id' => $c412, 'company_id' => $host, 'role' => 'bnb_host', 'status' => 'active']);
        $items = collect(app(ListingCatalog::class)->items())->keyBy('price');

        $this->assertSame('Urban Nest Airbnbs', $items[4500.0]['host'], 'a short stay names its BnB company');
        $this->assertNull($items[5000.0]['host']);
        $this->assertSame('One Bedroom BnB · Pinecrest View', $items[4500.0]['title']);
        $this->assertContains('Short stay (BnB)', $items[4500.0]['tags']);

        $this->assertCount(3, $items[4500.0]['photos']);
        $this->assertStringEndsWith('living.webp', $items[4500.0]['photo'], 'its own first photo is the cover');
        $this->assertStringEndsWith('gate.webp', $items[4500.0]['photos'][2]);
        $this->assertCount(1, $items[5000.0]['photos'], 'the neighbour shows only the building photo');
        $this->assertStringEndsWith('gate.webp', $items[5000.0]['photo']);
    }

    public function test_a_short_stay_shows_the_nights_that_are_taken_and_when_it_is_next_free(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-11-24 09:00:00');
        $p = DB::table('properties')->insertGetId(['name' => 'Pinecrest View', 'location' => 'Kilimani']);
        $one = $this->unit($p, 'C412', 4500);
        $a = $this->unit($p, 'D1', 7000, 'Two Bedroom');
        $b = $this->unit($p, 'D2', 7000, 'Two Bedroom');
        $stay = fn (int $unit, string $in, string $out, string $status = 'confirmed') => \App\Models\Booking::create(['unit_id' => $unit, 'source' => 'direct', 'status' => $status, 'check_in' => $in, 'check_out' => $out, 'guest_name' => 'Guest']);
        $stay($one, '2026-11-24', '2026-11-27');            // tonight and two more nights; the guest leaves on the 27th
        $stay($one, '2026-12-01', '2026-12-02', 'cancelled');
        $stay($a, '2026-11-24', '2026-11-26');              // one of the two identical flats is taken, the other is free
        $stay($b, '2026-11-25', '2026-11-26');

        $items = collect(app(ListingCatalog::class)->items())->keyBy('price');

        $this->assertSame(['2026-11-24', '2026-11-25', '2026-11-26'], $items[4500.0]['booked']);
        $this->assertSame('2026-11-27', $items[4500.0]['next_free']);
        $this->assertSame('Free from 27 Nov', $items[4500.0]['avail']);
        $this->assertSame(['2026-11-25'], $items[7000.0]['booked'], 'taken only on the night both flats are taken');
        $this->assertSame('Free tonight', $items[7000.0]['avail']);

        // A new booking shows at once (the listings are cached for a few minutes otherwise).
        $stay($b, '2026-11-24', '2026-11-25');
        $this->assertSame('Free from 26 Nov', collect(app(ListingCatalog::class)->items())->keyBy('price')[7000.0]['avail']);
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_a_listing_for_several_identical_houses_keeps_to_shared_photos(): void
    {
        $p = DB::table('properties')->insertGetId(['name' => 'Oak Court', 'location' => 'Ruaka']);
        $a = $this->unit($p, 'A1', 4000);
        $this->unit($p, 'A2', 4000);
        $this->photo($p, $a, 'unit', 'a1-only.webp', 1);
        $this->photo($p, $a, 'type', 'one-bed.webp', 2);

        $item = app(ListingCatalog::class)->items()[0];

        $this->assertSame('2 free tonight', $item['avail']);
        $this->assertCount(1, $item['photos']);
        $this->assertStringEndsWith('one-bed.webp', $item['photo']);
    }
}
