<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CompanyController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A BnB host (or manager) of units in someone else's building saw "0 properties" on its admin company page. */
class CompanyHostedPropertiesTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('unit_managements')) {
            Schema::create('unit_managements', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('unit_id'), $t->unsignedBigInteger('company_id'),
                $t->string('role', 12), $t->string('status', 10)->default('active'), $t->timestamps()]);
        }
        DB::table('companies')->insert([['id' => 10, 'name' => 'Building Owner Ltd'], ['id' => 20, 'name' => 'Urban Nest Airbnbs']]);
        DB::table('properties')->insert([['id' => 1, 'name' => 'Capital Gardens', 'company_id' => 10], ['id' => 2, 'name' => 'Riverside', 'company_id' => 10]]);
        foreach (['A1', 'A2', 'A3'] as $n) DB::table('units')->insert(['property_id' => 1, 'company_id' => 10, 'unit_number' => $n, 'rent_amount' => 1]);
        DB::table('units')->insert(['property_id' => 2, 'company_id' => 10, 'unit_number' => 'R1', 'rent_amount' => 1]);
        $a1 = DB::table('units')->where('unit_number', 'A1')->value('id');
        $a2 = DB::table('units')->where('unit_number', 'A2')->value('id');
        $r1 = DB::table('units')->where('unit_number', 'R1')->value('id');
        DB::table('unit_managements')->insert([
            ['unit_id' => $a1, 'company_id' => 20, 'role' => 'bnb_host', 'status' => 'active'],
            ['unit_id' => $a2, 'company_id' => 20, 'role' => 'bnb_host', 'status' => 'active'],
            ['unit_id' => $r1, 'company_id' => 20, 'role' => 'bnb_host', 'status' => 'ended'], // ended: not theirs any more
        ]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Sysadmin', 'company_id' => null]);
        $role = DB::table('roles')->insertGetId(['name' => 'sysadmin', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => \App\Models\User::class, 'model_id' => 1]);
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(1));
        \App\Support\CompanyAccess::flush();
    }

    public function test_hosts_see_the_buildings_and_only_the_units_they_host(): void
    {
        $rows = collect(app(CompanyController::class)->getProperties(20)->getData(true)['properties']);
        $this->assertSame(['Capital Gardens'], $rows->pluck('name')->all());
        $this->assertSame(2, $rows[0]['units_count']);
        $this->assertSame('BnB host', $rows[0]['role']);
        $this->assertFalse($rows[0]['owned']);

        $owner = collect(app(CompanyController::class)->getProperties(10)->getData(true)['properties']);
        $this->assertEqualsCanonicalizing(['Capital Gardens', 'Riverside'], $owner->pluck('name')->all());
        $this->assertSame('Owner', $owner->firstWhere('name', 'Capital Gardens')['role']);
        $this->assertSame(3, $owner->firstWhere('name', 'Capital Gardens')['units_count']);
    }
}
