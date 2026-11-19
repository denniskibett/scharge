<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What happened in the CRM in a time window: the cold-email funnel, new prospects and replies, what each
 * account manager did, and the hot leads to call next. Used by CRM › Team and the 07:30 daily recap email.
 * Rows the system wrote (campaign sends, auto-assignments, stage New → Contacted) have no author, so they
 * never count as a person's work.
 */
class CrmRecap
{
    public function __construct(public Carbon $from, public Carbon $to)
    {
    }

    /** Yesterday in Nairobi time (the working day the recap describes). */
    public static function yesterday(): self
    {
        $tz = config('app.schedule_timezone', 'Africa/Nairobi');

        return new self(now($tz)->subDay()->startOfDay()->utc(), now($tz)->startOfDay()->utc());
    }

    private function fu()
    {
        return CrmFollowUp::query();
    }

    private function companies()
    {
        return Company::withoutGlobalScope('customers');
    }

    /** Cold emails sent in the window and how far each got so far, plus things that happened in the window to any email. */
    public function funnel(): array
    {
        $sent = $this->fu()->where('type', 'email')->whereNotNull('details->track')->whereBetween('created_at', [$this->from, $this->to])->get();
        $reached = fn (string $step) => $sent->filter(fn ($e) => $e->detail($step.'_at'))->count();
        $events = fn (string $step) => $this->fu()->where('type', 'email')->whereNotNull('details->track')
            ->where('details->'.$step.'_at', '>=', $this->from->copy()->tz(config('app.timezone'))->toDateTimeString())
            ->where('details->'.$step.'_at', '<', $this->to->copy()->tz(config('app.timezone'))->toDateTimeString())->count();

        return [
            'sent' => $sent->count(),
            'by_campaign' => $sent->countBy(fn ($e) => strtok((string) ($e->detail('campaign') ?? 'Playbook'), ' '))->all(),
            'delivered' => $sent->filter(fn ($e) => in_array(EmailTracking::status($e), ['delivered', 'opened', 'clicked', 'replied', 'booked'], true))->count(),
            'bounced' => $sent->filter(fn ($e) => EmailTracking::status($e) === 'bounced')->count(),
            'waiting' => $sent->filter(fn ($e) => in_array(EmailTracking::status($e), ['sent', 'deferred'], true))->count(),
            'opened' => $reached('opened'), 'clicked' => $reached('clicked'), 'replied' => $reached('replied'), 'booked' => $reached('booked'),
            // anything in the window, including emails sent earlier
            'events' => ['opened' => $events('opened'), 'clicked' => $events('clicked'), 'replied' => $events('replied'), 'booked' => $events('booked')],
        ];
    }

    public function totals(): array
    {
        return [
            'new_prospects' => $this->companies()->whereBetween('created_at', [$this->from, $this->to])->count(),
            'prospects' => $this->companies()->where('lifecycle', 'prospect')->count(),
            'replies' => $this->fu()->where('type', 'email')->where('details->direction', 'in')->whereNull('details->auto')->whereBetween('created_at', [$this->from, $this->to])->count(),
            'replies_waiting' => $this->companies()->where('crm_profile->reply_pending', true)->count(),
            'demo_requests' => $this->fu()->where('type', 'demo')->whereBetween('created_at', [$this->from, $this->to])->count(),
            'stage_moves' => $this->fu()->where('type', 'stage')->whereNotNull('created_by')->whereBetween('created_at', [$this->from, $this->to])->get()
                ->countBy(fn ($f) => Company::CRM_STAGES[$f->detail('to')] ?? $f->detail('to'))->all(),
            'ready' => collect(Playbook::REF_PREFIX)->unique()->filter(fn ($p) => in_array($p, ['RESIDENTIAL', 'BNB', 'COMMERCIAL', 'UTILITIES'], true))
                ->mapWithKeys(fn ($p, $class) => [$p => EmailTracking::ready(PropertyClass::scope($this->companies(), $class))->count()])->all(),
            'overdue' => $this->fu()->overdue()->count(),
        ];
    }

