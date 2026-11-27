<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
use App\Modules\CRM\Mail\CrmOutreachEmail;
use App\Modules\CRM\Support\EmailBody;
use App\Modules\CRM\Support\Playbook;
use App\Modules\CRM\Support\PropertyClass;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends one cold email (Playbook or campaign) from sales@sharet.africa, signed by the account manager,
 * then logs it on the company thread, books the manager's call follow-up and moves New → Contacted.
 */
class OutreachService
{
    public function __construct(private ContactService $contacts)
    {
    }

    /**
     * @return array{manager: User, due: Carbon}
     *
     * @throws \Throwable when the mail could not be sent (nothing is logged then)
     */
    public function send(Company $company, User $sender, string $to, string $subject, string $body, int $followUpDays, ?string $campaign = null, array $cc = []): array
    {
        // One email per company: the other published addresses are copied (CC), never sent separately.
        $cc = array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $cc),
            fn ($e) => $e !== strtolower(trim($to)) && filter_var($e, FILTER_VALIDATE_EMAIL))));
        $manager = $company->owner_id ? ($company->fresh('owner')->owner ?? $sender) : $sender;
        $body = EmailBody::toHtml($body);

        $track = Str::random(24);
        $mailer = Mail::to($to);
        if ($cc) {
            $mailer->cc($cc);
        }
        $sent = $mailer->send(new CrmOutreachEmail($subject, $body, $manager, $company, $track));

        // Whoever sends the first email takes an unowned prospect: the follow-up is theirs.
        if (! $company->owner_id) {
            $company->update(['owner_id' => $sender->id]);
            // Campaign steps are the system's work, not the manager's (CRM › Team counts only what people did).
            $this->contacts->log($company, 'note', 'Owner: nobody → '.$sender->name, $campaign ? 'Assigned by the '.$campaign.' campaign.' : 'Took the account when sending the first email.', [], null, $campaign ? null : $sender->id);
        }

        $company->followUps()->create([
            'assigned_to' => $manager->id, 'created_by' => $campaign ? null : $sender->id, 'type' => 'email', 'status' => 'done',
            'title' => 'Cold email: '.$subject, 'notes' => EmailBody::toText($body), 'done_at' => now(),
            'details' => array_filter(['to' => $to, 'cc' => $cc ?: null, 'from' => Playbook::fromFor($company), 'sent_by' => $sender->id, 'playbook' => Playbook::segmentOf($company), 'campaign' => $campaign, 'ref' => Playbook::demoRef($company),
                'track' => $track, 'message_id' => $sent?->getMessageId(), 'type' => PropertyClass::of($company)]),
        ]);
        $due = now()->addWeekdays($followUpDays)->setTime(10, 0);
        $company->followUps()->create([
            'assigned_to' => $manager->id, 'created_by' => $campaign ? null : $sender->id, 'type' => 'call', 'status' => 'open',
            'title' => 'Follow up on the cold email to '.$company->name, 'due_at' => $due,
            'notes' => 'Emailed '.$to.($cc ? ' (cc '.implode(', ', $cc).')' : '').' on '.now()->format('j M').($campaign ? ' ('.$campaign.' campaign)' : '').'. Call to book a demo; if no answer, send a short follow-up email.',
        ]);
        if ($company->crm_stage === 'new' || $company->crm_stage === null) {
            $this->contacts->setStage($company, 'contacted', $campaign ? null : $sender->id);
        }

        $profile = array_filter(['email_subject' => $subject, 'email_body' => $body, 'last_emailed_at' => now()->toDateTimeString(), 'campaign' => $campaign]);
        $profile['email_bounced'] = false; // a new attempt clears an old bounce
        if (! $company->email) {
            $company->update(['email' => $to]);
        }
        $company->fresh()->forceFill(['crm_profile' => array_merge($company->fresh()->crm_profile ?? [], $profile)])->saveQuietly();

        return ['manager' => $manager, 'due' => $due];
    }

    /**
     * Send the company's last cold email again (same subject and text) to $to, copying $cc. Used after an automatic
     * reply: to the addresses it offered, or on the morning the person is back. Logged on the thread; no new call task
     * and no stage change. Returns null when there is no email to send again or the company asked not to be emailed.
     *
     * @throws \Throwable when the mail could not be sent (nothing is logged then)
     */
    public function resend(Company $company, string $to, array $cc, string $why): ?\App\Modules\CRM\Models\CrmFollowUp
    {
        $company = $company->fresh();

        return $this->sendText($company, $to, $cc, (string) $company->profile('email_subject'), (string) $company->profile('email_body'), $why, ['resend' => true]);
    }

    /**
     * Send one email to a prospect from their account manager (a win-back draft someone approved, say) and log it
     * on the thread. No new call task, no stage change. Null when there is nothing to send or they asked us to stop.
     * $thread: message-ids it follows (In-Reply-To/References), so it lands in the same conversation.
     *
     * @throws \Throwable when the mail could not be sent (nothing is logged then)
     */
    public function sendText(Company $company, string $to, array $cc, string $subject, string $body, string $why, array $details = [], ?int $authorId = null, array $thread = []): ?\App\Modules\CRM\Models\CrmFollowUp
    {
        if (trim($subject) === '' || trim($body) === '' || $company->profile('do_not_email') || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $cc = array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $cc),
            fn ($e) => $e !== strtolower(trim($to)) && filter_var($e, FILTER_VALIDATE_EMAIL))));
        $manager = ($company->owner_id ? User::find($company->owner_id) : null)
            ?? User::whereHas('roles', fn ($r) => $r->where('name', 'sysadmin'))->orderBy('id')->first();
        if (! $manager) {
            return null;
        }
        $html = EmailBody::toHtml($body);
        $track = Str::random(24);
        $mailer = Mail::to($to);
        if ($cc) {
            $mailer->cc($cc);
        }
        $sent = $mailer->send(new CrmOutreachEmail($subject, $html, $manager, $company, $track, array_values(array_filter($thread))));

        $row = $company->followUps()->create([
            'assigned_to' => $manager->id, 'type' => 'email', 'status' => 'done', 'done_at' => now(),
            'created_by' => $authorId,
            'title' => Str::limit((! empty($details['resend']) ? 'Email sent again: ' : 'Email: ').$subject, 250), 'notes' => $why."\n\n".EmailBody::toText($html),
            'details' => array_filter($details + ['to' => $to, 'cc' => $cc ?: null, 'from' => Playbook::fromFor($company), 'why' => $why, 'subject' => $subject,
                'ref' => Playbook::demoRef($company), 'track' => $track, 'message_id' => $sent?->getMessageId(), 'type' => PropertyClass::of($company)]),
        ]);
        $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['last_emailed_at' => now()->toDateTimeString()])])->saveQuietly();

        return $row;
    }
}
