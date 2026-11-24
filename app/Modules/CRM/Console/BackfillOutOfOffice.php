<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\InboundEmail;
use App\Modules\CRM\Support\OutOfOffice;
use Illuminate\Console\Command;

/**
 * Automatic replies logged before out-of-office handling existed (24 Nov 2026): each is read again as if it had just
 * arrived on its own date. Offered addresses get our email now; a return date still ahead is scheduled, one already
 * passed goes at the next 08:00 run. Skips replies already handled and companies that have written back since.
 */
class BackfillOutOfOffice extends Command
{
    protected $signature = 'crm:backfill-out-of-office {--dry-run : Show what would happen, send nothing}';

    protected $description = 'Act on automatic replies received before out-of-office handling existed';

    public function handle(ReplyService $replies): int
    {
        $rows = CrmFollowUp::where('type', 'email')->where('details->auto', true)->whereNull('details->out_of_office')
            ->whereNotNull('company_id')->with('company')->orderBy('id')->get();
        $acted = 0;
        foreach ($rows as $row) {
            $company = $row->company;
            if (! $company) {
                continue;
            }
            $mail = new InboundEmail;
            $mail->from = (string) $row->detail('from');
            $mail->fromName = (string) $row->detail('from_name');
            $mail->subject = (string) $row->detail('subject');
            $mail->text = (string) $row->notes;
            $mail->auto = true;
            if (OutOfOffice::isBounce($mail->subject, $mail->from)) {
                continue;
            }
            $wroteBack = $company->followUps()->where('type', 'email')->where('details->direction', 'in')->whereNull('details->auto')->where('id', '>', $row->id)->exists();
            if ($wroteBack) {
                continue;
            }
            if ($this->option('dry-run')) {
                $read = OutOfOffice::read($mail, $row->created_at, [$company->email]);
                if ($read['alternates'] || $read['return_on']) {
                    $this->line("#{$row->id} {$company->name} ({$mail->from}): ".implode(', ', $read['alternates']).($read['return_on'] ? ' back '.$read['return_on']->toDateString() : ''));
                    $acted++;
                }

                continue;
            }
            $done = $replies->autoReply($company, $mail, $row, $row->created_at);
            if ($done['alternates'] || $done['return_on']) {
                $this->line("#{$row->id} {$company->name}: ".($done['alternates'] ? 'emailed '.implode(', ', $done['alternates']) : '').($done['return_on'] ? ' resend '.$done['return_on'] : ''));
                $acted++;
            }
        }
        $this->info(($this->option('dry-run') ? 'Would act on ' : 'Acted on ').$acted.' of '.$rows->count().' automatic replies.');

        return self::SUCCESS;
    }
}
