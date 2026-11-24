<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\OutreachService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * A bounce that arrives as an email. Many mail servers accept our message and only then send back "address not
 * found" (Google, Office 365, cPanel). The postfix log (crm:mail-delivery) shows those as delivered, so the dead
 * address stayed on the company and the account manager kept waiting. Here the notice is read: the address is marked
 * as bounced, and the email goes again to the company's next known address when it has one. A company left with no
 * working address gets its website read for another (crm:late-bounces --find), and the email goes there.
 *
 * What was done is kept on the notice (details.bounce), so nothing is acted on twice.
 */
class LateBounce
{
    /** Stored bounce notices not acted on yet. */
    public static function pending(): Builder
    {
        return CrmFollowUp::where('type', 'email')->where('details->direction', 'in')->where('details->auto', true)->whereNull('details->bounce');
    }

    /**
     * @return array{failed: array<int,string>, primary: bool, resent_to: ?string}|null  null: not a bounce, or done before
     */
    public static function handle(CrmFollowUp $row, bool $dry = false): ?array
    {
        $subject = (string) $row->detail('subject');
        if ($row->detail('bounce') || ! $row->company || ! OutOfOffice::isBounce($subject, strtolower((string) $row->detail('from')))) {
            return null;
        }
        if (preg_match('/delay/i', $subject)) {   // "delivery delayed": their server is still trying
            return null;
        }
        $company = $row->company->fresh();
        $ours = ($id = $row->detail('answers')) ? $company->followUps()->where('type', 'email')->where('details->message_id', $id)->first() : null;
        $ours ??= EmailTracking::latestFor($company);
        $to = Str::lower(trim((string) ($ours?->detail('to') ?: $company->email)));
        $cc = array_map(fn ($e) => Str::lower(trim((string) $e)), (array) ($ours?->detail('cc') ?: []));
        preg_match_all('/[a-z0-9][a-z0-9._%+-]*@[a-z0-9-]+(?:\.[a-z0-9-]+)+/i', (string) $row->notes, $m);
        $mentioned = array_unique(array_map(fn ($e) => Str::lower(rtrim($e, '.')), $m[0]));
        $failedCc = array_values(array_intersect($cc, $mentioned));
        // Named, or nothing we wrote to is named (the notice quotes an alias or a group): it is the main address.
        $primary = $to !== '' && (in_array($to, $mentioned, true) || ! $failedCc);
        $done = ['failed' => array_values(array_filter(array_merge($primary ? [$to] : [], $failedCc))), 'primary' => $primary, 'resent_to' => null];
        if ($dry) {
            return $done;
        }

        $contacts = app(ContactService::class);
        $profile = $company->crm_profile ?? [];
        $profile['emails'] = array_values(array_filter($profile['emails'] ?? [], fn ($e) => ! in_array(Str::lower($e['email'] ?? ''), $done['failed'], true)));
        $profile['bounced_emails'] = array_values(array_unique(array_merge($profile['bounced_emails'] ?? [], $done['failed'])));
        $current = Str::lower(trim((string) $company->email)) === $to;
        if ($primary && $current) {
            $profile['email_bounced'] = true;
        }
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        foreach ($failedCc as $addr) {
            $contacts->log($company, 'note', 'Copy to '.$addr.' bounced', 'Their mail server returned it. The address was removed from the company\'s extra emails.');
        }
        if ($primary) {
            if ($ours) {
                $ours->forceFill(['details' => array_merge($ours->details ?? [], ['delivery' => 'bounced', 'delivery_reply' => 'Returned by their mail server after it was accepted.', 'delivery_at' => now()->toDateTimeString()])])->saveQuietly();
            }
            $contacts->log($company, 'note', 'Email to '.$to.' bounced', 'Their mail server accepted it, then returned it: the address does not work.');
            if ($current) {
                $done['resent_to'] = self::useNextAddress($company->fresh(), $to);
            }
        }
        $row->forceFill(['details' => array_merge($row->details ?? [], ['bounce' => $done])])->saveQuietly();

        return $done;
    }

    /** The company's next known address becomes its email and gets the cold email. Null when it has no other. */
    public static function useNextAddress(Company $company, string $bounced): ?string
    {
        $next = EmailFinder::ccFor($company)[0] ?? null;
        if (! $next || $company->profile('do_not_email')) {
            return null;
        }
        $profile = $company->crm_profile ?? [];
        $profile['emails'] = array_values(array_filter($profile['emails'] ?? [], fn ($e) => Str::lower($e['email'] ?? '') !== $next));
        $profile['email_bounced'] = false;
        $company->forceFill(['email' => $next, 'crm_profile' => $profile])->saveQuietly();

        return self::resend($company->fresh(), $next, 'Sent again: '.$bounced.' bounced, so it went to the next address on file.');
    }

    /**
     * For a company whose only address bounced: read its website for another, save it, and send the email there.
     * Returns the new address, or null when the site offers nothing new.
     */
    public static function findAndResend(Company $company, EmailFinder $finder, bool $dry = false): ?string
    {
        $company = $company->fresh();
        $website = $company->profile('website');
        if (! $company->profile('email_bounced') || $company->profile('do_not_email') || ! $website) {
            return null;
        }
        $dead = array_map('strtolower', array_merge([(string) $company->email], (array) $company->profile('bounced_emails', [])));
        $found = array_values(array_filter($finder->fromWebsite($website), fn ($e) => ! in_array(Str::lower($e['email']), $dead, true)));
        if (! $found) {
            if (! $dry) {
                $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['emails_checked_at' => now()->toDateString()])])->saveQuietly();
            }

            return null;
        }
        if ($dry) {
            return $found[0]['email'];
        }
        $old = (string) $company->email;
        EmailFinder::store($company, array_map(fn ($e) => ['email' => $e['email'], 'source' => $e['source']], $found), true);
        $company = $company->fresh();
        if ($company->profile('email_bounced') || Str::lower((string) $company->email) === Str::lower($old)) {
            return null;
        }
        app(ContactService::class)->log($company, 'note', 'New email found: '.$company->email, $old.' bounced. '.$company->email.' is published on their website ('.$website.').');

        return self::resend($company, (string) $company->email, 'Sent again: '.$old.' bounced; '.$company->email.' is on their website.');
    }

    private static function resend(Company $company, string $to, string $why): ?string
    {
        try {
            return app(OutreachService::class)->resend($company, $to, EmailFinder::ccFor($company), $why) ? $to : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
