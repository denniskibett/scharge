<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use Illuminate\Console\Command;

/**
 * Reads postfix's delivery results for our cold emails and records them on each email follow-up:
 * delivered (their mail server accepted it), delayed (still retrying) or bounced (refused).
 * A bounce marks the company's email as bad, so it goes back to the Unsent list.
 *
 * The mail log is root-only, so a root cron copies the postfix lines to storage/app/maillog.txt first
 * (see /etc/cron.d/scharge-mail-delivery on the server) and then runs this command as the site user.
 */
class MailDelivery extends Command
{
    protected $signature = 'crm:mail-delivery {--log= : Postfix log extract (default storage/app/maillog.txt)}';

    protected $description = 'Record delivered / delayed / bounced for cold emails from the postfix log';

    public function handle(ContactService $contacts): int
    {
        $path = $this->option('log') ?: storage_path('app/maillog.txt');
        if (! is_readable($path)) {
            $this->warn('No mail log extract at '.$path);

            return self::SUCCESS;
        }
        $queue = []; // postfix queue id => message-id
        $result = []; // message-id => [status, recipient, reply, time]
        foreach (new \SplFileObject($path) as $line) {
            if (preg_match('/postfix\/cleanup\[\d+\]: ([0-9A-F]+): message-id=<([^>]+)>/', $line, $m)) {
                $queue[$m[1]] = $m[2];
            } elseif (preg_match('/^(\w{3}\s+\d+ [\d:]+) .*postfix\/(?:smtp|error|local|virtual)\[\d+\]: ([0-9A-F]+): to=<([^>]+)>.*status=(sent|deferred|bounced) \((.*)\)\s*$/', $line, $m)) {
                if (isset($queue[$m[2]])) { // per recipient: a CC'd address has its own result
                    $rcpt = strtolower($m[3]);
                    $prev = $result[$queue[$m[2]]][$rcpt][0] ?? null;
                    if ($prev !== 'sent' && $prev !== 'bounced') { // final results win over "deferred"
                        $result[$queue[$m[2]]][$rcpt] = [$m[4], $m[3], mb_substr($m[5], 0, 300), $m[1]];
                    }
                }
            }
        }

        $updated = 0;
        CrmFollowUp::where('type', 'email')->whereNotNull('details->message_id')
            ->where(fn ($q) => $q->whereNull('details->delivery')->orWhere('details->delivery', 'deferred'))
            ->where('created_at', '>=', now()->subDays(14))
            ->with('company')->chunkById(200, function ($emails) use ($result, $contacts, &$updated) {
                foreach ($emails as $email) {
                    $id = trim((string) $email->detail('message_id'), '<>');
                    if (! isset($result[$id])) {
                        continue;
                    }
                    $byRcpt = $result[$id];
                    $primary = strtolower((string) $email->detail('to'));
                    [$status, $to, $reply, $at] = $byRcpt[$primary] ?? reset($byRcpt);
                    $ccStatus = array_map(fn ($r) => $r[0], array_diff_key($byRcpt, [$primary => 1]));
                    $email->forceFill(['details' => array_merge($email->details ?? [], array_filter([
                        'delivery' => $status, 'delivery_reply' => $reply, 'delivery_at' => $at, 'cc_delivery' => $ccStatus ?: null,
                    ]))])->saveQuietly();
                    $updated++;
                    // A copied address that bounced leaves the company's list; the email itself counts by the main recipient.
                    foreach ($byRcpt as $rcpt => [$st, $addr, $why]) {
                        if ($rcpt !== $primary && $st === 'bounced' && $email->company) {
                            $c = $email->company->fresh();
                            $p = $c->crm_profile ?? [];
                            $p['emails'] = array_values(array_filter($p['emails'] ?? [], fn ($e) => strtolower($e['email'] ?? '') !== $rcpt));
                            $p['bounced_emails'] = array_values(array_unique(array_merge($p['bounced_emails'] ?? [], [$rcpt])));
                            $c->forceFill(['crm_profile' => $p])->saveQuietly();
                            $contacts->log($c, 'note', 'Copy to '.$addr.' bounced', 'Their mail server refused it: '.$why.' The address was removed from the company\'s extra emails.');
                        }
                    }
                    if ($status === 'bounced' && $email->company) {
                        $c = $email->company;
                        $c->forceFill(['crm_profile' => array_merge($c->crm_profile ?? [], ['email_bounced' => true])])->saveQuietly();
                        $contacts->log($c, 'note', 'Email to '.$to.' bounced', 'Their mail server refused it: '.$reply.' Find a working email or call them; the company is back on the Unsent list.');
                    }
                }
            });
        $this->info($updated.' email(s) updated from the mail log.');

        return self::SUCCESS;
    }
}
