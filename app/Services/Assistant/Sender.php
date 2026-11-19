<?php

namespace App\Services\Assistant;

use App\Mail\AssistantEmail;
use App\Models\Company;
use App\Modules\CRM\Mail\CrmReplyEmail;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\EmailBody;
use App\Services\Connectors\CompanyMail;
use App\Services\WhatsApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sends (or, for a clean-up, applies) a draft a person approved on the Assistant page, through the same routes the rest
 * of scharge uses: CRM threads for prospects, the company's own mailbox / SMS sender / WhatsApp for its tenants.
 */
class Sender
{
    /** @return array{0: bool, 1: string} */
    public static function send(object $draft, $user, string $subject, string $body): array
    {
        $body = trim($body);
        if ($body === '' && $draft->channel !== 'action') {
            return [false, 'The message is empty.'];
        }
        try {
            $result = match ($draft->kind) {
                'prospect_reply' => self::prospectReply($draft, $user, $body),
                'prospect_followup' => self::prospectFollowUp($draft, $user, $subject, $body),
                'billing' => self::email(null, $draft, $subject, $body),
                'tenant_email' => self::email((int) $draft->company_id, $draft, $subject, $body),
                'arrears' => self::tenantMessage($draft, $subject, $body),
                'cleanup' => [true, 'Fixed '.Cleanup::apply($draft).' records.'],
                default => [false, 'Unknown kind of draft.'],
            };
        } catch (\Throwable $e) {
            $result = [false, mb_substr('Not sent: '.$e->getMessage(), 0, 300)];
        }
        DB::table('ai_drafts')->where('id', $draft->id)->update(array_filter([
            'status' => $result[0] ? ($draft->kind === 'cleanup' ? 'applied' : 'sent') : null,
            'subject' => $draft->channel === 'email' ? mb_substr($subject, 0, 255) : null, 'body' => $draft->channel === 'action' ? null : $body,
            'error' => $result[0] ? null : mb_substr($result[1], 0, 500), 'reviewed_by' => $user->id,
            'sent_at' => $result[0] ? now() : null, 'updated_at' => now(),
        ], fn ($v) => $v !== null));

        return $result;
    }

    private static function prospectReply(object $draft, $user, string $body): array
    {
        $company = Company::withoutGlobalScope('customers')->findOrFail(json_decode($draft->context, true)['company_id'] ?? 0);
        app(ReplyService::class)->answer($company, $user, $body);

        return [true, 'Sent in the same thread. The reply alert is cleared.'];
    }

    private static function prospectFollowUp(object $draft, $user, string $subject, string $body): array
    {
        $ctx = json_decode($draft->context, true) ?: [];
        $company = Company::withoutGlobalScope('customers')->findOrFail($ctx['company_id'] ?? 0);
        abort_unless(filter_var($draft->to_address, FILTER_VALIDATE_EMAIL), 422, 'No email address.');
        $manager = $company->owner_id ? (\App\Modules\Users\Models\User::find($company->owner_id) ?? $user) : $user;
        $html = EmailBody::toHtml($body);
        $sent = Mail::to($draft->to_address)->send(new CrmReplyEmail($subject, $html, $manager, $company, []));
        app(ContactService::class)->log($company, 'email', 'Follow-up: '.$subject, EmailBody::toText($html),
            array_filter(['direction' => 'out', 'to' => $draft->to_address, 'subject' => $subject, 'message_id' => $sent?->getMessageId(), 'assistant_draft' => $draft->id]), null, $user->id);
        CrmFollowUp::whereKey($ctx['follow_up_id'] ?? 0)->update(['status' => 'done', 'done_at' => now(), 'outcome' => 'Emailed (Claude draft, sent by '.$user->name.')']);

        return [true, 'Sent and logged on the prospect. The follow-up is done.'];
    }

    private static function email(?int $companyId, object $draft, string $subject, string $body): array
    {
        abort_unless(filter_var($draft->to_address, FILTER_VALIDATE_EMAIL), 422, 'No email address.');
        $inReplyTo = json_decode($draft->context, true)['in_reply_to'] ?? null;
        $mail = new AssistantEmail($subject ?: 'Message', $body, $inReplyTo);
        $companyId ? CompanyMail::send($companyId, $draft->to_address, $mail) : Mail::to($draft->to_address)->send($mail);

        return [true, 'Emailed to '.$draft->to_address.'.'];
    }

    private static function tenantMessage(object $draft, string $subject, string $body): array
    {
        $companyId = (int) $draft->company_id;
        if ($draft->channel === 'email') {
            return self::email($companyId, $draft, $subject ?: 'Your rent', $body);
        }
        if ($draft->channel === 'whatsapp' && ($wa = WhatsApp::forCompany($companyId))->canSend()) {
            $res = $wa->send($draft->to_address, $body);
            if ($res['success'] ?? false) {
                return [true, 'Sent on WhatsApp.'];
            }
        }
        $res = app(\App\Modules\SMS\Services\KenyaSMS::class)->forCompany($companyId)->sendOne($draft->to_address, $body);

        return ($res['success'] ?? false) ? [true, 'Sent by SMS to '.$draft->to_address.'.'] : [false, 'SMS not sent: '.($res['message'] ?? $res['error'] ?? 'unknown error')];
    }
}
