<?php

namespace Tests\Feature;

use App\Models\CompanyAgreement;
use App\Models\CompanyBrand;
use App\Models\CompanyPage;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Services\ListingCatalog;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\DB;

/** Hosted company website: template, drafts, scharge approval, own address, rentals, contact, DPA. docs/COMPANY_WEBSITE.md */
class CompanyWebsiteTest extends RentSchemaTestCase
{
    private array $a;

    private string $site = 'http://portal.danaff.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        ListingCatalog::forget();
        $this->a = $this->company('Danaff', [['rent_amount' => 25000]], automation: false);
        DB::table('units')->update(['status' => 'vacant']);
        $b = $this->company('Other', [['rent_amount' => 9000]], automation: false);
        DB::table('units')->where('property_id', DB::table('properties')->where('company_id', $b['company'])->value('id'))->update(['status' => 'vacant']);
        CompanyBrand::create(['company_id' => $this->a['company'], 'display_name' => 'Danaff Homes', 'domain' => 'portal.danaff.test', 'primary_color' => '#0F766E']);
    }

    private function user(?int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U '.$role, 'email' => uniqid().'@example.test', 'company_id' => $companyId, 'phone' => '0722'.random_int(100000, 999999)]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }

    public function test_company_edits_scharge_approves_then_it_goes_live_on_its_address(): void
    {
        $admin = $this->user($this->a['company'], 'admin');
        $this->actingAs($admin)->post('/settings/website/start')->assertRedirect();
        $this->assertSame(4, CompanyPage::count());
        $home = CompanyPage::where('slug', '')->sole();

        $this->actingAs($admin)->post('/settings/website/pages/'.$home->id, ['title' => 'Home', 'hero_title' => 'Homes by Danaff', 'hero_text' => 'Kilimani and Kileleshwa',
            'body' => "## Hello\n\n<script>alert(1)</script>", 'show_in_menu' => 1, 'action' => 'submit'])->assertRedirect();
        $this->actingAs($admin)->post('/settings/website/pages/'.$home->id, ['title' => 'Home', 'action' => 'publish'])->assertForbidden(); // only scharge publishes
        $this->assertSame('pending', $home->fresh()->status);
        $this->actingAs($admin)->get('/settings/website/pages/'.$home->id.'/preview')->assertOk()->assertSee('Homes by Danaff')->assertSee('Preview of your changes');

        auth()->logout();
        $this->get($this->site.'/')->assertRedirect($this->site.'/login'); // not live yet

        $staff = $this->user(null, 'sysadmin');
        $this->actingAs($staff)->get('/admin/website-approvals')->assertOk()->assertSee('Danaff');
        $this->actingAs($staff)->post('/admin/website-approvals/'.$home->id, ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('published', $home->fresh()->status);

        auth()->logout();
        $page = $this->get($this->site.'/')->assertOk()->assertSee('Homes by Danaff')->assertSee('Tenant & owner login', false);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->getContent()); // Markdown: raw HTML is escaped
        $this->get($this->site.'/about')->assertNotFound(); // still a draft
        $this->get('/about')->assertNotFound(); // scharge's own address has no such page
    }

    public function test_rentals_lists_only_the_companys_units_and_requests_reach_it(): void
    {
        $rentals = CompanyPage::create(['company_id' => $this->a['company'], 'slug' => 'rentals', 'template' => 'rentals', 'title' => 'Homes to rent', 'status' => 'published', 'published_at' => now()]);
        $html = $this->get($this->site.'/rentals')->assertOk()->getContent();
        $this->assertStringContainsString('KES 25,000', $html);
        $this->assertStringNotContainsString('KES 9,000', $html);
        $this->assertStringContainsString('name="listing_slug"', $html);
    }

    public function test_contact_form_becomes_an_enquiry_with_an_sms_to_the_admins(): void
    {
        $this->user($this->a['company'], 'admin');
        CompanyPage::create(['company_id' => $this->a['company'], 'slug' => 'contact', 'template' => 'contact', 'title' => 'Contact', 'status' => 'published', 'published_at' => now()]);
        $this->get($this->site.'/contact')->assertOk();
        $this->post($this->site.'/site/contact', ['name' => 'Akinyi Odhiambo', 'phone' => '0711000111', 'message' => 'Do you have a 2-bedroom?'])->assertRedirect();
        $lead = CrmFollowUp::sole();
        $this->assertSame((int) $this->a['company'], (int) $lead->company_id);
        $this->assertSame(1, $this->sent);
        $this->post('http://localhost/site/contact', ['name' => 'X', 'phone' => '0711000111'])->assertNotFound(); // only on a company's address
    }

    public function test_reserved_addresses_and_another_companys_pages_are_refused(): void
    {
        $admin = $this->user($this->a['company'], 'admin');
        $this->actingAs($admin)->post('/settings/website/pages', ['title' => 'Login', 'slug' => 'login'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/settings/website/pages', ['title' => 'Our buildings', 'slug' => 'our-buildings'])->assertRedirect();
        $page = CompanyPage::where('slug', 'our-buildings')->sole();

        $b = DB::table('companies')->where('name', 'Other')->value('id');
        $this->actingAs($this->user($b, 'admin'))->get('/settings/website/pages/'.$page->id)->assertNotFound();
    }

    public function test_the_admin_accepts_the_data_processing_agreement(): void
    {
        $admin = $this->user($this->a['company'], 'admin');
        $this->actingAs($admin)->get('/settings/data-agreement')->assertOk()->assertSee('Data Processing Agreement')->assertSee('Danaff');
        $this->actingAs($admin)->post('/settings/data-agreement', ['name' => 'Jane Otieno', 'title' => 'Director'])->assertSessionHasErrors('agree');
        $this->actingAs($admin)->post('/settings/data-agreement', ['name' => 'Jane Otieno', 'title' => 'Director', 'agree' => 1])->assertRedirect();
        $this->assertSame('Jane Otieno', CompanyAgreement::dpaAccepted($this->a['company'])?->accepted_name);

        $staff = $this->user(null, 'sysadmin');
        $this->actingAs($staff)->post('/settings/data-agreement?company='.$this->a['company'], ['name' => 'X', 'title' => 'Y', 'agree' => 1])->assertForbidden();
    }
}
