<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\InboundEmail;
use App\Modules\CRM\Support\OutOfOffice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Out-of-office replies: addresses they offer get our email at once and are saved on the company; a return date
 * sends the email again at 08:00 that morning. Bounces and plain "we got your email" notes do nothing.
 * Samples are shaped on real replies (names and addresses changed).
 */
class OutOfOfficeTest extends RentSchemaTestCase
{
    private int $co;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'kind' => 'string', 'domain' => 'string', 'reply_pending' => 'boolean'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        foreach (['sysadmin', 'account_manager'] as $role) {
            DB::table('roles')->insert(['name' => $role, 'guard_name' => 'web']);
        }
        $manager = DB::table('users')->insertGetId(['name' => 'Amina Manager', 'email' => 'amina@example.test']);
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'account_manager')->value('id'), 'model_type' => (new \App\Models\User)->getMorphClass(), 'model_id' => $manager]);
        $this->co = DB::table('companies')->insertGetId(['name' => 'Hilltop Developments', 'lifecycle' => 'prospect', 'crm_stage' => 'contacted', 'owner_id' => $manager,
            'email' => 'sales@hilltop.test', 'crm_profile' => json_encode(['email_subject' => 'Hilltop Developments: service charge collections?',
                'email_body' => '<p>Hello Hilltop team,</p><p>We help developers collect service charge.</p>', 'last_emailed_at' => '2026-11-20 10:00:00'])]);
        Carbon::setTestNow(Carbon::parse('2026-11-24 10:00:00', 'Africa/Nairobi')->utc());
    }

    private function mail(string $from, string $subject, string $body, bool $autoHeader = true): InboundEmail
    {
        return InboundEmail::parse("From: Someone <{$from}>\r\nTo: sales@sharet.africa\r\nSubject: {$subject}\r\nMessage-ID: <".uniqid()."@test>\r\n"
            .($autoHeader ? "Auto-Submitted: auto-replied\r\n" : '')."Content-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n");
    }

    public function test_it_reads_the_offered_addresses_and_ignores_the_senders_own_broken_signature(): void
    {
        $leave = $this->mail('alex@hilltop.test', 'OUT OF OFFICE Re: Hilltop Developments: service charge collections?',
            "I am currently away from the office on medical leave and will have limited access to email. For urgent matters, please contact Vero on salesassistant@hilltop.test tel. 0714000000, Rehema tv1@hilltop.test or Martha tv2@hilltop.test.\nRegards, Alex\nEmail: t lex@hilltop.test Web: www.hilltop.test");
        $r = OutOfOffice::read($leave, null, ['sales@hilltop.test']);
        $this->assertFalse($r['bounce']);
        $this->assertTrue($r['away']);
        $this->assertSame(['salesassistant@hilltop.test', 'tv1@hilltop.test', 'tv2@hilltop.test'], $r['alternates']);
        $this->assertNull($r['return_on']);

        $alt = OutOfOffice::read($this->mail('info@camp.test', 'Camp', 'Thank you! If you do not hear from us within 24hrs, our alternative email is campbookings@gmail.com for a 2 hr response time.'));
        $this->assertSame(['campbookings@gmail.com'], $alt['alternates']);

        $ack = OutOfOffice::read($this->mail('mapema@gmail.com', 'Thank You for Contacting Mapema', 'Our team will get back to you shortly. Daniel, Director, mapema@gmail.com, www.mapema.test'));
        $this->assertSame([], $ack['alternates']);
        $this->assertNull($ack['return_on']);

        $bounce = OutOfOffice::read($this->mail('mailer-daemon@host.test', 'Warning: message delayed 72 hours', 'Not delivered to someone@else.test'));
        $this->assertTrue($bounce['bounce']);
        $this->assertSame([], $bounce['alternates']);
    }

    public function test_it_reads_the_return_date_in_the_ways_people_write_it(): void
    {
        $received = Carbon::parse('2026-11-24 10:00', 'Africa/Nairobi'); // a Tuesday
        $cases = [
            'I will be back in the office on Monday 1st December 2026.' => '2026-12-01',
            'I am on annual leave and will return on 3rd December.' => '2026-12-03',
            'Back on December 8, I will reply then.' => '2026-12-08',
            'I am out of office until 28/11/2026.' => '2026-11-30',          // "until" a Saturday → the next weekday
            'On leave from 25th to 27th November.' => '2026-11-30',          // last day away is Friday the 27th → back Monday
            'I will be back on Monday.' => '2026-11-30',
            'I am back in the office on 5 January.' => '2027-01-05',
            'Thanks, we will get back to you shortly.' => null,
            'I returned on 2 November.' => null,                               // in the past
        ];
        foreach ($cases as $text => $expected) {
            $got = OutOfOffice::returnDate($text, $received);
            $this->assertSame($expected, $got?->toDateString(), $text);
            if ($got) {
                $this->assertSame('08:00', $got->format('H:i'));
            }
        }
    }

    public function test_an_out_of_office_with_other_addresses_emails_them_now_and_saves_them(): void
    {
        Mail::fake();
        $company = Company::withoutGlobalScopes()->find($this->co);
        $mail = $this->mail('alex@hilltop.test', 'Out of office', 'I am away on leave. For urgent matters contact salesassistant@hilltop.test or tv1@hilltop.test.');

        app(ReplyService::class)->record($company, $mail, null, 'sales@sharet.africa');

        Mail::assertSent(CrmOutreachEmail::class, fn ($m) => $m->hasTo('salesassistant@hilltop.test') && $m->hasCc('tv1@hilltop.test'));
        $company = $company->fresh();
        $this->assertSame(['salesassistant@hilltop.test', 'tv1@hilltop.test'], array_column($company->crm_profile['emails'], 'email'));
        $this->assertSame(2, $company->followUps()->where('type', 'contact')->count());
        $this->assertTrue($company->followUps()->where('details->resend', true)->exists());
        $this->assertNotTrue($company->crm_profile['reply_pending'] ?? null, 'an automatic reply raises no alert');
    }

    public function test_a_return_date_sends_the_email_again_that_morning_unless_they_wrote_back(): void
    {
        Mail::fake();
        $company = Company::withoutGlobalScopes()->find($this->co);
        app(ReplyService::class)->record($company, $this->mail('alex@hilltop.test', 'Out of office', 'I am on annual leave and will be back on Monday 1st December.'), null, 'sales@sharet.africa');

        $task = CrmFollowUp::where('details->resend_on_return', true)->firstOrFail();
        $this->assertSame('scheduled', $task->status);
        $this->assertSame('2026-12-01 08:00', $task->due_at->timezone('Africa/Nairobi')->format('Y-m-d H:i'));
        Mail::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-12-01 07:59', 'Africa/Nairobi')->utc());
        $this->artisan('crm:resend-returning')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-12-01 08:00', 'Africa/Nairobi')->utc());
        $this->artisan('crm:resend-returning')->assertSuccessful();
        Mail::assertSent(CrmOutreachEmail::class, fn ($m) => $m->hasTo('alex@hilltop.test'));
        $this->assertSame('done', $task->fresh()->status);

        // A second out-of-office, but this time they write back before the date: nothing goes.
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-12-02 10:00', 'Africa/Nairobi')->utc());
        app(ReplyService::class)->record($company->fresh(), $this->mail('alex@hilltop.test', 'Out of office', 'Away until 5th December.'), null, 'sales@sharet.africa');
        app(ReplyService::class)->record($company->fresh(), $this->mail('alex@hilltop.test', 'Re: service charge', 'Please call me next week.', false), null, 'sales@sharet.africa');
        Carbon::setTestNow(Carbon::parse('2026-12-08 08:00', 'Africa/Nairobi')->utc());
        $this->artisan('crm:resend-returning')->assertSuccessful();
        Mail::assertNotSent(CrmOutreachEmail::class);
        $this->assertSame('cancelled', CrmFollowUp::where('details->resend_on_return', true)->latest('id')->first()->status);
    }

    public function test_bounces_and_opted_out_companies_send_nothing(): void
    {
        Mail::fake();
        $company = Company::withoutGlobalScopes()->find($this->co);
        app(ReplyService::class)->record($company, $this->mail('mailer-daemon@host.test', 'Undeliverable: service charge', 'Could not deliver. Contact postmaster@host.test or admin@host.test'), null, 'sales@sharet.africa');
        $company->forceFill(['crm_profile' => array_merge($company->fresh()->crm_profile, ['do_not_email' => true])])->saveQuietly();
        app(ReplyService::class)->record($company->fresh(), $this->mail('alex@hilltop.test', 'Out of office', 'Back on 1 December. Urgent: tv1@hilltop.test'), null, 'sales@sharet.africa');

        Mail::assertNotSent(CrmOutreachEmail::class);
        $this->assertSame(0, CrmFollowUp::where('details->resend_on_return', true)->count());
    }
}
