<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\CRM\Controllers\Admin\CrmCompanyController;
use App\Modules\CRM\Controllers\Admin\CrmReplyController;
use App\Modules\CRM\Controllers\Admin\CrmSalesController;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Models\WaitlistEntry;
use App\Modules\CRM\Services\CommissionService;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Services\ReplyService;
use App\Modules\CRM\Support\CrmAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The CRM in the scharge app for scharge's sales people (sysadmin, account_manager, sales): the same book as the web
 * CRM (app/Modules/CRM). Every query goes through CrmAccess, so an account manager sees and changes only the prospects
 * they own plus unassigned leads; sysadmins and "crm.all" people see the whole pipeline. Sales and commission of other
 * people stay sysadmin-only (CrmAccess::seesAll), as on CRM › Sales tracker.
 * Routes: routes/api.php (MobileCan 'crm' + crm.view / crm.manage).
 */
class CrmController extends Controller
{
    use Presents;

    public function __construct(private ContactService $contacts)
    {
    }

    /** "My day": replies waiting, follow-ups due, new requests, and the book in numbers (CRM index stats). */
    public function summary(Request $request)
    {
        $user = $request->user();
        $mine = fn () => CrmAccess::companies(Company::crm(), $user);
        $fu = fn () => CrmAccess::followUps(CrmFollowUp::query(), $user);
        $todo = fn ($q) => $q->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', CrmFollowUp::NOT_TODO);

        $replies = $mine()->where('companies.reply_pending', 1)->orderByDesc('crm_profile->reply->at')->limit(20)
            ->get(['companies.id', 'name', 'tier', 'crm_stage', 'owner_id', 'crm_profile']);
        $due = $fu()->todo()->whereNotNull('due_at')->where('due_at', '<=', now()->endOfDay())
            ->with(['company:id,name,crm_stage', 'contact:id,name,phone,email'])->orderBy('due_at')->limit(30)->get();

        return response()->json([
            'sees_all' => CrmAccess::seesPipeline($user),
            'stats' => [
                'open' => $mine()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->count(),
                'replied' => $mine()->where('companies.reply_pending', 1)->count(),
                'overdue' => $fu()->overdue()->count(),
                'due_today' => $fu()->dueToday()->count(),
                'unattended' => $mine()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereDoesntHave('followUps', $todo)->count(),
                'new_requests' => $fu()->whereIn('type', array_keys(CrmFollowUp::REQUEST_TYPES))->where('status', 'open')->count(),
                'waitlist' => WaitlistEntry::whereIn('status', ['waiting', 'demo_requested'])->count(),
            ],
            'replies' => $replies->map(fn ($c) => $this->prospectRow($c))->values(),
            'due' => $due->map(fn ($f) => $this->followUp($f))->values(),
            'stages' => self::stages(),
        ]);
    }

    /** The pipeline / prospect list, with the web list's main filters (q, stage, attention, segment, county). */
    public function prospects(Request $request)
    {
        $user = $request->user();
        $todo = fn ($q) => $q->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', CrmFollowUp::NOT_TODO);
        $attention = $request->query('attention');
        $term = trim((string) $request->query('q'));

        // Phone-sized and fast: page on indexed columns only (replies first, then tier, then name); the next-due date
        // is looked up for the rows on this page, not grouped over every follow-up as the web list does for sorting.
        $page = CrmAccess::companies(Company::crm(), $user)
            ->select('companies.id')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($request->query('stage'), fn ($q, $stage) => $stage === 'open' ? $q->whereIn('crm_stage', Company::OPEN_CRM_STAGES) : $q->where('crm_stage', $stage))
            ->when($request->query('owner') === 'me', fn ($q) => $q->where('owner_id', $user->id))
            ->when($request->query('owner') === 'none', fn ($q) => $q->whereNull('owner_id'))
            ->when($attention === 'replied', fn ($q) => $q->where('companies.reply_pending', 1))
            ->when($attention === 'overdue', fn ($q) => $q->whereHas('followUps', fn ($f) => $f->overdue()))
            ->when($attention === 'unattended', fn ($q) => $q->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereDoesntHave('followUps', $todo))
            ->when($request->query('segment'), fn ($q, $v) => $q->where('crm_profile->segment', $v))
            ->when($request->query('county'), fn ($q, $v) => $q->where('crm_profile->county', $v))
            ->orderByDesc('companies.reply_pending')->orderBy('companies.tier')->orderBy('companies.name')->orderBy('companies.id')
            ->paginate(min(50, max(10, (int) $request->query('per_page', 25))));

        $ids = collect($page->items())->pluck('id');
        $models = Company::withoutGlobalScope('customers')->with('owner:id,name')->findMany($ids)->keyBy('id');
        $due = $todo(CrmFollowUp::query())->whereIn('company_id', $ids->all() ?: [0])->selectRaw('company_id, MIN(due_at) as next_due_at')
            ->groupBy('company_id')->pluck('next_due_at', 'company_id');
        $rows = $ids->map(fn ($id) => isset($models[$id]) ? $this->prospectData($models[$id], $due[$id] ?? null) : null)->filter()->values();

        return response()->json(['data' => $rows, 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'stages' => self::stages()]);
    }

