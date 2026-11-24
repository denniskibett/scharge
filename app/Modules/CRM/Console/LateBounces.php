<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Support\EmailFinder;
use App\Modules\CRM\Support\LateBounce;
use Illuminate\Console\Command;

/**
 * Bounce notices that came back as emails (Support\LateBounce): mark the dead address, send the email to the next
 * address on file, and (with --find) read the website of companies left without one for a new address.
 *
 *   crm:late-bounces --dry            what would be done; changes and sends nothing
 *   crm:late-bounces --find --limit=25
 */
class LateBounces extends Command
{
    protected $signature = 'crm:late-bounces {--dry : Show what would be done} {--find : Read the websites of bounced companies for another address and email it}
        {--limit=25 : Most websites to read with --find} {--owner= : Only this account manager\'s companies}';

    protected $description = 'Act on bounce notices that arrived as emails: mark dead addresses, find working ones, send again';

    public function handle(EmailFinder $finder): int
    {
        $dry = (bool) $this->option('dry');
        $owner = $this->option('owner');
        $mine = fn ($q) => $q->when($owner, fn ($w) => $w->whereIn('company_id', Company::withoutGlobalScopes()->where('owner_id', $owner)->select('id')));
        $marked = $resent = 0;
        foreach ($mine(LateBounce::pending())->orderBy('id')->cursor() as $row) {
            try {
                $done = LateBounce::handle($row, $dry);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if (! $done) {
                continue;
            }
            $marked++;
            $resent += $done['resent_to'] ? 1 : 0;
            $this->line('#'.$row->company_id.' '.$row->company?->name.': '.implode(', ', $done['failed']).' bounced'.($done['resent_to'] ? ', sent again to '.$done['resent_to'] : ''));
        }

        $found = $read = 0;
        if ($this->option('find')) {
            $companies = Company::withoutGlobalScopes()->where('lifecycle', 'prospect')->where('crm_profile->email_bounced', true)->whereNotNull('crm_profile->website')
                ->when($owner, fn ($q) => $q->where('owner_id', $owner))
                ->orderByDesc('updated_at')->limit((int) $this->option('limit'))->get();
            foreach ($companies as $company) {
                $read++;
                try {
                    $to = LateBounce::findAndResend($company, $finder, $dry);
                } catch (\Throwable $e) {
                    report($e);

                    continue;
                }
                if ($to) {
                    $found++;
                    $this->line('#'.$company->id.' '.$company->name.': '.($dry ? 'would email ' : 'emailed ').$to.' (from their website)');
                }
            }
        }
        $this->info(sprintf('%s%d bounce notice(s) acted on, %d sent again to another address on file; %d website(s) read, %d new address(es) emailed.',
            $dry ? '[dry run] ' : '', $marked, $resent, $read, $found));

        return self::SUCCESS;
    }
}
