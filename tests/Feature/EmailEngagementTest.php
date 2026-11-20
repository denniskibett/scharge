<?php

namespace Tests\Feature;

use App\Modules\CRM\Support\EmailEngagement;
use App\Services\Analytics\GeoIp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Every open and click of a cold email: when, roughly where and on what device (App\Modules\CRM\Support\EmailEngagement). */
class EmailEngagementTest extends RentSchemaTestCase
{
    private const TOKEN = 'AbCdEfGhIjKlMnOpQrStUvWx';

    private int $emailId;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('crm_email_events', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('follow_up_id'), $t->unsignedBigInteger('company_id')->nullable(),
            $t->string('kind'), $t->timestamp('occurred_at'), $t->string('via')->default('direct'), $t->string('ip_prefix')->nullable(), $t->string('country')->nullable(),
            $t->string('region')->nullable(), $t->string('city')->nullable(), $t->string('device')->nullable(), $t->string('os')->nullable(), $t->string('client')->nullable(),
            $t->timestamp('created_at')->nullable()]);
        $this->app->instance(GeoIp::class, new class extends GeoIp {
            public function locate(?string $ip, ?string $cdnCountry = null): array
            {
                return $ip === '41.90.1.7'
                    ? ['country_code' => 'KE', 'country' => 'Kenya', 'region' => 'Nairobi County', 'city' => 'Nairobi']
                    : ['country_code' => null, 'country' => null, 'region' => null, 'city' => null];
            }
        });
        $companyId = DB::table('companies')->insertGetId(['name' => 'Grey Oak Ltd', 'lifecycle' => 'prospect']);
        $this->emailId = DB::table('crm_follow_ups')->insertGetId(['company_id' => $companyId, 'type' => 'email', 'status' => 'done',
            'title' => 'Cold email', 'details' => json_encode(['track' => self::TOKEN, 'delivery' => 'sent']), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function open(string $ua, string $ip): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $ua])
            ->get('/e/'.self::TOKEN.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
    }

    public function test_an_open_on_an_android_phone_records_time_town_and_device_with_a_shortened_ip(): void
    {
        $this->open('Mozilla/5.0 (Linux; Android 13; SM-A145F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36', '41.90.1.7');

        $e = DB::table('crm_email_events')->first();
        $this->assertSame('open', $e->kind);
        $this->assertSame('direct', $e->via);
        $this->assertSame('Nairobi', $e->city);
        $this->assertSame('KE', $e->country);
        $this->assertSame('phone', $e->device);
        $this->assertSame('Android', $e->os);
        $this->assertSame('Chrome', $e->client);
        $this->assertSame('41.90.1.0', $e->ip_prefix);   // never the full address
        $this->assertNotNull(json_decode(DB::table('crm_follow_ups')->value('details'), true)['opened_at'] ?? null);
    }

    public function test_gmail_and_apple_proxies_are_marked_and_carry_no_place(): void
    {
        $this->open('Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)', '66.249.84.1');
        $this->open('Mozilla/5.0', '17.58.100.4');

        $rows = DB::table('crm_email_events')->orderBy('id')->get();
        $this->assertSame(['gmail', 'apple'], $rows->pluck('via')->all());
        $this->assertNull($rows[0]->city);
        $this->assertNull($rows[1]->device);

        $sum = EmailEngagement::summary($this->emailId);
        $this->assertSame(2, $sum['opens']);
        $this->assertNull($sum['place']);
        $this->assertTrue($sum['hidden']);
    }

    public function test_clicking_the_demo_link_records_a_click(): void
    {
        $email = \App\Modules\CRM\Models\CrmFollowUp::find($this->emailId);
        EmailEngagement::record($email, 'click', request()->create('/demo/X', 'GET', [], [], [], ['REMOTE_ADDR' => '41.90.1.7', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36']));

        $e = DB::table('crm_email_events')->first();
        $this->assertSame('click', $e->kind);
        $this->assertSame('computer', $e->device);
        $this->assertSame('Windows', $e->os);
        $this->assertSame(1, EmailEngagement::summary(null, $e->company_id)['clicks']);
    }

    public function test_details_older_than_twelve_months_are_deleted(): void
    {
        DB::table('crm_email_events')->insert([
            ['follow_up_id' => $this->emailId, 'kind' => 'open', 'occurred_at' => now()->subMonths(13), 'via' => 'direct'],
            ['follow_up_id' => $this->emailId, 'kind' => 'open', 'occurred_at' => now()->subMonths(2), 'via' => 'direct'],
        ]);

        $this->assertSame(1, EmailEngagement::prune());
        $this->assertSame(1, DB::table('crm_email_events')->count());
    }

    public function test_reading_times_give_a_best_time_to_reach_them(): void
    {
        foreach (['2026-11-17 05:10:00', '2026-11-18 05:40:00', '2026-11-19 05:05:00'] as $utc) {   // 08:xx in Nairobi, Tue-Thu
            DB::table('crm_email_events')->insert(['follow_up_id' => $this->emailId, 'kind' => 'open', 'occurred_at' => $utc, 'via' => 'gmail']);
        }

        $this->assertSame('Weekdays around 8am', EmailEngagement::summary($this->emailId)['best']);
    }
}
