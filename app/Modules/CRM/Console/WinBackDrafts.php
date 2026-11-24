<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Support\ReplyIntent;
use App\Modules\CRM\Support\WinBack;
use Illuminate\Console\Command;

/**
 * Weekday mornings: leads that turned us down and have been quiet for crm.winback.after_days get a value-led email
 * drafted on their page for the account manager to approve, edit or skip. Nothing is sent here.
 */
class WinBackDrafts extends Command
{
    protected $signature = 'crm:winback {--dry-run : List who is due, draft nothing}';

    protected $description = 'Draft win-back emails for leads that turned us down, for their account manager to approve';

    public function handle(): int
    {
        $due = WinBack::due();
        foreach ($due as $row) {
            $this->line(sprintf('%s #%d %s → %s (%s)', $this->option('dry-run') ? 'due' : 'drafted', $row['company']->id, $row['company']->name, $row['to'],
                ReplyIntent::LABELS[$row['reason']] ?? 'Lost'));
            if (! $this->option('dry-run')) {
                WinBack::queue($row['company'], $row['reason'], $row['to']);
            }
        }
        $this->info(($this->option('dry-run') ? 'Due: ' : 'Drafted: ').$due->count());

        return self::SUCCESS;
    }
}
