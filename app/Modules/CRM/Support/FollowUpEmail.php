<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The second email of a cold sequence: a short reply in the same thread as the first email, from the company's
 * account manager, sent once to prospects that have not answered a few working days after the first one.
 */
class FollowUpEmail
{
    public const STEP = 'follow_up';

    /**
     * First cold emails due a follow-up: sent at least $afterWeekdays working days ago, not bounced, nothing heard back
     * (no reply, no demo booked, still Contacted), not opted out, and no email either way on the thread since.
     * Clicked first, then the most opened, then the rest.
     *
     * @return Collection<int, array{first: CrmFollowUp, company: Company, to: string, cc: array<string>}>
     */
    public static function due(int $afterWeekdays = 4): Collection
    {
        $cut = now()->subWeekdays($afterWeekdays);
        $firsts = CrmFollowUp::where('type', 'email')->whereNotNull('details->track')->where('title', 'like', 'Cold email:%')
            ->where('created_at', '<=', $cut)->orderBy('id')->get()
            ->reject(fn (CrmFollowUp $e) => in_array(EmailTracking::status($e), ['bounced', 'replied', 'booked'], true) || ! filter_var($e->detail('to'), FILTER_VALIDATE_EMAIL))
            ->unique('company_id');
        if ($firsts->isEmpty()) {
            return collect();
        }
        $companies = Company::withoutGlobalScope('customers')->whereIn('id', $firsts->pluck('company_id'))->get()->keyBy('id');
        // Any email on the thread after the first (a reply, our answer, a resend, an earlier follow-up) means a person is on it.
        $busy = CrmFollowUp::where('type', 'email')->whereIn('company_id', $firsts->pluck('company_id'))
            ->get(['id', 'company_id', 'created_at'])->groupBy('company_id');

        return $firsts->filter(function (CrmFollowUp $e) use ($companies, $busy) {
            $c = $companies[$e->company_id] ?? null;

            return $c && $c->lifecycle === 'prospect' && $c->crm_stage === 'contacted'
                && ! $c->profile('do_not_email') && ! $c->profile('email_bounced') && ! $c->profile('reply_pending') && ! $c->reply_pending
                && ! ($busy[$e->company_id] ?? collect())->contains(fn ($x) => $x->id > $e->id);
        })->sortBy(fn (CrmFollowUp $e) => [$e->detail('clicked_at') ? 0 : 1, -(int) $e->detail('opens'), $e->detail('delivery') === 'sent' ? 0 : 1, $e->id])
            ->map(fn (CrmFollowUp $e) => ['first' => $e, 'company' => $companies[$e->company_id], 'to' => strtolower((string) $e->detail('to')), 'cc' => (array) ($e->detail('cc') ?? [])])
            ->values();
    }

    /** @return array{subject: string, body: string} */
    public static function compose(Company $company, ?User $manager, CrmFollowUp $first): array
    {
        $name = preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name);
        $fixes = collect(Playbook::pains($company))->take(2)->map(fn ($p) => '• '.ucfirst(rtrim($p[1], '.')))->join("\n");
        $sent = Carbon::parse($first->created_at)->tz(config('app.schedule_timezone', 'Africa/Nairobi'));
        $subject = trim(preg_replace('/^Cold email:\s*/', '', (string) $first->title));

        $body = "Hello {$name} team,\n\n"
            .'I am following up on my email of '.$sent->format('j F')." in case it got buried. In short, with scharge:\n{$fixes}\n\n"
            ."If that would help, I can show you how it works on one of your own properties in 20 minutes. You can pick a time here (2 minutes): {demo_link}\n\n"
            ."Or reply with a good time and number, and I will call you.\n\n"
            ."Kind regards,\n{manager}\nAccount Manager, scharge\n{manager_email}  {manager_phone}\nhttps://scharge.sharet.africa";

        return [
            'subject' => \Illuminate\Support\Str::limit('Re: '.($subject !== '' ? $subject : $name.': scharge'), 120, '…'),
            'body' => Playbook::fill($body, $company, $manager),
        ];
    }
}
