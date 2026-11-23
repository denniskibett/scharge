<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Support\Onboarding;
use Illuminate\Console\Command;

/**
 * Daily: every customer still setting up is checked against its manual. One that has sat on the same step for
 * three days gets a call on its account manager's calendar naming the step (and an account manager, if it had none).
 *
 *   crm:onboarding-stalls --dry   show what would be raised; changes nothing
 */
class OnboardingStalls extends Command
{
    protected $signature = 'crm:onboarding-stalls {--dry : Show what would be raised without creating tasks}';

    protected $description = 'Raise a call for the account manager of each customer stuck on a setup step';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $rows = [];
        foreach (Onboarding::companies() as $company) {
            try {
                $step = Onboarding::step($company);
                $task = Onboarding::check($company, $dry);
            } catch (\Throwable $e) {
                report($e);
                $rows[] = [$company->id, $company->name, 'error', substr($e->getMessage(), 0, 60)];

                continue;
            }
            $rows[] = [$company->id, $company->name, $step['key'] ?? 'set up',
                $task ? ($dry ? 'would raise' : 'raised').' for '.($task->assignee?->name ?? ($task->assigned_to ? 'user '.$task->assigned_to : 'nobody (no account manager)')) : '-'];
        }
        $this->table(['#', 'Company', 'Step', 'Task'], $rows);

        return self::SUCCESS;
    }
}
