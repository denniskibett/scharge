<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leads that turned us down get a second, value-led email drafted for their account manager to approve
 * (crm:winback). Who: their reply read as not interested, price or "we have a system" (ReplyIntent), or the stage is
 * Lost, and it has been quiet for crm.winback.after_days. The draft answers their reason with what they would save,
 * in their own numbers (ReplyIntent::facts), and the subsidy: the first payment covers three months, and vacant units
 * get cheaper over time. Nothing is sent until a person approves it on the lead page.
 */
class WinBack
{
    public const REASONS = ['not_interested', 'price', 'has_system', 'lost'];

    public static function afterDays(): int
    {
        return max(1, (int) config('crm.winback.after_days', 21));
    }

    /** Leads due a win-back draft now. @return Collection<int, array{company: Company, reason: string, to: string}> */
    public static function due(?Carbon $now = null): Collection
    {
        $now ??= now();
        $cutoff = $now->copy()->subDays(self::afterDays());

        return Company::withoutGlobalScope('customers')
            ->where(fn ($q) => $q->where('crm_stage', 'lost')->orWhereIn('crm_profile->reply_intent->intent', ['not_interested', 'price', 'has_system']))
            ->whereNotNull('email')
            ->get(['id', 'name', 'email', 'owner_id', 'crm_stage', 'crm_profile', 'updated_at'])
            ->map(fn (Company $c) => self::check($c, $cutoff))
            ->filter()
            ->values();
    }

    /** One company: the reason and address when a draft is due, else null. */
    public static function check(Company $company, Carbon $cutoff): ?array
    {
        $p = $company->crm_profile ?? [];
        if (! empty($p['do_not_email']) || ! empty($p['email_bounced']) || empty($p['email_subject'])) {
            return null; // asked us to stop, bounced, or never cold-emailed
        }
        $reply = CloseSteps::lastReply($company);
        $intent = (string) ($p['reply_intent']['intent'] ?? '');
        $reason = in_array($intent, ['not_interested', 'price', 'has_system'], true) && $reply && (int) ($p['reply_intent']['follow_up_id'] ?? 0) === $reply->id
            ? $intent
            : ($company->crm_stage === 'lost' ? 'lost' : null);
        if (! $reason) {
            return null;
        }
        // Quiet long enough: nothing from either side since the cutoff.
        $lastTouch = $company->followUps()->whereIn('type', ['email', 'call', 'whatsapp', 'meeting', 'stage'])->max('created_at');
        if ($lastTouch && Carbon::parse($lastTouch)->gt($cutoff)) {
            return null;
        }
        // One win-back at a time, and not again within 90 days of the last.
        if ($company->followUps()->where('details->winback', true)->where(fn ($q) => $q->whereIn('status', ['open', 'scheduled'])->orWhere('created_at', '>', now()->subDays(90)))->exists()) {
            return null;
        }
        $to = $reply ? (string) $reply->detail('from') : (string) $company->email;

        return filter_var($to, FILTER_VALIDATE_EMAIL) ? ['company' => $company, 'reason' => $reason, 'to' => $to] : null;
    }

    /** The draft: subject and plain text, in the account manager's voice (the signature is added when sent). */
    public static function draft(Company $company, string $reason, ?string $name = null): array
    {
        $first = trim(explode(' ', (string) $name)[0] ?? '');
        $first = ctype_upper(str_replace(' ', '', $first)) ? ucfirst(strtolower($first)) : $first;
        $f = ReplyIntent::facts($company);
        $hi = 'Hi '.($first !== '' ? $first : $company->name.' team').",\n\n";
        $time = $f['units'] && $f['hours']
            ? 'For '.number_format($f['units']).' units, that is about '.$f['hours'].' hours a month your team spends confirming M-Pesa messages and updating records by hand. scharge gives that time back: payments match themselves to the right tenant, receipts and statements go out on their own.'
            : 'Most teams spend hours every month confirming M-Pesa messages and updating records by hand. scharge gives that time back: payments match themselves to the right tenant, receipts and statements go out on their own.';
        $offer = ($f['fee'] ? 'Your first payment of KES '.number_format($f['fee']).' covers three months' : 'Your first payment covers three months').', so the next two are on us. Vacant units get 25% off after six months and 50% off after a year, so you pay less while a unit waits for a tenant.';

        $open = match ($reason) {
            'price' => 'When we last spoke, the cost was the concern, and that is fair. Here is what it looks like from the other side.',
            'has_system' => 'You mentioned you already have a system. This is not about replacing it; it is about the part it still leaves to your team.',
            'not_interested' => 'I know the timing was not right when we last wrote. One thing worth a second look, in case it has changed:',
            default => 'It has been a few weeks since we last spoke. One thing worth a second look:',
        };
        $close = match ($reason) {
            'has_system' => 'We can import your data and run beside your system for a month, so you can compare in your own numbers. Would that be worth 15 minutes?',
            default => 'Would 15 minutes this week be worth it to see what it saves on one of your properties? If not, no need to reply and we will leave it there.',
        };
        $subject = match ($reason) {
            'price' => $company->name.': what the hours back are worth',
            'has_system' => $company->name.': the part your system leaves by hand',
            default => $company->name.': a second look, three months for the price of one',
        };

        $sign = "\n\nKind regards,\n{manager}\nAccount Manager, scharge\n{manager_email}  {manager_phone}";
        $manager = $company->owner_id ? \App\Modules\Users\Models\User::find($company->owner_id) : null;

        return ['subject' => $subject, 'body' => Playbook::fill($hi.$open."\n\n".$time."\n\n".$offer."\n\n".$close.$sign, $company, $manager)];
    }

    /** Queue the draft as a to-do for the account manager to approve, edit or skip. */
    public static function queue(Company $company, string $reason, string $to): CrmFollowUp
    {
        $reply = CloseSteps::lastReply($company);
        $draft = self::draft($company, $reason, $reply?->detail('from_name'));

        return $company->followUps()->create([
            'assigned_to' => $company->owner_id, 'type' => 'email', 'status' => 'open', 'due_at' => now(),
            'title' => 'Approve the win-back email to '.$to,
            'notes' => 'They turned us down ('.(ReplyIntent::LABELS[$reason] ?? 'marked Lost').'); '.self::afterDays().' quiet days later, a value-led email is ready. Read it, change anything, then send or skip.',
            'details' => ['winback' => true, 'reason' => $reason, 'to' => $to, 'subject' => $draft['subject'], 'body' => $draft['body']],
        ]);
    }
}
