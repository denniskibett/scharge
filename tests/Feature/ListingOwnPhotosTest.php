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

        $items = collect(app(ListingCatalog::class)->items())->keyBy('price');

        $this->assertCount(3, $items[4500.0]['photos']);
        $this->assertStringEndsWith('living.webp', $items[4500.0]['photo'], 'its own first photo is the cover');
        $this->assertStringEndsWith('gate.webp', $items[4500.0]['photos'][2]);
        $this->assertCount(1, $items[5000.0]['photos'], 'the neighbour shows only the building photo');
        $this->assertStringEndsWith('gate.webp', $items[5000.0]['photo']);
    }

    public function test_a_listing_for_several_identical_houses_keeps_to_shared_photos(): void
    {
        $p = DB::table('properties')->insertGetId(['name' => 'Oak Court', 'location' => 'Ruaka']);
        $a = $this->unit($p, 'A1', 4000);
        $this->unit($p, 'A2', 4000);
        $this->photo($p, $a, 'unit', 'a1-only.webp', 1);
        $this->photo($p, $a, 'type', 'one-bed.webp', 2);

        $item = app(ListingCatalog::class)->items()[0];

        $this->assertSame('2 available', $item['avail']);
        $this->assertCount(1, $item['photos']);
        $this->assertStringEndsWith('one-bed.webp', $item['photo']);
    }
}
