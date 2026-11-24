<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\CloseSteps;
use App\Modules\CRM\Support\InboundEmail;
use App\Modules\CRM\Support\Playbook;
use App\Modules\CRM\Support\ReplyIntent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** Replies are read (seller, partner, not interested, system, price, later, interested); a seller moves to Land & home sales. */
class ReplyIntentTest extends RentSchemaTestCase
{
    public function test_real_replies_read_as_what_they_say(): void
    {
        $cases = [
            'My business focuses on selling plots and houses, not rental properties.' => 'seller',
            'We deal in land and plots in Kitengela.' => 'seller',
            'No but we can assist in the advertisement.' => 'partner',
            'At present, we do not have a requirement for additional services.' => 'not_interested',
            'Not interested, please remove me from your list.' => 'not_interested',
            'Thanks, we already use a property management system.' => 'has_system',
            'I had not noted the monthly cost of Ksh 40 per unit, which may be beyond our current budget.' => 'price',
            'Please call me next week' => 'later',
            'Please share more information regarding the subject mentioned above.' => 'interested',
            'We received your request successfully and our team is evaluating it.' => null,
        ];
        foreach ($cases as $text => $intent) {
            $this->assertSame($intent, ReplyIntent::read($text), $text);
        }
    }

    public function test_a_seller_reply_switches_the_playbook_and_leads_closing_with_a_suggested_answer(): void
    {
        Mail::fake();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'reply_pending' => 'boolean', 'kind' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        $id = DB::table('companies')->insertGetId(['name' => 'New Dawn Test', 'lifecycle' => 'prospect', 'crm_stage' => 'contacted', 'email' => 'realtor@test.test',
            'crm_profile' => json_encode(['segment' => 'Property Manager / Agency', 'last_emailed_at' => '2026-11-23 11:34:00', 'estimated_units' => 40])]);
        $company = Company::withoutGlobalScopes()->find($id);
        $mail = InboundEmail::parse("From: SAMMY THE REALTOR <realtor@test.test>\r\nTo: sales@sharet.africa\r\nSubject: Re: chasing tenants\r\nMessage-ID: <x1@test>\r\nContent-Type: text/plain\r\n\r\nMy business focuses on selling plots and houses, not rental properties.\r\n");

        app(ReplyService::class)->record($company, $mail, null, 'sales@sharet.africa');

        $company = $company->fresh();
        $this->assertSame('seller', $company->profile('reply_intent.intent'));
        $this->assertSame('Land & Home Sales', $company->profile('segment'));
        $this->assertSame('Property Manager / Agency', $company->profile('segment_before'));
        $this->assertSame('seller', Playbook::segmentOf($company));
        $this->assertTrue($company->followUps()->where('title', 'Reply read: Sells plots or houses, not rentals')->exists());

        $close = CloseSteps::for($company);
        $this->assertSame('Pivot to what fits a seller', $close['steps'][0]['title']);
        $this->assertStringStartsWith('Hi Sammy,', $close['suggested']);
        $this->assertStringContainsString('instalments', $close['suggested']);
        $this->assertNull($close['quote'], 'no rental per-unit figure for a seller');
    }

    public function test_the_backfill_reads_each_latest_reply_once(): void
    {
        foreach (['crm_profile' => 'json', 'crm_stage' => 'string', 'email' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        $id = DB::table('companies')->insertGetId(['name' => 'Diani Test', 'lifecycle' => 'prospect', 'crm_stage' => 'contacted']);
        CrmFollowUp::create(['company_id' => $id, 'type' => 'email', 'status' => 'done', 'notes' => 'We do not have a requirement for additional services.', 'details' => ['direction' => 'in', 'from' => 'grace@test.test']]);

        $this->artisan('crm:read-replies --dry-run')->assertSuccessful();
        $this->assertNull(Company::withoutGlobalScopes()->find($id)->profile('reply_intent'));
        $this->artisan('crm:read-replies')->assertSuccessful();
        $this->assertSame('not_interested', Company::withoutGlobalScopes()->find($id)->profile('reply_intent.intent'));
        $notes = CrmFollowUp::where('company_id', $id)->where('type', 'note')->count();
        $this->artisan('crm:read-replies')->assertSuccessful();
        $this->assertSame($notes, CrmFollowUp::where('company_id', $id)->where('type', 'note')->count(), 'read once');
    }

    public function test_answers_sell_the_saving_in_their_own_numbers(): void
    {
        config(['crm.minutes_per_payment' => 3]);
        $a = ReplyIntent::next('price', 'Emma', ['units' => 221, 'hours' => 11, 'fee' => 8840])['answer'];
        $this->assertStringContainsString('With 221 units', $a);
        $this->assertStringContainsString('about 11 hours a month', $a);
        $this->assertStringContainsString('KES 8,840 covers three months', $a);
        $this->assertStringNotContainsString('With 0 units', ReplyIntent::next('price', 'Emma')['answer'], 'no figure when we do not know their units');
    }

    public function test_a_colleague_at_the_same_business_domain_is_matched_but_free_mail_is_not(): void
    {
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'kind' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        $ktda = DB::table('companies')->insertGetId(['name' => 'KTDA Test', 'lifecycle' => 'prospect', 'email' => 'info@ktdateas.test', 'crm_profile' => json_encode(['last_emailed_at' => '2026-11-24 12:34:00'])]);
        DB::table('companies')->insertGetId(['name' => 'Gmail Co', 'lifecycle' => 'prospect', 'email' => 'someone@gmail.com', 'crm_profile' => json_encode(['last_emailed_at' => '2026-11-24 12:34:00'])]);
        $parse = fn ($from) => InboundEmail::parse("From: X <{$from}>\r\nSubject: AUTO: out of the office (returning 11/30/2026)\r\nAuto-Submitted: auto-replied\r\n\r\nI am out of the office.\r\n");

        [$c] = app(ReplyService::class)->match($parse('svieliza@ktdateas.test'));
        $this->assertSame($ktda, $c?->id);
        [$none] = app(ReplyService::class)->match($parse('stranger@gmail.com'));
        $this->assertNull($none);

        $r = \App\Modules\CRM\Support\OutOfOffice::read($parse('svieliza@ktdateas.test'), \Illuminate\Support\Carbon::parse('2026-11-24 12:37', 'Africa/Nairobi'));
        $this->assertSame('2026-11-30', $r['return_on']?->toDateString(), 'US date in the subject');
    }
}
