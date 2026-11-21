<?php

namespace Tests\Feature;

use App\Http\Controllers\BudgetController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Budgets page: a building's yearly service-charge plan against what was spent, and the charge per unit. */
class ServiceBudgetTest extends RentSchemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['service_budgets', 'service_budget_lines', 'expenses', 'expense_categories'] as $t) Schema::dropIfExists($t);
        Schema::create('service_budgets', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id'), $t->smallInteger('year'),
            $t->decimal('sinking_fund_percent', 5, 2)->default(10), $t->text('notes')->nullable(), $t->unsignedBigInteger('created_by')->nullable(), $t->timestamps()]);
        Schema::create('service_budget_lines', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('service_budget_id'), $t->string('name'), $t->unsignedBigInteger('expense_category_id')->nullable(),
            $t->decimal('annual_amount', 14, 2)->default(0), $t->integer('sort')->default(0), $t->timestamps()]);
        Schema::create('expense_categories', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->string('name'), $t->text('description')->nullable(), $t->timestamps()]);
        Schema::create('expenses', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('payee_id')->nullable(),
            $t->unsignedBigInteger('expense_category_id')->nullable(), $t->decimal('amount', 14, 2), $t->string('description')->nullable(), $t->date('expense_date'), $t->string('status')->nullable(), $t->timestamps()]);
        if (! Schema::hasColumn('invoice_items', 'paid_amount')) Schema::table('invoice_items', fn (Blueprint $t) => $t->decimal('paid_amount', 12, 2)->default(0));
        if (! Schema::hasColumn('units', 'is_active')) Schema::table('units', fn (Blueprint $t) => $t->boolean('is_active')->nullable());
    }

    public function test_budget_lines_spending_and_charge_per_unit(): void
    {
        DB::table('companies')->insert([['id' => 3, 'name' => 'Estate Co'], ['id' => 4, 'name' => 'Other Co']]);
        DB::table('users')->insert(['id' => 70, 'name' => 'Manager', 'company_id' => 3]);
        $prop = DB::table('properties')->insertGetId(['name' => 'Palm Court', 'company_id' => 3]);
        foreach (['1', '2', '3', '4'] as $n) DB::table('units')->insert(['property_id' => $prop, 'company_id' => 3, 'unit_number' => $n, 'rent_amount' => 20000, 'status' => 'occupied']);
        $security = DB::table('expense_categories')->insertGetId(['company_id' => 3, 'name' => 'Security']);
        $paint = DB::table('expense_categories')->insertGetId(['company_id' => 3, 'name' => 'Painting']);
        DB::table('expenses')->insert([
            ['property_id' => $prop, 'company_id' => 3, 'expense_category_id' => $security, 'amount' => 30000, 'expense_date' => '2026-03-31', 'status' => 'paid'],
            ['property_id' => $prop, 'company_id' => 3, 'expense_category_id' => $paint, 'amount' => 5000, 'expense_date' => '2026-04-02', 'status' => 'paid'],
            ['property_id' => $prop, 'company_id' => 3, 'expense_category_id' => $security, 'amount' => 99999, 'expense_date' => '2025-12-31', 'status' => 'paid'],   // last year
        ]);
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(70));
        \App\Support\CompanyAccess::flush();
        $c = app(BudgetController::class);

        $c->store(Request::create('/', 'POST', ['property_id' => $prop, 'year' => 2026]));
        $budget = \App\Models\ServiceBudget::firstOrFail();
        $this->assertSame(3, (int) $budget->company_id);
        $this->assertCount(count(\App\Models\ServiceBudget::SUGGESTED_LINES), $budget->lines);

        // Keep two lines: Security 120,000 and Cleaning 24,000; 10% sinking fund.
        $c->update(Request::create('/', 'PUT', ['sinking_fund_percent' => 10, 'lines' => [
            ['id' => $budget->lines[0]->id, 'name' => 'Security', 'annual_amount' => 120000],
            ['name' => 'Cleaning', 'annual_amount' => 24000],
        ]]), $budget);
        $this->assertSame(2, $budget->lines()->count());

        $f = $c->index(Request::create('/', 'GET', ['property' => $prop, 'year' => 2026]))->getData()['figures'];
        $this->assertEquals(144000, $f['planned']);
        $this->assertEquals(30000, $f['lines'][0]['spent']);          // Security, matched by name; last year's not counted
        $this->assertEquals(5000, $f['other_spent']);                 // Painting has no line
        $this->assertEquals(14400, $f['sinking']);
        $this->assertEquals(3300, $f['per_unit_month']);              // (144,000 + 14,400) / 4 units / 12

        // Another company's manager cannot open it.
        DB::table('users')->insert(['id' => 71, 'name' => 'Other', 'company_id' => 4]);
        Auth::setUser(\App\Models\User::withoutGlobalScopes()->find(71));
        \App\Support\CompanyAccess::flush();
        $this->assertSame(0, \App\Models\ServiceBudget::count());
    }
}
