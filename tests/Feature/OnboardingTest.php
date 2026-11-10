<?php

namespace Tests\Feature;

use App\Models\CompanyKyc;
use App\Models\KycDocument;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Services\Kyc\DocumentReader;
use App\Services\ListingCatalog;
use App\Services\Listings\ListingMatcher;
use App\Support\CompanyAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Listing enquiries to the right landlord, onboarding leads, match alerts, and KYC with document reading. docs/ONBOARDING.md */
class OnboardingTest extends RentSchemaTestCase
{
    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        ListingCatalog::forget();
        $this->a = $this->company('Sunrise', [['rent_amount' => 25000]], automation: false);
        DB::table('units')->update(['status' => 'vacant']); // advertised on Open listings
        DB::table('properties')->update(['location' => 'Kilimani']);
        // A building nobody manages on scharge yet (e.g. registered by a BnB host).
        $orphan = DB::table('properties')->insertGetId(['name' => 'Palm Heights', 'location' => 'Kilimani', 'company_id' => null]);
        DB::table('units')->insert(['property_id' => $orphan, 'unit_number' => 'B2', 'rent_amount' => 30000, 'status' => 'vacant']);
    }

    private function staff(?int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Staff '.$role, 'email' => uniqid().'@example.test', 'company_id' => $companyId, 'phone' => '0722'.random_int(100000, 999999)]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }

    private function listing(string $owner): array
    {
        return collect(app(ListingCatalog::class)->items())->firstWhere('owner', $owner);
    }

    private function enquire(array $item, string $name = 'Brian Otieno', string $phone = '0711222333')
    {
        return $this->post(route('site.listings.request'), ['name' => $name, 'phone' => $phone, 'when' => 'Saturday morning',
            'listing_title' => $item['title'], 'listing_area' => $item['area'], 'listing_owner' => $item['owner'], 'listing_action' => 'Book a viewing', 'listing_slug' => $item['slug']]);
    }

    public function test_an_enquiry_on_a_customers_listing_goes_to_that_landlord_with_an_sms(): void
    {
        $admin = $this->staff($this->a['company'], 'admin');
        $this->enquire($this->listing('Sunrise Court'))->assertSessionHasNoErrors();

        $lead = CrmFollowUp::where('type', 'viewing')->sole();
        $this->assertSame((int) $this->a['company'], (int) $lead->company_id);
        $this->assertSame('landlord', $lead->details['route']);
        $this->assertSame(1, $this->sent); // SMS to the landlord's admin

        $this->actingAs($admin)->get('/enquiries')->assertOk()->assertSee('Brian Otieno')->assertSee('Saturday morning');
        $this->actingAs($admin)->post('/enquiries/'.$lead->id, ['stage' => 'viewing', 'note' => 'Coming at 10'])->assertRedirect();
        $this->assertSame(['scheduled', 'viewing'], [$lead->fresh()->status, $lead->fresh()->details['stage']]);

        $other = $this->company('Other', [['rent_amount' => 1000]], automation: false);
        $this->actingAs($this->staff($other['company'], 'admin'))->get('/enquiries')->assertOk()->assertDontSee('Brian Otieno');
    }

    public function test_an_enquiry_on_an_unmanaged_building_becomes_an_onboarding_lead_for_scharge(): void
    {
        $this->enquire($this->listing('Palm Heights'))->assertSessionHasNoErrors();
        $lead = CrmFollowUp::where('type', 'viewing')->sole();
        $this->assertNull($lead->company_id);
        $this->assertSame('onboarding', $lead->details['route']);
        $this->assertStringStartsWith('Onboarding lead · ', $lead->title);
        $this->assertSame(0, $this->sent);
    }

    public function test_match_alerts_send_only_new_listings_once_a_day(): void
    {
        $this->post(route('site.listings.alert'), ['phone' => '0733444555', 'search_kind' => 'Homes to rent', 'search_area' => 'Kilimani', 'search_max' => 28000])->assertSessionHasNoErrors();
        $matcher = app(ListingMatcher::class);

        $this->assertSame(1, $matcher->run()['sent']); // Sunrise (25,000) matches; Palm Heights (30,000) is over budget
        $this->assertSame(1, $this->sent);
        $this->assertSame(0, $matcher->run()['sent']); // same day: nothing more
        $alert = CrmFollowUp::where('type', 'listing_alert')->sole();
        $this->assertCount(1, $alert->details['sent']);

        $this->travel(25)->hours();
        $this->assertSame(0, $matcher->run()['sent']); // no new listing since
    }

    public function test_kyc_reads_documents_fills_details_flags_mismatches_and_staff_verify(): void
    {
        Storage::fake('local');
        // Document reading: a stand-in for the Claude call, returning what it would read.
        $this->app->instance(DocumentReader::class, new class extends DocumentReader {
            public function read(KycDocument $doc): KycDocument
            {
                $doc->update(['read_status' => 'read', 'extracted' => match ($doc->kind) {
                    'national_id' => ['full_name' => 'JANE WANJIKU OTIENO', 'id_number' => '12345678', 'legible' => true, 'notes' => ''],
                    'kra_pin' => ['taxpayer_name' => 'Jane Otieno', 'kra_pin' => 'A012345678Z', 'legible' => true, 'notes' => ''],
                    'title_deed' => ['owner_name' => 'Peter Kamau', 'title_number' => 'NAIROBI/BLOCK1/23', 'legible' => true, 'notes' => ''],
                    default => [],
                }]);

                return $doc->fresh();
            }
        });
        $admin = $this->staff($this->a['company'], 'admin');
        foreach (['national_id', 'kra_pin', 'title_deed'] as $kind) {
            $this->actingAs($admin)->post('/settings/verify/upload', ['kind' => $kind, 'files' => [UploadedFile::fake()->image($kind.'.jpg')]])->assertRedirect();
        }
        // "after response" jobs run at the end of each request in tests.
        $kyc = CompanyKyc::where('company_id', $this->a['company'])->sole();
        $this->assertSame(['JANE WANJIKU OTIENO', '12345678', 'A012345678Z'], [$kyc->owner_name, $kyc->owner_id_number, $kyc->kra_pin]);
        $this->assertTrue(collect($kyc->checks)->contains(fn ($c) => str_contains($c['message'], 'Peter Kamau') && $c['level'] === 'warn'));
        $this->assertFalse(collect($kyc->checks)->contains('level', 'fail'));

        $this->actingAs($admin)->post('/settings/verify', ['owner_name' => $kyc->owner_name, 'kra_pin' => 'A012345678X', 'submit' => 1])->assertStatus(422); // differs from the certificate
        $this->actingAs($admin)->post('/settings/verify', ['owner_name' => $kyc->owner_name, 'owner_id_number' => '12345678', 'kra_pin' => 'A012345678Z', 'submit' => 1])->assertRedirect();
        $this->assertSame('submitted', $kyc->fresh()->status);

        $doc = KycDocument::where('kind', 'national_id')->sole();
        $other = $this->company('Other', [['rent_amount' => 1000]], automation: false);
        $this->actingAs($this->staff($other['company'], 'admin'))->get('/settings/verify/documents/'.$doc->id)->assertNotFound();

        $staff = $this->staff(null, 'sysadmin');
        $this->actingAs($staff)->get('/admin/kyc')->assertOk()->assertSee('Sunrise');
        $this->actingAs($staff)->get('/admin/kyc/'.$kyc->id)->assertOk()->assertSee('Peter Kamau');
        $this->actingAs($staff)->post('/admin/kyc/'.$kyc->id, ['decision' => 'verified'])->assertRedirect();
        $this->assertTrue(CompanyKyc::isVerified($this->a['company']));
        $this->assertTrue($this->listing('Sunrise Court')['verified']);
        $this->assertFalse($this->listing('Palm Heights')['verified']);
    }

    public function test_without_document_reading_uploads_wait_for_typed_details(): void
    {
        Storage::fake('local');
        $admin = $this->staff($this->a['company'], 'admin');
        $this->actingAs($admin)->post('/settings/verify/upload', ['kind' => 'national_id', 'files' => [UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')]])->assertRedirect();
        $this->assertSame('off', KycDocument::sole()->read_status);
        $this->actingAs($admin)->get('/settings/verify')->assertOk()->assertSee('National ID');
        $this->assertTrue(\App\Services\Kyc\KycChecks::sameName('Jane W. Otieno', 'OTIENO JANE WANJIKU'));
        $this->assertFalse(\App\Services\Kyc\KycChecks::sameName('Jane Otieno', 'Peter Kamau'));
    }
}
