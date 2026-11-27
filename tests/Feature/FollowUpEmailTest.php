<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\FollowUpEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** One follow-up, in the same thread, to cold-email prospects that have not answered after 4 working days. */
class FollowUpEmailTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'reply_pending' => 'boolean', 'kind' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        DB::table('users')->insert(['id' => 5, 'name' => 'Aggrey Oluoch', 'email' => 'aggrey@example.test', 'phone' => '0700000000']);
        Carbon::setTestNow('2026-11-18 10:00:00'); // Wednesday
    }

    private function emailed(string $name, array $details = [], array $company = []): int
    {
        $id = DB::table('companies')->insertGetId($company + ['name' => $name, 'lifecycle' => 'prospect', 'owner_id' => 5, 'crm_stage' => 'contacted',
            'email' => 'info@'.strtolower(str_replace(' ', '', $name)).'.test', 'crm_profile' => json_encode(['segment' => 'Property Manager / Agency'])]);
        CrmFollowUp::create(['company_id' => $id, 'assigned_to' => 5, 'type' => 'email', 'status' => 'done', 'title' => 'Cold email: '.$name.': chasing tenants for rent?',
            'details' => $details + ['to' => 'info@'.strtolower(str_replace(' ', '', $name)).'.test', 'track' => str_pad((string) $id, 24, 'x'), 'message_id' => 'first-'.$id.'@sharet.africa', 'campaign' => 'RESIDENTIAL 2026-11', 'delivery' => 'sent']]);

        return $id;
    }

    public function test_only_quiet_prospects_are_due_clicked_and_opened_first(): void
    {
        $plain = $this->emailed('Plain Homes');
        $opened = $this->emailed('Opened Homes', ['opened_at' => '2026-11-18 12:00:00', 'opens' => 3]);
        $clicked = $this->emailed('Clicked Homes', ['clicked_at' => '2026-11-18 12:00:00']);
        $this->emailed('Replied Homes', ['replied_at' => '2026-11-19 09:00:00']);
        $this->emailed('Bounced Homes', ['delivery' => 'bounced']);
        $this->emailed('Stop Homes', [], ['crm_profile' => json_encode(['do_not_email' => true])]);
        $this->emailed('Demo Homes', [], ['crm_stage' => 'demo_booked']);
        $answered = $this->emailed('Answered Homes');
        CrmFollowUp::create(['company_id' => $answered, 'type' => 'email', 'status' => 'done', 'title' => 'Re', 'details' => ['direction' => 'in', 'from' => 'a@answered.test']]);

        $this->assertCount(0, FollowUpEmail::due(4), 'too soon on the same day');
        Carbon::setTestNow('2026-11-24 10:00:00'); // 4 working days later
        $this->assertSame([$clicked, $opened, $plain], FollowUpEmail::due(4)->pluck('company.id')->all());
    }

    public function test_it_replies_in_the_same_thread_once_signed_by_the_manager(): void
    {
        Mail::fake();
        $id = $this->emailed('Plain Homes');
        Carbon::setTestNow('2026-11-24 10:00:00');

        $this->artisan('crm:follow-up --send --per-minute=60')->assertSuccessful();
        Mail::assertSent(CrmOutreachEmail::class, function (CrmOutreachEmail $m) use ($id) {
            $this->assertSame('Re: Plain Homes: chasing tenants for rent?', $m->subjectLine);
            $this->assertSame(['first-'.$id.'@sharet.africa'], $m->thread);
            $this->assertSame('<first-'.$id.'@sharet.africa>', $m->headers()->text['In-Reply-To']);
            $this->assertStringContainsString('following up on my email of 18 November', $m->body);
            $this->assertStringContainsString('Aggrey Oluoch', $m->body);

            return $m->hasTo('info@plainhomes.test');
        });
        $row = CrmFollowUp::where('details->step', 'follow_up')->first();
        $this->assertSame('FOLLOWUP 2026-11', $row->detail('campaign'));
        $this->assertNotNull($row->detail('track'), 'opens and clicks are tracked');

        $this->artisan('crm:follow-up --send --per-minute=60')->assertSuccessful();
        $this->assertSame(1, CrmFollowUp::where('details->step', 'follow_up')->count(), 'one follow-up only');
        $this->assertCount(0, FollowUpEmail::due(4));
        unset($id);
    }
}
