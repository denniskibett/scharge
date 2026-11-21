<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Inline edit on the Staff page: a company admin edits their own staff, keeps them in the company, staff roles only. */
class StaffQuickUpdateTest extends RentSchemaTestCase
{
    public function test_quick_update_stays_inside_the_company(): void
    {
        foreach (['admin', 'accountant', 'sysadmin'] as $r) DB::table('roles')->insert(['name' => $r, 'guard_name' => 'web']);
        DB::table('companies')->insert([['id' => 1, 'name' => 'Mine'], ['id' => 2, 'name' => 'Theirs']]);
        DB::table('users')->insert([
            ['id' => 61, 'name' => 'Boss', 'email' => 'boss@example.test', 'company_id' => 1],
            ['id' => 62, 'name' => 'My clerk', 'email' => 'clerk@example.test', 'company_id' => 1],
            ['id' => 63, 'name' => 'Their clerk', 'email' => 'other@example.test', 'company_id' => 2],
        ]);
        if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'status')) {
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->integer('status')->default(1));
        }
        $accountant = DB::table('roles')->where('name', 'accountant')->value('id');
        $sysadmin = DB::table('roles')->where('name', 'sysadmin')->value('id');
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(61));
        \App\Support\CompanyAccess::flush();
        $c = app(StaffController::class);
        $body = fn ($id, $role, $company) => Request::create('/', 'POST', ['name' => 'X', 'email' => "x$id@example.test", 'role_id' => $role, 'company_id' => $company, 'status' => 1]);

        $this->assertSame(403, $c->quickUpdate($body(63, $accountant, 2), 63)->getStatusCode());   // not my company
        $this->assertSame(422, $c->quickUpdate($body(62, $sysadmin, 1), 62)->getStatusCode());     // not a staff role
        $c->quickUpdate($body(62, $accountant, 2), 62);                                              // tries to move them
        $this->assertSame(1, (int) DB::table('users')->where('id', 62)->value('company_id'));
    }
}
