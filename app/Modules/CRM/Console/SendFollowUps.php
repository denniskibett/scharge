<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\OutreachService;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\FollowUpEmail;
use App\Modules\CRM\Support\PropertyClass;
use App\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Second cold email: one short follow-up, in the same thread as the first email, to prospects that have not answered
 * after a few working days (clicked first, then opened, then the rest). Signed by the account manager; shares the
 * campaigns' daily cap and bounce brake, so sales@ keeps its reputation.
 *
 *   crm:follow-up                       how many are due
 *   crm:follow-up --test=me@example.com send samples to that address only
 *   crm:follow-up --send                send (the scheduler runs this in batches)
 */
class SendFollowUps extends Command
{
    protected $signature = 'crm:follow-up
        {--test= : Send samples to this address only; no prospect is emailed and nothing is logged}
        {--samples=3 : How many sample emails in test mode}
        {--send : Really send}
        {--after=4 : Working days after the first email}
        {--limit=40 : Most emails in one run}
        {--per-minute=6 : Sending pace}
        {--daily-cap= : Most campaign emails per day, first emails and follow-ups together (Nairobi day)}
        {--max-bounce=10 : Stop for the day when more than this % of today\'s campaign emails bounced (once 40 have a result)}';

    protected $description = 'Send one follow-up email, in the same thread, to cold-email prospects that have not answered';

    public function handle(OutreachService $outreach): int
    {
        $due = FollowUpEmail::due((int) $this->option('after'));
        $this->line(sprintf('Follow-ups due: %s (%s clicked, %s opened).', number_format($due->count()),
            number_format($due->filter(fn ($d) => $d['first']->detail('clicked_at'))->count()),
            number_format($due->filter(fn ($d) => $d['first']->detail('opened_at'))->count())));

        if ($to = $this->option('test')) {
            foreach ($due->take((int) $this->option('samples')) as $d) {
                $manager = $this->manager($d['company']);
                $mail = FollowUpEmail::compose($d['company'], $manager, $d['first']);
                Mail::to($to)->send(new CrmOutreachEmail('[Test follow-up] '.$mail['subject'], EmailBody::toHtml($mail['body']), $manager, $d['company']));
                $this->info('Test sent to '.$to.': '.$d['company']->name.' (#'.$d['company']->id.', signed '.$manager?->name.')');
            }

            return self::SUCCESS;
        }
        if (! $this->option('send')) {
            $this->line('Nothing sent. Run with --test=<your email> to see samples, then --send once approved.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if (($cap = $this->option('daily-cap')) !== null) {
            $today = $this->sentToday();
            $limit = min($limit, max(0, (int) $cap - $today));
            if ($limit === 0) {
                $this->line(sprintf('Follow-ups wait: today\'s cap of %d campaign emails is reached (%d sent).', $cap, $today));

                return self::SUCCESS;
            }
        }
        if ($why = $this->tooManyBounces()) {
            $this->warn('Follow-ups stopped for today: '.$why);

            return self::SUCCESS;
        }

        $pause = max(1, (int) round(60 / max(1, (int) $this->option('per-minute'))));
        $campaign = 'FOLLOWUP '.now()->format('Y-m');
        $sent = 0; $failed = 0;
        foreach ($due->take($limit) as $d) {
            $company = $d['company']->fresh();
            $mail = FollowUpEmail::compose($company, $this->manager($company), $d['first']);
            try {
                $row = $outreach->sendText($company, $d['to'], $d['cc'], $mail['subject'], $mail['body'],
                    'Follow-up to the cold email of '.$d['first']->created_at->format('j M').' (no answer yet).',
                    ['campaign' => $campaign, 'step' => FollowUpEmail::STEP, 'follow_up_of' => $d['first']->id, 'playbook' => $d['first']->detail('playbook'), 'type' => PropertyClass::of($company)],
                    null, [$d['first']->detail('message_id')]);
                if (! $row) {
                    continue;
                }
                $sent++;
                $this->line('Sent '.$sent.': '.$company->name.' <'.$d['to'].'>'.($d['cc'] ? ' cc '.implode(', ', $d['cc']) : ''));
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('Failed: '.$company->name.' <'.$d['to'].'>: '.$e->getMessage());
            }
            if ($sent && $sent % 20 === 0 && ($why = $this->tooManyBounces())) {
                $this->warn('Stopped for today: '.$why);

                break;
            }
            sleep($pause);
        }
        $this->info(sprintf('%s: %d sent, %d failed.', $campaign, $sent, $failed));

        return self::SUCCESS;
    }

    private function manager(Company $company): ?User
    {
        return ($company->owner_id ? User::find($company->owner_id) : null)
            ?? User::whereHas('roles', fn ($r) => $r->where('name', 'sysadmin'))->orderBy('id')->first();
    }

    /** Campaign emails sent so far today (Nairobi day): first emails and follow-ups. */
    private function sentToday(): int
    {
        $start = now(config('app.schedule_timezone', 'Africa/Nairobi'))->startOfDay()->utc();

        return CrmFollowUp::where('type', 'email')->whereNotNull('details->campaign')->where('created_at', '>=', $start)->count();
    }

    private function tooManyBounces(): ?string
    {
        $start = now(config('app.schedule_timezone', 'Africa/Nairobi'))->startOfDay()->utc();
        $emails = CrmFollowUp::where('type', 'email')->whereNotNull('details->campaign')->where('created_at', '>=', $start)
            ->whereNotNull('details->delivery')->get(['details']);
        if ($emails->count() < 40) {
            return null;
        }
        $bounced = $emails->filter(fn ($e) => $e->detail('delivery') === 'bounced')->count();
        $rate = $bounced / $emails->count() * 100;

        return $rate > (float) $this->option('max-bounce')
            ? sprintf('%d of %d emails with a result bounced today (%.1f%%, limit %s%%).', $bounced, $emails->count(), $rate, $this->option('max-bounce'))
            : null;
    }
}
