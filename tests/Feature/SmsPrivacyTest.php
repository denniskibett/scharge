<?php

namespace Tests\Feature;

use App\Modules\SMS\Controllers\CampaignController;
use App\Modules\SMS\Models\SmsCampaign;
use App\Modules\SMS\Models\SmsLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** SMS campaigns and the SMS log were shown to every company; each company now sees only its own. */
class SmsPrivacyTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('sms_campaigns');
        Schema::dropIfExists('campaign_recipients');
        Schema::create('sms_campaigns', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->unsignedBigInteger('template_id')->nullable(), $t->integer('total_recipients')->default(0),
            $t->integer('sent_count')->default(0), $t->integer('failed_count')->default(0), $t->string('status')->default('pending'), $t->unsignedBigInteger('created_by')->nullable(), $t->timestamps(), $t->softDeletes()]);
        Schema::create('campaign_recipients', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('campaign_id'), $t->unsignedBigInteger('tenant_id')->nullable(), $t->string('phone_number'),
            $t->text('message')->nullable(), $t->string('status')->default('pending'), $t->timestamp('sent_at')->nullable(), $t->text('error_message')->nullable(), $t->timestamps()]);
        Schema::table('sms_logs', fn (Blueprint $t) => $t->unsignedBigInteger('campaign_id')->nullable());

        DB::table('users')->insert([
            ['id' => 11, 'name' => 'Landlord A', 'company_id' => 1, 'phone' => '+254700000001'],
            ['id' => 12, 'name' => 'Tenant of A', 'company_id' => 1, 'phone' => '+254711111111'],
            ['id' => 21, 'name' => 'Landlord B', 'company_id' => 2, 'phone' => '+254700000002'],
            ['id' => 22, 'name' => 'Tenant of B', 'company_id' => 2, 'phone' => '+254722222222'],
        ]);
        DB::table('sms_campaigns')->insert([['id' => 1, 'name' => 'A rent reminder', 'created_by' => 11], ['id' => 2, 'name' => 'B rent reminder', 'created_by' => 21]]);
        DB::table('campaign_recipients')->insert([['campaign_id' => 1, 'phone_number' => '+254711111111', 'status' => 'delivered'], ['campaign_id' => 1, 'phone_number' => '+254700000009', 'status' => 'failed']]);
        DB::table('sms_logs')->insert([
            ['recipient_phone' => '0711111111', 'message' => 'Your rent is due', 'campaign_id' => null],  // A's tenant, local format
            ['recipient_phone' => '254722222222', 'message' => 'B private message', 'campaign_id' => null],
            ['recipient_phone' => '+254799999999', 'message' => 'In A campaign', 'campaign_id' => 1],
        ]);
    }

    private function actAs(int $id): void
    {
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find($id));
    }

    public function test_a_company_sees_only_its_own_campaigns_and_messages(): void
    {
        $this->actAs(11);
        $this->assertSame(['A rent reminder'], SmsCampaign::pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Your rent is due', 'In A campaign'], SmsLog::pluck('message')->all());

        $this->actAs(21);
        $this->assertSame(['B rent reminder'], SmsCampaign::pluck('name')->all());
        $this->assertSame(['B private message'], SmsLog::pluck('message')->all());
    }

    public function test_jobs_and_webhooks_without_a_user_still_see_everything(): void
    {
        Auth::logout();
        $this->assertSame(2, SmsCampaign::count());
        $this->assertSame(3, SmsLog::count());
    }

    public function test_campaign_details_page_shows_the_count_and_hides_other_companies(): void
    {
        $this->actAs(11);
        $view = app(CampaignController::class)->show(1);
        $this->assertSame(['total' => 2, 'sent' => 1, 'failed' => 1, 'pending' => 0], $view->getData()['stats']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(CampaignController::class)->show(2);
    }
}
