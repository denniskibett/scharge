<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Services\OutreachService;
use App\Modules\CRM\Support\EmailBody;
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
        {--follow-up=3 : Working days until the call follow-up}';

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
        $this->line(sprintf('%s audience: %s prospects with a business email, not emailed before, not opted out.', $type, number_format($base()->count())));

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

        if (! $this->option('send')) {
            $this->line('Nothing sent. Run with --test=<your email> to see samples, then --send once approved.');

            return self::SUCCESS;
        }

        $pause = max(1, (int) round(60 / max(1, (int) $this->option('per-minute'))));
        $sent = 0; $failed = 0; $seen = [];
        foreach ($base()->orderBy('tier')->orderBy('id')->limit((int) $this->option('limit'))->get() as $company) {
            $email = strtolower(trim($company->email));
            if (isset($seen[$email]) || $company->followUps()->where('type', 'email')->where('details->to', $email)->exists()) {
                continue; // one email per address, even when several branches share it
            }
            $seen[$email] = true;
            $manager = $pick($company);
            $mail = Playbook::compose($company, $manager, [], null);
            try {
                $outreach->send($company, $manager, $email, $mail['subject'], $mail['body'], (int) $this->option('follow-up'), $campaign);
                $sent++;
                $this->line('Sent '.$sent.': '.$company->name.' <'.$email.'> ('.Playbook::demoRef($company).', '.$manager->name.')');
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('Failed: '.$company->name.' <'.$email.'>: '.$e->getMessage());
            }
            sleep($pause);
        }
        $this->info(sprintf('%s campaign: %d sent, %d failed.', $campaign, $sent, $failed));

        return self::SUCCESS;
    }

    /** Prospects of these classes we may cold-email; $withEmail=false lists them all (test samples). */
    private function audience(array $classes, bool $withEmail = true): Builder
    {
        $q = EmailTracking::ready(Company::withoutGlobalScope('customers'), $withEmail)
            ->where(fn ($w) => collect($classes)->each(fn ($c) => $w->orWhere(fn ($x) => PropertyClass::scope($x, $c))));

        return $q;
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
