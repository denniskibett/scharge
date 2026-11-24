<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\DB;

/** The sysadmin's Properties list says who each building belongs to: its company, or the hosts running units in it. */
class PropertyOwnersTest extends RentSchemaTestCase
{
    public function test_a_building_shows_its_company_or_its_hosts(): void
    {
        $landlord = DB::table('companies')->insertGetId(['name' => 'Danaff Kenya']);
        $host = DB::table('companies')->insertGetId(['name' => 'Urban Nest Airbnbs']);
        $owned = DB::table('properties')->insertGetId(['name' => 'Milan', 'company_id' => $landlord]);
        $unclaimed = DB::table('properties')->insertGetId(['name' => 'Capital Gardens', 'company_id' => null]);
        $empty = DB::table('properties')->insertGetId(['name' => 'Nobody Court', 'company_id' => null]);
        foreach ([[$unclaimed, 'B1205', 'active'], [$owned, 'A1', 'active'], [$empty, 'X1', 'ended']] as [$property, $number, $status]) {
            $unit = DB::table('units')->insertGetId(['property_id' => $property, 'unit_number' => $number, 'rent_amount' => 0]);
            DB::table('unit_managements')->insert(['unit_id' => $unit, 'company_id' => $host, 'role' => 'bnb_host', 'status' => $status]);
        }

        $owners = PropertyController::owners([$owned, $unclaimed, $empty]);

        $this->assertSame('Hosted by Urban Nest Airbnbs', $owners[$unclaimed]);
        $this->assertSame('Danaff Kenya · hosts: Urban Nest Airbnbs', $owners[$owned]);
        $this->assertSame('No company yet', $owners[$empty], 'a host that has left is not named');
    }
}
