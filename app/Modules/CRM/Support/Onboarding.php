<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Support\OnboardingGuide;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nobody onboards alone. Every new customer (a sign-up, or a deal marked Won) gets an account manager at once, and
 * a company that sits on the same setup step for three days puts a call on that account manager's calendar naming
 * the step. The tasks carry details.onboarding (welcome | stall | tenant_list), which also sends them to the
 * account manager's own calendar (CalendarInvite).
 *
 * State is kept on the company (crm_profile.onboarding: step, since, alerted{step: date}); no schema change.
 * Daily check: crm:onboarding-stalls (Console\OnboardingStalls).
 */
class Onboarding
{
    /** Days on one step before the account manager is told. */
    public const STALL_DAYS = 3;

    /** Days before the same step is raised again (only once the earlier task is closed). */
    public const REPEAT_DAYS = 7;

    /** The setup steps that must happen before a company is really using scharge (OnboardingGuide chapter keys). */
    public const CORE = ['property', 'units', 'tenants', 'payments'];

    /** What the account manager does about each stuck step. */
    private const ACTIONS = [
        'property' => 'Call and add the first building with them on the phone (Properties > Add property), or ask for the list of buildings and add them yourself.',
        'units' => 'Ask for the unit list with rents. Send the Excel template and import it for them (Properties > Import).',
        'tenants' => 'Ask for their tenant list in any form (Excel, a photo of the rent book) and load it for them. Moving their data in is free.',
        'payments' => 'Set up how they get paid with them on the call: Sharet Pay, or their own paybill or till on Integrations. Then send the first bills together.',
    ];

    /** Account managers who can take a new customer: active lately when we can tell, otherwise all of them. */
    public static function managers(): Collection
    {
        $all = User::whereHas('roles', fn ($q) => $q->where('name', 'account_manager'))->whereNotNull('email')->orderBy('id')->get();
        if ($all->count() > 1 && Schema::hasTable('login_events')) {
            $recent = DB::table('login_events')->whereIn('user_id', $all->pluck('id'))->where('success', true)
                ->where('created_at', '>=', now()->subDays(30))->distinct()->pluck('user_id');
            $active = $all->whereIn('id', $recent)->values();
            if ($active->isNotEmpty()) {
                return $active;
            }
        }

        return $all;
    }

    /**
     * Gives the company an account manager if it has none: the preferred one (whoever won the deal) when they are
     * staff, else the one set in config, else the account manager with the fewest customers.
     */
    public static function assignOwner(Company $company, ?int $preferred = null): ?User
    {
        if ($company->owner_id) {
            return User::find($company->owner_id);
        }
        $managers = self::managers();
        $pick = ($preferred ? User::whereKey($preferred)->whereHas('roles', fn ($q) => $q->whereIn('name', ['account_manager', 'sales']))->first() : null)
            ?? (($fixed = (int) config('crm.onboarding_owner')) ? User::find($fixed) : null);
        if (! $pick && $managers->isNotEmpty()) {
            $load = Company::withoutGlobalScopes()->where('lifecycle', 'customer')->whereIn('owner_id', $managers->pluck('id'))
                ->selectRaw('owner_id, COUNT(*) AS n')->groupBy('owner_id')->pluck('n', 'owner_id');
            $pick = $managers->sortBy(fn (User $u) => [(int) ($load[$u->id] ?? 0), $u->id])->first();
        }
        if (! $pick) {
            return null;
        }
        $company->forceFill(['owner_id' => $pick->id])->saveQuietly();
        $company->setRelation('owner', $pick);
        $company->followUps()->create(['type' => 'note', 'status' => 'done', 'done_at' => now(),
            'title' => 'Account manager: '.$pick->name.' (given automatically)']);

        return $pick;
    }

    /** An onboarding to-do for the company's account manager (given one first if it has none). */
    public static function task(Company $company, string $kind, string $title, string $notes, array $details = [], string $type = 'call', ?int $contactId = null): CrmFollowUp
    {
        $owner = self::assignOwner($company);

        return $company->followUps()->create([
            'user_id' => $contactId, 'assigned_to' => $owner?->id, 'type' => $type, 'status' => 'open',
            'title' => $title, 'notes' => $notes, 'due_at' => self::nextWorkingTime(),
            'details' => ['onboarding' => $kind] + $details,
        ]);
    }

    /** A sign-up just created this company: its account manager calls to welcome them. */
    public static function welcome(Company $company, User $admin): ?CrmFollowUp
    {
        if ($company->followUps()->whereNotNull('details->onboarding')->exists()) {
            return null;
        }
        $kind = \App\Http\Controllers\Auth\AccountActivationController::KINDS[$company->kind ?? ''] ?? 'New customer';

        return self::task($company, 'welcome', 'Welcome call: '.$company->name.' just signed up',
            $admin->name.' ('.$kind.') signed up on the website. Phone '.($company->phone ?: $admin->phone ?: 'not given').', email '.$admin->email.".\n"
            .'Call today: ask how many buildings and units they have, offer to load their units and tenants for them (free), and book a time to send the first bills together.',
            ['signup_user' => $admin->id], 'call', $admin->id);
    }

