<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\OpenPipeline;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Sales pages count the open pipeline in the database: every imported prospect is an open deal (about 180,000),
 * so loading them ran the page out of memory. The numbers must match what loading each company used to give.
 */
class OpenPipelineTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'crm_stage' => 'string', 'tier' => 'string', 'kind' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        Carbon::setTestNow('2026-11-24 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prospect(string $name, string $stage, ?int $owner, array $profile = []): int
    {
        return DB::table('companies')->insertGetId(['name' => $name, 'lifecycle' => 'prospect', 'crm_stage' => $stage, 'owner_id' => $owner, 'crm_profile' => json_encode($profile ?: (object) [])]);
    }

    public function test_the_numbers_match_loading_every_company(): void
    {
        $price = 300;   // pipeline price per unit (the system settings table is not part of this schema)
        foreach (range(1, 40) as $i) {
            $this->prospect("Import {$i}", 'new', null);                                    // imported, nothing known
        }
        $a = $this->prospect('Entered value', 'proposal', 5, ['deal_value' => 'KES 12,000', 'estimated_units' => 999, 'expected_close' => '2026-11-28']);
        $b = $this->prospect('Units only', 'contacted', 5, ['estimated_units' => 20]);
        $this->prospect('Units only, same size', 'contacted', 5, ['estimated_units' => 20]);
        $this->prospect('Other owner', 'negotiation', 6, ['estimated_units' => '10', 'expected_close' => '2027-02-01']);
        $this->prospect('Won already', 'won', 5, ['deal_value' => 5000]);
        CrmFollowUp::create(['company_id' => $b, 'type' => 'call', 'status' => 'open', 'title' => 'Call', 'due_at' => '2026-11-26 10:00:00']);
        CrmFollowUp::create(['company_id' => $a, 'type' => 'note', 'status' => 'done', 'title' => 'Old note', 'due_at' => '2026-11-01 10:00:00']);
        foreach ([['contacted', $b], ['won', DB::table('companies')->where('name', 'Won already')->value('id')]] as [$to, $company]) {
            CrmFollowUp::create(['company_id' => $company, 'type' => 'stage', 'status' => 'done', 'title' => 'Stage', 'done_at' => '2026-11-20 10:00:00', 'details' => ['to' => $to]]);
        }

        $companies = Company::withoutGlobalScopes();
        $groups = OpenPipeline::groups($companies, ['pipeline_price_per_unit' => $price]);

        $this->assertLessThan(10, $groups->count(), '44 open deals come back as a handful of rows');
        $all = OpenPipeline::sum($groups);
        $this->assertSame(44, $all['open']);
        $this->assertSame(2, $all['late']);
        $this->assertSame(12000 + 2 * 20 * $price + 10 * $price, $all['pipeline']);
        $this->assertSame((int) round(12000 * 0.5 + 2 * 20 * $price * 0.1 + 10 * $price * 0.75), $all['forecast']);
        $this->assertSame(43, $all['no_next_step'], 'only the company with a dated open task has a next step');

        $mine = OpenPipeline::sum($groups->filter(fn ($g) => $g->owner_id === 5));
        $this->assertSame(['open' => 3, 'late' => 1, 'pipeline' => 12000 + 40 * $price], array_intersect_key($mine, ['open' => 1, 'late' => 1, 'pipeline' => 1]));
        $this->assertSame(40, OpenPipeline::sum($groups->filter(fn ($g) => $g->owner_id === null))['open']);

        $this->assertSame(['Entered value', 'Other owner'], OpenPipeline::closing($companies)->pluck('name')->sort()->values()->all());
        $this->assertSame(1, OpenPipeline::closingBetween($companies, now()->startOfMonth(), now()->endOfMonth()));
        // The count is kept for a few minutes; a stage change makes the next load count again.
        $this->prospect('Added later', 'new', null);
        $this->assertSame(44, OpenPipeline::sum(OpenPipeline::groups($companies, ['pipeline_price_per_unit' => $price]))['open']);
        app(\App\Modules\CRM\Services\ContactService::class)->setStage(Company::withoutGlobalScopes()->find($b), 'qualified', 5);
        $this->assertSame(45, OpenPipeline::sum(OpenPipeline::groups($companies, ['pipeline_price_per_unit' => $price]))['open']);

        $moves = OpenPipeline::closedMoves(Company::withoutGlobalScopes()->select('companies.id'), now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame(['won'], $moves->map(fn ($m) => $m->detail('to'))->all(), 'the campaign\'s "contacted" moves are not loaded');
    }
}
