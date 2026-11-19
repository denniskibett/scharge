<?php

namespace App\Services\Assistant;

use App\Models\Invoice;
use App\Services\Integrations;
use App\Services\Rent\BillNotifier;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reads a company's own inbox (Integrations › Email from your address, "Let the Claude assistant read this inbox"),
 * read-only: messages are opened with FT_PEEK, never marked read, moved or deleted. Only emails from the company's
 * tenants get a draft reply, with their balance and unit as facts. The last UID seen is kept on the card.
 */
class Inbox
{
    private const IMAP = ['google' => 'imap.gmail.com', 'microsoft' => 'outlook.office365.com', 'zoho' => 'imap.zoho.com'];

    public static function poll(int $companyId): int
    {
        $mail = Integrations::for($companyId, 'company_email');
        if (! $mail || $mail->get('read_inbox') !== 'yes' || ! function_exists('imap_open')) {
            return 0;
        }
        $host = self::IMAP[$mail->get('provider')] ?? $mail->get('imap_host');
        if (! $host) {
            return 0;
        }
        $user = (string) ($mail->get('username') ?: $mail->get('from_address'));
        $box = @imap_open('{'.$host.':993/imap/ssl}INBOX', $user, (string) $mail->get('password'), OP_READONLY, 1);
        if (! $box) {
            Log::warning('Assistant inbox: could not sign in', ['company' => $companyId, 'error' => imap_last_error()]);
            imap_errors();

            return 0;
        }
        $n = 0;
        try {
            $last = (int) $mail->get('imap_last_uid', 0);
            $uids = $last ? (imap_search($box, 'UNDELETED', SE_UID) ?: []) : (imap_search($box, 'SINCE "'.now()->subDays(2)->format('j-M-Y').'"', SE_UID) ?: []);
            $uids = array_slice(array_values(array_filter($uids, fn ($u) => $u > $last)), -30);
            foreach ($uids as $uid) {
                $n += (int) self::one($companyId, $box, (int) $uid);
                $last = max($last, (int) $uid);
            }
            if ($last !== (int) $mail->get('imap_last_uid', 0)) {
                $fresh = \App\Models\CompanyIntegration::find($mail->id);
                $fresh->mergeCredentials(['imap_last_uid' => $last]);
                $fresh->save();
                Integrations::forget($companyId, 'company_email');
            }
        } finally {
            @imap_close($box);
            imap_errors();
            imap_alerts();
        }

        return $n;
    }

    private static function one(int $companyId, $box, int $uid): bool
    {
        $header = @imap_rfc822_parse_headers((string) imap_fetchheader($box, $uid, FT_UID));
        $from = strtolower(($header->from[0]->mailbox ?? '').'@'.($header->from[0]->host ?? ''));
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $tenancy = CompanyAccess::bypass(fn () => RentRun::ownedByQuery($companyId)->where('status', 'active')
            ->whereHas('tenant.user', fn ($u) => $u->where('email', $from))->with('tenant.user', 'unit.property')->latest('id')->first());
        if (! $tenancy) {
            return false; // only tenants' emails
        }
        $subject = Str::limit(trim(iconv_mime_decode((string) ($header->subject ?? ''), 0, 'UTF-8') ?: ''), 200);
        $text = Str::limit(self::text($box, $uid), 4000);
        $notifier = app(BillNotifier::class);
        $open = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->where('tenancy_id', $tenancy->id)->whereColumn('total_paid', '<', 'total_amount')->orderBy('billing_month')->get());
        $messageId = trim((string) ($header->message_id ?? ''));

        return (bool) Claude::draft($companyId, [
            'kind' => 'tenant_email', 'ref' => 'tenant_email:'.sha1($messageId ?: $uid.'|'.$from), 'channel' => 'email',
            'to_name' => $tenancy->tenant->user->name, 'to_address' => $from, 'subject' => preg_match('/^re:/i', $subject) ? $subject : 'Re: '.$subject,
            'task' => 'A tenant emailed the office. Draft the reply: answer what they asked using the facts; if it is a repair, say it has been noted and someone will be in touch; if it needs a decision from the landlord, say you will come back to them. Subject: "Re: " plus theirs.',
            'facts' => ['tenant' => $tenancy->tenant->user->name, 'unit' => $tenancy->unit?->unit_number, 'building' => $tenancy->unit?->property?->name,
                'moved_in' => $tenancy->move_in_date?->toDateString(), 'their_email' => ['subject' => $subject, 'text' => $text],
                'balance_owed_kes' => round($open->sum(fn ($i) => $notifier->balance($i))), 'pay_link' => $open->first() ? $notifier->payLink($open->first()) : null],
            'context' => ['tenancy_id' => $tenancy->id, 'in_reply_to' => $messageId ?: null],
        ]);
    }

    /** Plain text of a message (first text/plain part, else HTML stripped). */
    private static function text($box, int $uid): string
    {
        $structure = imap_fetchstructure($box, $uid, FT_UID);
        $walk = function ($part, string $no) use (&$walk, $box, $uid) {
            if (! empty($part->parts)) {
                foreach ($part->parts as $i => $p) {
                    if (($t = $walk($p, $no === '' ? (string) ($i + 1) : $no.'.'.($i + 1))) !== null) {
                        return $t;
                    }
                }

                return null;
            }
            $sub = strtolower((string) ($part->subtype ?? ''));
            if ((int) ($part->type ?? 0) !== 0 || ! in_array($sub, ['plain', 'html'], true)) {
                return null;
            }
            $raw = imap_fetchbody($box, $uid, $no === '' ? '1' : $no, FT_UID | FT_PEEK);
            $raw = match ((int) ($part->encoding ?? 0)) { 3 => base64_decode($raw), 4 => quoted_printable_decode($raw), default => $raw };
            $charset = collect($part->parameters ?? [])->firstWhere('attribute', 'charset')?->value ?? collect($part->parameters ?? [])->firstWhere('attribute', 'CHARSET')?->value;
            if ($charset && strtoupper($charset) !== 'UTF-8') {
                $raw = @mb_convert_encoding($raw, 'UTF-8', $charset) ?: $raw;
            }

            return $sub === 'html' ? trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div)[^>]*>/i', "\n", $raw)))) : trim($raw);
        };
        $text = (string) $walk($structure, '');

        // Drop the quoted earlier conversation.
        return trim(preg_split('/\n(On .+wrote:|-{2,} ?Original Message|From: .+\nSent: )/i', $text)[0]);
    }
}
