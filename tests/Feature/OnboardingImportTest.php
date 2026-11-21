<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Services\Properties\PropertyOnboarding;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Onboarding upload: Excel files as they come from clients, and the tenant living in each unit with what they owe. */
class OnboardingImportTest extends RentSchemaTestCase
{
    private PropertyOnboarding $import;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('tenants', fn (Blueprint $t) => [$t->string('id_number')->nullable(), $t->text('notes')->nullable()]);
        Schema::table('users', fn (Blueprint $t) => [$t->timestamp('email_verified_at')->nullable()]);
        DB::table('roles')->insertOrIgnore(['name' => 'tenant', 'guard_name' => 'web']);
        $this->import = app(PropertyOnboarding::class);
    }

    private function tmp(string $ext): string
    {
        return tempnam(sys_get_temp_dir(), 'onb').'.'.$ext;
    }

    public function test_an_excel_file_is_read_the_way_the_client_sees_it(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['Building Name', 'House No', 'Monthly Rent', 'Tenant', 'Tenant Phone', 'Date moved in', 'Arrears', 'KPLC meter']], null, 'A1');
        // Typed the way Excel stores them: numbers, a real date, a phone that lost its 0.
        $sheet->setCellValue('A2', 'Simba Apartment');
        $sheet->setCellValue('B2', 3);
        $sheet->setCellValue('C2', 18000);
        $sheet->setCellValue('D2', 'Judy Wanjiru');
        $sheet->setCellValue('E2', 712345678);
        $sheet->setCellValue('F2', ExcelDate::PHPToExcel(new \DateTime('2025-03-01')));
        $sheet->getStyle('F2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->setCellValue('G2', '=6000*2');
        $sheet->setCellValue('H2', 37123456789);
        $path = $this->tmp('xlsx');
        (new Xlsx($book))->save($path);

        $this->assertTrue(PropertyOnboarding::isWorkbook($path));
        $read = $this->import->read($path);
        $this->assertSame([], $read['missing']);
        $row = $read['rows'][0];
        $this->assertSame(['Simba Apartment', '3', '18000', 'Judy Wanjiru', '712345678', '2025-03-01', '12000', '37123456789'],
            [$row['property_name'], $row['unit_number'], $row['rent'], $row['tenant_name'], $row['tenant_phone'], $row['move_in_date'], $row['balance'], $row['power_meter_no']]);
    }

    public function test_the_excel_template_reads_back_with_every_column(): void
    {
        $path = $this->tmp('xlsx');
        file_put_contents($path, $this->import->templateXlsx());
        $read = $this->import->read($path);
        $this->assertSame([[], []], [$read['missing'], $read['unknown']]);
        $this->assertCount(2, $read['rows']);
        $this->assertSame('0722 111 222', $read['rows'][0]['tenant_phone']);   // kept as text: the leading 0 survives
    }

    public function test_the_template_downloads_without_signing_in(): void
    {
        $this->get('/onboarding-template.xlsx')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function tenantStep(Unit $unit, array $row): array
    {
        $clean = new \ReflectionMethod(PropertyOnboarding::class, 'clean');
        $errors = [];
        $d = $clean->invokeArgs($this->import, [$row + ['property_name' => 'Simba', 'unit_number' => $unit->unit_number], &$errors]);
        $this->assertSame([], $errors);

        $report = new \ReflectionProperty(PropertyOnboarding::class, 'report');
        $report->setValue($this->import, ['commit' => true, 'counts' => array_fill_keys(['tenants_created', 'tenants_matched', 'tenants_skipped', 'balances_added', 'balances_total'], 0)]);
        $company = new \ReflectionProperty(PropertyOnboarding::class, 'company');
        $company->setValue($this->import, \App\Models\Company::withoutGlobalScopes()->find($unit->company_id));

        $out = ['filled' => [], 'kept' => [], 'notes' => []];
        $step = new \ReflectionMethod(PropertyOnboarding::class, 'tenant');
        $step->invokeArgs($this->import, [$unit, \App\Models\Property::withoutGlobalScopes()->find($unit->property_id), $d, true, &$out]);

        return ['out' => $out, 'counts' => $report->getValue($this->import)['counts']];
    }

    public function test_the_tenant_and_their_balance_come_in_once(): void
    {
        $companyId = DB::table('companies')->insertGetId(['name' => 'Real Diani']);
        $propertyId = DB::table('properties')->insertGetId(['name' => 'Simba Apartment', 'company_id' => $companyId]);
        $unit = Unit::withoutGlobalScopes()->create(['property_id' => $propertyId, 'company_id' => $companyId, 'unit_number' => '3', 'rent_amount' => 18000, 'status' => 'vacant']);
        $row = ['tenant_name' => 'Judy Wanjiru', 'tenant_phone' => '712345678', 'move_in_date' => '01/03/2025', 'balance' => 'KES 12,000'];

        $first = $this->tenantStep($unit, $row);
        $this->assertSame([1, 1, 12000.0], [$first['counts']['tenants_created'], $first['counts']['balances_added'], (float) $first['counts']['balances_total']]);

        $user = DB::table('users')->where('name', 'Judy Wanjiru')->first();
        $this->assertSame('+254712345678', $user->phone);
        $tenant = DB::table('tenants')->where('user_id', $user->id)->first();
        $this->assertSame($companyId, (int) $tenant->company_id);
        $tenancy = DB::table('tenancies')->where('tenant_id', $tenant->id)->first();
        $this->assertSame(['active', '2025-03-01', $companyId, $propertyId], [$tenancy->status, substr($tenancy->move_in_date, 0, 10), (int) $tenancy->company_id, (int) $tenancy->property_id]);
        $this->assertSame('occupied', $unit->fresh()->status);
        $bill = DB::table('invoices')->where('tenancy_id', $tenancy->id)->first();
        $this->assertSame(['move_in', 'unpaid', 12000.0, $companyId], [$bill->invoice_type, $bill->status, (float) $bill->total_amount, (int) $bill->company_id]);
        $this->assertSame('Balance brought forward (from previous records)', DB::table('invoice_items')->where('invoice_id', $bill->id)->value('description'));
        $this->assertTrue(DB::table('model_has_roles')->where('model_id', $user->id)->exists());

        // The same file again: the tenant is recognised, and no second tenant or balance appears.
        $again = $this->tenantStep($unit->fresh(), $row);
        $this->assertSame([0, 1, 0], [$again['counts']['tenants_created'], $again['counts']['tenants_matched'], $again['counts']['balances_added']]);
        $this->assertSame(1, DB::table('invoices')->count());

        // Someone else on a let unit: reported, nothing changed.
        $other = $this->tenantStep($unit->fresh(), ['tenant_name' => 'Peter Otieno', 'tenant_phone' => '0733000111']);
        $this->assertSame(1, $other['counts']['tenants_skipped']);
        $this->assertSame(1, DB::table('tenancies')->count());
    }
}