    /**
     * The setup step the company is on: the first core chapter of its manual that is not done.
     * Short-stay hosts have no tenants or rent bills: for them it is their listings with a price.
     *
     * @return array{key: string, title: string, progress: string}|null  null once set up
     */
    public static function step(Company $company): ?array
    {
        $core = ($company->kind ?? null) === 'bnb_host' ? ['units'] : self::CORE;
        foreach ((new OnboardingGuide($company))->chapters() as $ch) {
            if (in_array($ch['key'], $core, true) && ! $ch['done']) {
                return ['key' => $ch['key'], 'title' => $ch['title'], 'progress' => $ch['progress']];
            }
        }

        return null;
    }

    /**
     * Daily check of one company. Notes when it moves to a new step; when it has sat on a step for STALL_DAYS,
     * creates the account manager's call (unless one is already open). Returns the task it created.
     */
    public static function check(Company $company, bool $dry = false): ?CrmFollowUp
    {
        $state = (array) (($company->crm_profile ?? [])['onboarding'] ?? []);
        $step = self::step($company);

        if (! $step) {
            if (! $dry && ($state['step'] ?? null) !== null) {
                self::remember($company, ['step' => null, 'since' => null, 'done_at' => now()->toIso8601String()]);
            }

            return null;
        }
        if (($state['step'] ?? null) !== $step['key']) {
            // First look at this company: it has been here since it was created. Otherwise it has just moved on.
            $since = isset($state['step']) || isset($state['done_at']) || ! $company->created_at ? now() : $company->created_at;
            $state = array_merge($state, ['step' => $step['key'], 'since' => Carbon::parse($since)->toIso8601String()]);
            if (! $dry) {
                self::remember($company, ['step' => $state['step'], 'since' => $state['since']]);
            }
        }

        $since = Carbon::parse($state['since']);
        $last = $state['alerted'][$step['key']] ?? null;
        if ($since->gt(now()->subDays(self::STALL_DAYS))
            || ($last && Carbon::parse($last)->gt(now()->subDays(self::REPEAT_DAYS)))
            || $company->followUps()->todo()->whereNotNull('details->onboarding')->exists()) {
            return null;
        }

        $days = max(self::STALL_DAYS, (int) $since->diffInDays(now()));
        $admin = User::where('company_id', $company->id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'property_manager', 'bnb_host']))->orderBy('id')->first();
        $title = 'Onboarding stalled: '.$company->name.' is stuck on "'.$step['title'].'"';
        $notes = $company->name.' has been on this step for '.$days.' days ('.$step['progress'].").\n"
            .'Contact: '.trim(($admin?->name ?? 'no admin login yet').' · '.($company->phone ?: $admin?->phone ?: 'no phone').' · '.($company->email ?: $admin?->email ?: 'no email'), ' ·').".\n"
            .'What to do: '.(self::ACTIONS[$step['key']] ?? 'Call and finish this step with them.');
        if ($dry) {
            return new CrmFollowUp(['company_id' => $company->id, 'title' => $title, 'notes' => $notes, 'assigned_to' => $company->owner_id]);
        }

        $task = self::task($company, 'stall', $title, $notes, ['step' => $step['key'], 'days' => $days], 'call', $admin?->id);
        self::remember($company, ['alerted' => array_merge((array) ($state['alerted'] ?? []), [$step['key'] => now()->toIso8601String()])]);

        return $task;
    }

    /** Customers still setting up: a real business with at least one login, not yet through the core steps. */
    public static function companies(): Collection
    {
        return Company::withoutGlobalScopes()->where('lifecycle', 'customer')
            ->when(Schema::hasColumn('companies', 'source'), fn ($q) => $q->where(fn ($w) => $w->whereNull('source')->orWhere('source', '!=', \App\Support\ApiSandbox::SOURCE)))
            ->whereIn('id', User::whereNotNull('company_id')->select('company_id'))
            ->orderBy('id')->get();
    }

    /** Merge into crm_profile.onboarding (other writers keep their own keys). */
    public static function remember(Company $company, array $changes): void
    {
        DB::transaction(function () use ($company, $changes) {
            $row = Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($company->id);
            $profile = $row->crm_profile ?? [];
            $profile['onboarding'] = array_filter(array_merge((array) ($profile['onboarding'] ?? []), $changes), fn ($v) => $v !== null && $v !== []);
            $row->forceFill(['crm_profile' => $profile])->saveQuietly();
            $company->crm_profile = $profile;
            $company->syncOriginalAttribute('crm_profile');
        });
    }

    /** Two hours from now in working hours (Mon-Fri, 08:00-17:00 Nairobi); otherwise 10:00 on the next working day. */
    public static function nextWorkingTime(): Carbon
    {
        $at = now('Africa/Nairobi')->addHours(2)->second(0);
        if ($at->isWeekend() || $at->hour >= 17 || $at->hour < 8) {
            if ($at->hour >= 17 || $at->isWeekend()) {
                $at->addDay();
            }
            while ($at->isWeekend()) {
                $at->addDay();
            }
            $at->setTime(10, 0);
        }

        return $at->utc()->setTimezone(config('app.timezone'));
    }
}
