<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Database\Eloquent\Builder;

/**
 * What happened to a cold email after we sent it, stored on its "email" follow-up (details):
 *   sent        our mail server accepted it (OutreachService)
 *   delivered   the prospect's mail server accepted it (postfix log, crm:mail-delivery)
 *   deferred    their server has not accepted it yet; postfix keeps retrying
 *   bounced     their server refused it (bad address): the company goes back to Unsent
 *   opened      the email was opened (tracking image; some mail apps block images)
 *   clicked     they opened their demo form from the email
 *   replied     they answered the email (crm:mail-replies)
 *   booked      they submitted the demo form
 */
class EmailTracking
{
    public const LABELS = [
        'sent' => 'Sent', 'deferred' => 'Delayed', 'bounced' => 'Bounced', 'delivered' => 'Delivered',
        'opened' => 'Opened', 'clicked' => 'Clicked the link', 'replied' => 'Replied', 'booked' => 'Booked a demo',
    ];

    public const TONES = [
        'sent' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        'deferred' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        'bounced' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        'delivered' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'opened' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'clicked' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'replied' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'booked' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    ];

    /** The furthest step this email reached. */
    public static function status(CrmFollowUp $email): string
    {
        return match (true) {
            (bool) $email->detail('booked_at') => 'booked',
            (bool) $email->detail('replied_at') => 'replied',
            (bool) $email->detail('clicked_at') => 'clicked',
            (bool) $email->detail('opened_at') => 'opened',
            $email->detail('delivery') === 'bounced' => 'bounced',
            $email->detail('delivery') === 'sent' => 'delivered',
            $email->detail('delivery') === 'deferred' => 'deferred',
            default => 'sent',
        };
    }

    public static function find(?string $token): ?CrmFollowUp
    {
        return $token && preg_match('/^[A-Za-z0-9]{24}$/', $token)
            ? CrmFollowUp::where('type', 'email')->where('details->track', $token)->first()
            : null;
    }

    /** Record a step once (first time wins); opens also count. */
    public static function mark(?CrmFollowUp $email, string $step): void
    {
        if (! $email) {
            return;
        }
        $d = $email->details ?? [];
        if ($step === 'opened') {
            $d['opens'] = ($d['opens'] ?? 0) + 1;
        }
        $d[$step.'_at'] ??= now()->toDateTimeString();
        $email->forceFill(['details' => $d])->saveQuietly();
    }

    /** The latest cold email to this company (for click/booked from the demo form). */
    public static function latestFor(Company $company): ?CrmFollowUp
    {
        return $company->followUps()->where('type', 'email')->whereNotNull('details->track')->latest('id')->first();
    }

    /** Prospects a campaign may email: a business email, never emailed, not opted out, still New. */
    public static function flagColumns(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('companies', 'email_blocked');
    }

    public static function ready(Builder $query, bool $withEmail = true): Builder
    {
        $query->where('lifecycle', 'prospect')
            ->where(fn ($w) => $w->whereNull('crm_stage')->orWhere('crm_stage', 'new'));
        // Stored flag columns when present (database/schema/2026_11_19_crm_mail_flags.sql), else the JSON itself.
        if (self::flagColumns()) {
            $query->where('companies.email_blocked', 0)->where('companies.email_sent', 0);
        } else {
            $query->where(fn ($w) => $w->whereNull('crm_profile->do_not_email')->orWhere('crm_profile->do_not_email', false))
                ->whereNull('crm_profile->last_emailed_at')
                ->where(fn ($w) => $w->whereNull('crm_profile->email_no_mx')->orWhere('crm_profile->email_no_mx', false))
                ->where(fn ($w) => $w->whereNull('crm_profile->email_shared')->orWhere('crm_profile->email_shared', false));
        }

        return $withEmail ? $query->whereNotNull('email')->where('email', 'like', '%_@_%._%') : $query;
    }

    /**
     * Prospects we still have to reach: no usable email, or their email bounced, and not opted out.
     * Account managers call, WhatsApp or find an email for these.
     */
    public static function unsent(Builder $query): Builder
    {
        $query->where('lifecycle', 'prospect')
            ->where(fn ($w) => $w->whereNull('crm_stage')->orWhereIn('crm_stage', ['new', 'contacted']));
        if (self::flagColumns()) {
            return $query->where('companies.email_dne', 0)->where(fn ($w) => $w
                ->where(fn ($x) => $x->where(fn ($e) => $e->whereNull('email')->orWhere('email', '')->orWhere('email', 'not like', '%_@_%._%'))->where('companies.email_sent', 0))
                ->orWhere('companies.email_problem', 1));
        }

        return $query
            ->where(fn ($w) => $w->whereNull('crm_profile->do_not_email')->orWhere('crm_profile->do_not_email', false))
            ->where(fn ($w) => $w
                ->where(fn ($x) => $x->where(fn ($e) => $e->whereNull('email')->orWhere('email', '')->orWhere('email', 'not like', '%_@_%._%'))->whereNull('crm_profile->last_emailed_at'))
                ->orWhere('crm_profile->email_bounced', true)
                ->orWhere('crm_profile->email_no_mx', true));
    }
}
