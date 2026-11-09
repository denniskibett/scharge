<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Setup › Server security: every domain and site on the VPS, from the defense agent's latest report
 * (ops/defense-agent, published to config('server_security.report_dir')). Read-only; sysadmin only.
 */
class ServerSecurityController extends Controller
{
    public const SEV = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'ok' => 0];

    public function index(Request $request)
    {
        $dir = rtrim((string) config('server_security.report_dir'), '/');
        $report = $this->json("$dir/latest.json");
        if (! $report) {
            return view('admin.server-security.index', ['report' => null, 'dir' => $dir]);
        }
        $history = collect($this->json("$dir/history.json") ?? [])->take(-30)->values();
        $findings = collect($report['findings'] ?? []);

        // One row per site / domain: worst severity, counts, and the headline facts
        $names = collect(array_keys($report['sites'] ?? []))->merge(array_keys($report['domains'] ?? []))->unique();
        $rows = $names->map(function ($name) use ($report, $findings) {
            $mine = $findings->where('scope', $name);
            $site = $report['sites'][$name] ?? [];
            $dns = $report['domains'][$name] ?? [];
            $worst = $mine->sortByDesc(fn ($f) => self::SEV[$f['severity']])->first()['severity'] ?? 'ok';

            return [
                'name' => $name,
                'worst' => $worst,
                'counts' => $mine->countBy('severity')->all(),
                'mail_host' => (bool) ($site['mail_host'] ?? str_starts_with($name, 'mail.')),
                'resolves' => isset($dns['a']) ? ! empty($dns['a']) : null,
                'tls_days' => $site['tls']['days_left'] ?? null,
                'tls_ok' => $site['tls']['verified'] ?? null,
                'issuer' => $site['tls']['issuer'] ?? null,
                'http' => $site['http']['status'] ?? null,
                'spf' => array_key_exists('spf', $dns) ? (bool) $dns['spf'] : null,
                'dkim' => $dns['dkim'] ?? null,
                'dmarc' => array_key_exists('dmarc', $dns) ? (bool) $dns['dmarc'] : null,
            ];
        })->sortBy([fn ($a, $b) => self::SEV[$b['worst']] <=> self::SEV[$a['worst']], fn ($a, $b) => strcmp($a['name'], $b['name'])])->values();

        $sev = $request->query('severity');
        $scope = $request->query('scope');
        $list = $findings
            ->when($sev, fn ($c) => $c->where('severity', $sev))
            ->when($scope, fn ($c) => $c->where('scope', $scope))
            ->values();

        return view('admin.server-security.index', [
            'report' => $report, 'dir' => $dir, 'history' => $history, 'rows' => $rows, 'findings' => $list,
            'server' => $report['server_checks'] ?? [], 'severity' => $sev, 'scope' => $scope,
            'stale' => \Carbon\Carbon::parse($report['generated_at'])->lt(now()->subHours(30)),
        ]);
    }

    private function json(string $path): ?array
    {
        if (! is_readable($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
