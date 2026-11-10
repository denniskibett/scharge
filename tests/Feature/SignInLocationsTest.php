<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analytics\GeoIp;
use App\Support\CompanyAccess;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/** Where people sign in from: recorded on every channel, located offline, reported to scharge staff. docs/SIGN_IN_LOCATIONS.md */
class SignInLocationsTest extends RentSchemaTestCase
{
    private array $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bareLayout();
        $this->a = $this->company('Danaff', [['rent_amount' => 10000]], automation: false);
        // Stand-in for the DB-IP database: one public address in Nairobi, one in Mombasa.
        $this->app->instance(GeoIp::class, new class extends GeoIp {
            public function locate(?string $ip, ?string $cdnCountry = null): array
            {
                return match ($ip) {
                    '41.90.1.1' => ['country_code' => 'KE', 'country' => 'Kenya', 'region' => 'Nairobi County', 'city' => 'Nairobi'],
                    '105.160.2.2' => ['country_code' => 'KE', 'country' => 'Kenya', 'region' => 'Mombasa County', 'city' => 'Mombasa'],
                    default => parent::locate($ip, $cdnCountry),
                };
            }
        });
    }

    private function user(?int $companyId, string $role): User
    {
        $id = DB::table('users')->insertGetId(['name' => 'U '.$role, 'email' => uniqid().'@example.test', 'company_id' => $companyId]);
        $roleId = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
        CompanyAccess::flush();

        return User::find($id);
    }

    public function test_website_sign_ins_and_failures_are_recorded_with_their_city(): void
    {
        $admin = $this->user($this->a['company'], 'admin');
        $this->app['request']->server->set('REMOTE_ADDR', '41.90.1.1');
        event(new Login('web', $admin, false));
        $this->app['request']->server->set('REMOTE_ADDR', '105.160.2.2');
        event(new Failed('web', $admin, ['email' => $admin->email]));

        $rows = DB::table('login_events')->orderBy('id')->get();
        $this->assertSame([['Nairobi', 1, 'web', 'admin'], ['Mombasa', 0, 'web', 'admin']],
            $rows->map(fn ($r) => [$r->city, (int) $r->success, $r->channel, $r->role])->all());
        $this->assertSame((int) $this->a['company'], (int) $rows[0]->company_id);
    }

    public function test_private_addresses_have_no_location_and_a_cdn_country_is_used(): void
    {
        $geo = new GeoIp;
        $this->assertNull($geo->locate('192.168.1.10')['country_code']);
        $this->assertSame('KE', $geo->locate('10.0.0.1', 'ke')['country_code']);
        $this->assertNull($geo->locate('10.0.0.1', 'XX')['country_code']);
    }

    public function test_staff_see_the_report_and_account_managers_only_their_companies(): void
    {
        $this->app['request']->server->set('REMOTE_ADDR', '41.90.1.1');
        event(new Login('web', $this->user($this->a['company'], 'admin'), false));
        $other = $this->company('Other', [['rent_amount' => 5000]], automation: false);
        $this->app['request']->server->set('REMOTE_ADDR', '105.160.2.2');
        event(new Login('web', $this->user($other['company'], 'admin'), false));

        $this->actingAs($this->user(null, 'sysadmin'))->get('/admin/sign-ins')->assertOk()->assertSee('Nairobi')->assertSee('Mombasa')->assertSee('DB-IP');

        $am = $this->user(null, 'account_manager');
        DB::table('companies')->where('id', $this->a['company'])->update(['owner_id' => $am->id]);
        $this->actingAs($am)->get('/admin/sign-ins')->assertOk()->assertSee('Nairobi')->assertDontSee('Mombasa');

        $this->actingAs($this->user($this->a['company'], 'admin'))->get('/admin/sign-ins')->assertForbidden();
    }
}
