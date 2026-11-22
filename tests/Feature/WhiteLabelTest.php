<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\CompanyBrand;
use App\Models\CompanyIntegration;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Data\CompanyBackup;
use App\Services\Data\CompanyExport;
use App\Services\Rent\RentRun;
use App\Support\Brand;
use App\Support\CompanyAccess;
use App\Support\InvoiceLink;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** White label: brand per company and own address, full data export, nightly copy, read API. docs/WHITE_LABEL.md */
class WhiteLabelTest extends RentSchemaTestCase
{
    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        $this->a = $this->company('Danaff', [['rent_amount' => 10000], ['rent_amount' => 12000]], automation: false);
        $this->b = $this->company('Other', [['rent_amount' => 50000]], automation: false);
        app(RentRun::class)->run($this->a['company'], $this->month('2026-12'), notify: false);
        app(RentRun::class)->run($this->b['company'], $this->month('2026-12'), notify: false);
        CompanyBrand::create(['company_id' => $this->a['company'], 'display_name' => 'Danaff Homes', 'domain' => 'portal.danaff.test', 'primary_color' => '#0F766E', 'show_powered_by' => false]);
    }

    private function staff(int $companyId, ?string $role = null): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Staff', 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        if ($role) {
            $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        }
        CompanyAccess::flush();

        return User::find($id);
    }

    public function test_the_brand_follows_the_address_and_the_company(): void
    {
        $this->get('http://portal.danaff.test/')->assertRedirect('http://portal.danaff.test/login');
        $this->assertSame('Danaff Homes', Brand::forDomain('portal.danaff.test')?->display_name);
        $this->assertNull(Brand::forDomain('scharge.sharet.africa'));
        $this->assertSame((int) $this->a['company'], (int) Brand::forCompany($this->a['company'])->company_id);
        $this->assertNull(Brand::forCompany($this->b['company']));
        $css = Brand::css(Brand::forCompany($this->a['company']));
        $this->assertStringContainsString('.bg-brand-500,.hover\:bg-brand-500:hover,.focus\:bg-brand-500:focus{background-color:#0f766e}', $css);
    }

    public function test_scharge_staff_linked_to_a_company_still_see_scharge(): void
    {
        $this->actingAs($this->staff($this->a['company'], 'sysadmin'));
        $this->assertNull(Brand::current());
        $this->actingAs($this->staff($this->a['company'], 'admin'));
        $this->assertSame('Danaff Homes', Brand::current()?->display_name);
    }

    public function test_only_the_companys_people_stay_signed_in_on_its_address(): void
    {
        $other = $this->staff($this->b['company'], 'admin');
        $this->actingAs($other)->get('http://portal.danaff.test/rent')->assertRedirect('http://portal.danaff.test/login');
        $this->assertGuest();

        $own = $this->staff($this->a['company'], 'admin');
        $this->actingAs($own)->get('http://portal.danaff.test/rent')->assertOk();
    }

    public function test_bill_links_use_the_companys_own_address(): void
    {
        $danaff = Invoice::where('tenancy_id', $this->a['tenancies'][0])->first();
        $other = Invoice::where('tenancy_id', $this->b['tenancies'][0])->first();
        $this->assertStringStartsWith('https://portal.danaff.test/public/invoice/'.$danaff->id.'?t=', InvoiceLink::url($danaff));
        $this->assertStringStartsWith('https://portal.danaff.test/public/invoice/', InvoiceLink::url($danaff->id));
        $this->assertStringNotContainsString('danaff', InvoiceLink::url($other));
    }

    public function test_the_page_lists_own_sender_email_mpesa_and_payouts_with_this_companys_status(): void
    {
        $row = fn (int $company, string $provider, array $credentials) => tap(new CompanyIntegration(['company_id' => $company, 'provider' => $provider, 'enabled' => true]),
            fn ($r) => $r->mergeCredentials($credentials))->save();
        $row($this->a['company'], 'kenyasms_sender', ['setup' => 'managed', 'sender_id' => 'DANAFF', 'approval' => 'submitted']);
        $row($this->a['company'], 'company_email', ['from_address' => 'accounts@danaff.test']);
        $row($this->b['company'], 'mpesa_daraja', ['shortcode' => '555111', 'kind' => 'paybill']);   // another company's paybill

        $this->actingAs($this->staff($this->a['company'], 'admin'))->get('/settings/white-label')->assertOk()
            ->assertSee('Your own SMS sender ID')->assertSee('DANAFF: waiting for the networks to approve it')
            ->assertSee('Email from your own address')->assertSee('Sending from accounts@danaff.test')
            ->assertSee('Your own M-Pesa paybill or till (Daraja)')->assertSee('Your own payment rails')
            ->assertDontSee('555111');
    }

    public function test_admin_edits_the_brand_but_only_scharge_sets_the_address(): void
    {
        $admin = $this->staff($this->a['company'], 'admin');
        $this->actingAs($admin)->get('/settings/white-label')->assertOk()->assertSee('Danaff Homes')->assertSee('Download all data');
        $this->actingAs($admin)->post('/settings/white-label', ['display_name' => 'Danaff', 'primary_color' => '#112233', 'domain' => 'evil.test', 'show_powered_by' => 1])->assertRedirect();
        $brand = CompanyBrand::where('company_id', $this->a['company'])->sole();
        $this->assertSame(['Danaff', '#112233', 'portal.danaff.test', false], [$brand->display_name, $brand->primary_color, $brand->domain, $brand->show_powered_by]);

        $this->actingAs($this->staff($this->a['company'], 'accountant'))->get('/settings/white-label')->assertForbidden();
    }

    public function test_export_has_the_companys_whole_history_and_nothing_else(): void
    {
        DB::table('payments')->insert(['invoice_id' => Invoice::where('tenancy_id', $this->a['tenancies'][0])->value('id'), 'amount' => 500, 'status' => 'completed']);
        $file = app(CompanyExport::class)->zip($this->a['company']);
        $zip = new \ZipArchive();
        $zip->open($file);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame([2, 2, 2, 1], [$manifest['rows']['tenancies'], $manifest['rows']['invoices'], $manifest['rows']['units'], $manifest['rows']['payments']]);
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= $zip->getFromIndex($i);
        }
        $this->assertStringNotContainsString('Other Court', $all);
        $this->assertStringNotContainsString('password', $all);
        $zip->close();
        unlink($file);

        $this->actingAs($this->staff($this->a['company'], 'admin'))->get('/settings/white-label/export')->assertOk()->assertDownload();
    }

    public function test_nightly_copy_writes_the_zip_and_removes_old_copies(): void
    {
        $fake = Storage::fake('backup-target');
        $fake->put('danaff-'.$this->a['company'].'-20200101-023000.zip', 'old');
        $row = CompanyIntegration::create(['company_id' => $this->a['company'], 'provider' => 'data_backup', 'enabled' => true, 'credentials' => ['method' => 'sftp', 'keep_days' => '30']]);
        $backup = new class(app(CompanyExport::class), $fake) extends CompanyBackup {
            public function __construct(CompanyExport $e, private Filesystem $target)
            {
                parent::__construct($e);
            }

            protected function disk(\App\Models\CompanyIntegration $row): Filesystem
            {
                return $this->target;
            }
        };

        [$ok, $message] = $backup->run($row);

        $this->assertTrue($ok, $message);
        $this->assertStringContainsString('Removed 1 older copy', $message);
        $this->assertCount(1, $fake->files());
        $this->assertStringStartsWith('danaff-', $fake->files()[0]);
        $this->assertTrue($row->fresh()->last_test_ok);
    }

    public function test_read_api_pages_through_only_the_keys_company(): void
    {
        $key = new ApiKey(['company_id' => $this->a['company'], 'name' => 'Danaff ERP', 'environment' => 'live', 'scopes' => ['read'], 'enabled' => true]);
        $plain = $key->issue();
        $key->save();

        $page = $this->withToken($plain)->getJson('/api/v1/invoices?limit=1')->assertOk()->json();
        $this->assertTrue($page['has_more']);
        $this->assertCount(1, $page['data']);
        $next = $this->withToken($plain)->getJson('/api/v1/invoices?limit=1&after='.$page['next_after'])->assertOk()->json();
        $this->assertFalse($next['has_more']);
        $ids = array_merge(array_column($page['data'], 'tenancy_id'), array_column($next['data'], 'tenancy_id'));
        $this->assertEqualsCanonicalizing(array_map('intval', $this->a['tenancies']), array_map('intval', $ids));

        $people = $this->withToken($plain)->getJson('/api/v1/people')->assertOk()->json('data');
        $this->assertArrayNotHasKey('password', $people[0]);
        $this->withToken($plain)->getJson('/api/v1/secrets')->assertNotFound();
        $this->flushHeaders()->getJson('/api/v1/invoices')->assertUnauthorized();
    }
}
