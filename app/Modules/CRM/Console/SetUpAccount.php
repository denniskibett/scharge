<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Services\DemoOnboarding;
use Illuminate\Console\Command;

/** Done-for-you onboarding from the command line (the CRM page has the same button: Onboarding › Set up their account). */
class SetUpAccount extends Command
{
    protected $signature = 'crm:set-up {company* : CRM company ids} {--no-send : Set up and build the PDF, but do not email them}';

    protected $description = 'Set a demo company\'s account up from the demo data and email the welcome link and onboarding to-do PDF';

    public function handle(DemoOnboarding $onboarding): int
    {
        foreach ($this->argument('company') as $id) {
            $company = Company::withoutGlobalScope('customers')->find($id);
            if (! $company) {
                $this->warn("#{$id}: not found");

                continue;
            }
            if ($company->profile('setup.started_at')) {
                $this->warn("#{$id} {$company->name}: already set up on ".$company->profile('setup.started_at'));

                continue;
            }
            $r = $onboarding->setUp($company, null, ! $this->option('no-send'));
            $this->info(sprintf('#%d %s: %s; %s; sent: %s%s', $company->id, $company->name,
                $r['user'] ? 'login '.$r['user']->email : 'no login',
                collect($r['properties'])->map(fn ($p) => $p['name'].' ('.$p['status'].')')->join(', ') ?: 'no properties',
                $r['sent'] ? 'yes' : 'no', $r['problems'] ? ' | check: '.implode(' ', $r['problems']) : ''));
            $this->line('  PDF: '.$r['pdf']);
        }

        return self::SUCCESS;
    }
}
