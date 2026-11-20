<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Models\Invoice;
use App\Models\RentMessage;
use App\Services\Rent\ArrearsReminders;
use App\Services\Rent\RentRun;
use App\Services\Rent\RentSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The repo's migrations cannot build the schema yet (SYSTEM_AUDIT.md §2), so rent and company-isolation tests create
 * just the tables they need, matching the live columns. SMS calls are faked and counted in $sent.
 */
abstract class RentSchemaTestCase extends TestCase
{
    protected int $sent = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->schema();
        config(['sms.kenyasms.api_key' => 'test', 'sms.kenyasms.base_url' => 'https://sms.example.test/api/v1', 'sms.kenyasms.sandbox' => true]);
        Http::fake(['sms.example.test/*' => function () {
            $this->sent++;

            return Http::response(['data' => ['message_id' => 'm'.$this->sent, 'status' => 'queued']]);
        }]);
    }

    /** Pages render inside a bare layout: the real one (sidebar, header) needs the whole scharge database. */
    protected function bareLayout(): void
    {
        $dir = sys_get_temp_dir().'/scharge-test-views/layouts';
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/app.blade.php', "<main>@yield('content')</main>");
        app('view')->getFinder()->prependLocation(dirname($dir));
        app('view')->getFinder()->flush();
    }

    protected function schema(): void
    {
        foreach (['companies', 'users', 'tenants', 'properties', 'units', 'tenancies', 'invoices', 'invoice_items', 'water_readings', 'company_integrations', 'rent_messages', 'sms_logs', 'payments', 'roles', 'model_has_roles', 'unit_managements', 'paybill_payments', 'rent_holds', 'transfers', 'transactions', 'wallets', 'permissions', 'role_has_permissions', 'model_has_permissions', 'bookings', 'unit_channels', 'company_brands', 'api_keys', 'crm_follow_ups', 'company_kyc', 'kyc_documents', 'unit_photos', 'company_pages', 'company_agreements', 'login_events'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('companies', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('lifecycle')->default('customer'), $t->unsignedBigInteger('owner_id')->nullable(), $t->timestamps()]);
        Schema::create('users', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('first_name')->nullable(), $t->string('last_name')->nullable(), $t->string('email')->nullable(),
            $t->string('phone')->nullable(), $t->string('country')->nullable(), $t->string('country_code')->nullable(), $t->string('lead_source')->nullable(),
            $t->unsignedBigInteger('company_id')->nullable(), $t->string('password')->nullable(), $t->timestamps()]);
        Schema::create('tenants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('user_id'), $t->unsignedBigInteger('company_id')->nullable(), $t->timestamps()]);
        Schema::create('properties', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('location')->nullable(), $t->unsignedBigInteger('company_id')->nullable(), $t->decimal('water_rate', 12, 2)->nullable(), $t->timestamps()]);
        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('property_id');
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('unit_number');
            $t->string('unit_type')->default('1br');
            $t->decimal('rent_amount', 12, 2);
            $t->decimal('water_charge', 12, 2)->default(0);
            $t->decimal('service_charge', 12, 2)->default(0);
            $t->decimal('garbage_charge', 12, 2)->default(0);
            $t->decimal('security_charge', 12, 2)->default(0);
            $t->string('water_billing_type')->default('consumption');
            $t->decimal('custom_water_rate', 12, 2)->nullable();
            $t->string('status')->default('occupied');
            $t->string('stay_type')->default('long_stay');
            $t->boolean('is_active')->nullable();
            $t->decimal('bnb_nightly_rate', 12, 2)->nullable();
            $t->decimal('security_deposit_amount', 12, 2)->nullable();
            $t->string('furnishing_status')->nullable();
            $t->timestamps();
        });
        Schema::create('tenancies', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('property_id')->nullable(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('unit_id'),
            $t->date('move_in_date'), $t->date('move_out_date')->nullable(), $t->text('notes')->nullable(), $t->string('status')->default('active'), $t->timestamps()]);
        Schema::create('invoices', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('tenancy_id'), $t->string('invoice_type'), $t->date('billing_month')->nullable(),
            $t->decimal('total_amount', 12, 2), $t->decimal('total_paid', 12, 2)->default(0), $t->string('status')->default('draft'), $t->unsignedBigInteger('company_id')->nullable(), $t->timestamps()]);
        Schema::create('invoice_items', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('invoice_id'), $t->string('item_type'), $t->string('description'), $t->decimal('amount', 12, 2),
            $t->decimal('paid_amount', 12, 2)->default(0), $t->unsignedBigInteger('payment_id')->nullable(), $t->decimal('water_units_used', 12, 2)->nullable(), $t->unsignedBigInteger('company_id')->nullable(), $t->timestamps(), $t->softDeletes()]);
        Schema::create('water_readings', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('unit_id'), $t->decimal('previous_reading', 12, 2), $t->decimal('current_reading', 12, 2),
            $t->decimal('consumption', 12, 2)->nullable(), $t->date('reading_date'), $t->timestamps()]);
        Schema::create('company_integrations', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->string('provider'), $t->text('credentials')->nullable(),
            $t->boolean('enabled')->default(false), $t->unsignedBigInteger('updated_by')->nullable(), $t->timestamp('last_tested_at')->nullable(), $t->boolean('last_test_ok')->nullable(), $t->string('last_test_message')->nullable(), $t->timestamps()]);
        Schema::create('sms_logs', fn (Blueprint $t) => [$t->id(), $t->string('recipient_phone'), $t->text('message'), $t->string('status')->default('pending'), $t->timestamps()]);
        Schema::create('payments', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('invoice_id')->nullable(), $t->unsignedBigInteger('tenant_id')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->unsignedBigInteger('invoice_item_id')->nullable(), $t->string('payment_method')->nullable(), $t->string('source')->nullable(), $t->decimal('amount', 12, 2)->default(0), $t->decimal('wallet_balance_before', 12, 2)->nullable(), $t->decimal('wallet_balance_after', 12, 2)->nullable(), $t->string('transaction_reference')->nullable(), $t->string('external_reference')->nullable(), $t->string('status')->default('completed'), $t->boolean('is_reconciled')->default(false), $t->timestamp('reconciled_at')->nullable(), $t->unsignedBigInteger('reconciled_by')->nullable(), $t->json('meta')->nullable(), $t->timestamps(), $t->softDeletes()]);
        Schema::create('unit_managements', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('unit_id'), $t->unsignedBigInteger('company_id'), $t->string('role')->default('bnb_host'), $t->string('status')->default('active'), $t->timestamps()]);
        Schema::create('roles', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name')->default('web'), $t->timestamps()]);
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name')->default('web'), $t->timestamps()]);
        Schema::create('role_has_permissions', fn (Blueprint $t) => [$t->unsignedBigInteger('permission_id'), $t->unsignedBigInteger('role_id')]);
        Schema::create('model_has_permissions', fn (Blueprint $t) => [$t->unsignedBigInteger('permission_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
        Schema::create('model_has_roles', fn (Blueprint $t) => [$t->unsignedBigInteger('role_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
        (require base_path('database/migrations/2026_11_10_120000_create_rent_messages_table.php'))->up();
        (require base_path('database/migrations/2026_11_11_000100_create_paybill_payments_and_rent_holds.php'))->up();
        (require base_path('database/migrations/2026_11_11_000200_create_bookings_and_unit_channels.php'))->up();
        (require base_path('database/migrations/2026_11_11_000300_create_company_brands.php'))->up();
        (require base_path('database/migrations/2026_11_11_000400_create_kyc_tables.php'))->up();
        (require base_path('database/migrations/2026_11_11_000500_create_company_pages_and_agreements.php'))->up();
        (require base_path('database/migrations/2026_11_11_000700_create_login_events.php'))->up();
        Schema::create('crm_follow_ups', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->unsignedBigInteger('assigned_to')->nullable(),
            $t->unsignedBigInteger('created_by')->nullable(), $t->string('type'), $t->string('status')->default('open'), $t->string('title')->nullable(), $t->text('notes')->nullable(),
            $t->timestamp('due_at')->nullable(), $t->timestamp('done_at')->nullable(), $t->string('outcome')->nullable(), $t->integer('score')->nullable(), $t->string('token')->nullable(), $t->json('details')->nullable(), $t->timestamps()]);
        Schema::create('api_keys', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('company_id')->nullable(), $t->unsignedBigInteger('user_id')->nullable(), $t->string('name'),
            $t->string('environment')->default('live'), $t->json('scopes')->nullable(), $t->json('allowed_ips')->nullable(), $t->string('prefix')->nullable(), $t->string('last_four')->nullable(),
            $t->string('hash')->nullable(), $t->boolean('enabled')->default(true), $t->timestamp('last_used_at')->nullable(), $t->timestamps()]);
        \App\Support\Brand::forget();
        // bavix wallet (tenant credit), the package's own tables
        foreach (['2026_06_10_124230_create_wallets_table.php', '2026_06_10_182923_create_transactions_table.php', '2026_06_10_192923_create_transfers_table.php'] as $m) {
            (require base_path('database/migrations/'.$m))->up();
        }
        \App\Support\CompanyAccess::flush();
    }

    /** A company with one property and occupied units; returns tenancy ids. */
    protected function company(string $name, array $units, bool $automation = true, array $settings = []): array
    {
        $companyId = DB::table('companies')->insertGetId(['name' => $name]);
        $propertyId = DB::table('properties')->insertGetId(['name' => $name.' Court', 'company_id' => $companyId, 'water_rate' => 100]);
        $ids = [];
        foreach ($units as $i => $u) {
            $unitId = DB::table('units')->insertGetId($u + ['property_id' => $propertyId, 'unit_number' => 'A'.($i + 1), 'rent_amount' => 10000]);
            $userId = DB::table('users')->insertGetId(['name' => 'Tenant '.$name.' '.($i + 1), 'phone' => '07120000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)]);
            $tenantId = DB::table('tenants')->insertGetId(['user_id' => $userId, 'company_id' => $companyId]);
            $ids[] = DB::table('tenancies')->insertGetId(['company_id' => $companyId, 'tenant_id' => $tenantId, 'unit_id' => $unitId, 'move_in_date' => '2026-01-15', 'status' => 'active']);
        }
        if ($automation) {
            CompanyIntegration::create(['company_id' => $companyId, 'provider' => RentSettings::PROVIDER, 'enabled' => true,
                'credentials' => $settings + ['invoice_day' => '1', 'due_day' => '5', 'send_bill' => 'yes', 'reminder_days' => '1, 7, 14', 'channel' => 'sms']]);
        }

        return ['company' => $companyId, 'tenancies' => $ids];
    }

    protected function month(string $ym): CarbonImmutable
    {
        return CarbonImmutable::parse($ym.'-01', RentSettings::TZ);
    }
}
