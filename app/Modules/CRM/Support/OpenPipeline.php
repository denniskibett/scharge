<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\CommissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The open pipeline as numbers, without loading the companies. The CRM holds every imported prospect as an open
 * deal (about 180,000 at stage "new"), so the Sales pages must never fetch them one by one: the database counts
 * them per account manager and stage, and only the handful of late-stage deals on the closing board are loaded.
 */
class OpenPipeline
{
    /** Likelihood of closing per stage, for the weighted forecast. */
    public const WEIGHTS = ['new' => 0.05, 'contacted' => 0.1, 'qualified' => 0.2, 'demo_booked' => 0.35, 'proposal' => 0.5, 'negotiation' => 0.75];

    /** Stages on the closing board. */
    public const CLOSING = ['qualified', 'demo_booked', 'proposal', 'negotiation'];

    /**
     * Open deals grouped by owner, stage and what sets their value.
     *
     * @return Collection<int, object{owner_id: ?int, crm_stage: string, n: int, with_next: int, value: int}>
     *                                                                         value = expected monthly fee of ONE deal in the group
     */
    public static function groups(Builder $companies, ?array $settings = null): Collection
    {
        $commission = app(CommissionService::class);
        $settings ??= $commission->settings();
        // Companies with something still to do that has a date: the rest have "no next step".
        $next = CrmFollowUp::query()->todo()->whereNotNull('due_at')->whereNotNull('company_id')->select('company_id')->distinct();

        return (clone $companies)->whereIn('crm_stage', Company::OPEN_CRM_STAGES)
            ->leftJoinSub($next, 'nx', 'nx.company_id', '=', 'companies.id')
            ->select('companies.owner_id', 'companies.crm_stage', 'companies.crm_profile->deal_value as dv', 'companies.crm_profile->estimated_units as eu')
            ->selectRaw('COUNT(*) as n, COUNT(nx.company_id) as with_next')
            ->groupBy('companies.owner_id', 'companies.crm_stage', 'dv', 'eu')
            ->toBase()->get()
            ->map(fn ($g) => (object) [
                'owner_id' => $g->owner_id === null ? null : (int) $g->owner_id, 'crm_stage' => $g->crm_stage,
                'n' => (int) $g->n, 'with_next' => (int) $g->with_next,
                'value' => $commission->dealValueFrom($g->dv, $g->eu, $settings),
            ]);
    }

    /**
     * Totals of some groups (all of them, or one owner's).
     *
     * @return array{open: int, late: int, pipeline: int, forecast: int, no_next_step: int}
     */
    public static function sum(Collection $groups): array
    {
        return [
            'open' => (int) $groups->sum('n'),
            'late' => (int) $groups->whereIn('crm_stage', ['proposal', 'negotiation'])->sum('n'),
            'pipeline' => (int) $groups->sum(fn ($g) => $g->n * $g->value),
            'forecast' => (int) round($groups->sum(fn ($g) => $g->n * $g->value * (self::WEIGHTS[$g->crm_stage] ?? 0))),
            'no_next_step' => (int) $groups->sum(fn ($g) => $g->n - $g->with_next),
        ];
    }

    /** The late-stage deals themselves (few), for the closing board. */
    public static function closing(Builder $companies, int $limit = 400): Collection
    {
        return (clone $companies)->whereIn('crm_stage', self::CLOSING)
            ->with('owner:id,name')
            ->withMin(['followUps as next_due_at' => fn ($q) => $q->todo()], 'due_at')
            ->orderBy('tier')->orderBy('name')->limit($limit)
            ->get(['id', 'name', 'tier', 'crm_stage', 'owner_id', 'crm_profile']);
    }

    /** How many open deals have an expected close date inside the period. */
    public static function closingBetween(Builder $companies, Carbon $from, Carbon $to): int
    {
        return (clone $companies)->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereNotNull('crm_profile->expected_close')
            ->select('companies.crm_profile->expected_close as expected_close')->toBase()->pluck('expected_close')
            ->filter(function ($d) use ($from, $to) {
                try {
                    return $d && Carbon::parse($d)->between($from, $to);
                } catch (\Throwable) {
                    return false;
                }
            })->count();
    }

    /** Won and lost stage changes inside the period (not the thousands of "contacted" moves a campaign writes). */
    public static function closedMoves(Builder|\Illuminate\Database\Query\Builder $companyIds, Carbon $from, Carbon $to): Collection
    {
        return CrmFollowUp::where('type', 'stage')->whereBetween('done_at', [$from, $to])
            ->whereIn('details->to', ['won', 'lost'])
            ->whereIn('company_id', $companyIds)
            ->with('company:id,name,owner_id,crm_profile,crm_stage')
            ->orderByDesc('done_at')->get();
    }
}
