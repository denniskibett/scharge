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

    public function test_the_team_is_sysadmins_and_the_account_manager_with_work_copy(): void
    {
        $sys = DB::table('roles')->insertGetId(['name' => 'sysadmin', 'guard_name' => 'web']);
        $am = DB::table('roles')->insertGetId(['name' => 'account_manager', 'guard_name' => 'web']);
        $morph = (new \App\Models\User)->getMorphClass();
        $admin = DB::table('users')->insertGetId(['name' => 'Sysadmin', 'email' => 'sysadmin@sharet.africa']);
        $manager = DB::table('users')->insertGetId(['name' => 'Waithaka', 'email' => 'waithakakamau44@gmail.com']);
        DB::table('model_has_roles')->insert([['role_id' => $sys, 'model_type' => $morph, 'model_id' => $admin], ['role_id' => $am, 'model_type' => $morph, 'model_id' => $manager]]);
        $company = \App\Models\Company::withoutGlobalScopes()->forceCreate(['name' => 'Daykio Plantations', 'owner_id' => $manager]);
        config(['crm.reply_cc' => ['Boss@Example.com']]);

        $this->assertSame(['sysadmin@sharet.africa', 'waithakakamau44@gmail.com', 'wkamau@sharet.africa', 'boss@example.com'], ReplyService::team($company));
    }

    public function test_the_copy_names_the_prospect_and_answers_them(): void
    {
        $company = \App\Models\Company::withoutGlobalScopes()->forceCreate(['name' => 'Daykio Plantations']);
        $reply = new \App\Modules\CRM\Models\CrmFollowUp(['notes' => 'Please share more information.', 'details' => ['from' => 'pkagendo@daykio.com', 'from_name' => 'Purity Kagendo', 'subject' => 'Re: service charge', 'mailbox' => 'sales']]);
        $mail = new \App\Modules\CRM\Mail\CrmReplyCopy($company, $reply);

        $mail->assertHasSubject('Reply from Purity Kagendo (Daykio Plantations): Re: service charge');
        $mail->assertHasReplyTo('pkagendo@daykio.com');
        $mail->assertSeeInText('Please share more information.');
    }
}
