<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AccountActivationController;
use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Mail\FollowUpInvite;
use App\Modules\CRM\Mail\TenantListUploaded;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Support\Onboarding;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Onboarding is never left alone: a new customer gets an account manager at once, a company stuck on one setup
 * step for three days puts a call on their calendar, the sign-up form sets the business up without a second page,
 * and a tenant list uploaded from the dashboard reaches the account manager with a task.
 */
class OnboardingOwnerStallsAndTenantListTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'phone' => 'string', 'crm_stage' => 'string', 'tier' => 'string', 'kind' => 'string', 'source' => 'string', 'is_active' => 'boolean'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        foreach (['account_manager', 'admin', 'bnb_host', 'sysadmin'] as $role) {
            DB::table('roles')->insert(['name' => $role, 'guard_name' => 'web']);
        }
        DB::table('users')->insert([
            ['id' => 5, 'name' => 'Amina Manager', 'email' => 'amina@example.test', 'phone' => '+254700000005', 'company_id' => null],
            ['id' => 6, 'name' => 'Brian Manager', 'email' => 'brian@example.test', 'phone' => '+254700000006', 'company_id' => null],
        ]);
        foreach ([5, 6] as $id) {
            $this->role($id, 'account_manager');
        }
        // Amina already looks after two customers, Brian one.
        foreach (['Old One' => 5, 'Old Two' => 5, 'Old Three' => 6] as $name => $owner) {
            DB::table('companies')->insert(['name' => $name, 'lifecycle' => 'customer', 'owner_id' => $owner, 'created_at' => '2026-01-01 00:00:00']);
        }
        Carbon::setTestNow('2026-11-23 09:00:00');   // a Monday, 12:00 in Nairobi
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function role(int $userId, string $role): void
    {
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', $role)->value('id'), 'model_type' => User::class, 'model_id' => $userId]);
    }

    /** A customer with an admin login, created $daysAgo days ago. */
    private function customer(string $name, int $daysAgo, ?int $owner = null): Company
    {
        $id = DB::table('companies')->insertGetId(['name' => $name, 'lifecycle' => 'customer', 'kind' => 'manager', 'owner_id' => $owner, 'phone' => '254711000001',
            'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)]);
        $uid = DB::table('users')->insertGetId(['name' => $name.' Admin', 'email' => 'admin'.$id.'@example.test', 'phone' => '+2547110000'.$id, 'company_id' => $id]);
        $this->role($uid, 'admin');

        return Company::withoutGlobalScopes()->findOrFail($id);
    }

    public function test_a_new_customer_gets_the_account_manager_with_the_fewest_customers(): void
    {
        $company = $this->customer('Fresh Homes', 0);
        $owner = Onboarding::assignOwner($company);

        $this->assertSame(6, $owner->id);                                   // Brian has one customer, Amina two
        $this->assertSame(6, (int) DB::table('companies')->where('id', $company->id)->value('owner_id'));
        $this->assertSame(6, Onboarding::assignOwner($company->fresh())->id, 'an owner is never replaced');
    }

    public function test_marking_a_deal_won_gives_it_to_whoever_won_it(): void
    {
        $id = DB::table('companies')->insertGetId(['name' => 'Grey Oak Ltd', 'lifecycle' => 'prospect', 'crm_stage' => 'proposal']);
        app(ContactService::class)->setStage(Company::withoutGlobalScopes()->find($id), 'won', 5);

        $this->assertSame(5, (int) DB::table('companies')->where('id', $id)->value('owner_id'));
    }

    public function test_a_company_stuck_for_three_days_puts_a_call_on_its_account_managers_calendar(): void
    {
        $stuck = $this->customer('Stuck Estates', 5);
        $new = $this->customer('Just Joined', 1);

        Artisan::call('crm:onboarding-stalls');

        $task = CrmFollowUp::where('company_id', $stuck->id)->where('type', 'call')->first();
        $this->assertNotNull($task);
        $this->assertSame('stall', $task->detail('onboarding'));
        $this->assertSame('property', $task->detail('step'));
        $this->assertStringContainsString('Add your property', $task->title);
        $this->assertStringContainsString('5 days', $task->notes);
        $this->assertSame(6, (int) $task->assigned_to, 'it had no account manager: it was given one');
        $this->assertTrue($task->due_at->isFuture());
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->task->is($task) && $m->hasTo('brian@example.test'));
        $this->assertSame(0, CrmFollowUp::where('company_id', $new->id)->where('type', 'call')->count(), 'one day in is not a stall');

        // Not raised twice while the call is open, nor again within a week once it is closed.
        Artisan::call('crm:onboarding-stalls');
        $task->update(['status' => 'done', 'done_at' => now()]);
        Carbon::setTestNow('2026-11-25 09:00:00');
        Artisan::call('crm:onboarding-stalls');
        $this->assertSame(1, CrmFollowUp::where('company_id', $stuck->id)->where('type', 'call')->count());

        // They add a building: the clock starts again on the next step, and three days later that one is raised.
        DB::table('properties')->insert(['name' => 'Oak Court', 'company_id' => $stuck->id]);
        Artisan::call('crm:onboarding-stalls');
        $this->assertSame('units', data_get(Company::withoutGlobalScopes()->find($stuck->id)->crm_profile, 'onboarding.step'));
        $this->assertSame(1, CrmFollowUp::where('company_id', $stuck->id)->where('type', 'call')->count());
        Carbon::setTestNow('2026-11-30 09:00:00');
        Artisan::call('crm:onboarding-stalls');
        $this->assertSame('units', CrmFollowUp::where('company_id', $stuck->id)->where('type', 'call')->latest('id')->first()->detail('step'));
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $stuck = $this->customer('Stuck Estates', 5);
        Artisan::call('crm:onboarding-stalls', ['--dry' => true]);

        $this->assertStringContainsString('would raise', Artisan::output());
        $this->assertSame(0, CrmFollowUp::count());
        $this->assertNull(DB::table('companies')->where('id', $stuck->id)->value('owner_id'));
        Mail::assertNothingSent();
    }

    public function test_the_business_named_at_sign_up_is_set_up_once_the_email_is_confirmed(): void
    {
        foreach (['email_confirmed_at' => 'timestamp', 'email_verified_at' => 'timestamp', 'role_id' => 'unsignedBigInteger', 'status' => 'integer'] as $col => $type) {
            if (! Schema::hasColumn('users', $col)) {
                Schema::table('users', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        $id = DB::table('users')->insertGetId(['name' => 'Wanjiru New', 'email' => 'wanjiru@example.test', 'company_id' => null]);
        $user = User::find($id);
        AccountActivationController::remember($user, ['business_name' => '  Urban   Nest Homes ', 'kind' => 'manager', 'phone' => '0712345678', 'password' => 'never kept']);

        $this->assertSame(['business_name', 'kind', 'phone'], array_keys(AccountActivationController::remembered($user)));
        config(['kyc.email_otp.enabled' => true]);
        if (! AccountActivationController::emailConfirmed($user)) {
            $this->assertNull(AccountActivationController::finishSignup($user), 'nothing happens before the email is confirmed');
        }
        $user->forceFill(['email_confirmed_at' => now(), 'email_verified_at' => now()])->save();
        $company = AccountActivationController::finishSignup($user->fresh());

        $this->assertSame('Urban Nest Homes', $company->name);
        $this->assertSame('signup', $company->source);
        $this->assertSame($company->id, (int) $user->fresh()->company_id);
        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertSame(6, (int) $company->fresh()->owner_id, 'a sign-up has an account manager from the first minute');
        $welcome = CrmFollowUp::where('company_id', $company->id)->where('details->onboarding', 'welcome')->first();
        $this->assertSame(6, (int) $welcome->assigned_to);
        $this->assertStringContainsString('Wanjiru New', $welcome->notes);
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->task->is($welcome));
        $this->assertNull(AccountActivationController::remembered($user), 'the saved answers are cleared');
        $this->assertNotNull(AccountActivationController::nameTaken('urban nest homes'));
    }

    public function test_a_tenant_list_uploaded_from_the_dashboard_reaches_the_account_manager(): void
    {
        Storage::fake('local');
        $company = $this->customer('Oak Court Managers', 1, 5);
        $admin = User::where('company_id', $company->id)->first();

        $this->actingAs($admin)->post(route('guide.tenant-list'), ['list' => UploadedFile::fake()->create('Tenants Nov.xlsx', 40), 'note' => 'Block B is new'])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Amina Manager'));

        $task = CrmFollowUp::where('company_id', $company->id)->where('details->onboarding', 'tenant_list')->first();
        $this->assertNotNull($task);
        $this->assertSame(5, (int) $task->assigned_to);
        $this->assertSame('Tenants Nov.xlsx', $task->detail('file_name'));
        $this->assertStringContainsString('Block B is new', $task->notes);
        Storage::disk('local')->assertExists($task->detail('file'));
        $this->assertStringStartsWith('onboarding/'.$company->id.'/', $task->detail('file'));
        $this->assertSame('Tenants Nov.xlsx', data_get(Company::withoutGlobalScopes()->find($company->id)->crm_profile, 'guide.tenant_list.name'));
        Mail::assertSent(TenantListUploaded::class, fn ($m) => $m->hasTo('amina@example.test') && count($m->attachments()) === 1);
        $html = (new TenantListUploaded($task->fresh(['company']), $admin))->render();
        $this->assertStringContainsString('Oak Court Managers sent their tenant list', $html);
        $this->assertStringContainsString('/crm/follow-ups/'.$task->id.'/file', $html);

        // Not a file we take: nothing is stored and no task is made.
        $this->actingAs($admin)->post(route('guide.tenant-list'), ['list' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('list');
        $this->assertSame(1, CrmFollowUp::where('details->onboarding', 'tenant_list')->count());
    }
}
