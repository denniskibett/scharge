<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The prospect demo form: four short steps, each saved as they go (DemoRequestController::draft). */
class DemoFormStepsTest extends RentSchemaTestCase
{
    private const REF = 'SC-AB12CD';

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_profile' => 'json', 'email' => 'string', 'crm_stage' => 'string', 'tier' => 'string'] as $col => $type) {
            if (! Schema::hasColumn('companies', $col)) {
                Schema::table('companies', fn (Blueprint $t) => $t->{$type}($col)->nullable());
            }
        }
        $this->companyId = DB::table('companies')->insertGetId(['name' => 'Grey Oak Ltd', 'lifecycle' => 'prospect',
            'crm_profile' => json_encode(['demo_ref' => self::REF, 'area' => 'Kilimani'])]);
    }

    private function profile(): array
    {
        return json_decode(DB::table('companies')->where('id', $this->companyId)->value('crm_profile'), true);
    }

    public function test_each_step_is_saved_and_cleaned(): void
    {
        $this->post('/demo/'.self::REF.'/draft', [
            'step' => 2, 'role' => 'manager', 'name' => '<b>Jane</b> Wanjiru', 'phone' => '0712 345 678',
            'properties' => [['name' => 'Oak Court', 'type' => 'Not a real type', 'units' => '40']], 'channel' => 'Pigeon',
        ])->assertOk()->assertJson(['saved' => true, 'step' => 2]);

        $draft = $this->profile()['demo_draft'];
        $this->assertSame(2, $draft['step']);
        $this->assertSame('Jane Wanjiru', $draft['data']['name']);          // tags stripped
        $this->assertSame(40, $draft['data']['properties'][0]['units']);
        $this->assertNull($draft['data']['properties'][0]['type']);       // only known property types are kept
        $this->assertArrayNotHasKey('channel', $draft['data']);           // only known channels are kept
    }

    public function test_coming_back_continues_at_the_saved_step_with_answers_filled_in(): void
    {
        $this->post('/demo/'.self::REF.'/draft', ['step' => 3, 'name' => 'Jane Wanjiru', 'phone' => '0712345678'])->assertOk();

        $this->get('/demo/'.self::REF)->assertOk()
            ->assertSee('data-start="3"', false)
            ->assertSee('value="Jane Wanjiru"', false)
            ->assertSee('Your properties');
    }

    public function test_the_honeypot_saves_nothing_and_an_unknown_ref_is_404(): void
    {
        $this->post('/demo/'.self::REF.'/draft', ['website_url' => 'spam', 'name' => 'Bot'])->assertOk();
        $this->assertArrayNotHasKey('demo_draft', $this->profile());
        $this->post('/demo/SC-ZZ99ZZ/draft', ['name' => 'X'])->assertNotFound();
    }
}
