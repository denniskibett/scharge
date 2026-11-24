<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\OutreachService;
use Illuminate\Console\Command;

/**
 * The morning someone is back from leave (their automatic reply said when), the last cold email goes to them again.
 * Rows are made by ReplyService::autoReply (type email, status scheduled, details.resend_on_return). Skipped when
 * they have written back since, or asked not to be emailed. Runs at 08:00 Nairobi.
 */
class ResendReturning extends Command
{
    protected $signature = 'crm:resend-returning {--dry-run : List what is due, send nothing}';

    protected $description = 'Send the cold email again to people whose out-of-office reply said they are back today';

    public function handle(OutreachService $outreach): int
    {
        $due = CrmFollowUp::where('type', 'email')->where('status', 'scheduled')->where('details->resend_on_return', true)
            ->where('due_at', '<=', now())->with('company')->orderBy('id')->limit(200)->get();
        $sent = 0; $skipped = 0;
        foreach ($due as $task) {
            $company = $task->company;
            $to = (string) $task->detail('to');
            $repliedSince = $company && $company->followUps()->where('type', 'email')->where('details->direction', 'in')
                ->whereNull('details->auto')->where('id', '>', (int) $task->detail('reply_id'))->exists();
            if (! $company || $repliedSince || $company->profile('do_not_email')) {
                $task->update(['status' => 'cancelled', 'notes' => trim($task->notes."\n\nNot sent: ".(! $company ? 'the company is gone.' : ($repliedSince ? 'they wrote back before then.' : 'they asked not to be emailed.')))]);
                $skipped++;

                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("due: #{$task->id} {$company->name} → {$to}");

                continue;
            }
            try {
                $row = $outreach->resend($company, $to, [], 'Sent again on the morning '.$to.' is back (their automatic reply gave the date).');
                $task->update(['status' => $row ? 'done' : 'cancelled', 'done_at' => now()]);
                $row ? $sent++ : $skipped++;
            } catch (\Throwable $e) {
                report($e); // left scheduled: tried again on the next run
            }
        }
        $this->info("Sent {$sent}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
