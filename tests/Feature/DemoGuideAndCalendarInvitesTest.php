<?php

namespace Tests\Feature;

use App\Http\Controllers\CalendarController;
use App\Modules\CRM\Mail\DemoHandlingGuide;
use App\Modules\CRM\Mail\DemoRequestNotice;
use App\Modules\CRM\Mail\FollowUpInvite;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CalendarInvite;
use App\Modules\CRM\Support\DemoGuide;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * A demo request also sends the account manager a guide (how to handle it, how to onboard), every dated CRM task
 * reaches their own calendar as an emailed invitation, and overdue tasks stay visible on the scharge calendar.
 */
class DemoGuideAndCalendarInvitesTest extends RentSchemaTestCase
{
    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'tier' => 'string', 'kind' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        DB::table('roles')->insert(['name' => 'account_manager', 'guard_name' => 'web']);
        DB::table('users')->insert([
            ['id' => 5, 'name' => 'Amina Manager', 'email' => 'amina@example.test', 'phone' => '+254700000005', 'company_id' => null],
            ['id' => 6, 'name' => 'Brian Manager', 'email' => 'brian@example.test', 'phone' => '+254700000006', 'company_id' => null],
            ['id' => 9, 'name' => 'Grace Prospect', 'email' => 'grace@greyoak.test', 'phone' => '+254711000009', 'company_id' => null],
        ]);
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('name', 'account_manager')->value('id'), 'model_type' => \App\Models\User::class, 'model_id' => 5]);
        $this->companyId = DB::table('companies')->insertGetId(['name' => 'Grey Oak Ltd', 'lifecycle' => 'prospect', 'owner_id' => 5,
            'crm_profile' => json_encode(['demo_ref' => 'SC-AB12CD', 'segment' => 'Property Manager / Agency'])]);
        Carbon::setTestNow('2026-11-23 09:00:00');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(array $over = []): CrmFollowUp
    {
        return CrmFollowUp::create($over + ['company_id' => $this->companyId, 'user_id' => 9, 'assigned_to' => 5, 'type' => 'call',
            'status' => 'open', 'title' => 'Call Grace', 'due_at' => '2026-11-25 14:00:00']);
    }

    public function test_the_guide_is_built_from_what_they_asked_for(): void
    {
        $demo = CalendarInvite::quietly(fn () => $this->task(['type' => 'demo', 'details' => ['ref' => 'SC-AB12CD', 'units' => 120,
            'properties' => [['name' => 'Oak Court', 'units' => 120]], 'interests' => ['water', 'gate'], 'billing_now' => 'Excel',
            'contact' => ['date' => '2026-11-25', 'window' => '14:00-15:00', 'channel' => 'WhatsApp']]]));
        $guide = DemoGuide::for($demo->fresh(['company', 'contact', 'assignee']));

        $this->assertStringContainsString('Wed 25 Nov, 14:00-15:00', $guide['when']);
        $this->assertStringContainsString('120 units', $guide['before'][0]);
        $this->assertStringContainsString('Excel', $guide['before'][0]);
        $this->assertCount(2, $guide['show']);                       // water and gate, nothing else
        $this->assertStringStartsWith('Water:', $guide['show'][0]);
        $this->assertSame('Send them the template', $guide['onboard'][0]['title']);
        $this->assertStringContainsString('company='.$this->companyId, $guide['onboard'][3]['url']);

        $html = (new DemoHandlingGuide($demo->fresh(['company', 'contact', 'assignee'])))->render();
        $this->assertStringContainsString('How to handle Grey Oak Ltd', $html);
        $this->assertStringContainsString('When they say yes: onboard them', $html);
    }

    public function test_the_demo_notice_carries_the_calendar_invitation(): void
    {
        $demo = CalendarInvite::quietly(fn () => $this->task(['type' => 'demo', 'details' => ['ref' => 'SC-AB12CD', 'contact' => ['date' => '2026-11-25', 'window' => '14:00-15:00', 'channel' => 'Phone']]]));
        $files = (new DemoRequestNotice($demo->fresh(['company', 'contact', 'assignee'])))->attachments();

        $this->assertCount(1, $files);   // no PDF in this test, just the invitation
        $ics = CalendarInvite::ics($demo);
        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertStringContainsString('UID:crm-task-'.$demo->id.'@', $ics);
        $this->assertStringContainsString('DTSTART:'.$demo->due_at->copy()->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('SUMMARY:Demo: Grey Oak Ltd', $ics);
        $this->assertStringContainsString('mailto:amina@example.test', $ics);
        Mail::assertNothingSent();   // created quietly: the notice is the invitation
    }

    public function test_a_dated_task_is_invited_once_updated_when_moved_and_cancelled_when_dropped(): void
    {
        $task = $this->task();
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->method === 'REQUEST' && $m->sequence === 0 && $m->hasTo('amina@example.test'));

        $task->update(['notes' => 'Bring the price list']);          // nothing their calendar shows changed
        Mail::assertSent(FollowUpInvite::class, 1);

        $task->update(['due_at' => '2026-11-26 10:00:00']);         // moved: same event, next sequence
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->method === 'REQUEST' && $m->sequence === 1);

        $task->update(['assigned_to' => 6]);                         // handed over: off Amina's calendar, on Brian's
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->method === 'CANCEL' && $m->hasTo('amina@example.test'));
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->method === 'REQUEST' && $m->hasTo('brian@example.test'));

        $task->update(['status' => 'cancelled']);
        Mail::assertSent(FollowUpInvite::class, fn ($m) => $m->method === 'CANCEL' && $m->hasTo('brian@example.test'));
        $this->assertTrue($task->fresh()->detail('invite.cancelled'));
    }

    public function test_tasks_without_a_time_or_an_owner_and_finished_ones_send_nothing(): void
    {
        $this->task(['due_at' => null]);
        $this->task(['assigned_to' => null]);
        $this->task(['type' => 'import', 'status' => 'done']);
        $this->task(['due_at' => '2026-11-20 10:00:00']);            // already in the past
        Mail::assertNothingSent();

        $done = $this->task();
        $done->update(['status' => 'done', 'done_at' => now()]);     // finished: stays on the calendar as history
        Mail::assertSent(FollowUpInvite::class, 1);

        config(['crm.calendar_invites' => false]);
        $this->task(['title' => 'Switched off']);
        Mail::assertSent(FollowUpInvite::class, 1);
    }

    public function test_overdue_tasks_stay_on_today_in_the_scharge_calendar(): void
    {
        CalendarInvite::quietly(function () {
            $this->task(['title' => 'This week', 'due_at' => '2026-11-25 14:00:00']);
            $this->task(['title' => 'Old one', 'due_at' => '2026-10-02 09:00:00']);
            $this->task(['title' => 'Old but done', 'due_at' => '2026-10-03 09:00:00', 'status' => 'done']);
            $this->task(['title' => 'A link, not a to-do', 'type' => 'contact', 'due_at' => '2026-11-24 09:00:00']);
        });
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(5));
        $method = new \ReflectionMethod(CalendarController::class, 'eventsFollowups');
        $events = $method->invoke(app(CalendarController::class), Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30 23:59:59'), [], Auth::user());

        $this->assertCount(2, $events);
        $late = $events->first(fn ($e) => str_contains($e['title'], 'Overdue since 2 Oct'));
        $this->assertNotNull($late);
        $this->assertTrue($late['allDay']);
        $this->assertSame('Danger', $late['extendedProps']['calendar']);
        $this->assertStringStartsWith('2026-11-23', $late['start']);
    }
}
