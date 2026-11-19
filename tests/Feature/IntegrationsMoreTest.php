<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Connectors\Accounting;
use App\Services\Connectors\BankStatements;
use App\Services\Connectors\Etims;
use App\Services\Connectors\Hub;
use App\Services\Connectors\Webhooks;
use App\Services\InvoicePaymentService;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Integrations added in docs/INTEGRATIONS.md: webhooks out, feeds, bank import, KRA eTIMS, accounting, meters gateway. */
class IntegrationsMoreTest extends RentSchemaTestCase
{
    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['webhook_deliveries', 'integration_links', 'bank_statement_lines', 'meters', 'meter_readings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('webhook_deliveries', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('event'), $t->string('event_id')->unique(),
            $t->json('payload'), $t->string('status')->default('pending'), $t->unsignedTinyInteger('attempts')->default(0), $t->unsignedSmallInteger('response_code')->nullable(),
            $t->string('response_body')->nullable(), $t->timestamp('next_attempt_at')->nullable(), $t->timestamp('sent_at')->nullable(), $t->timestamps()]);
        Schema::create('integration_links', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('provider'), $t->string('object_type'),
            $t->unsignedBigInteger('object_id'), $t->string('remote_id')->nullable(), $t->string('status')->default('pending'), $t->unsignedTinyInteger('attempts')->default(0),
            $t->string('error', 500)->nullable(), $t->json('data')->nullable(), $t->timestamp('next_attempt_at')->nullable(), $t->timestamp('synced_at')->nullable(), $t->timestamps(),
            $t->unique(['company_id', 'provider', 'object_type', 'object_id'])]);
        Schema::create('bank_statement_lines', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->string('source')->default('upload'), $t->string('bank')->nullable(),
            $t->string('file_name')->nullable(), $t->string('line_hash'), $t->date('posted_on'), $t->string('description', 500)->nullable(), $t->string('reference')->nullable(),
            $t->decimal('amount', 14, 2), $t->unsignedBigInteger('invoice_id')->nullable(), $t->string('match_reason')->nullable(), $t->unsignedBigInteger('payment_id')->nullable(),
            $t->string('status')->default('new'), $t->unsignedBigInteger('posted_by')->nullable(), $t->timestamps(), $t->unique(['company_id', 'line_hash'])]);
        \App\Services\Integrations::forgetInstalled();
        $this->a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 8000]], settings: ['send_bill' => 'no']);
    }

    private function hooks(array $extra = []): CompanyIntegration
    {
        return CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'webhooks', 'enabled' => true,
            'credentials' => $extra + ['url' => 'https://hooks.example.test/in', 'secret' => 'shh', 'events' => '']]);
    }

    private function bill(int $tenancy = 0): Invoice
    {
        app(RentRun::class)->run($this->a['company'], $this->month('2026-11'));

        return CompanyAccess::bypass(fn () => Invoice::where('tenancy_id', $this->a['tenancies'][$tenancy])->firstOrFail());
    }

    private function pay(Invoice $invoice, float $amount): void
    {
        DB::transaction(fn () => app(InvoicePaymentService::class)->apply($invoice->fresh(), $amount,
            ['method' => 'cash', 'source' => 'web', 'transaction_reference' => null, 'external_reference' => 'R1', 'label' => 'Cash']));
    }

    public function test_webhooks_are_signed_stored_and_retried(): void
    {
        $this->hooks();
        $calls = [];
        Http::fake(['hooks.example.test/*' => function ($req) use (&$calls) {
            $calls[] = $req;

            return Http::response('nope', count($calls) === 1 ? 500 : 200);
        }]);

        $invoice = $this->bill();   // invoice.issued x2 (first fails)
        $this->assertSame(2, DB::table('webhook_deliveries')->where('event', 'invoice.issued')->count());
        $first = $calls[0];
        $body = $first->body();
        [$t, $v1] = sscanf($first->header('X-Scharge-Signature')[0], 't=%d,v1=%s');
        $this->assertSame(hash_hmac('sha256', $t.'.'.$body, 'shh'), $v1);
        $this->assertSame('invoice.issued', json_decode($body, true)['event']);
        $this->assertSame(1, DB::table('webhook_deliveries')->where('status', 'pending')->count());

        DB::table('webhook_deliveries')->update(['next_attempt_at' => now()->subMinute()]);
        $this->assertSame(1, Webhooks::retryDue());
        $this->assertSame(2, DB::table('webhook_deliveries')->where('status', 'sent')->count());

        $this->pay($invoice, 10000);   // payment.received + invoice.paid
        $this->assertEqualsCanonicalizing(['invoice.issued', 'payment.received', 'invoice.paid'], DB::table('webhook_deliveries')->distinct()->pluck('event')->all());
        $payment = json_decode(DB::table('webhook_deliveries')->where('event', 'payment.received')->value('payload'), true)['data'];
        $this->assertSame(10000.0, (float) $payment['amount']);
        $issued = json_decode(DB::table('webhook_deliveries')->where('event', 'invoice.issued')->value('payload'), true)['data'];
        $this->assertNotEmpty($issued['lines']);   // sent after the bill's lines were saved
        $this->assertSame('A1', $payment['unit']['number']);
    }

    public function test_webhooks_send_only_the_chosen_events_and_test_pings(): void
    {
        $row = $this->hooks(['events' => 'payment.received']);
        Http::fake(['hooks.example.test/*' => Http::response('ok')]);
        $this->bill();
        $this->assertSame(0, DB::table('webhook_deliveries')->count());
        [$ok, $message] = Webhooks::test($row);
        $this->assertTrue($ok, $message);
        Http::assertSent(fn ($r) => $r->header('X-Scharge-Event')[0] === 'ping');
    }

    public function test_feeds_need_the_token_and_give_csv_and_calendar(): void
    {
        $this->bill();
        $user = User::create(['name' => 'Admin', 'email' => 'a@example.test', 'company_id' => $this->a['company']]);
        $row = CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'data_feeds', 'enabled' => true, 'credentials' => ['months' => '12', 'feed_token' => str_repeat('t', 40)]]);
        $base = '/feeds/'.$this->a['company'].'/'.str_repeat('t', 40);

        $this->get($base.'/arrears.csv')->assertOk()->assertSee('INV-', false)->assertSee('Tenant Sunrise 1');
        $this->get($base.'/tenants.csv')->assertOk()->assertSee('Tenant Sunrise 2');
        $this->get($base.'/calendar.ics')->assertOk()->assertSee('BEGIN:VCALENDAR', false);
        $this->get('/feeds/'.$this->a['company'].'/'.str_repeat('x', 40).'/arrears.csv')->assertNotFound();
        $row->update(['enabled' => false]);
        Cache::flush();
        $this->get($base.'/arrears.csv')->assertNotFound();
    }

    public function test_bank_statement_csv_is_matched_by_bill_number_unit_and_posted_once(): void
    {
        $invoice = $this->bill();
        $second = CompanyAccess::bypass(fn () => Invoice::where('tenancy_id', $this->a['tenancies'][1])->firstOrFail());
        $csv = tempnam(sys_get_temp_dir(), 'stmt');
        file_put_contents($csv, "Equity Bank statement\nAccount,0123\n\nTransaction Date,Narrative,Debit,Credit,Balance\n"
            ."05/11/2026,CASH DEP INV-".str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)." JANE,,10000.00,50000\n"
            ."06/11/2026,EFT A2 RENT NOV,,\"8,000.00\",58000\n"
            ."07/11/2026,ATM WITHDRAWAL,2000.00,,56000\n"
            ."08/11/2026,MPESA C2B UNKNOWN PERSON,,1234.00,57234\n");
        $lines = BankStatements::parse($csv, 'statement.csv');
        $this->assertCount(3, $lines);   // money in only
        $this->assertSame('2026-11-05', $lines[0]['posted_on']);

        $r = BankStatements::import($this->a['company'], $lines, 'upload', 'equity', 'statement.csv');
        $this->assertSame(['added' => 3, 'posted' => 0, 'suggested' => 2], $r);
        $this->assertSame($invoice->id, (int) DB::table('bank_statement_lines')->where('amount', 10000)->value('invoice_id'));
        $this->assertSame($second->id, (int) DB::table('bank_statement_lines')->where('amount', 8000)->value('invoice_id'));
        $this->assertSame(0, BankStatements::import($this->a['company'], $lines, 'upload', 'equity', 'statement.csv')['added']);   // same file twice

        $line = DB::table('bank_statement_lines')->where('amount', 10000)->first();
        $this->assertTrue(BankStatements::post($this->a['company'], $line->id, $invoice->id, null));
        $this->assertFalse(BankStatements::post($this->a['company'], $line->id, $invoice->id, null));
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_sure_matches_are_recorded_at_once_when_the_company_chose_it(): void
    {
        $invoice = $this->bill();
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'bank_import', 'enabled' => true, 'credentials' => ['auto_post' => 'post']]);
        $r = BankStatements::import($this->a['company'], [['posted_on' => '2026-11-05', 'amount' => 10000.0, 'description' => 'RENT A1', 'reference' => 'FT1']], 'upload', null, null);
        $this->assertSame(1, $r['posted']);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_etims_signs_a_paid_bill_once_with_gap_free_numbers(): void
    {
        $row = CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'etims', 'enabled' => true, 'credentials' => [
            'environment' => 'sandbox', 'tin' => 'P051234567X', 'bhf_id' => '00', 'device_serial' => 'DEV1', 'when' => 'paid', 'tax_type' => 'A']]);
        $sales = [];
        Http::fake([
            '*/selectInitOsdcInfo' => Http::response(['resultCd' => '000', 'data' => ['info' => ['cmcKey' => 'CMC']]]),
            '*/saveItem' => Http::response(['resultCd' => '000']),
            '*/saveTrnsSalesOsdc' => function ($req) use (&$sales) {
                $sales[] = $req->data();

                return Http::response(['resultCd' => '000', 'data' => ['rcptNo' => count($sales), 'rcptSign' => 'SIGN'.count($sales), 'intrlData' => 'X']]);
            },
        ]);
        [$ok, $msg] = Etims::test($row);
        $this->assertTrue($ok, $msg);
        $this->assertSame('CMC', $row->fresh()->get('cmc_key'));
        Cache::flush();

        $invoice = $this->bill();
        $this->assertCount(0, $sales);   // only when paid
        $this->pay($invoice, 10000);
        $this->assertCount(1, $sales);
        $this->assertSame(1, $sales[0]['invcNo']);
        $this->assertSame(10000, (int) $sales[0]['totAmt']);
        $this->assertSame('A', $sales[0]['itemList'][0]['taxTyCd']);
        $this->assertSame('SIGN1', Etims::receipt($invoice->id)['signature']);

        $second = CompanyAccess::bypass(fn () => Invoice::where('tenancy_id', $this->a['tenancies'][1])->firstOrFail());
        $this->pay($second, 8000);
        $this->assertSame(2, $sales[1]['invcNo']);
        Etims::queue($this->a['company'], $invoice->id, 'paid');   // already signed
        $this->assertCount(2, $sales);
    }

    public function test_quickbooks_gets_customer_invoice_and_payment(): void
    {
        CompanyIntegration::create(['company_id' => null, 'provider' => 'quickbooks_app', 'enabled' => true, 'credentials' => ['environment' => 'sandbox', 'client_id' => 'cid', 'client_secret' => 'cs']]);
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'quickbooks', 'enabled' => true, 'credentials' => [
            'access_token' => 'AT', 'refresh_token' => 'RT', 'expires_at' => time() + 3600, 'realm_id' => '9130', 'connected_at' => '2026-01-01']]);
        Http::fake([
            'sandbox-quickbooks.api.intuit.com/v3/company/9130/query*' => fn ($r) => Http::response(['QueryResponse' => str_contains(urldecode($r->url()), 'from Item') ? ['Item' => [['Id' => '7']]] : []]),
            'sandbox-quickbooks.api.intuit.com/v3/company/9130/customer*' => Http::response(['Customer' => ['Id' => 'C1']]),
            'sandbox-quickbooks.api.intuit.com/v3/company/9130/invoice*' => Http::response(['Invoice' => ['Id' => 'I1']]),
            'sandbox-quickbooks.api.intuit.com/v3/company/9130/payment*' => Http::response(['Payment' => ['Id' => 'P1']]),
        ]);
        $invoice = $this->bill();
        $this->pay($invoice, 10000);

        $links = DB::table('integration_links')->where('provider', 'quickbooks')->get()->keyBy('object_type');
        $this->assertSame('C1', $links['customer']->remote_id);
        $this->assertSame('synced', $links['invoice']->status);
        $this->assertSame('P1', $links['payment']->remote_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/payment') && $r['Line'][0]['LinkedTxn'][0]['TxnId'] === 'I1');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/invoice') && $r['Line'][0]['SalesItemLineDetail']['ItemRef']['value'] === '7');
    }

    public function test_accounting_failures_are_retried_and_old_records_skipped(): void
    {
        CompanyIntegration::create(['company_id' => null, 'provider' => 'xero_app', 'enabled' => true, 'credentials' => ['client_id' => 'cid', 'client_secret' => 'cs']]);
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'xero', 'enabled' => true, 'credentials' => [
            'access_token' => 'AT', 'refresh_token' => 'RT', 'expires_at' => time() + 3600, 'tenant_id' => 'T', 'connected_at' => '2026-01-01']]);
        $fail = true;
        Http::fake(['api.xero.com/api.xro/2.0/Contacts*' => fn ($r) => $r->method() === 'GET' ? Http::response(['Contacts' => []]) : Http::response(['Contacts' => [['ContactID' => 'X1']]]),
            'api.xero.com/api.xro/2.0/Invoices' => function () use (&$fail) {
                return $fail ? Http::response(['Message' => 'down'], 503) : Http::response(['Invoices' => [['InvoiceID' => 'XI']]]);
            }]);
        $this->bill();
        $link = DB::table('integration_links')->where('provider', 'xero')->where('object_type', 'invoice')->first();
        $this->assertSame('pending', $link->status);
        $this->assertStringContainsString('503', $link->error);

        $fail = false;
        DB::table('integration_links')->update(['next_attempt_at' => now()->subMinute()]);
        Accounting::retryDue();
        $this->assertSame('XI', DB::table('integration_links')->where('id', $link->id)->value('remote_id'));
    }

    public function test_meter_gateway_posts_readings_by_serial_with_the_company_key(): void
    {
        Schema::create('meters', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('utility'), $t->string('kind')->default('manual'), $t->string('billing')->default('consumption'), $t->string('serial_number')->nullable(), $t->string('make')->nullable(), $t->string('model')->nullable(),
            $t->string('unit_of_measure')->nullable(), $t->float('multiplier')->default(1), $t->float('rate_per_unit')->nullable(), $t->float('initial_reading')->default(0), $t->float('last_reading')->nullable(),
            $t->timestamp('last_read_at')->nullable(), $t->string('status')->default('active'), $t->text('notes')->nullable(), $t->string('device_token_prefix')->nullable(), $t->string('device_token_hash')->nullable(), $t->timestamps()]);
        Schema::create('meter_readings', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('meter_id'), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id')->nullable(),
            $t->string('utility')->nullable(), $t->float('reading'), $t->float('previous_reading')->nullable(), $t->float('consumption')->nullable(), $t->float('rate_applied')->nullable(), $t->float('charge')->nullable(),
            $t->timestamp('read_at')->nullable(), $t->string('source')->nullable(), $t->unsignedBigInteger('recorded_by')->nullable(), $t->string('photo_path')->nullable(), $t->string('status')->default('accepted'),
            $t->string('flag_reason')->nullable(), $t->string('client_ref')->nullable(), $t->unsignedBigInteger('water_reading_id')->nullable(), $t->string('note')->nullable(), $t->json('raw')->nullable(), $t->timestamps()]);
        $meter = \App\Models\Meter::create(['company_id' => $this->a['company'], 'utility' => 'water', 'kind' => 'smart', 'billing' => 'consumption',
            'serial_number' => 'SN1', 'unit_of_measure' => 'm3', 'multiplier' => 1, 'initial_reading' => 10, 'status' => 'active']);
        $key = 'mgw_'.$this->a['company'].'_'.str_repeat('a', 32);
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'smart_meters', 'enabled' => true, 'credentials' => ['gateway_key' => $key]]);

        $this->postJson('/api/v1/meter-readings', ['serial' => 'SN1', 'reading' => 15, 'client_ref' => 'm1'], ['Authorization' => 'Bearer '.$key])
            ->assertOk()->assertJson(['accepted' => 1]);
        $this->postJson('/api/v1/meter-readings', ['readings' => [['serial' => 'SN1', 'reading' => 15, 'client_ref' => 'm1'], ['serial' => 'NOPE', 'reading' => 1]]], ['Authorization' => 'Bearer '.$key])
            ->assertOk()->assertJson(['accepted' => 1, 'unknown' => 1]);
        $this->assertSame(1, \App\Models\MeterReading::where('meter_id', $meter->id)->count());
        $this->postJson('/api/v1/meter-readings', ['serial' => 'SN1', 'reading' => 16], ['Authorization' => 'Bearer mgw_'.$this->a['company'].'_'.str_repeat('b', 32)])->assertUnauthorized();
    }

    public function test_the_page_lists_the_new_cards_and_the_bank_import_page_renders(): void
    {
        $this->bareLayout();
        $this->bill();
        $user = User::create(['name' => 'Owner', 'email' => 'o@example.test', 'company_id' => $this->a['company']]);
        $this->withoutMiddleware([\App\Http\Middleware\CheckAccess::class, \Spatie\Permission\Middleware\RoleMiddleware::class]);
        CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'kenyasms_sender', 'enabled' => true, 'credentials' => ['setup' => 'managed', 'sender_id' => 'SUNRISE', 'approval' => 'requested']]);

        $keys = collect($this->actingAs($user)->getJson('/integrations')->assertOk()->json('providers'))->keyBy('key');
        foreach (['webhooks', 'company_email', 'data_feeds', 'bank_import', 'jenga', 'etims', 'quickbooks', 'xero', 'zoho_books', 'smart_meters'] as $k) {
            $this->assertTrue($keys->has($k), $k);
        }
        $this->assertFalse($keys->has('quickbooks_app'));   // platform only
        $this->assertNotContains('approval', collect($keys['kenyasms_sender']['fields'])->pluck('key'));   // staff only
        $this->assertSame('Requested: scharge is on it', $keys['kenyasms_sender']['extras'][0]['value']);
        $this->assertTrue($keys['quickbooks']['oauth']);

        // Saving as a company admin cannot approve its own sender name, and feeds get links.
        $this->actingAs($user)->putJson('/integrations/kenyasms_sender', ['values' => ['setup' => 'managed', 'sender_id' => 'SUNRISE', 'approval' => 'approved']])->assertOk();
        $this->assertSame('requested', CompanyIntegration::where('provider', 'kenyasms_sender')->first()->get('approval'));
        $feeds = $this->actingAs($user)->putJson('/integrations/data_feeds', ['values' => ['months' => '12']])->assertOk()->json('provider.extras');
        $this->assertStringContainsString('/feeds/'.$this->a['company'].'/', $feeds[0]['value']);
        $meters = $this->actingAs($user)->putJson('/integrations/smart_meters', ['values' => ['vendor' => 'Stron']])->assertOk()->json('reveal.value');
        $this->assertMatchesRegularExpression('/^mgw_'.$this->a['company'].'_\w{32}$/', $meters);

        $this->actingAs($user)->get('/integrations')->assertOk()->assertSee('integrationsPage', false);
        $this->actingAs($user)->get('/bank-import')->assertOk()->assertSee('Upload statement');
    }

    public function test_sender_id_goes_to_the_networks_on_the_signed_letter_and_switches_on_when_approved(): void
    {
        $this->bareLayout();
        config(['mail.default' => 'testmail', 'mail.mailers.testmail' => ['transport' => 'array']]);
        $user = User::create(['name' => 'Owner', 'email' => 'o@example.test', 'company_id' => $this->a['company']]);
        $this->withoutMiddleware([\App\Http\Middleware\CheckAccess::class]);
        CompanyIntegration::create(['company_id' => null, 'provider' => 'kenyasms', 'enabled' => true, 'credentials' => ['api_key' => 'test', 'sender_id' => 'SCHARGE', 'mode' => 'live', 'sender_requests_to' => 'desk@sms.example.test']]);
        \App\Models\CompanyKyc::create(['company_id' => $this->a['company'], 'status' => 'verified', 'business_name' => 'Sunrise Ltd', 'kra_pin' => 'P051234567X']);

        $this->actingAs($user)->putJson('/integrations/kenyasms_sender', ['values' => ['setup' => 'managed', 'sender_id' => 'sunrise']])->assertOk();
        $row = fn () => CompanyIntegration::where('provider', 'kenyasms_sender')->first();
        $this->assertSame('requested', $row()->get('approval'));
        $this->assertSame('SUNRISE', $row()->get('sender_id'));
        $this->assertNotSame('SUNRISE', \App\Services\Integrations::smsSender($this->a['company'])['sender_id']);   // not until approved

        $pdf = $this->actingAs($user)->get('/integrations/kenyasms_sender/template/letter')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $file = \Illuminate\Http\UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf');
        $this->actingAs($user)->post('/integrations/kenyasms_sender/file/letter', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'Sent to the networks'));
        $this->assertSame('submitted', $row()->get('approval'));
        $sent = app('mail.manager')->mailer('testmail')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('desk@sms.example.test', $sent[0]->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertCount(1, $sent[0]->getOriginalMessage()->getAttachments());

        config(['sms.kenyasms.base_url' => 'https://senders.example.test/api/v1']);
        Http::fake(['senders.example.test/api/v1/account/sender-ids' => Http::response(['data' => [['sender_id' => 'SUNRISE', 'status' => 'active']]])]);
        $this->assertSame(1, \App\Services\Connectors\SenderIds::sync());
        $this->assertSame('approved', $row()->get('approval'));
        Cache::flush();
        $this->assertSame('SUNRISE', \App\Services\Integrations::smsSender($this->a['company'])['sender_id']);

        $steps = collect(\App\Services\Connectors\GoLive::steps($this->a['company']))->keyBy('label');
        $this->assertTrue($steps['SMS under your name']['done']);
        $this->assertTrue($steps['Verify your business']['done']);
        $this->assertFalse($steps['Get paid']['done']);
        $this->actingAs($user)->get('/integrations')->assertOk()->assertSee('Go live under your own name');
    }
}
