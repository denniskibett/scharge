<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Services\OutreachService;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\EmailFinder;
use App\Modules\CRM\Support\EmailTracking;
use App\Modules\CRM\Support\Playbook;
use App\Modules\CRM\Support\PropertyClass;
use App\Modules\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * Cold-email campaign for one property type, from sales@sharet.africa, signed by each company's account manager.
 *
 *   crm:campaign BNB                               how many prospects it would reach (nothing is sent)
 *   crm:campaign BNB --test=me@example.com         sample emails to that address only, nothing logged
 *   crm:campaign RESIDENTIAL --send --limit=200    really send (only after the test was approved)
 *
 * Each email is researched-style (the segment's likely problems), carries the company's demo link
 * (/demo/BNB-XXXXXX, /demo/RESIDENTIAL-XXXXXX, …) and is logged with a call follow-up for the account manager, like a Playbook send.
 */
class SendCampaign extends Command
{
    protected $signature = 'crm:campaign
        {type : BNB, RESIDENTIAL, COMMERCIAL, UTILITIES, MIXED or LAND}
        {--test= : Send samples to this address only; no prospect is emailed and nothing is logged}
        {--samples=2 : How many sample emails in test mode}
        {--send : Really send to the prospects}
        {--limit=100 : Most emails in one run}
        {--per-minute=10 : Sending pace}
        {--manager= : User id that takes unowned prospects (default: account managers in turn)}
        {--follow-up=3 : Working days until the call follow-up}
        {--cc=all : Copy the company\'s other published addresses (crm_profile.emails): all, or none}
        {--after= : Wait for these types first (comma list, e.g. RESIDENTIAL): send nothing while any of them still has prospects to email}
        {--daily-cap= : Most campaign emails per day, all types together (Nairobi day); the ramp in routes/console.php sets it}
        {--max-bounce=10 : Stop for the day when more than this % of today\'s campaign emails bounced (once 40 have a result)}';

    protected $description = 'Cold-email one property type from sales@sharet.africa (test first, then send)';

    public function handle(OutreachService $outreach): int
    {
        $type = strtoupper($this->argument('type'));
        $classes = array_keys(Playbook::REF_PREFIX, $type, true);
        if (! $classes) {
            $this->error('Unknown type '.$type.'. Use one of: '.implode(', ', array_unique(Playbook::REF_PREFIX)));

            return self::FAILURE;
        }
        $campaign = $type.' '.now()->format('Y-m');
        $base = fn () => $this->audience($classes);
        $this->line(sprintf('%s audience: %s prospects with a business email, not emailed before, not opted out (%s with extra addresses to CC).', $type,
            number_format($base()->count()), number_format($base()->whereNotNull('crm_profile->emails')->where('crm_profile->emails', '!=', '[]')->count())));

        $managers = $this->managers();
        if (! $managers) {
            $this->error('No account manager to sign the emails. Pass --manager=<user id>.');

            return self::FAILURE;
        }
        $turn = 0;
        $pick = function (Company $c) use ($managers, &$turn) {
            return $c->owner_id && ($owner = User::find($c->owner_id)) ? $owner : $managers[$turn++ % count($managers)];
        };

        if ($to = $this->option('test')) {
            $samples = $base()->inRandomOrder()->limit((int) $this->option('samples'))->get();
            if ($samples->isEmpty()) {
                $samples = $this->audience($classes, false)->inRandomOrder()->limit((int) $this->option('samples'))->get();
                $this->warn('No '.$type.' prospect has an email yet; the samples use prospects without one.');
            }
            foreach ($samples as $company) {
                $manager = $pick($company);
                $mail = Playbook::compose($company, $manager, [], null);
                Mail::to($to)->send(new CrmOutreachEmail('[Test '.$type.'] '.$mail['subject'], EmailBody::toHtml($mail['body']), $manager, $company));
                $this->info('Test sent to '.$to.': '.$company->name.' (#'.$company->id.', ref '.Playbook::demoRef($company).', signed '.$manager->name.')');
            }

            return self::SUCCESS;
        }

        // Priority: residential prospects are the core market, so the other types wait until that audience is used up.
        foreach (array_filter(array_map('trim', explode(',', strtoupper((string) $this->option('after'))))) as $first) {
            $firstClasses = array_keys(Playbook::REF_PREFIX, $first, true);
            if ($firstClasses && ($left = $this->audience($firstClasses)->count()) > 0) {
                $this->line(sprintf('%s waits: %s %s prospects still to email first.', $type, number_format($left), $first));

                return self::SUCCESS;
            }
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
                $this->line(sprintf('%s waits: today\'s cap of %d campaign emails is reached (%d sent).', $type, $cap, $today));

                return self::SUCCESS;
            }
        }
        if ($why = $this->tooManyBounces()) {
            $this->warn($type.' stopped for today: '.$why);

            return self::SUCCESS;
        }

        $pause = max(1, (int) round(60 / max(1, (int) $this->option('per-minute'))));
        $sent = 0; $failed = 0; $skipped = 0; $seen = [];
        foreach ($base()->orderBy('tier')->orderBy('id')->limit($limit + 50)->get() as $company) {
            if ($sent >= $limit) {
                break;
            }
            $email = strtolower(trim($company->email));
            if (isset($seen[$email]) || \App\Modules\CRM\Models\CrmFollowUp::where('type', 'email')->where('details->to', $email)->exists()) {
                // One email per address, even when several branches share it. Marked, so it leaves the audience
                // (otherwise it would hold up the types waiting with --after).
                $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['email_shared' => true])])->saveQuietly();

                continue;
            }
            if (! $this->domainTakesMail($email)) {
                // A domain with no mail server can only bounce, and bounces hurt sales@'s reputation: back to Unsent instead.
                $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['email_no_mx' => true])])->saveQuietly();
                $skipped++;
                $this->line('Skipped (domain takes no mail): '.$company->name.' <'.$email.'>');

                continue;
            }
            if ($sent && $sent % 20 === 0 && ($why = $this->tooManyBounces())) {
                $this->warn('Stopped for today: '.$why);

                break;
            }
            $seen[$email] = true;
            // Still one email per company: its other published addresses are copied, unless another company already got them.
            $cc = $this->option('cc') === 'none' ? [] : array_values(array_filter(EmailFinder::ccFor($company), fn ($e) => ! isset($seen[$e])));
            foreach ($cc as $e) {
                $seen[$e] = true;
            }
            $manager = $pick($company);
            $mail = Playbook::compose($company, $manager, [], null);
            try {
                $outreach->send($company, $manager, $email, $mail['subject'], $mail['body'], (int) $this->option('follow-up'), $campaign, $cc);
                $sent++;
                $this->line('Sent '.$sent.': '.$company->name.' <'.$email.'>'.($cc ? ' cc '.implode(', ', $cc) : '').' ('.Playbook::demoRef($company).', '.$manager->name.')');
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('Failed: '.$company->name.' <'.$email.'>: '.$e->getMessage());
            }
            sleep($pause);
        }
        $this->info(sprintf('%s campaign: %d sent, %d failed, %d skipped (no mail server).', $campaign, $sent, $failed, $skipped));

        return self::SUCCESS;
    }

    /** Prospects of these classes we may cold-email; $withEmail=false lists them all (test samples). */
    private function audience(array $classes, bool $withEmail = true): Builder
    {
        $q = EmailTracking::ready(Company::withoutGlobalScope('customers'), $withEmail)
            ->where(fn ($w) => collect($classes)->each(fn ($c) => $w->orWhere(fn ($x) => PropertyClass::scope($x, $c))));

        return $q;
    }

    /** Campaign emails sent so far today (Nairobi day), all types. */
    private function sentToday(): int
    {
        $start = now(config('app.schedule_timezone', 'Africa/Nairobi'))->startOfDay()->utc();

        return \App\Modules\CRM\Models\CrmFollowUp::where('type', 'email')->whereNotNull('details->campaign')->where('created_at', '>=', $start)->count();
    }

    /** Why to stop, when too many of today's campaign emails bounced; null when sending may go on. */
    private function tooManyBounces(): ?string
    {
        $start = now(config('app.schedule_timezone', 'Africa/Nairobi'))->startOfDay()->utc();
        $emails = \App\Modules\CRM\Models\CrmFollowUp::where('type', 'email')->whereNotNull('details->campaign')->where('created_at', '>=', $start)
            ->whereNotNull('details->delivery')->get(['details']); // only those postfix has a result for
        if ($emails->count() < 40) {
            return null;
        }
        $bounced = $emails->filter(fn ($e) => $e->detail('delivery') === 'bounced')->count();
        $rate = $bounced / $emails->count() * 100;

        return $rate > (float) $this->option('max-bounce')
            ? sprintf('%d of %d emails with a result bounced today (%.1f%%, limit %s%%). Clean the list on CRM › Campaigns before sending more.', $bounced, $emails->count(), $rate, $this->option('max-bounce'))
            : null;
    }

    private array $mx = [];

    private function domainTakesMail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return false;
        }

        return $this->mx[$domain] ??= (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'));
    }

    /** @return array<int, User> */
    private function managers(): array
    {
        if ($id = $this->option('manager')) {
            return array_filter([User::find($id)]);
        }
        $ams = User::whereHas('roles', fn ($q) => $q->where('name', 'account_manager'))->orderBy('id')->get()->all();

        return $ams ?: User::whereHas('roles', fn ($q) => $q->where('name', 'sysadmin'))->orderBy('id')->limit(1)->get()->all();
    }
}
