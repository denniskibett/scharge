<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\LateBounce;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * A bounce that comes back as an email ("address not found" after their server accepted our message) marks the
 * address as dead and sends the email to the company's next address; the account manager is not left waiting.
 */
class LateBounceTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'tier' => 'string', 'kind' => 'string', 'domain' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        DB::table('roles')->insert(['name' => 'account_manager', 'guard_name' => 'web']);
        DB::table('users')->insert(['id' => 5, 'name' => 'Annete Manager', 'email' => 'annete@example.test', 'company_id' => null]);
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'account_manager')->value('id'), 'model_type' => \App\Models\User::class, 'model_id' => 5]);
        Mail::fake();
    }

    /** A prospect we emailed at $email, with the bounce notice that came back. Returns [company id, notice]. */
    private function bounced(string $name, string $email, array $extra, string $notice, string $subject = 'Delivery Status Notification (Failure)'): array
    {
        $id = DB::table('companies')->insertGetId(['name' => $name, 'lifecycle' => 'prospect', 'crm_stage' => 'contacted', 'owner_id' => 5, 'email' => $email,
            'crm_profile' => json_encode(['email_subject' => $name.': long-stay guests?', 'email_body' => 'Hello, a short note from scharge.',
                'emails' => array_map(fn ($e) => ['email' => $e, 'source' => 'website', 'verified' => 'syntax+mx'], $extra)])]);
        CrmFollowUp::create(['company_id' => $id, 'type' => 'email', 'status' => 'done', 'title' => 'Cold email', 'details' => ['to' => $email, 'cc' => $extra, 'message_id' => 'ours-'.$id.'@sharet.africa']]);
        $row = CrmFollowUp::create(['company_id' => $id, 'type' => 'email', 'status' => 'done', 'title' => 'Automatic reply from Mail Delivery Subsystem', 'notes' => $notice,
            'details' => ['direction' => 'in', 'auto' => true, 'from' => 'mailer-daemon@googlemail.com', 'subject' => $subject, 'answers' => 'ours-'.$id.'@sharet.africa']]);

        return [$id, $row];
    }

    public function test_a_dead_address_is_marked_and_the_email_goes_to_the_next_one(): void
    {
        [$id, $row] = $this->bounced('Kilifi Hostel', 'info@visitkilifi.test', ['bookings@visitkilifi.test'],
            "** Address not found ** Your message wasn't delivered to info@visitkilifi.test because the address couldn't be found.");

        $done = LateBounce::handle($row);

        $company = Company::withoutGlobalScopes()->find($id);
        $this->assertSame(['failed' => ['info@visitkilifi.test'], 'primary' => true, 'resent_to' => 'bookings@visitkilifi.test'], $done);
        $this->assertSame('bookings@visitkilifi.test', $company->email, 'the next address on file is now the company email');
        $this->assertContains('info@visitkilifi.test', $company->profile('bounced_emails'));
        $this->assertFalse((bool) $company->profile('email_bounced'));
        $this->assertSame('bounced', CrmFollowUp::where('company_id', $id)->where('details->message_id', 'ours-'.$id.'@sharet.africa')->first()->detail('delivery'));
        Mail::assertSent(CrmOutreachEmail::class, fn ($m) => $m->hasTo('bookings@visitkilifi.test'));
        $this->assertNull(LateBounce::handle($row->fresh()), 'a notice is acted on once');
        Mail::assertSent(CrmOutreachEmail::class, 1);
    }

    public function test_with_no_other_address_the_company_is_flagged_for_a_new_one(): void
    {
        [$id, $row] = $this->bounced('Dil Panga', 'info@dilpanga.test', [],
            'A message that you sent could not be delivered. The following address(es) failed: jai@dilpanga.test (ultimately generated from info@dilpanga.test)',
            'Mail delivery failed: returning message to sender');

        Artisan::call('crm:late-bounces');

        $company = Company::withoutGlobalScopes()->find($id);
        $this->assertTrue((bool) $company->profile('email_bounced'), 'back on the Unsent list until a working address is found');
        $this->assertSame('info@dilpanga.test', $company->email);
        $this->assertNull($row->fresh()->detail('bounce.resent_to'));
        Mail::assertNothingSent();
    }

    public function test_a_copied_address_that_bounces_is_dropped_and_a_delay_notice_is_left_alone(): void
    {
        [$id, $row] = $this->bounced('Shujaa Mall', 'info@shujaamall.test', ['leasing@shujaamall.test'], 'The following address failed: leasing@shujaamall.test: mailbox disabled');
        [$id2, $late] = $this->bounced('Slow Server Ltd', 'info@slow.test', [], 'Delivery to info@slow.test is delayed; we will keep trying.', 'Delivery Status Notification (Delay)');

        Artisan::call('crm:late-bounces', ['--dry' => true]);
        $this->assertNull($row->fresh()->detail('bounce'), 'the dry run changes nothing');
        Artisan::call('crm:late-bounces');

        $company = Company::withoutGlobalScopes()->find($id);
        $this->assertSame('info@shujaamall.test', $company->email);
        $this->assertFalse((bool) $company->profile('email_bounced'));
        $this->assertSame([], $company->profile('emails'));
        $this->assertSame(['leasing@shujaamall.test'], $company->profile('bounced_emails'));
        $this->assertFalse((bool) Company::withoutGlobalScopes()->find($id2)->profile('email_bounced'));
        $this->assertNull($late->fresh()->detail('bounce'));
        Mail::assertNothingSent();
    }
}