    /** One row per person: their book and what they did in the window. */
    public function people(Collection $ids): Collection
    {
        $count = fn ($q, string $col) => $q->selectRaw($col.' as uid, COUNT(*) as n')->groupBy('uid')->pluck('n', 'uid');
        $in = fn ($q, string $col = 'created_at') => $q->whereBetween($col, [$this->from, $this->to]);
        $owned = $count($this->companies()->whereIn('owner_id', $ids), 'owner_id');
        $open = $count($this->companies()->whereIn('owner_id', $ids)->whereIn('crm_stage', Company::OPEN_CRM_STAGES), 'owner_id');
        $waiting = $count($this->companies()->whereIn('owner_id', $ids)->where('crm_profile->reply_pending', true), 'owner_id');
        $cold = $count($in($this->fu()->where('type', 'email')->whereNotNull('details->track')->whereIn('assigned_to', $ids)), 'assigned_to');
        $opened = $count($this->fu()->where('type', 'email')->whereNotNull('details->track')->whereIn('assigned_to', $ids)
            ->where('details->opened_at', '>=', $this->from->copy()->tz(config('app.timezone'))->toDateTimeString())
            ->where('details->opened_at', '<', $this->to->copy()->tz(config('app.timezone'))->toDateTimeString()), 'assigned_to');
        $replies = $in($this->fu()->where('crm_follow_ups.type', 'email')->where('crm_follow_ups.details->direction', 'in')->whereNull('crm_follow_ups.details->auto'), 'crm_follow_ups.created_at')
            ->join('companies', 'companies.id', '=', 'crm_follow_ups.company_id')->whereIn('companies.owner_id', $ids)
            ->selectRaw('companies.owner_id as uid, COUNT(*) as n')->groupBy('uid')->pluck('n', 'uid');
        $answered = $count($in($this->fu()->where('type', 'email')->where('details->direction', 'out')->whereIn('created_by', $ids)), 'created_by');
        $done = $in($this->fu()->where('status', 'done')->whereIn('created_by', $ids)->whereIn('type', ['call', 'whatsapp', 'meeting', 'note', 'stage', 'demo'])
            ->where('title', 'not like', 'Owner:%'), 'done_at')
            ->selectRaw('created_by as uid, type, COUNT(*) as n')->groupBy('uid', 'type')->get()->groupBy('uid')->map(fn ($r) => $r->pluck('n', 'type'));
        $overdue = $count($this->fu()->overdue()->whereIn('assigned_to', $ids), 'assigned_to');
        $lastActive = $this->fu()->whereIn('created_by', $ids)->where('title', 'not like', 'Owner:%')
            ->selectRaw('created_by as uid, MAX(created_at) as at')->groupBy('uid')->pluck('at', 'uid');
        $lastLogin = Schema::hasTable('login_events')
            ? DB::table('login_events')->whereIn('user_id', $ids)->where('success', true)->selectRaw('user_id as uid, MAX(created_at) as at')->groupBy('uid')->pluck('at', 'uid')
            : collect();
        $loggedIn = Schema::hasTable('login_events')
            ? DB::table('login_events')->whereIn('user_id', $ids)->where('success', true)->whereBetween('created_at', [$this->from, $this->to])->distinct()->pluck('user_id')->all()
            : [];

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email'])->map(fn (User $p) => [
            'user' => $p,
            'owned' => (int) ($owned[$p->id] ?? 0), 'open' => (int) ($open[$p->id] ?? 0),
            'cold' => (int) ($cold[$p->id] ?? 0), 'opened' => (int) ($opened[$p->id] ?? 0), 'replies' => (int) ($replies[$p->id] ?? 0),
            'waiting' => (int) ($waiting[$p->id] ?? 0), 'answered' => (int) ($answered[$p->id] ?? 0),
            'calls' => (int) ($done[$p->id]['call'] ?? 0), 'whatsapp' => (int) ($done[$p->id]['whatsapp'] ?? 0),
            'meetings' => (int) (($done[$p->id]['meeting'] ?? 0) + ($done[$p->id]['demo'] ?? 0)),
            'notes' => (int) ($done[$p->id]['note'] ?? 0), 'stages' => (int) ($done[$p->id]['stage'] ?? 0),
            'overdue' => (int) ($overdue[$p->id] ?? 0),
            'logged_in' => in_array($p->id, $loggedIn),
            'last_active' => isset($lastActive[$p->id]) ? Carbon::parse($lastActive[$p->id]) : null,
            'last_login' => isset($lastLogin[$p->id]) ? Carbon::parse($lastLogin[$p->id]) : null,
        ]);
    }

    /**
     * Prospects who opened or clicked a cold email but haven't replied, booked or moved past Contacted:
     * the warmest calls to make next. Clicked first, then most opens.
     */
    public function hotLeads(?Collection $ownerIds = null, int $limit = 25): Collection
    {
        return $this->fu()->where('type', 'email')->whereNotNull('details->track')
            ->where(fn ($q) => $q->whereNotNull('details->opened_at')->orWhereNotNull('details->clicked_at'))
            ->whereNull('details->booked_at')->whereNull('details->replied_at')
            ->with(['company' => fn ($q) => $q->with('owner:id,name')])
            ->latest('id')->limit(500)->get()
            ->filter(fn ($e) => $e->company && in_array($e->company->crm_stage, [null, 'new', 'contacted'], true) && ! $e->company->profile('reply_pending')
                && (! $ownerIds || $ownerIds->contains($e->company->owner_id)))
            ->unique('company_id')
            ->sortByDesc(fn ($e) => [(bool) $e->detail('clicked_at'), (int) $e->detail('opens'), (string) $e->detail('opened_at')])
            ->take($limit)->values();
    }
}
