<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\DemoRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** A demo request is kept once per ref: the same answers again change nothing, new answers merge in, old duplicates merge into one. */
class DemoDuplicatesTest extends RentSchemaTestCase
{
    private int $co;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'tier' => 'string', 'kind' => 'string', 'reply_pending' => 'boolean'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        DB::table('roles')->insert(['name' => 'account_manager', 'guard_name' => 'web']);
        DB::table('users')->insert(['id' => 5, 'name' => 'Amina Manager', 'email' => 'amina@example.test']);
        $this->co = DB::table('companies')->insertGetId(['name' => 'Jourdan Test Ltd', 'lifecycle' => 'prospect', 'owner_id' => 5, 'crm_stage' => 'contacted',
            'crm_profile' => json_encode(['demo_ref' => 'RESIDENTIAL-TEST01', 'segment' => 'Property Manager / Agency'])]);
        Carbon::setTestNow('2026-11-24 09:00:00');
        Mail::fake();
        Storage::fake('local');
    }

    private function form(array $over = []): array
    {
        return array_replace_recursive([
            'role' => array_key_first(DemoRequestService::ROLES), 'name' => 'Emma Wanja', 'job_title' => 'Accountant', 'phone' => '0703442537', 'email' => 'emma@jourdan.test',
            'interests' => ['rent', 'mpesa'], 'properties' => [['name' => 'Blemerg', 'type' => DemoRequestService::PROPERTY_TYPES[0], 'location' => 'Adlife Plaza', 'units' => 221]],
            'billing_now' => null, 'contact_date' => '2026-11-26', 'contact_window' => DemoRequestService::WINDOWS[0], 'channel' => DemoRequestService::CHANNELS[0], 'message' => null,
        ], $over);
    }

    private function demos()
    {
        return CrmFollowUp::where('type', 'demo')->where('company_id', $this->co);
    }

    public function test_the_same_answers_twice_make_one_request_and_one_set_of_emails(): void
    {
        $service = app(DemoRequestService::class);
        $company = \App\Models\Company::withoutGlobalScopes()->find($this->co);
        $first = $service->submit($company, $this->form(), 'RESIDENTIAL-TEST01');
        $mails = count(Mail::sent(\App\Modules\CRM\Mail\DemoRequestNotice::class));
        $second = $service->submit($company->fresh(), $this->form(), 'RESIDENTIAL-TEST01');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->demos()->count());
        $this->assertSame(1, $company->followUps()->where('type', 'note')->where('title', 'like', 'Demo form submitted%')->count());
        $this->assertSame($mails, count(Mail::sent(\App\Modules\CRM\Mail\DemoRequestNotice::class)));
    }

    public function test_new_answers_merge_into_the_open_request(): void
    {
        $service = app(DemoRequestService::class);
        $company = \App\Models\Company::withoutGlobalScopes()->find($this->co);
        $first = $service->submit($company, $this->form(['billing_now' => DemoRequestService::BILLING_NOW[0]]), 'RESIDENTIAL-TEST01');
        $service->submit($company->fresh(), $this->form(['interests' => ['water'], 'message' => 'Call after lunch',
            'properties' => [['name' => 'Second Court', 'type' => DemoRequestService::PROPERTY_TYPES[0], 'location' => 'Ngong Rd', 'units' => 30]]]), 'RESIDENTIAL-TEST01');

        $this->assertSame(1, $this->demos()->count());
        $d = $first->fresh();
        $this->assertEqualsCanonicalizing(['water', 'rent', 'mpesa'], $d->detail('interests'));
        $this->assertSame(['Second Court', 'Blemerg'], array_column($d->detail('properties'), 'name'));
        $this->assertSame(251, $d->detail('units'));
        $this->assertSame(DemoRequestService::BILLING_NOW[0], $d->detail('billing_now'), 'kept from the first: the second left it blank');
        $this->assertSame('Call after lunch', $d->notes);
        $this->assertNotNull($d->detail('pdf'));
        $this->assertTrue($company->followUps()->where('title', 'Demo request updated (RESIDENTIAL-TEST01)')->exists());
    }

    public function test_existing_duplicates_merge_into_the_oldest_and_its_notes_point_there(): void
    {
        $base = ['company_id' => $this->co, 'user_id' => 5, 'assigned_to' => 5, 'type' => 'demo', 'status' => 'open', 'title' => 'Demo request (RESIDENTIAL-TEST01): Emma, 221 units', 'due_at' => '2026-11-24 06:00:00'];
        $a = CrmFollowUp::create($base + ['details' => ['ref' => 'RESIDENTIAL-TEST01', 'interests' => ['rent'], 'properties' => [['name' => 'Blemerg', 'units' => 221]], 'units' => 221, 'billing_now' => 'Excel']]);
        $b = CrmFollowUp::create($base + ['notes' => 'Please call', 'details' => ['ref' => 'RESIDENTIAL-TEST01', 'interests' => ['mpesa'], 'properties' => [['name' => 'Blemerg', 'units' => 221]], 'units' => 221]]);
        foreach ([$a, $b] as $f) {
            CrmFollowUp::create(['company_id' => $this->co, 'type' => 'note', 'status' => 'done', 'title' => 'Demo form submitted (RESIDENTIAL-TEST01)', 'details' => ['demo_id' => $f->id]]);
        }

        $this->artisan('crm:merge-demo-duplicates --dry-run')->assertSuccessful();
        $this->assertSame(2, $this->demos()->where('status', 'open')->count());

        $this->artisan('crm:merge-demo-duplicates')->assertSuccessful();
        $this->assertSame([$a->id], $this->demos()->where('status', 'open')->pluck('id')->all());
        $kept = $a->fresh();
        $this->assertEqualsCanonicalizing(['rent', 'mpesa'], $kept->detail('interests'));
        $this->assertSame('Excel', $kept->detail('billing_now'));
        $this->assertSame('Please call', $kept->notes);
        $this->assertNotNull($kept->detail('pdf'));
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame(1, CrmFollowUp::where('type', 'note')->where('company_id', $this->co)->count());
        Mail::assertNothingSent();
    }
}
