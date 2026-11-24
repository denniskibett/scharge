<?php

namespace Tests\Feature;

use App\Support\UnitPricing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Units in use pay in full; vacant units: 0% off for the first 6 months as a client, 25% off from month 7, 50% off from the first anniversary. */
class UnitPricingTest extends RentSchemaTestCase
{
    private int $co;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('company_subscriptions');
        Schema::create('company_subscriptions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id'), $t->unsignedBigInteger('plan_id'), $t->string('status'),
            $t->timestamp('starts_at')->nullable(), $t->timestamp('ends_at')->nullable(), $t->timestamps()]);
        if (! Schema::hasColumn('units', 'status')) {
            Schema::table('units', fn (Blueprint $t) => $t->string('status')->nullable());
        }
        config(['billing.vacant_discounts' => [6 => 25, 12 => 50]]);
        $this->co = $this->company('Kilimani Heights', [[], [], [], [], []], automation: false)['company'];
        $ids = DB::table('units')->orderBy('id')->pluck('id');
        DB::table('units')->whereIn('id', $ids->take(3))->update(['status' => 'occupied']);
        DB::table('units')->whereIn('id', $ids->slice(3))->update(['status' => 'vacant']);
    }

    public function test_the_discount_steps_by_months_as_a_client(): void
    {
        $since = Carbon::parse('2026-01-15');
        $this->assertSame(0.0, UnitPricing::vacantDiscount($since, Carbon::parse('2026-07-14')));
        $this->assertSame(25.0, UnitPricing::vacantDiscount($since, Carbon::parse('2026-07-15')));
        $this->assertSame(25.0, UnitPricing::vacantDiscount($since, Carbon::parse('2027-01-14')));
        $this->assertSame(50.0, UnitPricing::vacantDiscount($since, Carbon::parse('2027-01-15')));
        $this->assertSame(50.0, UnitPricing::vacantDiscount($since, Carbon::parse('2029-06-01')));
        $this->assertSame(0.0, UnitPricing::vacantDiscount(null));
    }

    public function test_a_month_bills_units_in_use_in_full_and_vacant_units_at_the_discount(): void
    {
        DB::table('company_subscriptions')->insert(['company_id' => $this->co, 'plan_id' => 1, 'status' => 'active', 'starts_at' => '2026-01-01 08:00:00', 'created_at' => '2026-01-01 08:00:00']);

        $new = UnitPricing::month($this->co, 40, Carbon::parse('2026-03-01'));
        $this->assertSame([3, 2, 0.0], [$new['in_use'], $new['vacant'], $new['discount']]);
        $this->assertSame(200.0, $new['amount']);                     // 5 units × 40: no discount in the first 6 months

        $sixMonths = UnitPricing::month($this->co, 40, Carbon::parse('2026-07-01'));
        $this->assertSame(25.0, $sixMonths['discount']);
        $this->assertSame(30.0, $sixMonths['vacant_rate']);
        $this->assertSame(180.0, $sixMonths['amount']);               // 3 × 40 + 2 × 30

        $year = UnitPricing::month($this->co, 40, Carbon::parse('2027-01-01'));
        $this->assertSame(160.0, $year['amount']);                    // 3 × 40 + 2 × 20
        $this->assertSame('Vacant units (50% off)', $year['lines'][1]['month']);
    }

    public function test_the_client_clock_starts_at_the_first_subscription_not_when_a_prospect_was_imported(): void
    {
        DB::table('companies')->where('id', $this->co)->update(['created_at' => '2025-01-01 00:00:00']);
        $this->assertSame('2025-01-01', UnitPricing::clientSince($this->co)->toDateString());

        DB::table('company_subscriptions')->insert(['company_id' => $this->co, 'plan_id' => 1, 'status' => 'active', 'starts_at' => '2026-11-24 09:00:00', 'created_at' => '2026-11-24 09:00:00']);
        $this->assertSame('2026-11-24', UnitPricing::clientSince($this->co)->toDateString());
        $this->assertSame(0.0, UnitPricing::month($this->co, 40, Carbon::parse('2026-12-01'))['discount']);
    }
}
