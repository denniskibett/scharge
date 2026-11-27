<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Services\ContactService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A company removed from the CRM is hidden everywhere, marked as reached out, and never imported again. */
class CompanySoftDeleteTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'domain' => 'string', 'name_key' => 'string', 'deleted_at' => 'timestamp'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
    }

    public function test_removed_company_is_hidden_kept_and_not_added_again(): void
    {
        $id = DB::table('companies')->insertGetId(['name' => 'Diani Sea Lodge', 'lifecycle' => 'prospect', 'crm_stage' => 'nurture', 'email' => 'reservation@dianisea.test', 'domain' => 'dianisea.test', 'name_key' => 'dianisealodge', 'crm_profile' => '{}']);
        $c = Company::withoutGlobalScope('customers')->find($id);
        if (! Schema::hasColumn('companies', 'deleted_at') || ! in_array('not_deleted', array_keys($c->getGlobalScopes()), true)) {
            $this->markTestSkipped('deleted_at scope is resolved once per process');
        }
        $c->softDelete('Declined twice', true, 1);

        $this->assertNull(Company::withoutGlobalScope('customers')->find($id), 'hidden from the CRM');
        $row = DB::table('companies')->find($id);
        $this->assertNotNull($row->deleted_at, 'kept on record');
        $profile = json_decode($row->crm_profile, true);
        $this->assertTrue($profile['reached_out']);
        $this->assertTrue($profile['do_not_email']);

        $found = app(ContactService::class)->findCompany('dianisea.test', null);
        $this->assertSame($id, $found?->id, 'imports match the removed company, so it is not created again');
    }
}