    /** One prospect: details, contacts, to-dos and the latest history. */
    public function prospect(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $rows = $company->followUps()->with(['contact:id,name,phone,email', 'assignee:id,name'])->orderByDesc('created_at')->orderByDesc('id')->limit(80)->get();

        return response()->json($this->prospectRow($company) + [
            'contacts' => $rows->where('type', 'contact')->map(fn ($r) => ['name' => $r->contact?->name, 'phone' => $r->contact?->phone, 'email' => $r->contact?->email,
                'role' => $r->detail('role'), 'primary' => (bool) $r->detail('primary')])->values(),
            'todo' => $rows->filter(fn ($r) => $r->is_open && ! in_array($r->type, CrmFollowUp::NOT_TODO, true))->sortBy('due_at')->map(fn ($f) => $this->followUp($f))->values(),
            'history' => $rows->filter(fn ($r) => ! $r->is_open && $r->type !== 'contact')->take(30)->map(fn ($f) => $this->followUp($f))->values(),
            'stages' => self::stages(),
            'action_types' => array_intersect_key(CrmFollowUp::TYPES, array_flip(CrmFollowUp::ACTION_TYPES)),
        ]);
    }

    /** Move the stage (same as the web Status card: "won" turns a prospect into a customer). */
    public function stage(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'stage' => ['required', Rule::in(array_keys(Company::CRM_STAGES))],
            'reason' => ['nullable', 'required_if:stage,lost', 'string', 'max:255'],
        ], ['reason.required_if' => 'Say why it was lost.']);
        $author = $request->user()->id;
        $this->contacts->setStage($company, $input['stage'], $author, $input['reason'] ?? null);
        if ($input['stage'] === 'won' && $company->lifecycle === 'prospect') {
            $company->update(['lifecycle' => 'customer', 'is_active' => true]);
            $this->contacts->log($company, 'note', 'Became a customer', 'Set up their subscription from Admin › Companies.', [], null, $author);
        }

        return response()->json($this->prospectRow($company->fresh()));
    }

    /** Log something that happened (mode=log) or schedule something to do (mode=schedule), as on the web. */
    public function addFollowUp(Request $request, int $id)
    {
        $company = $this->company($request, $id);
        $schedule = $request->input('mode') === 'schedule';
        $input = $request->validate([
            'mode' => ['required', Rule::in(['log', 'schedule'])],
            'type' => ['required', Rule::in(CrmFollowUp::ACTION_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_at' => [$schedule ? 'required' : 'nullable', 'date'],
        ]);
        $author = $request->user()->id;
        $row = $company->followUps()->create([
            'assigned_to' => $company->owner_id ?? $author, 'created_by' => $author, 'type' => $input['type'],
            'status' => $schedule ? 'open' : 'done', 'title' => $input['title'], 'notes' => $input['notes'] ?? null,
            'due_at' => $schedule ? $input['due_at'] : null, 'done_at' => $schedule ? null : now(),
        ]);
        if (! $schedule && $input['type'] !== 'note' && $company->crm_stage === 'new') {
            $this->contacts->setStage($company, 'contacted', $author);
        }

        return response()->json($this->followUp($row->load('company:id,name,crm_stage')), 201);
    }

    /** Clear a prospect's reply alert, optionally moving the stage (CRM › reply "Mark handled"). */
    public function replyHandled(Request $request, int $id, ReplyService $replies)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(CrmReplyController::stages()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $replies->handled($company, $request->user(), $input['stage'] ?? null, $input['note'] ?? null);

        return response()->json($this->prospectRow($company->fresh()));
    }

    /** Answer the reply in the same e-mail thread (from the prospect's account manager's work address). */
    public function replyAnswer(Request $request, int $id, ReplyService $replies)
    {
        $company = $this->company($request, $id);
        $input = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'stage' => ['nullable', Rule::in(array_keys(CrmReplyController::stages()))],
        ]);
        try {
            $replies->answer($company, $request->user(), $input['body'], $input['stage'] ?? null);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The answer could not be sent: '.$e->getMessage()], 422);
        }

        return response()->json($this->prospectRow($company->fresh()));
    }

    /** Follow-ups: overdue, today, open (to do) or done. */
    public function followUps(Request $request)
    {
        $view = in_array($request->query('view'), ['overdue', 'today', 'open', 'done'], true) ? $request->query('view') : 'open';
        $q = $this->visible($request)->with(['company:id,name,tier,crm_stage', 'contact:id,name,phone,email', 'assignee:id,name'])
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($view === 'today', fn ($q) => $q->dueToday())
            ->when($view === 'open', fn ($q) => $q->todo())
            ->when($view === 'done', fn ($q) => $q->whereIn('type', array_merge(CrmFollowUp::ACTION_TYPES, ['demo']))->whereIn('status', ['done', 'missed', 'cancelled']))
            ->orderBy($view === 'done' ? 'done_at' : 'due_at', $view === 'done' ? 'desc' : 'asc');

        return response()->json($this->page($q, fn ($f) => $this->followUp($f), $request) + ['counts' => [
            'overdue' => $this->visible($request)->overdue()->count(),
            'today' => $this->visible($request)->dueToday()->count(),
            'open' => $this->visible($request)->todo()->count(),
        ]]);
    }

    /** Close, miss, cancel or reschedule a follow-up, and book the next step in one go (web: Follow-ups › Update). */
    public function updateFollowUp(Request $request, int $id)
    {
        $followUp = $this->visible($request)->find($id);
        abort_unless($followUp, 403, 'This follow-up belongs to another account manager.');
        $input = $request->validate([
            'status' => ['required', Rule::in(array_keys(CrmFollowUp::STATUSES))],
            'outcome' => ['nullable', 'required_if:status,done,missed,cancelled', 'string', 'max:255'],
            'due_at' => ['nullable', 'required_if:status,scheduled', 'date'],
            'next_title' => ['nullable', 'string', 'max:255'],
            'next_due_at' => ['nullable', 'required_with:next_title', 'date', 'after:now'],
        ], ['outcome.required_if' => 'Say what happened so the next person has context.', 'due_at.required_if' => 'Pick the date and time.']);
        $author = $request->user()->id;
        $closed = in_array($input['status'], ['done', 'missed', 'cancelled'], true);
        $followUp->update(array_filter([
            'status' => $input['status'], 'outcome' => $input['outcome'] ?? null,
            'due_at' => $input['status'] === 'scheduled' ? $input['due_at'] : null, 'done_at' => $closed ? now() : null,
        ], fn ($v) => $v !== null));
        if (! empty($input['next_title'])) {
            CrmFollowUp::create([
                'company_id' => $followUp->company_id, 'user_id' => $followUp->user_id, 'assigned_to' => $followUp->assigned_to ?? $author,
                'created_by' => $author, 'type' => in_array($followUp->type, CrmFollowUp::ACTION_TYPES, true) ? $followUp->type : 'call',
                'status' => 'open', 'title' => $input['next_title'], 'due_at' => $input['next_due_at'],
            ]);
        }
        $company = $followUp->company;
        $warning = $closed && empty($input['next_title']) && $company && in_array($company->crm_stage, Company::OPEN_CRM_STAGES, true) && ! $company->openFollowUps()->exists()
            ? "{$company->name} has no next step booked. Schedule one so they don't go cold." : null;

        return response()->json($this->followUp($followUp->fresh(['company:id,name,crm_stage', 'contact:id,name,phone,email'])) + ['warning' => $warning]);
    }

    /** Inbound website requests: demos, API keys, listing requests, listing alerts. */
    public function requests(Request $request)
    {
        $type = array_key_exists($request->query('type'), CrmFollowUp::REQUEST_TYPES) ? $request->query('type') : 'demo';
        $q = $this->visible($request)->where('type', $type)->with(['contact:id,name,phone,email', 'company:id,name,crm_stage', 'assignee:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest();

        return response()->json($this->page($q, fn ($f) => $this->followUp($f), $request) + [
            'types' => CrmFollowUp::REQUEST_TYPES,
            'open' => $this->visible($request)->whereIn('type', array_keys(CrmFollowUp::REQUEST_TYPES))->where('status', 'open')
                ->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    public function waitlist(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $q = WaitlistEntry::query()->with(['user:id,name,phone,email', 'company:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($term !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest();

        return response()->json($this->page($q, fn ($e) => ['id' => $e->id, 'name' => $e->user?->name, 'phone' => $e->user?->phone, 'email' => $e->user?->email,
            'country' => $e->country_name, 'company' => $e->company?->name, 'status' => $e->status, 'status_label' => WaitlistEntry::STATUSES[$e->status] ?? $e->status,
            'project' => $e->detail('project_type'), 'units' => $e->detail('units'), 'joined_at' => $e->created_at?->toIso8601String()], $request)
            + ['statuses' => WaitlistEntry::STATUSES]);
    }

    public function updateWaitlist(Request $request, int $id)
    {
        $entry = WaitlistEntry::findOrFail($id);
        $entry->update($request->validate(['status' => ['required', Rule::in(array_keys(WaitlistEntry::STATUSES))]]));

        return response()->json(['id' => $entry->id, 'status' => $entry->status, 'status_label' => WaitlistEntry::STATUSES[$entry->status] ?? $entry->status]);
    }

    /**
     * Sales tracker + funnel for this person (sysadmins: the whole team): open deals and the stage funnel of their
     * book, wins and losses in the period, settled platform fees and their commission, forecast. Same rules as
     * CRM › Sales tracker (CommissionService).
     */
    public function sales(Request $request, CommissionService $commission)
    {
        $user = $request->user();
        $seesAll = CrmAccess::seesAll($user);
        $period = array_key_exists($request->query('period'), CrmSalesController::PERIODS) ? $request->query('period') : 'month';
        [$from, $to] = CrmSalesController::range($period);
        $book = fn () => CrmAccess::companies(Company::crm(), $user)->when(! $seesAll, fn ($q) => $q->where('owner_id', $user->id));

        $open = $book()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->withMin(['followUps as next_due_at' => fn ($q) => $q->todo()], 'due_at')
            ->get(['id', 'name', 'tier', 'crm_stage', 'owner_id', 'crm_profile']);
        $moves = CrmFollowUp::where('type', 'stage')->whereBetween('done_at', [$from, $to])->whereIn('company_id', $book()->select('companies.id'))
            ->with('company:id,name,owner_id,crm_profile,crm_stage')->orderByDesc('done_at')->get();
        $won = $moves->filter(fn ($m) => $m->detail('to') === 'won' && $m->company?->crm_stage === 'won')->unique('company_id');
        $lost = $moves->filter(fn ($m) => $m->detail('to') === 'lost' && $m->company?->crm_stage === 'lost')->unique('company_id');

        $settings = $commission->settings();
        $lines = $commission->lines($from, $to, $seesAll ? null : [$user->id]);
        $rate = $seesAll ? null : $commission->rateFor($user->id, $settings);
        $managed = $seesAll ? null : ($commission->managed([$user->id])[$user->id] ?? null);
        $value = fn ($c) => CrmSalesController::value($c);
        $forecast = (int) round($open->sum(fn ($c) => $value($c) * (CrmSalesController::STAGE_WEIGHTS[$c->crm_stage] ?? 0)));
        $counts = $book()->selectRaw('crm_stage, COUNT(*) as total')->groupBy('crm_stage')->pluck('total', 'crm_stage');

        return response()->json([
            'period' => $period, 'periods' => CrmSalesController::PERIODS, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'scope' => $seesAll ? 'team' : 'mine',
            'funnel' => collect(Company::CRM_STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)])->values(),
            'totals' => [
                'open' => $open->count(), 'pipeline' => self::money($open->sum($value)), 'forecast' => self::money($forecast),
                'won' => $won->count(), 'won_value' => self::money($won->sum(fn ($m) => $value($m->company))), 'lost' => $lost->count(),
                'win_rate' => $won->count() + $lost->count() ? (int) round($won->count() / ($won->count() + $lost->count()) * 100) : null,
                'no_next_step' => $open->whereNull('next_due_at')->count(),
                'fees' => self::money($lines->sum('fee')), 'commission' => self::money($lines->sum('commission')),
                'rate' => $rate, 'forecast_commission' => $rate !== null ? self::money(round($forecast * $rate / 100)) : null,
                'units' => $managed ? (int) $managed->units : ($seesAll ? null : 0),
                'properties' => $managed ? (int) $managed->properties : ($seesAll ? null : 0),
            ],
            'closing' => $open->whereIn('crm_stage', CrmSalesController::CLOSING_STAGES)->sortBy('tier')->take(30)->map(fn ($c) => $this->prospectRow($c) + ['value' => self::money($value($c))])->values(),
            'wins' => $won->take(10)->map(fn ($m) => ['id' => $m->company_id, 'name' => $m->company?->name, 'at' => $m->done_at?->toIso8601String(), 'value' => self::money($value($m->company))])->values(),
            // Paid platform invoices of the companies this person owns (commission is earned once a bill is settled).
            'lines' => $seesAll ? [] : $lines->sortByDesc('paid_at')->take(40)->map(fn ($l) => ['company' => $l->company, 'property' => $l->property,
                'invoice' => $l->invoice_number ?? $l->invoice_id, 'period' => $l->period, 'paid_at' => $l->paid_at?->toIso8601String(), 'units' => $l->units,
                'fee' => self::money($l->fee), 'commission' => self::money($l->commission)])->values(),
        ]);
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function company(Request $request, int $id): Company
    {
        $company = Company::crm()->find($id);
        abort_unless($company, 404, 'No such prospect.');
        CrmAccess::authorizeCompany($company, $request->user());

        return $company;
    }

    private function visible(Request $request): Builder
    {
        return CrmAccess::followUps(CrmFollowUp::query(), $request->user());
    }

    private function prospectRow(Company $c, $nextDue = null): array
    {
        return $this->prospectData($c->loadMissing('owner:id,name'), $nextDue);
    }

    private function prospectData(Company $c, $nextDue = null): array
    {
        $reply = $c->profile('reply');
        $pending = (bool) $c->profile('reply_pending', $c->reply_pending); // the column is a stored copy of this

        return [
            'id' => $c->id, 'name' => $c->name, 'tier' => $c->tier, 'stage' => $c->crm_stage, 'stage_label' => Company::CRM_STAGES[$c->crm_stage] ?? $c->crm_stage,
            'owner' => $c->relationLoaded('owner') ? $c->owner?->name : null, 'mine' => $c->owner_id !== null && (int) $c->owner_id === (int) request()->user()?->id, 'unassigned' => $c->owner_id === null,
            'phone' => $c->phone, 'email' => $c->email, 'website' => $c->profile('website'), 'segment' => $c->profile('segment'),
            'place' => collect([$c->profile('city') ?? $c->profile('location'), $c->profile('sub_county'), $c->profile('county')])->filter()->unique()->join(', ') ?: null,
            'units' => $c->profile('units'), 'expected_close' => $c->profile('expected_close'), 'lost_reason' => $c->profile('lost_reason'),
            'next_due_at' => $nextDue ? \Illuminate\Support\Carbon::parse($nextDue)->toIso8601String() : null,
            'reply' => $pending && is_array($reply) ? ['from' => $reply['name'] ?? $reply['from'] ?? null, 'subject' => $reply['subject'] ?? null,
                'snippet' => $reply['snippet'] ?? null, 'at' => $reply['at'] ?? null, 'opt_out' => (bool) ($reply['opt_out'] ?? false)] : null,
        ];
    }

    private function followUp(CrmFollowUp $f): array
    {
        return [
            'id' => $f->id, 'type' => $f->type, 'type_label' => CrmFollowUp::TYPES[$f->type] ?? $f->type, 'status' => $f->status,
            'status_label' => (in_array($f->type, ['demo', 'viewing', 'api_keys'], true) ? CrmFollowUp::DEMO_STATUSES : CrmFollowUp::STATUSES)[$f->status] ?? $f->status,
            'title' => $f->title, 'notes' => $f->notes, 'outcome' => $f->outcome,
            'due_at' => $f->due_at?->toIso8601String(), 'done_at' => $f->done_at?->toIso8601String(), 'created_at' => $f->created_at?->toIso8601String(),
            'overdue' => (bool) $f->is_overdue, 'open' => (bool) $f->is_open,
            'company' => $f->company ? ['id' => $f->company->id, 'name' => $f->company->name, 'stage' => $f->company->crm_stage] : null,
            'contact' => $f->contact ? ['name' => $f->contact->name, 'phone' => $f->contact->phone, 'email' => $f->contact->email] : null,
            'assigned_to' => $f->relationLoaded('assignee') ? $f->assignee?->name : null,
        ];
    }

    private static function stages(): array
    {
        return collect(Company::CRM_STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    private function page($query, callable $map, Request $request): array
    {
        $p = $query->paginate(min(50, max(10, (int) $request->query('per_page', 25))));

        return ['data' => collect($p->items())->map($map)->values(), 'page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()];
    }
}
