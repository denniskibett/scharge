<?php

namespace App\Services\Assistant;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\Playbook;
use App\Models\Invoice;
use App\Services\Integrations;
use App\Services\Rent\BillNotifier;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where the Claude assistant's drafts come from (`assistant:run`, every 15 minutes in working hours). Each source
 * gathers facts, never sends: drafts wait on the Assistant page. Each thing is drafted once (ai_drafts.source_ref).
 */
class Sources
{
    /** Everything due now. @return array<string, int> drafts made per source */
    public static function run(): array
    {
        if (! Claude::installed() || ! Claude::apiKey()) {
            return [];
        }
        $out = ['prospect_reply' => 0, 'prospect_followup' => 0, 'billing' => 0, 'arrears' => 0, 'tenant_email' => 0];
        if (Claude::enabledFor(null)) {
            $out['prospect_reply'] = self::prospectReplies();
            $out['prospect_followup'] = self::prospectFollowUps();
            $out['billing'] = self::billing();
        }
        foreach (\App\Models\CompanyIntegration::where('provider', 'assistant')->where('enabled', true)->whereNotNull('company_id')->pluck('company_id') as $companyId) {
            $out['arrears'] += rescue(fn () => self::arrears((int) $companyId), 0);
            $out['tenant_email'] += rescue(fn () => Inbox::poll((int) $companyId), 0);
        }

        return $out;
    }

