<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CloseSteps;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Once a client answers, Actions opens on closing steps for their stage (onboarding after Won), with their monthly figure. */
class CloseStepsTest extends RentSchemaTestCase
{
    private Company $co;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'reply_pending' => 'boolean'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        Schema::dropIfExists('company_subscriptions');
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->timestamp('starts_at')->nullable(), $t->timestamps()]);
        $id = DB::table('companies')->insertGetId(['name' => 'Jourdan Test', 'lifecycle' => 'prospect', 'crm_stage' => 'contacted', 'crm_profile' => json_encode(['estimated_units' => 221])]);
        $this->co = Company::withoutGlobalScopes()->find($id);
    }

    public function test_a_contacted_prospect_that_never_answered_is_not_engaged(): void
    {
        $this->assertFalse(CloseSteps::engaged($this->co));
    }

    public function test_a_reply_makes_it_engaged_and_the_first_step_is_to_answer_it(): void
    {
        CrmFollowUp::create(['company_id' => $this->co->id, 'type' => 'email', 'status' => 'done', 'notes' => 'Ksh 40 per unit may be over our budget.',
            'details' => ['direction' => 'in', 'from' => 'emma@jourdan.test', 'from_name' => 'Emma']]);
        CrmFollowUp::create(['company_id' => $this->co->id, 'type' => 'email', 'status' => 'done', 'notes' => 'Out of office', 'details' => ['direction' => 'in', 'auto' => true, 'from' => 'x@y.test']]);

        $this->assertTrue(CloseSteps::engaged($this->co));
        $c = CloseSteps::for($this->co);
        $this->assertSame('Emma', $c['said']['from']);
        $this->assertFalse($c['said']['answered']);
        $this->assertSame('Answer their reply today', $c['steps'][0]['title']);
        $this->assertSame(8840.0, $c['quote']['start']);   // 221 units × KES 40, no vacancy known yet
        $this->assertSame($c['quote']['start'], $c['quote']['year']);
    }

    public function test_steps_follow_the_stage_and_become_onboarding_after_won(): void
    {
        foreach (['demo_booked' => 'Confirm the time the day before', 'proposal' => 'Send the figure in writing', 'negotiation' => 'Find what is still in the way', 'won' => 'Send them the template'] as $stage => $first) {
            $this->co->forceFill(['crm_stage' => $stage])->saveQuietly();
            $c = CloseSteps::for($this->co->fresh());
            $this->assertTrue(CloseSteps::engaged($this->co->fresh()), $stage);
            $this->assertSame($first, $c['steps'][0]['title'], $stage);
            $this->assertSame($stage === 'won', $c['onboarding']);
        }
    }

    public function test_the_figure_uses_units_in_use_and_the_vacant_discount_once_units_are_in(): void
    {
        $prop = DB::table('properties')->insertGetId(['name' => 'Blemerg', 'company_id' => $this->co->id]);
        if (! Schema::hasColumn('units', 'status')) {
            Schema::table('units', fn (Blueprint $t) => $t->string('status')->nullable());
        }
        $cols = Schema::getColumnListing('units');
        foreach (range(1, 10) as $i) {
            DB::table('units')->insert(array_intersect_key(['property_id' => $prop, 'company_id' => $this->co->id, 'status' => $i <= 8 ? 'occupied' : 'vacant', 'unit_number' => 'U'.$i, 'rent_amount' => 10000, 'name' => 'U'.$i], array_flip($cols)));
        }
        $this->co->forceFill(['crm_stage' => 'proposal'])->saveQuietly();

        $q = CloseSteps::for($this->co->fresh())['quote'];
        $this->assertSame([8, 2], [$q['in_use'], $q['vacant']]);
        $this->assertSame([400.0, 380.0, 360.0], [$q['start'], $q['six'], $q['year']]); // 8×40 + 2×40, then 2×30, then 2×20
    }
}
