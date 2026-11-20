<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Modules\SMS\Services\KenyaSMS;
use App\Services\Integrations;
use Illuminate\Support\Facades\Cache;

/** Each client's SMS sender ID works only for that client; scharge's own (SHARETENT) is the platform default. */
class SmsSenderIdsTest extends RentSchemaTestCase
{
    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.kenyasms.sender_id' => 'SHARETENT', 'sms.kenyasms.api_key' => 'env-key', 'sms.kenyasms.sandbox' => true]);
        $this->a = $this->company('Danaff', [['rent_amount' => 10000]], automation: false);
        $this->b = $this->company('Other Ltd', [['rent_amount' => 9000]], automation: false);
        Integrations::forgetInstalled();
        Cache::flush();
    }

    public function test_a_clients_sender_id_is_used_only_for_that_client(): void
    {
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'kenyasms_sender', 'enabled' => true,
            'credentials' => ['sender_id' => 'DANAFFKENYA', 'api_key' => 'danaff-key']]);
        Cache::flush();

        $this->assertSame('DANAFFKENYA', app(KenyaSMS::class)->forCompany($this->a['company'])->senderId());
        $this->assertSame('SHARETENT', app(KenyaSMS::class)->forCompany($this->b['company'])->senderId());
        $this->assertSame('SHARETENT', app(KenyaSMS::class)->forCompany(null)->senderId());
        $this->assertSame('danaff-key', Integrations::smsSender($this->a['company'])['api_key']);
        $this->assertSame('env-key', Integrations::smsSender($this->b['company'])['api_key']);
    }

    public function test_the_platform_card_set_by_a_sysadmin_wins_over_the_env(): void
    {
        CompanyIntegration::create(['company_id' => null, 'provider' => 'kenyasms', 'enabled' => true,
            'credentials' => ['sender_id' => 'SHARETENT2', 'api_key' => 'platform-key']]);
        Cache::flush();

        $this->assertSame('SHARETENT2', Integrations::smsSender(null)['sender_id']);
        $this->assertSame('platform-key', Integrations::smsSender($this->b['company'])['api_key']);
    }

    public function test_a_managed_name_waits_for_network_approval(): void
    {
        $row = CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'kenyasms_sender', 'enabled' => true,
            'credentials' => ['sender_id' => 'DANAFFKENYA', 'setup' => 'managed', 'approval' => 'submitted']]);
        Cache::flush();
        $this->assertSame('SHARETENT', Integrations::smsSender($this->a['company'])['sender_id']);

        $row->mergeCredentials(['approval' => 'approved']);
        $row->save();
        Cache::flush();
        $this->assertSame('DANAFFKENYA', Integrations::smsSender($this->a['company'])['sender_id']);
    }
}
