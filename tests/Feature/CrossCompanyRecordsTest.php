<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\StaffController;
use App\Modules\Maintenance\Models\Maintenance;
use App\Modules\Security\Models\SecurityLog;
use App\Modules\SMS\Models\SmsTemplate;
use App\Modules\Water\Models\WaterReading;
use App\Support\CompanyAccess;
use App\Support\TenantSelf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Repairs, the gate log, water readings, SMS templates and staff were reachable across companies by id (and the gate
 * log in every list). Each company now finds only its own; a resident only their own; jobs still see everything.
 */
class CrossCompanyRecordsTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['maintenances', 'security', 'sms_templates'] as $t) Schema::dropIfExists($t);
        Schema::create('maintenances', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->unsignedBigInteger('tenant_id')->nullable(), $t->string('name'), $t->timestamps()]);
        Schema::create('security', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('visitor_name_snapshot'), $t->timestamps()]);
        Schema::create('sms_templates', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->text('content')->nullable(), $t->text('placeholders')->nullable(),
            $t->unsignedBigInteger('created_by')->nullable(), $t->timestamps()]);
        if (! Schema::hasColumn('users', 'status')) Schema::table('users', fn ($t) => $t->integer('status')->default(0));
        if (! Schema::hasColumn('users', 'deleted_at')) Schema::table('users', fn ($t) => $t->softDeletes());

        foreach (['admin', 'sysadmin', 'tenant', 'security'] as $r) DB::table('roles')->insert(['name' => $r, 'guard_name' => 'web']);
        DB::table('companies')->insert([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        DB::table('users')->insert([
            ['id' => 11, 'name' => 'Admin A', 'email' => 'a@example.test', 'company_id' => 1],
            ['id' => 21, 'name' => 'Admin B', 'email' => 'b@example.test', 'company_id' => 2],
            ['id' => 22, 'name' => 'Guard B', 'email' => 'g@example.test', 'company_id' => 2],
            ['id' => 31, 'name' => 'Resident 1 of A', 'email' => 'r1@example.test', 'company_id' => null],
            ['id' => 32, 'name' => 'Resident 2 of A', 'email' => 'r2@example.test', 'company_id' => null],
            ['id' => 90, 'name' => 'scharge', 'email' => 's@example.test', 'company_id' => null],
        ]);
        $role = fn ($name, $uid) => DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', $name)->value('id'), 'model_type' => \App\Models\User::class, 'model_id' => $uid]);
        $role('admin', 11); $role('admin', 21); $role('security', 22); $role('tenant', 31); $role('tenant', 32); $role('sysadmin', 90);

        DB::table('properties')->insert([['id' => 1, 'name' => 'Block A', 'company_id' => 1], ['id' => 2, 'name' => 'Block B', 'company_id' => 2]]);
        DB::table('units')->insert([
            ['id' => 101, 'property_id' => 1, 'company_id' => 1, 'unit_number' => 'A1', 'rent_amount' => 1],
            ['id' => 102, 'property_id' => 1, 'company_id' => 1, 'unit_number' => 'A2', 'rent_amount' => 1],
            ['id' => 201, 'property_id' => 2, 'company_id' => 2, 'unit_number' => 'B1', 'rent_amount' => 1],
        ]);
        DB::table('tenants')->insert([['id' => 301, 'user_id' => 31, 'company_id' => 1], ['id' => 302, 'user_id' => 32, 'company_id' => 1]]);
        DB::table('tenancies')->insert([
            ['tenant_id' => 301, 'unit_id' => 101, 'company_id' => 1, 'move_in_date' => '2026-01-01', 'status' => 'active'],
            ['tenant_id' => 302, 'unit_id' => 102, 'company_id' => 1, 'move_in_date' => '2026-01-01', 'status' => 'active'],
        ]);

        DB::table('maintenances')->insert([
            ['id' => 1, 'company_id' => 1, 'unit_id' => 101, 'tenant_id' => 301, 'name' => 'A1 tap'],
            ['id' => 2, 'company_id' => null, 'unit_id' => 102, 'tenant_id' => 302, 'name' => 'A2 door'],   // older row, no company
            ['id' => 3, 'company_id' => 2, 'unit_id' => 201, 'tenant_id' => null, 'name' => 'B1 roof'],
        ]);
        DB::table('security')->insert([
            ['id' => 1, 'company_id' => 1, 'unit_id' => 101, 'visitor_name_snapshot' => 'Visitor to A'],
            ['id' => 2, 'company_id' => null, 'unit_id' => 201, 'visitor_name_snapshot' => 'Visitor to B'],
        ]);
        DB::table('water_readings')->insert([
            ['id' => 1, 'company_id' => 1, 'unit_id' => 101, 'previous_reading' => 0, 'current_reading' => 5, 'reading_date' => '2026-11-01'],
            ['id' => 2, 'company_id' => null, 'unit_id' => 102, 'previous_reading' => 0, 'current_reading' => 6, 'reading_date' => '2026-11-01'],
            ['id' => 3, 'company_id' => 2, 'unit_id' => 201, 'previous_reading' => 0, 'current_reading' => 7, 'reading_date' => '2026-11-01'],
        ]);
        DB::table('sms_templates')->insert([
            ['id' => 1, 'name' => 'A reminder', 'created_by' => 11], ['id' => 2, 'name' => 'B reminder', 'created_by' => 21],
            ['id' => 3, 'name' => 'scharge default', 'created_by' => 90], ['id' => 4, 'name' => 'Old default', 'created_by' => null],
        ]);
    }

    private function actAs(?int $id): void
    {
        $id ? Auth::setUser(\App\Models\User::withoutGlobalScopes()->find($id)) : Auth::logout();
        CompanyAccess::flush();
        TenantSelf::flush();
    }

    public function test_each_company_finds_only_its_own_repairs_gate_log_and_readings(): void
    {
        $this->actAs(11);
        $this->assertEqualsCanonicalizing(['A1 tap', 'A2 door'], Maintenance::pluck('name')->all());
        $this->assertNull(Maintenance::find(3));
        $this->assertSame(['Visitor to A'], SecurityLog::pluck('visitor_name_snapshot')->all());
        $this->assertNull(SecurityLog::find(2));
        $this->assertEqualsCanonicalizing([1, 2], WaterReading::pluck('id')->all());
        $this->assertSame(0, WaterReading::whereIn('unit_id', [201])->count());

        $this->actAs(22);   // B's guard
        $this->assertSame(['Visitor to B'], SecurityLog::pluck('visitor_name_snapshot')->all());
        $this->assertSame(['B1 roof'], Maintenance::pluck('name')->all());

        $this->actAs(90);   // sysadmin
        $this->assertSame(3, Maintenance::count());
        $this->actAs(null); // scheduler, queue, callbacks
        $this->assertSame(3, WaterReading::count());
        $this->assertSame(2, SecurityLog::count());
    }

    public function test_a_resident_finds_their_own_repairs_and_readings_not_a_neighbours(): void
    {
        $this->actAs(31);
        $this->assertSame(['A1 tap'], Maintenance::pluck('name')->all());
        $this->assertNull(Maintenance::find(2));
        $this->assertSame([1], WaterReading::pluck('id')->all());
    }

    public function test_sms_templates_are_per_company_with_scharge_ones_shared_read_only(): void
    {
        $this->actAs(11);
        $this->assertEqualsCanonicalizing(['A reminder', 'scharge default', 'Old default'], SmsTemplate::pluck('name')->all());
        $this->assertEqualsCanonicalizing([1, 3, 4], \App\Models\SmsTemplate::pluck('id')->all());
        $this->assertTrue(SmsTemplate::find(1)->editableByUser());
        $this->assertFalse(SmsTemplate::find(3)->editableByUser());
        $this->assertFalse(SmsTemplate::find(4)->editableByUser());
        $this->assertNull(SmsTemplate::find(2));

        $this->actAs(null);
        $this->assertSame(4, SmsTemplate::count());
    }

    public function test_a_company_admin_cannot_open_delete_or_create_staff_outside_their_company(): void
    {
        $this->actAs(11);
        $c = app(StaffController::class);
        foreach (['show', 'destroy'] as $method) {
            try {
                $method === 'show' ? $c->show(22) : $c->destroy(new Request, 22);
                $this->fail("$method reached another company's staff");
            } catch (HttpException $e) {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
        $this->assertSame(1, DB::table('users')->where('id', 22)->whereNull('deleted_at')->count());

        $body = fn ($roleName, $email) => Request::create('/', 'POST', ['name' => 'New', 'email' => $email, 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'role_id' => DB::table('roles')->where('name', $roleName)->value('id'), 'company_id' => 2]);
        $this->assertSame(422, $c->store($body('sysadmin', 'new1@gmail.com'))->getStatusCode());   // not a staff role
        $this->assertSame(200, $c->store($body('security', 'new2@gmail.com'))->getStatusCode());
        $this->assertSame(1, (int) DB::table('users')->where('email', 'new2@gmail.com')->value('company_id'));   // own company, not the one sent
    }
}
