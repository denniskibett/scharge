<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\CommissionService;
use App\Modules\CRM\Support\CrmAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Sales tracker for scharge's internal account managers: one scoreboard row each (won, lost, win rate, activity,
 * pipeline, overdue work, properties managed, platform fees earned and their commission on them) and a closing
 * board of late-stage deals with one-click stage moves. Commission rules: see CommissionService.
 * Account managers see their own book (plus unassigned leads); sysadmins see the whole team.
 */
class CrmSalesController extends Controller
{
    public function __construct(private CommissionService $commission)
    {
    }

    public const PERIODS = ['week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'quarter' => 'This quarter', 'year' => 'This year'];

    /** Stages on the closing board, in order; "advance" moves a deal one column right. */
    public const CLOSING_STAGES = ['qualified', 'demo_booked', 'proposal', 'negotiation'];

    /** Likelihood of closing per stage, for the weighted forecast. */
    public const STAGE_WEIGHTS = ['new' => 0.05, 'contacted' => 0.1, 'qualified' => 0.2, 'demo_booked' => 0.35, 'proposal' => 0.5, 'negotiation' => 0.75];

    public function index(Request $request): View
    {
        $user = $request->user();
        $seesAll = CrmAccess::seesAll($user);
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';
        [$from, $to] = self::range($period);
        $staff = CrmCompanyController::staff();
        // Account managers only track themselves; sysadmins can focus on one person.
        $ownerFilter = $seesAll ? $request->query('owner') : (string) $user->id;

        $companies = CrmAccess::companies(Company::crm(), $user)
            ->when($ownerFilter, fn ($q, $o) => $o === 'none' ? $q->whereNull('owner_id') : $q->where('owner_id', $o));

        $open = (clone $companies)->whereIn('crm_stage', Company::OPEN_CRM_STAGES)
            ->with('owner:id,name')
            ->withMin(['followUps as next_due_at' => fn ($q) => $q->todo()], 'due_at')
            ->get(['id', 'name', 'tier', 'crm_stage', 'owner_id', 'crm_profile']);

        // Stage changes inside the period, credited to the company's current owner.
        $moves = CrmFollowUp::where('type', 'stage')
            ->whereBetween('done_at', [$from, $to])
            ->whereIn('company_id', (clone $companies)->select('companies.id'))
            ->with('company:id,name,owner_id,crm_profile,crm_stage')
            ->orderByDesc('done_at')
            ->get();
        $won = $moves->filter(fn ($m) => $m->detail('to') === 'won' && $m->company?->crm_stage === 'won')->unique('company_id');
        $lost = $moves->filter(fn ($m) => $m->detail('to') === 'lost' && $m->company?->crm_stage === 'lost')->unique('company_id');

        $activity = CrmFollowUp::whereIn('type', ['call', 'email', 'whatsapp', 'meeting', 'demo'])
            ->where('status', 'done')->whereBetween('done_at', [$from, $to])
            ->when(! $seesAll, fn ($q) => $q->where('created_by', $user->id))
            ->selectRaw('COALESCE(created_by, assigned_to) as staff_id, COUNT(*) as total')
            ->groupBy('staff_id')->pluck('total', 'staff_id');
        $overdue = CrmAccess::followUps(CrmFollowUp::query(), $user)->overdue()
            ->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        $rows = $this->scoreboard($staff, $seesAll ? null : $user->id, $open, $won, $lost, $activity, $overdue, $ownerFilter);
        $lines = $this->commission->lines($from, $to, $rows->pluck('id')->filter()->values()->all());
        $earned = $this->commission->totalsByOwner($lines);
        $settings = $this->commission->settings();
        $ids = $rows->pluck('id')->filter()->values()->all();
        $managed = $this->commission->managedProperties($ids);
        $rows = $rows->map(fn ($r) => $r + [
            'properties' => $r['id'] ? (int) ($managed[$r['id']] ?? 0) : null,
            'earned' => $r['id'] ? ($earned[$r['id']] ?? ['collected' => 0, 'fees' => 0, 'commission' => 0, 'properties' => 0]) : null,
            'rate' => $r['id'] ? $this->commission->rateFor($r['id'], $settings) : null,
            // Weighted pipeline (expected monthly platform fees) × this person's share.
            'forecast_commission' => $r['id'] ? (int) round($open->where('owner_id', $r['id'])->sum(fn ($c) => self::value($c) * (self::STAGE_WEIGHTS[$c->crm_stage] ?? 0)) * $this->commission->rateFor($r['id'], $settings) / 100) : null,
        ]);

        $board = collect(self::CLOSING_STAGES)->mapWithKeys(fn ($stage) => [$stage => $open->where('crm_stage', $stage)
            ->sortBy(fn ($c) => [self::date($c->profile('expected_close'))?->timestamp ?? PHP_INT_MAX, $c->tier, $c->name])->values()]);

        return view('crm.sales', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'won' => $won->count(),
                'won_value' => $won->sum(fn ($m) => self::value($m->company)),
                'lost' => $lost->count(),
                'open' => $open->count(),
                'pipeline' => $open->sum(fn ($c) => self::value($c)),
                'forecast' => (int) round($open->sum(fn ($c) => self::value($c) * (self::STAGE_WEIGHTS[$c->crm_stage] ?? 0))),
                'collected' => $lines->sum('collected'),
                'fees' => $lines->sum('fee'),
                'commission' => $lines->sum('commission'),
                'closing' => $open->filter(fn ($c) => ($d = self::date($c->profile('expected_close'))) && $d->between($from, $to))->count(),
            ],
            'board' => $board,
            'recentWins' => $won->take(8),
            'staff' => $staff,
            'ownerFilter' => $ownerFilter,
            'seesAll' => $seesAll,
            'lines' => ($ownerFilter && $ownerFilter !== 'none') || ! $seesAll ? $lines : collect(),
            'settings' => $settings,
        ]);
    }

    /** Sysadmins set the account-manager share of platform fees, with per-person overrides. */
    public function updateCommission(Request $request): RedirectResponse
    {
        abort_unless(CrmAccess::seesAll($request->user()), 403);
        $input = $request->validate([
            'rate' => ['required', 'numeric', 'between:0,100'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.rate' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $staffIds = CrmCompanyController::staff()->pluck('id')->all();
        $overrides = collect($input['overrides'] ?? [])
            ->filter(fn ($o, $id) => in_array((int) $id, $staffIds, true) && isset($o['rate']) && $o['rate'] !== '')
            ->map(fn ($o) => ['rate' => (float) $o['rate']])
            ->all();

        $this->commission->save(['rate' => (float) $input['rate'], 'overrides' => $overrides]);

        return back()->with('success', 'Commission plan saved.');
    }

    /** Commission statement (one line per paid sweep per property) as CSV, for payroll. Managers get only their own. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';
        [$from, $to] = self::range($period);
        $owner = CrmAccess::seesAll($user) ? $request->query('owner') : $user->id;
        $lines = $this->commission->lines($from, $to, $owner && $owner !== 'none' ? [(int) $owner] : null);
        $names = CrmCompanyController::staff()->pluck('name', 'id');

        return response()->streamDownload(function () use ($lines, $names) {
            $out = fopen('php://output', 'w');
            $row = fn (array $cells) => fputcsv($out, $cells, ',', '"', '');
            $row(['Account manager', 'Company', 'Property', 'Payout date', 'Rent collected', 'Platform fee', 'Share %', 'Commission']);
            foreach ($lines as $l) {
                $row([$names[$l->owner_id] ?? $l->owner_id, $l->company, $l->property, $l->swept_at->toDateString(), $l->collected, $l->fee, $l->rate, $l->commission]);
            }
            $row(['Total', '', '', '', $lines->sum('collected'), $lines->sum('fee'), '', $lines->sum('commission')]);
            fclose($out);
        }, 'commission-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** One row per staff member (and "Unassigned"), busiest first. */
    private function scoreboard($staff, ?int $only, Collection $open, Collection $won, Collection $lost, $activity, $overdue, ?string $ownerFilter): Collection
    {
        $people = $staff->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);
        if ($only) {
            $people = $people->where('id', $only)->values();
        } elseif ($ownerFilter && $ownerFilter !== 'none') {
            $people = $people->where('id', (int) $ownerFilter)->values();
        }
        if (! $only && (! $ownerFilter || $ownerFilter === 'none')) {
            $people->push(['id' => null, 'name' => 'Unassigned']);
        }

        return $people->map(function ($p) use ($open, $won, $lost, $activity, $overdue) {
            $mine = $open->where('owner_id', $p['id']);
            $w = $won->filter(fn ($m) => $m->company->owner_id === $p['id']);
            $l = $lost->filter(fn ($m) => $m->company->owner_id === $p['id']);

            return $p + [
                'open' => $mine->count(),
                'late' => $mine->whereIn('crm_stage', ['proposal', 'negotiation'])->count(),
                'pipeline' => $mine->sum(fn ($c) => self::value($c)),
                'no_next_step' => $mine->whereNull('next_due_at')->count(),
                'won' => $w->count(),
                'won_value' => $w->sum(fn ($m) => self::value($m->company)),
                'lost' => $l->count(),
                'win_rate' => $w->count() + $l->count() ? (int) round($w->count() / ($w->count() + $l->count()) * 100) : null,
                'activity' => $p['id'] ? (int) ($activity[$p['id']] ?? 0) : null,
                'overdue' => (int) ($overdue[$p['id'] ?? ''] ?? 0),
            ];
        })->filter(fn ($r) => $r['id'] !== null || $r['open'] || $r['won'] || $r['lost'])
            ->sortByDesc(fn ($r) => [$r['won_value'], $r['won'], $r['pipeline'], $r['open']])->values();
    }

    /** Monthly deal value in KES from crm_profile.deal_value ("KES 45,000" → 45000). */
    public static function value(?Company $company): int
    {
        return (int) preg_replace('/\D/', '', (string) $company?->profile('deal_value'));
    }

    public static function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function range(string $period): array
    {
        $now = now();

        return match ($period) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }
}
