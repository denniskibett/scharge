<?php

namespace Tests\Feature\Kyc;

use App\Services\EmailOtp;
use App\Services\KycLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KycLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['kyc.guard.log_dir' => sys_get_temp_dir().'/scharge-kyc-test']);
        File::delete(KycLog::path());
        Cache::flush();
    }

    private function user(int $id): object
    {
        return new class($id) {
            public $email_confirmed_at;
            public string $email;
            public function __construct(public int $id) { $this->email = "person{$id}@sharet.africa"; }
            public function forceFill(array $a) { foreach ($a as $k => $v) { $this->$k = $v; } return $this; }
            public function save() { return true; }
        };
    }

    private function seedCode($user, string $code = '123456'): void
    {
        Cache::put('email-otp:'.$user->id, ['hash' => Hash::make($code), 'email' => $user->email, 'expires' => time() + 600, 'attempts' => 0, 'sent_at' => time()], 600);
    }

    public function test_attempts_are_written_as_json_lines_without_the_code(): void
    {
        $u = $this->user(1);
        $this->seedCode($u);
        EmailOtp::verify($u, '000000');
        EmailOtp::verify($u, '123456');

        $rows = KycLog::read(1);
        $this->assertSame(['otp.wrong', 'otp.ok'], array_column($rows, 'event'));
        $this->assertSame('person1@sharet.africa', $rows[0]['email']);
        $this->assertStringNotContainsString('000000', File::get(KycLog::path()));
        $this->assertStringNotContainsString('123456', File::get(KycLog::path()));
    }

    public function test_guessing_across_many_accounts_blocks_the_network(): void
    {
        foreach (range(1, 5) as $id) {
            $u = $this->user($id);
            $this->seedCode($u);
            EmailOtp::verify($u, '999999');
        }
        $v = KycLog::verdict();
        $this->assertFalse($v['allow']);

        // The network is now refused even with the right code on a fresh account.
        $u = $this->user(6);
        $this->seedCode($u);
        $this->assertFalse(EmailOtp::verify($u, '123456')['ok']);
        $this->assertSame('otp.blocked', last(KycLog::read(1))['event']);
    }

    public function test_one_account_resending_and_retrying_is_stopped(): void
    {
        config(['kyc.guard.ip_wrong_per_hour' => 1000, 'kyc.guard.ip_lockouts_per_day' => 1000]);
        $u = $this->user(7);
        foreach (range(1, 3) as $round) {
            $this->seedCode($u);
            foreach (range(1, 5) as $i) {
                EmailOtp::verify($u, '00000'.$i);
            }
        }
        $this->assertFalse(KycLog::verdict($u)['allow']);
        $this->assertTrue(KycLog::verdict($this->user(8))['allow']);
    }

    public function test_sign_up_is_refused_from_a_flagged_network(): void
    {
        foreach (range(1, 5) as $id) {
            KycLog::record('otp.wrong', $this->user($id));
        }
        $this->post('/register', ['name' => 'X', 'email' => 'new@sharet.africa', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertSame('signup.blocked', last(KycLog::read(1))['event']);
        $this->assertGuest();
    }
}
