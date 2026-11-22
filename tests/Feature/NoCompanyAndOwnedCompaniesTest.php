<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CompanyController;
use App\Support\CompanyAccess;
use App\Support\TenantSelf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A login with no company (not yet activated, a guest, no role) used to get no company filter and so found every
 * company's units; an account manager could open companies outside their own book on /admin/companies.
 */
class NoCompanyAndOwnedCompaniesTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['sysadmin', 'account_manager', 'sales', 'guest', 'admin'] as $r) DB::table('roles')->insert(['name' => $r, 'guard_name' => 'web']);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Sysadmin', 'company_id' => null], ['id' => 2, 'name' => 'Manager', 'company_id' => null],
            ['id' => 3, 'name' => 'Sales', 'company_id' => null], ['id' => 4, 'name' => 'Guest', 'company_id' => null],
            ['id' => 5, 'name' => 'Just signed up', 'company_id' => null], ['id' => 6, 'name' => 'Other manager', 'company_id' => null],
        ]);
        foreach ([1 => 'sysadmin', 2 => 'account_manager', 3 => 'sales', 4 => 'guest', 6 => 'account_manager'] as $uid => $role) {
            DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', $role)->value('id'), 'model_type' => \App\Models\User::class, 'model_id' => $uid]);
        }
        DB::table('companies')->insert([['id' => 10, 'name' => 'Mine', 'owner_id' => 2], ['id' => 20, 'name' => 'Theirs', 'owner_id' => 6]]);
        DB::table('properties')->insert([['id' => 1, 'name' => 'P10', 'company_id' => 10], ['id' => 2, 'name' => 'P20', 'company_id' => 20]]);
        DB::table('units')->insert([['property_id' => 1, 'company_id' => 10, 'unit_number' => 'M1', 'rent_amount' => 1], ['property_id' => 2, 'company_id' => 20, 'unit_number' => 'T1', 'rent_amount' => 1]]);
    }

    private function actAs(int $id): void
    {
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find($id));
        CompanyAccess::flush();
        TenantSelf::flush();
    }

    public function test_a_login_without_a_company_finds_no_companys_records(): void
    {
        foreach ([4, 5] as $id) {   // guest, no role
            $this->actAs($id);
            $this->assertSame([0], CompanyAccess::ids());
            $this->assertSame(0, \App\Models\Unit::count());
        }
        $this->actAs(2);
        $this->assertSame(['M1'], \App\Models\Unit::pluck('unit_number')->all());   // an account manager: the companies they own
        foreach ([1, 3] as $id) {   // sysadmin, sales: scharge staff see all
            $this->actAs($id);
            $this->assertNull(CompanyAccess::ids());
        }
    }

    public function test_the_filter_follows_a_company_given_part_way_through_a_request(): void
    {
        $this->actAs(5);
        $this->assertSame([0], CompanyAccess::ids());
        Auth::user()->company_id = 10;   // activation: the business was just created
        $this->assertSame([10], CompanyAccess::ids());
    }

    public function test_an_account_manager_opens_only_companies_in_their_own_book(): void
    {
        $call = function (int $companyId) {
            $request = Request::create("/admin/companies/$companyId/users", 'GET');
            $request->setRouteResolver(fn () => tap(Route::getRoutes()->match($request), fn ($r) => $r->bind($request)));
            app()->instance('request', $request);

            return app(CompanyController::class)->callAction('getCompanyUsers', [$companyId]);
        };

        $this->actAs(2);
        try {
            $call(20);
            $this->fail('opened a company outside their book');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertNotSame(404, $call(10)->getStatusCode());

        $this->actAs(1);   // sysadmin: any company
        $this->assertNotSame(404, $call(20)->getStatusCode());
    }
}
