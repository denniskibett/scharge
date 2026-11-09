<?php

namespace App\Console\Commands;

use App\Services\KycLog;
use Illuminate\Console\Command;

/** Who is guessing email codes or flooding sign-ups? Reads storage/logs/kyc/attempts-*.jsonl. */
class KycReport extends Command
{
    protected $signature = 'kyc:report {--hours=24 : How far back to look} {--ip= : Show every event from one IP} {--json : Print the summary as JSON}';

    protected $description = 'Summarise KYC (email code / sign-up) attempts and flag likely brute-forcing';

    public function handle(): int
    {
        $rows = KycLog::read((int) $this->option('hours'));

        if ($ip = $this->option('ip')) {
            $this->table(['At', 'Event', 'User', 'Email', 'Attempt', 'Note'], array_map(fn ($r) => [
                $r['at'], $r['event'], $r['user_id'] ?? '', $r['email'] ?? '', $r['attempt'] ?? '', implode('; ', (array) ($r['reasons'] ?? $r['form'] ?? '')),
            ], array_values(array_filter($rows, fn ($r) => ($r['ip'] ?? null) === $ip))));

            return self::SUCCESS;
        }

        $by = [];
        foreach ($rows as $r) {
            $k = $r['ip'] ?? '?';
            $by[$k] ??= ['ip' => $k, 'wrong' => 0, 'ok' => 0, 'locked' => 0, 'blocked' => 0, 'signups' => 0, 'accounts' => [], 'last' => ''];
            $s = &$by[$k];
            match ($r['event']) {
                'otp.wrong' => $s['wrong']++,
                'otp.locked' => [$s['wrong']++, $s['locked']++],
                'otp.ok' => $s['ok']++,
                'otp.blocked', 'signup.blocked' => $s['blocked']++,
                'signup.ok' => $s['signups']++,
                default => null,
            };
            if (in_array($r['event'], ['otp.wrong', 'otp.locked'], true)) {
                $s['accounts'][$r['user_id'] ?? $r['email'] ?? '?'] = true;
            }
            $s['last'] = $r['at'];
            unset($s);
        }

        $g = config('kyc.guard');
        $out = array_map(function ($s) use ($g) {
            $s['accounts'] = count($s['accounts']);
            $s['verdict'] = $s['blocked'] > 0 || $s['accounts'] >= $g['ip_accounts_per_hour'] || $s['locked'] >= $g['ip_lockouts_per_day']
                ? 'BLOCK' : ($s['wrong'] > max(5, $s['ok'] * 5) ? 'watch' : 'ok');

            return $s;
        }, $by);
        usort($out, fn ($a, $b) => [$b['verdict'] === 'BLOCK', $b['wrong']] <=> [$a['verdict'] === 'BLOCK', $a['wrong']]);

        if ($this->option('json')) {
            $this->line(json_encode(array_values($out), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info(count($rows).' events in the last '.$this->option('hours').' h. Files: '.dirname(KycLog::path()));
        $this->table(['IP', 'Verdict', 'Wrong', 'OK', 'Lock-outs', 'Blocked', 'Sign-ups', 'Accounts tried', 'Last seen'],
            array_map(fn ($s) => [$s['ip'], $s['verdict'], $s['wrong'], $s['ok'], $s['locked'], $s['blocked'], $s['signups'], $s['accounts'], $s['last']], $out));

        return self::SUCCESS;
    }
}