    /** Prospects who replied to scharge and are waiting for an answer. */
    public static function prospectReplies(): int
    {
        $n = 0;
        $companies = Company::withoutGlobalScope('customers')->where('reply_pending', true)->limit(40)->get();
        foreach ($companies as $company) {
            $reply = $company->profile('reply') ?? [];
            if (empty($reply['follow_up_id']) || ! empty($reply['opt_out']) || ! filter_var($reply['from'] ?? null, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $thread = CrmFollowUp::where('company_id', $company->id)->where('type', 'email')->latest('id')->limit(6)->get()->reverse()
                ->map(fn ($f) => ['when' => $f->created_at?->toDateString(), 'direction' => $f->details['direction'] ?? 'out', 'title' => $f->title, 'text' => Str::limit((string) $f->notes, 1500)])->values()->all();
            $n += (bool) Claude::draft(null, [
                'kind' => 'prospect_reply', 'ref' => 'prospect_reply:'.$reply['follow_up_id'], 'channel' => 'email',
                'to_name' => $reply['name'] ?? $company->name, 'to_address' => $reply['from'], 'subject' => 'Re: '.($reply['subject'] ?? 'scharge'),
                'task' => 'A prospect answered our email about scharge. Answer their reply helpfully: answer what they asked, and suggest the next step (a short demo or a call). Subject: "Re: " plus their subject.',
                'facts' => self::prospectFacts($company) + ['their_reply' => ['from' => $reply['name'] ?? null, 'subject' => $reply['subject'] ?? null,
                    'text' => Str::limit((string) CrmFollowUp::find($reply['follow_up_id'])?->notes, 4000)], 'conversation' => $thread],
                'context' => ['company_id' => $company->id, 'reply_follow_up_id' => (int) $reply['follow_up_id']],
            ]);
        }

        return $n;
    }

    /** CRM follow-ups due today for prospects we can email. */
    public static function prospectFollowUps(): int
    {
        $n = 0;
        $due = CrmFollowUp::whereIn('status', ['open', 'scheduled'])->whereIn('type', ['email', 'call', 'follow_up'])
            ->where('due_at', '<=', now()->endOfDay())->where('due_at', '>=', now()->subDays(14))
            ->where(fn ($q) => $q->whereNull('details->answer_reply')->orWhere('details->answer_reply', false))
            ->whereNotNull('company_id')->with(['company' => fn ($q) => $q->withoutGlobalScope('customers')])->limit(40)->get();
        foreach ($due as $f) {
            $company = $f->company;
            if (! $company || $company->lifecycle !== 'prospect' || $company->profile('do_not_email') || ! filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $n += (bool) Claude::draft(null, [
                'kind' => 'prospect_followup', 'ref' => 'prospect_followup:'.$f->id, 'channel' => 'email',
                'to_name' => $company->name, 'to_address' => $company->email,
                'task' => 'Write the follow-up email this CRM task asks for, to move this prospect to a demo. Short, specific to their kind of business, one clear call to action (the demo link).',
                'facts' => self::prospectFacts($company) + ['follow_up_task' => ['title' => $f->title, 'notes' => Str::limit((string) $f->notes, 1500), 'due' => $f->due_at?->toDateString()]],
                'context' => ['company_id' => $company->id, 'follow_up_id' => $f->id],
            ]);
        }

        return $n;
    }

    /** scharge's subscription invoices: a reminder 3 days before the due date, and one when it is overdue. */
    public static function billing(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('subscription_invoices')) {
            return 0;
        }
        $n = 0;
        $rows = DB::table('subscription_invoices as i')->join('company_subscriptions as s', 's.id', '=', 'i.company_subscription_id')
            ->where('i.status', 'pending')->whereNotNull('i.due_date')->where('i.due_date', '<=', now()->addDays(3)->toDateString())
            ->where('i.due_date', '>=', now()->subDays(30)->toDateString())
            ->select('i.*', 's.company_id')->limit(60)->get();
        foreach ($rows as $inv) {
            $company = Company::withoutGlobalScope('customers')->find($inv->company_id);
            $to = self::billingContact($company);
            if (! $company || ! $to) {
                continue;
            }
            $overdue = $inv->due_date < now()->toDateString();
            $n += (bool) Claude::draft(null, [
                'kind' => 'billing', 'ref' => 'billing:'.$inv->id.':'.($overdue ? 'overdue' : 'upcoming'), 'channel' => 'email',
                'to_name' => $to['name'], 'to_address' => $to['email'],
                'task' => $overdue
                    ? 'Their scharge subscription invoice is overdue. Remind them politely; say that while it is unpaid their scharge account is locked except the payment page, and that paying unlocks it at once. Include the payment link.'
                    : 'Their scharge subscription invoice is due soon. A friendly heads-up with the amount, due date and payment link.',
                'facts' => ['company' => $company->name, 'invoice_number' => $inv->invoice_number, 'amount' => (float) $inv->amount, 'currency' => $inv->currency ?: 'KES',
                    'due_date' => $inv->due_date, 'overdue' => $overdue, 'payment_link' => url('/billing'),
                    'policy' => 'Pay one month and the next three months are unlocked. Unpaid after the due date locks the account except the payment page.'],
                'context' => ['company_id' => $company->id, 'subscription_invoice_id' => $inv->id],
            ]);
        }

        return $n;
    }

    /** A company's tenants who are late: one personal follow-up draft per tenant per week. */
    public static function arrears(int $companyId): int
    {
        $row = Integrations::for($companyId, 'assistant');
        $after = $row?->get('arrears', '7');
        if (! $row || $after === 'off') {
            return 0;
        }
        $notifier = app(BillNotifier::class);
        $week = now()->format('o-\WW');
        $late = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->with(['tenancy.tenant.user', 'tenancy.unit.property'])
            ->whereIn('status', ['unpaid', 'partial'])->whereColumn('total_paid', '<', 'total_amount')
            ->whereHas('tenancy', fn ($t) => $t->where('status', 'active')->where(fn ($q) => RentRun::ownedBy($q, $companyId)))
            ->whereNotIn('tenancy_id', \App\Models\RentHold::activeTenancyIds($companyId) ?: [0])
            ->where('created_at', '<=', now()->subDays((int) $after))->orderBy('billing_month')->get()->groupBy('tenancy_id'));
        $n = 0;
        foreach ($late->take(30) as $tenancyId => $bills) {
            $tenancy = $bills->first()->tenancy;
            $user = $tenancy?->tenant?->user;
            $channel = $row->get('arrears_channel', 'sms');
            $email = filter_var($user?->email, FILTER_VALIDATE_EMAIL) ? $user->email : null;
            $channel = $channel === 'email' && ! $email ? 'sms' : $channel;
            if (! $user || (! $user->phone && $channel !== 'email')) {
                continue;
            }
            $paid = DB::table('payments')->whereIn('invoice_id', \App\Modules\Payments\Models\Invoice::withoutGlobalScopes()->where('tenancy_id', $tenancyId)->select('id'))
                ->where('status', 'completed')->latest('id')->first(['amount', 'created_at']);
            $n += (bool) Claude::draft($companyId, [
                'kind' => 'arrears', 'ref' => "arrears:{$tenancyId}:{$week}", 'channel' => $channel,
                'to_name' => $user->name, 'to_address' => $channel === 'email' ? $email : $user->phone,
                'task' => 'This tenant is behind on rent. Write a personal, respectful follow-up asking them to pay the total owed through the pay link, or to reply if they need to agree a date. Firmer if it is more than one month, never rude.',
                'facts' => ['tenant_first_name' => Str::before(trim((string) $user->name), ' ') ?: 'there', 'unit' => $tenancy->unit?->unit_number,
                    'building' => $tenancy->unit?->property?->name, 'total_owed_kes' => round($bills->sum(fn ($i) => $notifier->balance($i))),
                    'bills' => $bills->map(fn ($i) => ['month' => $i->billing_month?->format('F Y'), 'balance_kes' => round($notifier->balance($i))])->values()->all(),
                    'pay_link' => $notifier->payLink($bills->first()), 'last_payment' => $paid ? ['kes' => (float) $paid->amount, 'on' => substr((string) $paid->created_at, 0, 10)] : null],
                'context' => ['tenancy_id' => (int) $tenancyId, 'invoice_ids' => $bills->pluck('id')->all()],
            ]);
        }

        return $n;
    }

    private static function prospectFacts(Company $company): array
    {
        $manager = $company->owner_id ? \App\Models\User::find($company->owner_id) : null;

        return ['prospect' => ['company' => $company->name, 'kind' => Playbook::segmentOf($company), 'stage' => $company->crm_stage,
            'location' => $company->address ?? null, 'units' => $company->profile('units')], 'demo_link' => Playbook::demoUrl($company),
            'account_manager' => $manager?->name, 'likely_problems_and_how_scharge_helps' => array_values(Playbook::pains($company))];
    }

    /** Who gets scharge's subscription emails: the company's email, else its first admin. */
    private static function billingContact(?Company $company): ?array
    {
        if (! $company) {
            return null;
        }
        if (filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
            return ['name' => $company->name, 'email' => $company->email];
        }
        $admin = rescue(fn () => \App\Models\User::where('company_id', $company->id)->role('admin')->whereNotNull('email')->first(), null, false);

        return $admin && filter_var($admin->email, FILTER_VALIDATE_EMAIL) ? ['name' => $admin->name, 'email' => $admin->email] : null;
    }
}
