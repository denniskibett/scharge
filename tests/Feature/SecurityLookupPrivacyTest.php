<?php

namespace Tests\Feature;

use App\Http\Controllers\SecurityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** The gate-pass lookups let a tenant see only their own unit, not their neighbours' phone numbers. */
class SecurityLookupPrivacyTest extends RentSchemaTestCase
{
    public function test_a_tenant_sees_only_their_own_unit_and_staff_see_all(): void
    {
        $roleTenant = DB::table('roles')->insertGetId(['name' => 'tenant', 'guard_name' => 'web']);
        $roleAdmin = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web']);
        DB::table('companies')->insert(['id' => 5, 'name' => 'Estate']);
        $prop = DB::table('properties')->insertGetId(['name' => 'Block A', 'company_id' => 5]);
        foreach ([[31, 'Amina', '+254711000001', 301, '1'], [32, 'Neighbour', '+254711000002', 302, '2']] as [$uid, $name, $phone, $tid, $no]) {
            DB::table('users')->insert(['id' => $uid, 'name' => $name, 'phone' => $phone, 'company_id' => 5]);
            DB::table('tenants')->insert(['id' => $tid, 'user_id' => $uid, 'company_id' => 5]);
            $unit = DB::table('units')->insertGetId(['property_id' => $prop, 'company_id' => 5, 'unit_number' => $no, 'rent_amount' => 10000, 'status' => 'occupied']);
            DB::table('tenancies')->insert(['tenant_id' => $tid, 'unit_id' => $unit, 'company_id' => 5, 'property_id' => $prop, 'move_in_date' => '2026-01-01', 'status' => 'active']);
            DB::table('model_has_roles')->insert(['role_id' => $roleTenant, 'model_type' => \App\Models\User::class, 'model_id' => $uid]);
        }
        DB::table('users')->insert(['id' => 40, 'name' => 'Manager', 'company_id' => 5]);
        DB::table('model_has_roles')->insert(['role_id' => $roleAdmin, 'model_type' => \App\Models\User::class, 'model_id' => 40]);
        $neighbourUnit = DB::table('units')->where('unit_number', '2')->value('id');
        $c = app(SecurityController::class);

        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(31));
        $units = json_decode($c->getUnitsByProperty(Request::create('/', 'GET', ['property_id' => $prop]))->getContent(), true)['units'];
        $this->assertSame(['1'], array_column($units, 'unit_number'));
        $this->assertSame(403, $c->getTenantsByUnit(Request::create('/', 'GET', ['unit_id' => $neighbourUnit]))->getStatusCode());

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(40));
        $units = json_decode($c->getUnitsByProperty(Request::create('/', 'GET', ['property_id' => $prop]))->getContent(), true)['units'];
        $this->assertCount(2, $units);
        $this->assertSame(200, $c->getTenantsByUnit(Request::create('/', 'GET', ['unit_id' => $neighbourUnit]))->getStatusCode());
    }
}
