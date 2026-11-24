<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\ReplyIntent;
use Illuminate\Console\Command;

/**
 * Read what each company's latest real reply says (ReplyIntent) for replies logged before replies were read
 * (24 Nov 2026), or again after RULES change. A seller moves to the Land & home sales playbook. Each reply is read once
 * unless --again.
 */
class ReadReplies extends Command
{
    protected $signature = 'crm:read-replies {--dry-run : Show what each reply reads as, change nothing} {--again : Read replies already read}';

    protected $description = 'Read what prospects\' replies say: seller, not interested, has a system, price, later, interested';

    public function handle(): int
    {
        $latest = CrmFollowUp::where('type', 'email')->where('details->direction', 'in')->whereNull('details->auto')->whereNotNull('company_id')
            ->selectRaw('MAX(id) as id')->groupBy('company_id')->pluck('id');
        $counts = [];
        foreach (CrmFollowUp::whereIn('id', $latest)->with('company')->orderBy('id')->get() as $reply) {
            $company = $reply->company;
            if (! $company || (! $this->option('again') && (int) $company->profile('reply_intent.follow_up_id') === $reply->id)) {
                continue;
            }
            $intent = $this->option('dry-run') ? ReplyIntent::read((string) $reply->notes) : ReplyIntent::apply($company, $reply);
            $counts[$intent ?? 'unclear'] = ($counts[$intent ?? 'unclear'] ?? 0) + 1;
            $this->line(sprintf('#%d %s (%s): %s', $reply->id, $company->name, $reply->detail('from'), $intent ? ReplyIntent::LABELS[$intent] : 'unclear'));
        }
        ksort($counts);
        $this->info(($this->option('dry-run') ? 'Would read: ' : 'Read: ').json_encode($counts));

        return self::SUCCESS;
    }
}
