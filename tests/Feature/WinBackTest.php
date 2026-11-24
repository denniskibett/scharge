<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\WinBack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** Leads that turned us down get a value-led draft after 21 quiet days; nothing goes out until someone approves it. */
class WinBackTest extends RentSchemaTestCase
{
    private int $priceCo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'reply_pending' => 'boolean', 'kind' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        Schema::dropIfExists('company_subscriptions');
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->timestamp('starts_at')->nullable(), $t->timestamps()]);
        DB::table('users')->insert(['id' => 5, 'name' => 'Waithaka Kammau', 'email' => 'waithaka@example.test', 'phone' => '0745907070']);
        config(['crm.winback.after_days' => 21, 'crm.minutes_per_payment' => 3]);
        Carbon::setTestNow('2026-11-01 09:00:00');

        $this->priceCo = $this->prospect('Jourdan Test', 'contacted', 'ewanja@jourdan.test', 'I had not noted the monthly cost of Ksh 40 per unit, which may be beyond our current budget.', 'price', ['estimated_units' => 221]);
        $this->prospect('Diani Test', 'contacted', 'grace@diani.test', 'We do not have a requirement for additional services.', 'not_interested');
        $this->prospect('Keen Test', 'contacted', 'keen@keen.test', 'Please send me more information.', 'interested');      // interested: not a win-back
        $this->prospect('Stop Test', 'lost', 'stop@stop.test', 'Remove me from your list.', 'not_interested', ['do_not_email' => true]); // asked us to stop
    }

    private function prospect(string $name, string $stage, string $from, string $text, string $intent, array $profile = []): int
    {
        $id = DB::table('companies')->insertGetId(['name' => $name, 'lifecycle' => 'prospect', 'owner_id' => 5, 'crm_stage' => $stage, 'email' => 'info@'.explode('@', $from)[1],
            'crm_profile' => json_encode($profile + ['email_subject' => 'Chasing rent?', 'email_body' => 'Hello', 'last_emailed_at' => '2026-10-28 10:00:00'])]);
        $reply = CrmFollowUp::create(['company_id' => $id, 'type' => 'email', 'status' => 'done', 'notes' => $text, 'details' => ['direction' => 'in', 'from' => $from, 'from_name' => explode(' ', $name)[0]]]);
        $c = Company::withoutGlobalScopes()->find($id);
        $c->forceFill(['crm_profile' => array_merge($c->crm_profile, ['reply_intent' => ['intent' => $intent, 'follow_up_id' => $reply->id]])])->saveQuietly();

        return $id;
    }

    public function test_only_turned_down_leads_quiet_for_three_weeks_get_a_draft_once(): void
    {
        $this->assertCount(0, WinBack::due(), 'too soon');

        Carbon::setTestNow('2026-11-24 09:00:00');
        $due = WinBack::due();
        $this->assertEqualsCanonicalizing(['Jourdan Test', 'Diani Test'], $due->pluck('company.name')->all());
        $this->assertSame('ewanja@jourdan.test', $due->firstWhere('company.id', $this->priceCo)['to'], 'the person who replied');

        $this->artisan('crm:winback --dry-run')->assertSuccessful();
        $this->assertSame(0, CrmFollowUp::where('details->winback', true)->count());
        $this->artisan('crm:winback')->assertSuccessful();
        $this->assertSame(2, CrmFollowUp::where('details->winback', true)->where('status', 'open')->count());
        $this->artisan('crm:winback')->assertSuccessful();
        $this->assertSame(2, CrmFollowUp::where('details->winback', true)->count(), 'one draft at a time');
    }

    public function test_the_draft_sells_the_saving_and_the_subsidy_and_is_signed(): void
    {
        $c = Company::withoutGlobalScopes()->find($this->priceCo);
        $d = WinBack::draft($c, 'price', 'EMMA WANJA');
        $this->assertStringStartsWith('Hi Emma,', $d['body']);
        $this->assertStringContainsString('the cost was the concern', $d['body']);
        $this->assertStringContainsString('For 221 units, that is about 11 hours a month', $d['body']);
        $this->assertStringContainsString('Your first payment of KES 8,840 covers three months', $d['body']);
        $this->assertStringContainsString('25% off after six months and 50% off after a year', $d['body']);
        $this->assertStringContainsString("Kind regards,\nWaithaka Kammau", $d['body']);
    }

    public function test_nothing_is_sent_until_approved_and_an_edited_draft_goes_as_edited(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-11-24 09:00:00');
        $this->artisan('crm:winback')->assertSuccessful();
        Mail::assertNothingSent();

        $draft = CrmFollowUp::where('details->winback', true)->where('company_id', $this->priceCo)->firstOrFail();
        $row = app(\App\Modules\CRM\Services\OutreachService::class)->sendText(Company::withoutGlobalScopes()->find($this->priceCo), $draft->detail('to'), [],
            'Edited subject', "Hi Emma,\n\nEdited text.", 'Win-back email approved by Waithaka.', ['winback' => true], 5);
        Mail::assertSent(CrmOutreachEmail::class, fn ($m) => $m->hasTo('ewanja@jourdan.test'));
        $this->assertSame('Email: Edited subject', $row->title);
        $this->assertNotNull($row->detail('track'));
    }
}
