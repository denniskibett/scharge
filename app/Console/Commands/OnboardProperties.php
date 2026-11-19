<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Properties\PropertyOnboarding;
use Illuminate\Console\Command;

/**
 * Staff onboarding a client from the CSV template (same rules as the Import page). Dry run unless --commit.
 *   php artisan properties:onboard --template=onboarding.csv          write the blank template
 *   php artisan properties:onboard client.csv --company=12 --role=manager           preview
 *   php artisan properties:onboard client.csv --company=12 --role=manager --commit  import
 */
class OnboardProperties extends Command
{
    protected $signature = 'properties:onboard
        {file? : CSV filled from the template}
        {--company= : Company id the properties are onboarded for}
        {--role=manager : manager | owner | developer | bnb_host}
        {--commit : Save (default is a dry run)}
        {--template= : Write the blank template to this path and stop}
        {--json= : Also write the full report as JSON to this path}';

    protected $description = 'Onboard properties and units from the CSV template, matching what is already on scharge';

    public function handle(PropertyOnboarding $onboarding): int
    {
        if ($t = $this->option('template')) {
            file_put_contents($t, $onboarding->template());
            $this->info("Template written to {$t}");

            return self::SUCCESS;
        }
        $file = $this->argument('file');
        if (! $file || ! is_readable($file)) {
            $this->error('Pass the CSV file.');

            return self::FAILURE;
        }
        $company = Company::withoutGlobalScopes()->find($this->option('company'));
        if (! $company) {
            $this->error('Pass --company=<id> of an existing company.');

            return self::FAILURE;
        }
        $read = $onboarding->read($file);
        if ($read['missing']) {
            $this->error('Missing columns: '.implode(', ', $read['missing']));

            return self::FAILURE;
        }
        $report = $onboarding->run($company, (string) $this->option('role'), $read['rows'], null, (bool) $this->option('commit'));
        if (! empty($report['fatal'])) {
            $this->error($report['fatal']);

            return self::FAILURE;
        }

        $this->line(($report['commit'] ? 'IMPORTED' : 'DRY RUN (nothing saved)')." for {$company->name} as {$report['role']}");
        if ($read['unknown']) {
            $this->line('Ignored columns: '.implode(', ', $read['unknown']));
        }
        $this->table(['Building', 'Result', 'scharge id', 'Notes'], array_map(fn ($p) => [
            $p['name'].($p['location'] ? " ({$p['location']})" : ''), $p['action'], $p['id'] ?? '',
            trim(implode(' ', $p['notes']).' '.implode(' ', array_map(fn ($m) => "#{$m['id']} {$m['name']} {$m['score']}%", $p['candidates']))
                .' '.implode(' ', array_map(fn ($m) => "CRM#{$m['id']} {$m['name']}", $p['crm']))),
        ], $report['properties']));
        $this->table(['Line', 'Unit', 'Result', 'Filled', 'Kept', 'Notes'], array_map(fn ($r) => [
            $r['line'], $r['property'].' / '.$r['unit'], $r['unit_action'], implode(', ', $r['filled']),
            implode('; ', array_map(fn ($k) => "{$k['field']}: {$k['existing']} (file {$k['file']})", $r['kept'])),
            implode('; ', array_merge($r['errors'], $r['notes'])),
        ], $report['rows']));
        $this->table(['Count', 'n'], collect($report['counts'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        if ($j = $this->option('json')) {
            file_put_contents($j, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
