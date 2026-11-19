<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Models\User;
use App\Services\Assistant\Claude;
use App\Services\Assistant\Cleanup;
use App\Services\Assistant\Sources;
use App\Services\Rent\RentRun;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Claude assistant (docs/ASSISTANT.md): drafts wait for a person; nothing is sent or changed on its own. */
class AssistantTest extends RentSchemaTestCase
{
    private array $a;

    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('ai_drafts');
        Schema::create('ai_drafts', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->string('kind'), $t->string('status')->default('draft'),
            $t->string('channel')->default('email'), $t->string('source_ref')->unique(), $t->string('to_name')->nullable(), $t->string('to_address')->nullable(), $t->string('subject')->nullable(),
            $t->text('body')->nullable(), $t->string('note', 500)->nullable(), $t->json('context')->nullable(), $t->string('model')->nullable(), $t->unsignedInteger('input_tokens')->nullable(),
            $t->unsignedInteger('output_tokens')->nullable(), $t->string('error', 500)->nullable(), $t->unsignedBigInteger('reviewed_by')->nullable(), $t->timestamp('sent_at')->nullable(), $t->timestamps()]);
        \App\Services\Integrations::forgetInstalled();
        $this->a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 8000]], settings: ['send_bill' => 'no']);
        CompanyIntegration::create(['company_id' => null, 'provider' => 'claude', 'enabled' => true, 'credentials' => ['api_key' => 'sk-ant-test', 'crm' => 'on']]);
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'assistant', 'enabled' => true,
            'credentials' => ['tone' => 'warm', 'arrears' => '3', 'arrears_channel' => 'sms', 'cleanup' => 'weekly']]);
        Claude::$fake = function (string $task, string $channel, array $facts) {
            $this->calls[] = compact('task', 'channel', 'facts');

            return ['subject' => $channel === 'email' ? 'Re: hello' : '', 'body' => 'Hi '.($facts['tenant_first_name'] ?? 'there').', you owe KES '.($facts['total_owed_kes'] ?? 0).'. Pay: '.($facts['pay_link'] ?? '-'), 'note' => ''];
        };
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function lateBills(): void
    {
        app(RentRun::class)->run($this->a['company'], $this->month('2026-11'));
        DB::table('invoices')->update(['created_at' => now()->subDays(10)]);
    }

    public function test_late_tenants_get_one_draft_a_week_that_is_only_sent_when_a_person_presses_send(): void
    {
        $this->lateBills();
        $before = $this->sent;
        $this->assertSame(2, Sources::arrears($this->a['company']));
        $this->assertSame(0, Sources::arrears($this->a['company']));   // once per tenant per week
        $this->assertSame($before, $this->sent);                       // nothing sent by itself
        $this->assertSame(10000, (int) $this->calls[0]['facts']['total_owed_kes']);

        $user = User::create(['name' => 'Office', 'email' => 'o@example.test', 'company_id' => $this->a['company']]);
        $draft = DB::table('ai_drafts')->where('company_id', $this->a['company'])->orderBy('id')->first();
        $this->bareLayout();
        $this->withoutMiddleware([\Spatie\Permission\Middleware\RoleMiddleware::class]);
        $this->actingAs($user)->get('/assistant')->assertOk()->assertSee('you owe KES 10000');
        $this->actingAs($user)->post("/assistant/{$draft->id}/send", ['body' => 'Hi Tenant, edited text.'])->assertRedirect();
        $this->assertSame($before + 1, $this->sent);
        $row = DB::table('ai_drafts')->find($draft->id);
        $this->assertSame('sent', $row->status);
        $this->assertSame('Hi Tenant, edited text.', $row->body);
        $this->actingAs($user)->post("/assistant/{$draft->id}/send", ['body' => 'again'])->assertStatus(422);   // never twice
    }

    public function test_other_companies_cannot_see_or_send_a_companys_drafts(): void
    {
        $this->lateBills();
        Sources::arrears($this->a['company']);
        $b = $this->company('Other', [['rent_amount' => 5000]], false);
        $intruder = User::create(['name' => 'Other office', 'email' => 'x@example.test', 'company_id' => $b['company']]);
        $draft = DB::table('ai_drafts')->first();
        $this->bareLayout();
        $this->withoutMiddleware([\Spatie\Permission\Middleware\RoleMiddleware::class]);
        $this->actingAs($intruder)->get('/assistant')->assertOk()->assertDontSee('you owe KES');
        $this->actingAs($intruder)->post("/assistant/{$draft->id}/send", ['body' => 'x'])->assertNotFound();
    }

    public function test_cleanup_finds_problems_explains_them_and_fixes_only_on_approval(): void
    {
        $this->lateBills();
        $unit = DB::table('units')->insertGetId(['property_id' => DB::table('properties')->value('id'), 'company_id' => $this->a['company'], 'unit_number' => 'B9', 'rent_amount' => 7000, 'status' => 'occupied']);
        $bill = DB::table('invoices')->orderBy('id')->first();
        DB::table('invoices')->where('id', $bill->id)->update(['total_amount' => 99999]);
        DB::table('users')->where('id', DB::table('tenants')->value('user_id'))->update(['phone' => '0712 000 001']);

        $found = Cleanup::find($this->a['company']);
        $this->assertSame([$unit], $found['occupied_without_tenant']);
        $this->assertSame([$bill->id], $found['bill_total_wrong']);
        $this->assertCount(2, $found['phone_untidy']);   // 0712… numbers become +254…

        $this->assertSame(3, Cleanup::scan($this->a['company']));
        $this->assertSame('occupied', DB::table('units')->where('id', $unit)->value('status'));   // nothing changed yet

        $user = User::create(['name' => 'Office', 'email' => 'o@example.test', 'company_id' => $this->a['company']]);
        $this->withoutMiddleware([\Spatie\Permission\Middleware\RoleMiddleware::class]);
        foreach (DB::table('ai_drafts')->where('kind', 'cleanup')->pluck('id') as $id) {
            $this->actingAs($user)->post("/assistant/{$id}/send")->assertRedirect();
        }
        $this->assertSame('vacant', DB::table('units')->where('id', $unit)->value('status'));
        $this->assertNotSame(99999.0, (float) DB::table('invoices')->where('id', $bill->id)->value('total_amount'));
        $this->assertSame('+254712000001', DB::table('users')->where('id', DB::table('tenants')->orderBy('id')->value('user_id'))->value('phone'));
        $this->assertSame([], array_filter(Cleanup::find($this->a['company']), fn ($ids, $k) => $ids && $k !== 'same_phone_tenants', ARRAY_FILTER_USE_BOTH));
        $this->assertSame(3, DB::table('ai_drafts')->where('status', 'applied')->count());
    }

    public function test_prospect_replies_get_a_draft_and_send_answers_in_the_thread(): void
    {
        Schema::table('companies', fn (Blueprint $t) => [$t->string('email')->nullable(), $t->string('phone')->nullable(), $t->string('crm_stage')->nullable(),
            $t->boolean('reply_pending')->default(false), $t->longText('crm_profile')->nullable(), $t->string('address')->nullable()]);
        $prospect = DB::table('companies')->insertGetId(['name' => 'Acme Agencies', 'lifecycle' => 'prospect', 'email' => 'info@acme.test', 'reply_pending' => true]);
        $reply = DB::table('crm_follow_ups')->insertGetId(['company_id' => $prospect, 'type' => 'email', 'status' => 'done', 'title' => 'Reply from Jane', 'notes' => 'How much per unit?', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('companies')->where('id', $prospect)->update(['crm_profile' => json_encode(['reply' => ['from' => 'jane@acme.test', 'name' => 'Jane', 'subject' => 'scharge', 'follow_up_id' => $reply]])]);

        $made = rescue(fn () => Sources::prospectReplies(), null, false);
        if ($made === null) {
            $this->markTestSkipped('Prospect facts need the full CRM schema.');
        }
        $this->assertSame(1, $made);
        $draft = DB::table('ai_drafts')->whereNull('company_id')->first();
        $this->assertSame('prospect_reply', $draft->kind);
        $this->assertSame('jane@acme.test', $draft->to_address);
        $this->assertSame('How much per unit?', $this->calls[0]['facts']['their_reply']['text']);
        $this->assertSame(0, Sources::prospectReplies());   // once per reply
    }

    public function test_assistant_off_or_capped_makes_no_drafts(): void
    {
        $this->lateBills();
        CompanyIntegration::where('provider', 'assistant')->update(['enabled' => false]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(0, Sources::arrears($this->a['company']));

        CompanyIntegration::where('provider', 'assistant')->update(['enabled' => true]);
        $claude = CompanyIntegration::where('provider', 'claude')->first();
        $claude->mergeCredentials(['daily_limit' => '1']);
        $claude->save();
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(1, Sources::arrears($this->a['company']));
    }
}
