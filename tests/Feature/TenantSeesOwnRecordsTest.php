<?php

namespace Tests\Feature;

use App\Support\CompanyAccess;
use App\Support\TenantSelf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A resident signed in to the dashboard saw every resident of the company (tenants, tenancies, invoices) and,
 * because their user row has no company, every company's units. They now see their own records only.
 */
class TenantSeesOwnRecordsTest extends RentSchemaTestCase
{
    public function test_a_tenant_sees_only_their_own_records_and_their_landlords_units(): void
    {
        $tenantRole = DB::table('roles')->insertGetId(['name' => 'tenant', 'guard_name' => 'web']);
        DB::table('companies')->insert([['id' => 7, 'name' => 'Landlord'], ['id' => 8, 'name' => 'Other landlord']]);
        $prop = DB::table('properties')->insertGetId(['name' => 'Block A', 'company_id' => 7]);
        $otherProp = DB::table('properties')->insertGetId(['name' => 'Other block', 'company_id' => 8]);
        DB::table('units')->insert(['property_id' => $otherProp, 'company_id' => 8, 'unit_number' => 'X9', 'rent_amount' => 1, 'status' => 'vacant']);
        foreach ([[51, 501, '1'], [52, 502, '2']] as [$uid, $tid, $no]) {
            DB::table('users')->insert(['id' => $uid, 'name' => "Resident $no", 'company_id' => null]);
            DB::table('model_has_roles')->insert(['role_id' => $tenantRole, 'model_type' => \App\Models\User::class, 'model_id' => $uid]);
            DB::table('tenants')->insert(['id' => $tid, 'user_id' => $uid, 'company_id' => 7]);
            $unit = DB::table('units')->insertGetId(['property_id' => $prop, 'company_id' => 7, 'unit_number' => $no, 'rent_amount' => 10000, 'status' => 'occupied']);
            $ten = DB::table('tenancies')->insertGetId(['tenant_id' => $tid, 'unit_id' => $unit, 'company_id' => 7, 'property_id' => $prop, 'move_in_date' => '2026-01-01', 'status' => 'active']);
            DB::table('invoices')->insert(['tenancy_id' => $ten, 'company_id' => 7, 'invoice_type' => 'monthly', 'billing_month' => '2026-11-01', 'total_amount' => 10000, 'status' => 'unpaid']);
        }

        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(51));
        CompanyAccess::flush();
        TenantSelf::flush();
        $this->assertSame([501], \App\Models\Tenant::pluck('id')->all());
        $this->assertSame(1, \App\Models\Tenancy::count());
        $this->assertSame(1, \App\Models\Invoice::count());
        $this->assertEqualsCanonicalizing(['1', '2'], \App\Models\Unit::pluck('unit_number')->all());   // the landlord's units, not X9
        $this->assertSame(['Block A'], \App\Models\Property::visible()->pluck('name')->all());

        Auth::logout();
        CompanyAccess::flush();
        TenantSelf::flush();
        $this->assertSame(2, \App\Models\Invoice::count());   // jobs and callbacks still see everything
    }
}
