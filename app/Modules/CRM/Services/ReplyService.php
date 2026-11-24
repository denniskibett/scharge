<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmReplyCopy;
use App\Modules\CRM\Mail\CrmReplyEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\EmailFinder;
use App\Modules\CRM\Support\EmailTracking;
use App\Modules\CRM\Support\InboundEmail;
use App\Modules\CRM\Support\Normalizer;
use App\Modules\CRM\Support\OutOfOffice;
use App\Modules\CRM\Support\Playbook;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Prospect replies to our cold emails. Each reply is logged on the company thread and raises an alert
 * (crm_profile.reply) on the CRM row and company page plus an "Answer the reply" to-do for the account manager,
 * until the manager answers it from the CRM or marks it handled (moving the stage at the same time).
 */
class ReplyService
{
    public function __construct(private ContactService $contacts, private ?OutreachService $outreach = null)
    {
        $this->outreach ??= app(OutreachService::class);
    }

    /** The company and our email this message answers, or null when it is not a prospect's reply. */
    public function match(InboundEmail $mail): array
    {
        $q = fn () => Company::withoutGlobalScope('customers');
        if (! $mail->from || self::isOurs($mail->from)) {
            return [null, null]; // our own staff or system mail, never a prospect's reply
        }
        if ($mail->replyTo) {
            $ours = CrmFollowUp::where('type', 'email')->whereIn('details->message_id', $mail->replyTo)->latest('id')->first();
            if ($ours && $ours->company_id) {
                return [$ours->company()->withoutGlobalScope('customers')->first(), $ours];
            }
        }
        $company = $q()->where('email', $mail->from)->whereNotNull('crm_profile->last_emailed_at')->latest('id')->first()
            ?? $q()->whereNotNull('crm_profile->last_emailed_at')->where('crm_profile', 'like', '%"'.addcslashes($mail->from, '%_').'"%')->latest('id')->first();
        if ($company) {
            return [$company, EmailTracking::latestFor($company)];
        }
        if (preg_match('/\b('.Playbook::REF_PATTERN.')\b/', $mail->subject, $r)) {
            $company = $q()->where('crm_profile->demo_ref', strtoupper($r[1]))->first();
            if ($company) {
                return [$company, EmailTracking::latestFor($company)];
            }
        }

        return [null, null];
    }

    /** Our own address: sharet.africa mail, or any address of a sysadmin or account manager (their personal Gmail too). */
    public static function isOurs(string $email): bool
    {
        $email = strtolower(trim($email));
        if (str_ends_with($email, '@sharet.africa')) {
            return true;
        }

        return User::whereRaw('LOWER(email) = ?', [$email])->whereHas('roles', fn ($r) => $r->whereIn('name', ['sysadmin', 'account_manager']))->exists();
    }

