<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\InboundEmail;
use Illuminate\Console\Command;

/**
 * Prospect replies → CRM alerts. The mailboxes are root-only on the server, so a root cron
 * (/usr/local/bin/scharge-mailreplies) copies new messages that answer our emails, or come from an address we
 * emailed, into storage/app/inbox/<mailbox>__<id>.eml; this command logs each on the company thread, raises the
 * reply alert, and deletes the copy. It also refreshes storage/app/crm-emailed.txt, the address list the cron
 * uses to pick messages.
 */
class MailReplies extends Command
{
    protected $signature = 'crm:mail-replies {--dir= : Folder of .eml copies (default storage/app/inbox)} {--keep : Keep the copies after reading}';

    protected $description = 'Log prospect replies to cold emails and raise the reply alert on the CRM row';

    public function handle(ReplyService $replies): int
    {
        $this->writeAddressList();

        $dir = $this->option('dir') ?: storage_path('app/inbox');
        $files = is_dir($dir) ? glob($dir.'/*.eml') : [];
        $logged = 0; $skipped = 0;
        foreach ($files as $file) {
            $raw = @file_get_contents($file, false, null, 0, 5_000_000);
            if ($raw === false) {
                continue;
            }
            $mailbox = str_contains(basename($file), '__') ? explode('__', basename($file))[0] : 'sales';
            try {
                $mail = InboundEmail::parse($raw);
                [$company, $ours] = $replies->match($mail);
                if ($company && $replies->record($company, $mail, $ours, $mailbox.'@sharet.africa')) {
                    $logged++;
                    $this->line('Reply: '.$company->name.' <'.$mail->from.'> '.$mail->subject.($mail->auto ? ' (automatic)' : ''));
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->warn('Could not read '.basename($file).': '.$e->getMessage());
            }
            if (! $this->option('keep')) {
                @unlink($file);
            }
        }
        $this->info($logged.' repl'.($logged === 1 ? 'y' : 'ies').' logged, '.$skipped.' not from a prospect or already logged.');

        return self::SUCCESS;
    }

    /** Every address we cold-emailed (main and copied), one per line, for the root cron's From filter. */
    private function writeAddressList(): void
    {
        $emails = [];
        Company::withoutGlobalScope('customers')->whereNotNull('crm_profile->last_emailed_at')
            ->select(['id', 'email', 'crm_profile'])->chunkById(1000, function ($rows) use (&$emails) {
                foreach ($rows as $c) {
                    $emails[] = $c->email;
                    foreach ((array) ($c->crm_profile['emails'] ?? []) as $e) {
                        $emails[] = is_array($e) ? ($e['email'] ?? null) : $e;
                    }
                }
            });
        $emails = array_unique(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $emails), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        sort($emails);
        @file_put_contents(storage_path('app/crm-emailed.txt'), implode("\n", $emails)."\n", LOCK_EX);
    }
}
