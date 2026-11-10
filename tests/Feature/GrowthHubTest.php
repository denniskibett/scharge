<?php

namespace Tests\Feature;

use App\Models\CompanyIntegration;
use App\Models\User;
use App\Services\Sharet\GrowthHub;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Website analytics › Sharet growth hub. The project's migrations do not run on sqlite (duplicate tables),
 * so this test builds just the tables it needs.
 */
class GrowthHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('company_integrations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id')->nullable(); $t->string('provider', 40); $t->text('credentials')->nullable();
            $t->boolean('enabled')->default(true); $t->timestamp('last_tested_at')->nullable(); $t->boolean('last_test_ok')->nullable();
            $t->string('last_test_message')->nullable(); $t->unsignedBigInteger('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('email')->unique(); $t->string('password')->nullable();
            $t->unsignedBigInteger('company_id')->nullable(); $t->rememberToken(); $t->timestamp('email_verified_at')->nullable(); $t->timestamps();
        });
        $tables = config('permission.table_names');
        Schema::create($tables['roles'], function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create($tables['permissions'], function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create($tables['model_has_roles'], function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create($tables['model_has_permissions'], function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create($tables['role_has_permissions'], function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });

        Cache::flush();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        // The page is tested inside a bare layout; the real dashboard layout needs the full schema.
        $this->app['view']->getFinder()->prependLocation(base_path('tests/stubs/views'));
    }

    private function connect(string $key = 'sk_live_test123'): void
    {
        CompanyIntegration::create(['company_id' => null, 'provider' => 'sharet', 'enabled' => true,
            'credentials' => ['api_key' => $key, 'base_url' => 'https://sharet.test/api/v1', 'environment' => 'live']]);
    }

    private function summary(array $totals = [], array $extra = []): array
    {
        return ['object' => 'growth.summary', 'version' => 1, 'from' => '2026-10-12', 'to' => '2026-11-10', 'sites' => [array_merge([
            'key' => 'scharge', 'name' => 'Scharge', 'url' => 'https://scharge.sharet.africa', 'last_data_at' => now()->subHour()->toIso8601String(),
            'totals' => array_merge(['visits' => 420, 'page_views' => 1300, 'conversions' => 21, 'conversion_rate' => 5.0, 'organic_share' => 40, 'paid_share' => 10], $totals),
            'daily' => [['date' => '2026-11-09', 'page_views' => 40, 'visits' => 12, 'conversions' => 1], ['date' => '2026-11-10', 'page_views' => 50, 'visits' => 15, 'conversions' => 0]],
            'conversions' => [['name' => 'lead', 'label' => 'Lead', 'count' => 18]],
            'channels' => [['channel' => 'organic', 'label' => 'Organic search', 'visits' => 168, 'share' => 40.0, 'conversions' => 9, 'conversion_rate' => 5.36]],
            'sources' => [['source' => 'facebook', 'medium' => 'paid_social', 'campaign' => 'landlords', 'visits' => 60, 'conversions' => 6, 'conversion_rate' => 10.0]],
            'landing_pages' => [
                ['path' => '/rent-collection-mpesa', 'visits' => 90, 'conversions' => 9, 'conversion_rate' => 10.0],
                ['path' => '/airbnb', 'visits' => 70, 'conversions' => 0, 'conversion_rate' => 0.0],
            ],
            'seo' => ['checked_at' => now()->subHours(3)->toIso8601String(), 'sitemap_urls' => 21, 'counts' => ['red' => 1, 'amber' => 2, 'green' => 18],
                'fix_first' => [['level' => 'red', 'url' => 'https://scharge.sharet.africa/credits', 'text' => 'Title too short.']]],
        ], $extra)]];
    }

    private function staff(string $role = 'sysadmin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::forceCreate(['name' => 'Staff', 'email' => $role.'@scharge.test', 'password' => bcrypt('x')]);
        $user->assignRole($role);

        return $user;
    }

    public function test_connected_summary_is_read_with_the_platform_key_and_cached(): void
    {
        $this->connect();
        Http::fake([
            'sharet.test/api/v1/growth/summary*' => Http::response($this->summary()),
            'sharet.test/api/v1/accounts/me' => Http::response(['sharet_id' => 'SH-QPAKAK']),
        ]);

        $data = app(GrowthHub::class)->summary(30);
        $this->assertSame('ok', $data['state']);
        $this->assertSame(420, $data['site']['totals']['visits']);
        $this->assertSame('SH-QPAKAK', $data['account']);
        $this->assertSame('live', $data['environment']);

        app(GrowthHub::class)->summary(30); // cached: no second summary call
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/growth/summary') && $r->hasHeader('Authorization', 'Bearer sk_live_test123')
            && $r['site'] === 'scharge' && $r['from'] === now()->subDays(29)->toDateString());
    }

    public function test_not_connected_without_a_key(): void
    {
        Http::fake();
        $this->assertSame('not_connected', app(GrowthHub::class)->summary(30)['state']);
        Http::assertNothingSent();
    }

    public function test_errors_become_friendly_states(): void
    {
        $this->connect();
        foreach ([[401, 'unauthorized'], [403, 'forbidden'], [503, 'unavailable'], [500, 'unavailable'], [422, 'invalid']] as [$status, $state]) {
            Cache::flush();
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::fake(['sharet.test/*' => Http::response(['type' => 'about:blank', 'code' => $status === 403 ? 'growth_no_site' : 'x'], $status)]);
            $data = app(GrowthHub::class)->summary(7);
            $this->assertSame($state, $data['state'], "HTTP $status");
            $this->assertNotEmpty($data['message']);
        }

        Cache::flush();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));
        $this->assertSame('timeout', app(GrowthHub::class)->summary(7)['state']);
    }

    public function test_empty_site_and_advice_thresholds(): void
    {
        $this->connect();
        Http::fake(['sharet.test/api/v1/growth/summary*' => Http::response($this->summary(['visits' => 0, 'page_views' => 0, 'conversions' => 0], ['daily' => [], 'landing_pages' => []])), 'sharet.test/*' => Http::response([], 404)]);
        $data = app(GrowthHub::class)->summary(30);
        $this->assertSame('empty', $data['state']);
        $this->assertFalse(GrowthHub::advice($data['site'])['enough']);

        $advice = GrowthHub::advice($this->summary()['sites'][0]);
        $this->assertTrue($advice['enough']);
        $this->assertSame('/rent-collection-mpesa', $advice['best_page']['path']);
        $this->assertSame('facebook', $advice['best_source']['source']);
        $this->assertSame('/airbnb', $advice['leaks'][0]['path']);

        $this->assertFalse(GrowthHub::advice($this->summary(['visits' => 99])['sites'][0])['enough']);
        $this->assertFalse(GrowthHub::advice($this->summary(['conversions' => 9])['sites'][0])['enough']);
    }

    public function test_guests_and_non_staff_cannot_open_the_page(): void
    {
        $this->get('/admin/website-analytics/growth')->assertRedirect('/login');

        $this->actingAs($this->staff('tenant'))->get('/admin/website-analytics/growth')->assertForbidden();
    }

    public function test_staff_see_the_hub_numbers_and_ad_center(): void
    {
        $this->connect();
        Http::fake([
            'sharet.test/api/v1/growth/summary*' => Http::response($this->summary()),
            'sharet.test/api/v1/accounts/me' => Http::response(['sharet_id' => 'SH-QPAKAK']),
        ]);

        $this->actingAs($this->staff('account_manager'))->get('/admin/website-analytics/growth?days=90')->assertOk()
            ->assertSee('data-state="ok"', false)->assertSee('acts as SH-QPAKAK')->assertSee('Organic search')
            ->assertSee('/rent-collection-mpesa')->assertSee('Title too short.')->assertSee('data-advice="ready"', false)
            ->assertSee('Google Search')->assertSee('utm-builder', false)->assertSee(route('landing.rent-collection-mpesa'), false);
    }

    public function test_page_shows_a_friendly_state_when_the_hub_fails(): void
    {
        $this->connect();
        Http::fake(['sharet.test/*' => Http::response(['code' => 'growth_unavailable'], 503)]);

        $this->actingAs($this->staff())->get('/admin/website-analytics/growth')->assertOk()
            ->assertSee('data-state="unavailable"', false)->assertSee('Growth hub unavailable')->assertSee('Ready ad copy');
    }

    public function test_page_when_not_connected(): void
    {
        $this->actingAs($this->staff())->get('/admin/website-analytics/growth?days=7')->assertOk()
            ->assertSee('data-state="not_connected"', false)->assertSee('Settings › Integrations › Sharet Africa');
    }
}
