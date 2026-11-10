<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Tenancy;
use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\InvoiceLink;
use Illuminate\Support\Facades\DB;

/**
 * One landlord company never sees another's invoices or tenancies (CompanyAccess scope), and public bill links need
 * their secret code (InvoiceLink). Uses the hand-built schema (RentSchemaTestCase).
 */
class CompanyIsolationTest extends RentSchemaTestCase
{
    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->company('Sunrise', [['rent_amount' => 10000], ['rent_amount' => 12000]], automation: false);
        $this->b = $this->company('Other', [['rent_amount' => 50000]], automation: false);
        foreach (array_merge($this->a['tenancies'], $this->b['tenancies']) as $tenancyId) {
            DB::table('invoices')->insert(['tenancy_id' => $tenancyId, 'invoice_type' => 'monthly', 'billing_month' => '2026-12-01', 'total_amount' => 1000, 'status' => 'unpaid']);
        }
        // Older rows: no company_id on the invoice or tenancy, only on the unit / property.
        DB::table('invoices')->update(['company_id' => null]);
        DB::table('tenancies')->where('id', $this->a['tenancies'][1])->update(['company_id' => null]);
    }

    private function user(?int $companyId, ?string $role = null): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'Staff', 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        if ($role) {
            $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        }
        CompanyAccess::flush();

        return User::find($id);
    }

    public function test_a_company_user_only_finds_their_own_invoices_and_tenancies(): void
    {
        $this->actingAs($this->user($this->a['company']));

        $this->assertSame(2, Invoice::count());
        $this->assertSame(2, Tenancy::count()); // including the one linked only through its unit
        $other = Invoice::withoutGlobalScope('company')->where('tenancy_id', $this->b['tenancies'][0])->value('id');
        $this->assertNull(Invoice::find($other));
        $this->assertNull(Tenancy::find($this->b['tenancies'][0]));
    }

    public function test_the_other_company_sees_only_theirs_and_sysadmins_and_the_scheduler_see_all(): void
    {
        $this->actingAs($this->user($this->b['company']));
        $this->assertSame(1, Invoice::count());

        $this->actingAs($this->user(null, 'sysadmin'));
        $this->assertSame(3, Invoice::count());

        auth()->logout();
        $this->assertSame(3, Invoice::count());
        $this->assertSame(3, Tenancy::count());
    }

    public function test_public_bill_page_needs_the_secret_code(): void
    {
        $id = Invoice::where('tenancy_id', $this->a['tenancies'][0])->value('id');

        $this->get('/public/invoice/'.$id)->assertStatus(403)->assertSee('This link is not valid');
        $this->get('/public/invoice/'.$id.'?t='.InvoiceLink::token($id + 1))->assertStatus(403);
        $this->postJson('/public/invoice/'.$id.'/pay', ['phone' => '0712000001', 'amount' => 100])->assertStatus(403);
        $this->getJson('/public/invoice/'.$id.'/status/ws_CO_1')->assertStatus(403);

        $this->get(InvoiceLink::url($id))->assertOk()->assertSee('A1');
        $this->assertStringContainsString('?t='.InvoiceLink::token($id), InvoiceLink::url($id));
    }

    public function test_a_signed_in_landlord_of_another_company_can_still_open_a_tenants_bill_link(): void
    {
        $id = Invoice::where('tenancy_id', $this->a['tenancies'][0])->value('id');
        $this->actingAs($this->user($this->b['company']));

        $this->get(InvoiceLink::url($id))->assertOk();
    }

    public function test_payments_tenants_and_units_follow_the_same_rule(): void
    {
        foreach (Invoice::withoutGlobalScope('company')->get() as $invoice) {
            DB::table('payments')->insert(['invoice_id' => $invoice->id, 'amount' => 500, 'status' => 'completed']);
        }
        DB::table('tenants')->where('id', DB::table('tenancies')->where('id', $this->a['tenancies'][1])->value('tenant_id'))->update(['company_id' => null]);
        $bUnit = DB::table('tenancies')->where('id', $this->b['tenancies'][0])->value('unit_id');

        $this->actingAs($this->user($this->a['company']));
        $this->assertSame(2, Payment::count());
        $this->assertSame(2, Tenant::count()); // one only through its tenancy
        $this->assertSame(2, Unit::count());
        $this->assertNull(Unit::find($bUnit));

        // A unit A manages in B's building (a BnB host unit) is visible to A.
        DB::table('unit_managements')->insert(['unit_id' => $bUnit, 'company_id' => $this->a['company']]);
        $this->assertNotNull(Unit::find($bUnit));

        // System work (payment settlement, host units) sees everything, whoever is signed in.
        $this->assertSame(3, CompanyAccess::bypass(fn () => Payment::count()));
        $this->assertSame(3, CompanyAccess::bypass(fn () => Invoice::with('tenancy.tenant')->get()->filter(fn ($i) => $i->tenancy?->tenant)->count()));
        $this->assertSame(2, Payment::count()); // and the filter is back afterwards
    }

    public function test_a_tenant_added_by_a_company_user_stays_in_their_list(): void
    {
        $this->actingAs($this->user($this->a['company']));
        $userId = DB::table('users')->insertGetId(['name' => 'New Tenant', 'email' => 'new@example.test']);

        $tenant = Tenant::create(['user_id' => $userId]);

        $this->assertSame((int) $this->a['company'], (int) $tenant->company_id);
        $this->assertNotNull(Tenant::find($tenant->id));
    }
}
