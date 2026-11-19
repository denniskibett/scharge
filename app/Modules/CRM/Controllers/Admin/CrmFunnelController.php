<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\CrmAccess;
use App\Modules\CRM\Support\CrmRecap;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The sales funnel: where every prospect stands now (New → Won, with the step-to-step conversion),
 * how many moved into each stage in the period, and how far the cold emails got.
 * Everyone opens on their own book (companies.owner_id); sysadmins open on the whole team. Sysadmins and
 * "crm.all" people can switch to the whole team or any one person; others only ever see their own book.
 */
class CrmFunnelController extends Controller
{
    public const PERIODS = ['week' => 'Last 7 days', 'month' => 'Last 30 days', 'quarter' => 'Last 90 days', 'year' => 'Last 12 months'];

    /** The path a deal takes, in order. Lost and Nurture sit beside it. */
    public const PATH = ['new', 'contacted', 'qualified', 'demo_booked', 'proposal', 'negotiation', 'won'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $seesTeam = CrmAccess::seesPipeline($user);
        $staff = CrmCompanyController::staff();
        // Everyone opens on their own book; sysadmins open on the whole team. ?person=all or a person's id switches.
        $asked = $request->query('person');
        $person = ! $seesTeam ? $user : match (true) {
            $asked === 'all' => null,
            filled($asked) => $staff->firstWhere('id', (int) $asked),
            default => CrmAccess::seesAll($user) ? null : $user,
        };
        $ownerIds = $person ? collect([$person->id]) : null; // null = everyone

        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';
        $days = ['week' => 7, 'month' => 30, 'quarter' => 90, 'year' => 365][$period];
        $recap = new CrmRecap(now()->subDays($days), now());

        // Prospects, plus customers that went through the pipeline (self sign-ups with no stage stay out).
        $companies = fn () => Company::withoutGlobalScope('customers')->where(fn ($q) => $q->where('lifecycle', 'prospect')->orWhereNotNull('crm_stage'))
            ->when($ownerIds, fn ($q) => $q->whereIn('owner_id', $ownerIds));

        // Where everyone stands now. No stage yet counts as New.
        $now = $companies()->selectRaw("COALESCE(crm_stage, 'new') as stage, COUNT(*) as n")->groupBy('stage')->pluck('n', 'stage')->map(fn ($n) => (int) $n);
        $steps = $this->steps($now);

        // Moves into each stage in the period (people and the system, e.g. a campaign email moves New → Contacted).
        $moves = CrmFollowUp::query()->where('type', 'stage')->whereBetween('created_at', [$recap->from, $recap->to])
            ->when($ownerIds, fn ($q) => $q->whereIn('company_id', $companies()->select('id')))
            ->get(['details'])->countBy(fn ($f) => $f->detail('to'));

        $byPerson = $seesTeam && ! $person ? $this->byPerson($staff->pluck('id')) : collect();

        return view('crm.funnel', [
            'steps' => $steps, 'now' => $now, 'moves' => $moves, 'emails' => $recap->funnel($ownerIds), 'byPerson' => $byPerson,
            'staff' => $staff, 'person' => $person, 'seesTeam' => $seesTeam, 'period' => $period, 'recap' => $recap,
        ]);
    }

    /**
     * One row per step of the path: how many are at it now, how many reached it (at it or further along),
     * and the share of the previous step that got this far.
     */
    private function steps(Collection $now): array
    {
        $out = [];
        $prev = null;
        foreach (self::PATH as $i => $stage) {
            $reached = collect(array_slice(self::PATH, $i))->sum(fn ($s) => $now[$s] ?? 0);
            $out[] = ['stage' => $stage, 'label' => Company::CRM_STAGES[$stage], 'now' => (int) ($now[$stage] ?? 0), 'reached' => $reached,
                'rate' => $prev ? round($reached / $prev * 100, 1) : null];
            $prev = $reached ?: null;
        }

        return $out;
    }

    /** Each person's book by stage. */
    private function byPerson(Collection $ids): Collection
    {
        $rows = Company::withoutGlobalScope('customers')->whereIn('owner_id', $ids)->where(fn ($q) => $q->where('lifecycle', 'prospect')->orWhereNotNull('crm_stage'))
            ->selectRaw("owner_id, COALESCE(crm_stage, 'new') as stage, COUNT(*) as n")->groupBy('owner_id', 'stage')->get()
            ->groupBy('owner_id')->map(fn ($g) => $g->pluck('n', 'stage')->map(fn ($n) => (int) $n));

        return CrmCompanyController::staff()->whereIn('id', $ids)->map(fn ($p) => ['user' => $p, 'stages' => $rows[$p->id] ?? collect()])
            ->filter(fn ($r) => $r['stages']->sum() > 0)->values();
    }
}
