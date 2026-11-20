<?php

namespace Tests\Feature;

use App\Http\Controllers\UnitController;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Units belong to their property's company: new units keep it, and older units saved without one stay editable. */
class UnitAccessTest extends RentSchemaTestCase
{
    private function check(int $unitId, int $companyId): void
    {
        $user = new \App\Models\User(['name' => 'Agency admin']);
        $user->id = 9001;
        $user->company_id = $companyId;
        Auth::setUser($user);
        $m = new \ReflectionMethod(UnitController::class, 'authorizeCompanyAccess');
        $m->setAccessible(true);
        $m->invoke(app(UnitController::class), Unit::withoutGlobalScopes()->findOrFail($unitId));
    }

    public function test_a_new_unit_keeps_its_company(): void
    {
        $propertyId = DB::table('properties')->insertGetId(['name' => 'MILAN', 'company_id' => 96]);
        $unit = Unit::withoutGlobalScopes()->create(['property_id' => $propertyId, 'company_id' => 96, 'unit_number' => '2', 'unit_type' => '1br', 'rent_amount' => 15000, 'status' => 'vacant']);

        $this->assertSame(96, (int) DB::table('units')->where('id', $unit->id)->value('company_id'));
    }

    public function test_an_older_unit_without_a_company_is_editable_by_its_propertys_company_only(): void
    {
        $propertyId = DB::table('properties')->insertGetId(['name' => 'MILAN', 'company_id' => 96]);
        $unitId = DB::table('units')->insertGetId(['property_id' => $propertyId, 'company_id' => null, 'unit_number' => '1', 'rent_amount' => 15000]);

        $this->check($unitId, 96);   // no exception: allowed
        $this->expectException(HttpException::class);
        $this->check($unitId, 97);   // another company: 403
    }
}
