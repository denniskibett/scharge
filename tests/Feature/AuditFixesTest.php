<?php

namespace Tests\Feature;

use App\Http\Controllers\UnitController;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Fixes from the 21 Nov 2026 frontend audit: data entered in the dashboard is kept, and saved with its company. */
class AuditFixesTest extends RentSchemaTestCase
{
    private function actAs(int $companyId): void
    {
        $user = new \App\Models\User(['name' => 'Manager']);
        $user->id = 9100;
        $user->company_id = $companyId;
        Auth::setUser($user);
    }

    private function unit(): Unit
    {
        $propertyId = DB::table('properties')->insertGetId(['name' => 'Danaff Towers', 'company_id' => 96]);

        return Unit::withoutGlobalScopes()->create(['property_id' => $propertyId, 'company_id' => 96, 'unit_number' => 'D01-02', 'unit_type' => 'One Bedroom',
            'rent_amount' => 15000, 'status' => 'vacant', 'bnb_nightly_rate' => 4500, 'security_deposit_amount' => 30000]);
    }

    public function test_quick_unit_edit_keeps_what_it_does_not_send(): void
    {
        $unit = $this->unit();
        $this->actAs(96);
        $req = Request::create('/units/'.$unit->id, 'PUT', ['unit_number' => 'D01-02', 'unit_type' => 'One Bedroom', 'rent_amount' => 16000, 'status' => 'vacant']);
        $req->headers->set('Accept', 'application/json');
        app(UnitController::class)->update($req, $unit);

        $row = DB::table('units')->find($unit->id);
        $this->assertEquals(16000, $row->rent_amount);
        $this->assertEquals(4500, $row->bnb_nightly_rate);          // used to be wiped to NULL
        $this->assertEquals(30000, $row->security_deposit_amount);  // used to be wiped to NULL
    }

    public function test_unit_edit_rejects_a_list_of_numbers(): void
    {
        $unit = $this->unit();
        $this->actAs(96);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(UnitController::class)->update(Request::create('/units/'.$unit->id, 'PUT', ['unit_number' => '2,3', 'unit_type' => 'Studio', 'rent_amount' => 1, 'status' => 'vacant']), $unit);
    }

    public function test_tenancies_and_water_readings_take_their_units_company(): void
    {
        $unit = $this->unit();
        $tenancy = \App\Models\Tenancy::withoutGlobalScopes()->create(['tenant_id' => 1, 'unit_id' => $unit->id, 'move_in_date' => '2026-11-01', 'status' => 'active']);
        $this->assertSame(96, (int) $tenancy->company_id);
        $this->assertSame((int) $unit->property_id, (int) $tenancy->property_id);

        $reading = \App\Modules\Water\Models\WaterReading::create(['unit_id' => $unit->id, 'previous_reading' => 10, 'current_reading' => 12, 'consumption' => 2, 'reading_date' => '2026-11-20']);
        $this->assertSame(96, (int) DB::table('water_readings')->where('id', $reading->id)->value('company_id'));
    }

    public function test_a_tenant_cannot_be_put_on_a_unit_that_is_let(): void
    {
        $unit = $this->unit();
        DB::table('tenancies')->insert(['tenant_id' => 5, 'unit_id' => $unit->id, 'company_id' => 96, 'move_in_date' => '2026-01-01', 'status' => 'active']);
        $this->actAs(96);
        $m = new \ReflectionMethod(\App\Http\Controllers\TenantController::class, 'freeUnitOrFail');
        $m->setAccessible(true);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $m->invoke(app(\App\Http\Controllers\TenantController::class), $unit->id);
    }
}