    /** Who gets a copy of every prospect reply and of our answers: sysadmins, the company's account manager (and work copy), and CRM_REPLY_CC. */
    public static function team(Company $company): array
    {
        $to = User::whereHas('roles', fn ($r) => $r->where('name', 'sysadmin'))->pluck('email')->all();
        if ($company->owner_id && ($manager = User::find($company->owner_id))) {
            $to = array_merge($to, $manager->mailboxes());
        }
        $to = array_merge($to, (array) config('crm.reply_cc', []));
        $to = array_map(fn ($e) => strtolower(trim((string) $e)), $to);

        return array_values(array_unique(array_filter($to, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    /** Email the team a copy of a prospect's reply, skipping anyone the prospect already wrote to. */
    private function copyTeam(Company $company, CrmFollowUp $row, InboundEmail $mail): void
    {
        $already = strtolower(($mail->headers['to'] ?? '').' '.($mail->headers['cc'] ?? ''));
        $to = array_values(array_filter(self::team($company), fn ($e) => ! str_contains($already, $e)));
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new CrmReplyCopy($company, $row));
        } catch (\Throwable $e) {
            report($e); // the reply is logged either way
        }
    }

    /** Log a reply once; returns the logged row, or null when it was already logged. */
    public function record(Company $company, InboundEmail $mail, ?CrmFollowUp $ours, string $mailbox): ?CrmFollowUp
    {
        if ($mail->messageId && $company->followUps()->where('type', 'email')->where('details->in_message_id', $mail->messageId)->exists()) {
            return null;
        }
        // Everything from the email is outside data: saved as clean plain text only.
        $text = Normalizer::text($mail->fresh(), 8000, true) ?? '';
        $mail->fromName = Normalizer::text($mail->fromName, 120) ?? '';
        $mail->subject = Normalizer::text($mail->subject, 250) ?? '';
        $mail->from = Normalizer::email($mail->from) ?? '';
        $mail->messageId = Normalizer::text($mail->messageId, 250) ?? '';
        $who = $mail->fromName ?: $mail->from;
        $optOut = ! $mail->auto && $mail->optOut();
        $row = $company->followUps()->create([
            'assigned_to' => $company->owner_id, 'type' => 'email', 'status' => 'done', 'done_at' => now(),
            'title' => Str::limit(($mail->auto ? 'Automatic reply from ' : 'Reply from ').$who.': '.($mail->subject ?: '(no subject)'), 250),
            'notes' => $text,
            'details' => array_filter([
                'direction' => 'in', 'from' => $mail->from, 'from_name' => $mail->fromName ?: null, 'mailbox' => $mailbox,
                'subject' => $mail->subject, 'in_message_id' => $mail->messageId ?: null, 'answers' => $ours?->detail('message_id'),
                'auto' => $mail->auto ?: null, 'opt_out' => $optOut ?: null, 'received_at' => now()->toDateTimeString(),
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
        if ($mail->auto) {
            $this->autoReply($company, $mail, $row); // on the thread, no alert; acted on below

            return $row;
        }
        EmailTracking::mark($ours, 'replied');

        $profile = $company->fresh()->crm_profile ?? [];
        $profile['reply'] = [
            'at' => now()->toDateTimeString(), 'from' => $mail->from, 'name' => $mail->fromName, 'subject' => $mail->subject,
            'snippet' => Str::limit(preg_replace('/\s+/', ' ', $text), 220), 'follow_up_id' => $row->id, 'opt_out' => $optOut,
            'message_id' => $mail->messageId, 'mailbox' => $mailbox,
        ];
        $profile['reply_pending'] = true;
        if ($optOut) {
            $profile['do_not_email'] = true;
        }
        $company->fresh()->forceFill(['crm_profile' => $profile])->saveQuietly();

        // A to-do for the account manager, so it also shows on Follow-ups and goes overdue if nobody answers.
        $company->followUps()->where('type', 'email')->where('details->answer_reply', true)->whereIn('status', ['open', 'scheduled'])->update(['status' => 'cancelled']);
        $company->followUps()->create([
            'assigned_to' => $company->owner_id, 'type' => 'email', 'status' => 'open', 'due_at' => now()->addHours(4),
            'title' => 'Answer the reply from '.$who, 'notes' => Str::limit($text, 500),
            'details' => ['answer_reply' => true, 'reply_id' => $row->id],
        ]);
        $this->copyTeam($company, $row, $mail);
        if ($optOut) {
            $this->contacts->log($company, 'note', 'Asked not to be emailed', 'Their reply asked us to stop emailing, so campaigns skip them now. Call only if they asked for it.');
        }

        return $row;
    }

    /**
     * An automatic reply to our email. Addresses it offers ("for urgent matters contact …") get our email straight away
     * and are saved on the company (extra emails + contacts). A return date ("back on 1 December") schedules the email
     * to go again at 08:00 that morning (crm:resend-returning). Bounces and plain "we got your email" notes: nothing.
     */
    public function autoReply(Company $company, InboundEmail $mail, CrmFollowUp $row, ?\Illuminate\Support\Carbon $received = null): array
    {
        $company = $company->fresh();
        $known = array_merge([$company->email], EmailFinder::ccFor($company), (array) $company->profile('bounced_emails', []));
        $read = OutOfOffice::read($mail, $received ?? now(), $known);
        $done = ['alternates' => [], 'return_on' => null];
        if ($read['bounce'] || $company->profile('do_not_email') || ! $company->profile('email_subject')) {
            return $done;
        }
        $who = $mail->fromName ?: $mail->from;

        if ($read['alternates']) {
            $alts = $read['alternates'];
            try {
                if ($this->outreach->resend($company, $alts[0], array_slice($alts, 1), 'Sent to the addresses in the automatic reply from '.$who.'.')) {
                    $done['alternates'] = $alts;
                }
            } catch (\Throwable $e) {
                report($e);
            }
            EmailFinder::store($company->fresh(), array_map(fn ($e) => ['email' => $e, 'source' => 'auto-reply from '.$mail->from], $alts), false);
            foreach ($alts as $email) {
                try {
                    [$person] = $this->contacts->upsertPerson(['email' => $email], 'crm_auto_reply');
                    $this->contacts->attachContact($company, $person, 'Named in an automatic reply');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        if ($read['return_on']) {
            $company->followUps()->where('type', 'email')->where('details->resend_on_return', true)->whereIn('status', ['open', 'scheduled'])->update(['status' => 'cancelled']);
            $company->followUps()->create([
                'assigned_to' => $company->owner_id, 'type' => 'email', 'status' => 'scheduled', 'due_at' => $read['return_on']->copy()->timezone(config('app.timezone')),
                'title' => Str::limit('Send the email again: '.$who.' is back '.$read['return_on']->format('D j M'), 250),
                'notes' => 'Their automatic reply says they are back on '.$read['return_on']->format('l j F').'. The last cold email goes to '.$mail->from.' again at 08:00 that morning.',
                'details' => ['resend_on_return' => true, 'to' => $mail->from, 'reply_id' => $row->id],
            ]);
            $done['return_on'] = $read['return_on']->toDateString();
        }

        if ($done['alternates'] || $done['return_on']) {
            $row->update(['details' => array_merge($row->details ?? [], ['out_of_office' => array_filter($done)])]);
            $this->contacts->log($company, 'note', 'Automatic reply acted on', collect([
                $done['alternates'] ? 'Emailed the addresses it offered: '.implode(', ', $done['alternates']).'. Saved them on this company.' : null,
                $done['return_on'] ? 'The email goes to '.$mail->from.' again at 08:00 on '.$read['return_on']->format('l j F').'.' : null,
            ])->filter()->join(' '));
        }

        return $done;
    }

    /** Send the account manager's answer in the same thread, then clear the alert. */
    public function answer(Company $company, User $user, string $body, ?string $stage = null): void
    {
        $reply = $company->profile('reply') ?? [];
        $to = $reply['from'] ?? $company->email;
        abort_unless($to && filter_var($to, FILTER_VALIDATE_EMAIL), 422, 'No address to answer.');
        $subject = $reply['subject'] ?? null;
        $subject = $subject ? (preg_match('/^re:/i', $subject) ? $subject : 'Re: '.$subject) : 'Re: scharge';
        $manager = $company->owner_id ? (User::find($company->owner_id) ?? $user) : $user;
        $html = EmailBody::toHtml($body);
        $thread = array_values(array_filter([$reply['message_id'] ?? null]));

        $sent = Mail::to($to)->bcc(array_values(array_diff(self::team($company), [strtolower($to)])))->send(new CrmReplyEmail($subject, $html, $manager, $company, $thread));
        $this->contacts->log($company, 'email', 'Answered '.(($reply['name'] ?? '') ?: $to), EmailBody::toText($html),
            array_filter(['direction' => 'out', 'to' => $to, 'from' => Playbook::fromFor($company), 'subject' => $subject, 'answer_to' => $reply['follow_up_id'] ?? null, 'message_id' => $sent?->getMessageId()]), null, $user->id);
        $this->handled($company, $user, $stage, null, false);
    }

    /** Clear the alert (and its to-do); optionally move the stage and leave a note. */
    public function handled(Company $company, User $user, ?string $stage = null, ?string $note = null, bool $log = true): void
    {
        $company = $company->fresh();
        $profile = $company->crm_profile ?? [];
        $profile['reply_pending'] = false;
        $profile['reply_handled'] = ['at' => now()->toDateTimeString(), 'by' => $user->id];
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        $company->followUps()->where('type', 'email')->where('details->answer_reply', true)->whereIn('status', ['open', 'scheduled'])
            ->update(['status' => 'done', 'done_at' => now()]);
        if ($stage && $stage !== $company->crm_stage) {
            $this->contacts->setStage($company, $stage, $user->id);
        }
        $note = Normalizer::text($note, 1000, true);
        if ($log || $note) {
            $this->contacts->log($company, 'note', 'Reply handled', $note, [], null, $user->id);
        }
    }
}
