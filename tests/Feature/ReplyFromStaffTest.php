<?php

namespace Tests\Feature;

use App\Modules\CRM\Services\ReplyService;
use Illuminate\Support\Facades\DB;

/** Mail from our own team (sharet.africa, or a sysadmin's or account manager's personal address) is never a prospect's reply. */
class ReplyFromStaffTest extends RentSchemaTestCase
{
    public function test_staff_addresses_are_ours_and_prospects_are_not(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'account_manager', 'guard_name' => 'web']);
        $staff = DB::table('users')->insertGetId(['name' => 'Waithaka', 'email' => 'waithaka.test@gmail.com']);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => (new \App\Models\User)->getMorphClass(), 'model_id' => $staff]);
        DB::table('users')->insertGetId(['name' => 'Landlord', 'email' => 'landlord.test@gmail.com']);

        $this->assertTrue(ReplyService::isOurs('Waithaka.Test@gmail.com'));
        $this->assertTrue(ReplyService::isOurs('sales@sharet.africa'));
        $this->assertFalse(ReplyService::isOurs('landlord.test@gmail.com'));
        $this->assertFalse(ReplyService::isOurs('pkagendo@daykio.com'));
    }
}
