<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Support\CrmAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * /calendar: one calendar of what is happening, from the data scharge already has.
 * Each user sees what their role is about, inside their own company (tenants: only their own).
 * Feed: GET /calendar/events?start=…&end=…&types=rent,payments,… (FullCalendar event objects).
 */
class CalendarController extends Controller
{
    /** key => [label, colour level used by the TailAdmin calendar styles] */
    public const TYPES = [
        'meetings' => ['Meetings', 'Primary'],
        'rent' => ['Rent due', 'Warning'],
        'payments' => ['Payments', 'Success'],
        'readings' => ['Meter readings', 'Primary'],
        'repairs' => ['Maintenance requests', 'Danger'],
        'gate' => ['Gate & visitors', 'Primary'],
        'moves' => ['Move-ins & move-outs', 'Primary'],
        'payouts' => ['Payouts to landlords', 'Success'],
        'followups' => ['CRM follow-ups', 'Primary'],
        'renewals' => ['Subscription renewals', 'Warning'],
    ];

    /** Which event types each role sees. */
    private const ROLE_TYPES = [
        'sysadmin' => ['meetings', 'rent', 'payments', 'readings', 'repairs', 'gate', 'moves', 'payouts', 'followups', 'renewals'],
        'account_manager' => ['meetings', 'followups', 'renewals', 'payouts', 'payments'],
        'admin' => ['meetings', 'rent', 'payments', 'readings', 'repairs', 'gate', 'moves', 'payouts', 'renewals'],
        'accountant' => ['meetings', 'rent', 'payments', 'payouts'],
        'property_manager' => ['meetings', 'rent', 'payments', 'readings', 'repairs', 'gate', 'moves'],
        'meter_reader' => ['meetings', 'readings'],
        'maintenance' => ['meetings', 'repairs'],
        'cleaning_staff' => ['meetings', 'repairs', 'moves'],
        'security' => ['meetings', 'gate', 'moves'],
        'tenant' => ['rent', 'payments', 'repairs', 'gate', 'moves'],
        'bnb_host' => ['meetings'],
    ];

    public function index(Request $request)
    {
        $types = collect($this->allowedTypes($request))->mapWithKeys(fn ($t) => [$t => self::TYPES[$t]]);

        return view('calendar', ['types' => $types]);
    }

    public function events(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfMonth()))->startOfDay();
        $end = Carbon::parse($request->query('end', now()->endOfMonth()))->endOfDay();
        if ($start->diffInDays($end) > 100) {
            $end = $start->copy()->addDays(100);   // keep a single request bounded
        }

        $allowed = $this->allowedTypes($request);
        $wanted = $request->filled('types') ? array_intersect($allowed, explode(',', $request->query('types'))) : $allowed;
        $user = $request->user();
        $ctx = [
            'company' => $user->hasAnyRole(['sysadmin', 'account_manager']) ? null : (int) $user->company_id,
            'tenant' => $user->hasRole('tenant') && ! $user->hasAnyRole(['sysadmin', 'admin', 'property_manager']) ? DB::table('tenants')->where('user_id', $user->id)->value('id') ?? 0 : null,
            'owned' => $user->hasRole('account_manager') && ! $user->hasRole('sysadmin') ? DB::table('companies')->where('owner_id', $user->id)->pluck('id')->all() : null,
        ];

        $events = collect();
        foreach ($wanted as $type) {
            $method = 'events'.ucfirst($type);
            try {
                $events = $events->merge($this->{$method}($start, $end, $ctx, $user));
            } catch (\Throwable $e) {
                report($e);   // one broken source must not empty the whole calendar
            }
        }

        return response()->json($events->values());
    }

    private function allowedTypes(Request $request): array
    {
        $types = [];
        foreach ($request->user()->getRoleNames() as $role) {
            $types = array_merge($types, self::ROLE_TYPES[$role] ?? []);
        }

        return array_values(array_intersect(array_keys(self::TYPES), array_unique($types)));
    }

    private function event(string $type, string $id, string $title, $start, array $extra = []): array
    {
        return array_merge([
            'id' => "$type-$id",
            'title' => $title,
            'start' => Carbon::parse($start)->toIso8601String(),
            'allDay' => true,
            'extendedProps' => ['calendar' => self::TYPES[$type][1], 'type' => $type],
        ], $extra);
    }

    /** Applies the company / tenant scope to a query whose table has company_id (and optionally tenant_id). */
    private function scope($q, array $ctx, string|\Illuminate\Contracts\Database\Query\Expression $companyCol, ?string $tenantCol = null)
    {
        if ($ctx['tenant'] !== null && $tenantCol) {
            return $q->where($tenantCol, $ctx['tenant']);
        }
        if ($ctx['company']) {
            return $q->where($companyCol, $ctx['company']);
        }
        if ($ctx['owned'] !== null) {
            return $q->whereIn($companyCol, $ctx['owned'] ?: [0]);
        }

        return $q;
    }

    private function money($v): string
    {
        return 'KES '.number_format((float) $v);
    }

    // ---- sources ------------------------------------------------------------------------------

    /** Unpaid invoices, due on day N of the billing month; staff see one event per property per day. */
    private function eventsRent(Carbon $start, Carbon $end, array $ctx): Collection
    {
        $day = (int) config('calendar.rent_due_day', 5);
        $q = DB::table('invoices as i')
            ->leftJoin('tenancies as t', 't.id', '=', 'i.tenancy_id')
            ->leftJoin('properties as p', 'p.id', '=', DB::raw('COALESCE(i.property_id, t.property_id)'))
            ->whereIn('i.status', ['unpaid', 'partial', 'pending', 'overdue'])
            ->whereBetween('i.billing_month', [$start->copy()->subMonth()->startOfMonth(), $end]);
        $this->scope($q, $ctx, DB::raw('COALESCE(i.company_id, t.company_id)'), 't.tenant_id');

        if ($ctx['tenant'] !== null) {
            return $q->get(['i.id', 'i.billing_month', 'i.total_amount', 'i.total_paid'])->map(function ($r) use ($day) {
                $due = Carbon::parse($r->billing_month)->startOfMonth()->addDays($day - 1);

                return $this->event('rent', (string) $r->id, 'Due '.$this->money($r->total_amount - $r->total_paid), $due, [
                    'url' => route('public.invoice.show', $r->id),
                    'extendedProps' => ['calendar' => $due->isPast() ? 'Danger' : 'Warning', 'type' => 'rent'],
                ]);
            })->filter(fn ($e) => Carbon::parse($e['start'])->between(Carbon::parse(request('start', now()->startOfMonth())), Carbon::parse(request('end', now()->endOfMonth()))));
        }

        return $q->selectRaw('p.id as pid, p.name as pname, DATE(i.billing_month) as bm, COUNT(*) as n, SUM(i.total_amount - i.total_paid) as due')
            ->groupBy('p.id', 'p.name', DB::raw('DATE(i.billing_month)'))->get()
            ->map(function ($r) use ($day) {
                $due = Carbon::parse($r->bm)->startOfMonth()->addDays($day - 1);

                return $this->event('rent', ($r->pid ?? 0).'-'.$r->bm, ($r->pname ?? 'No property').': '.$r->n.' unpaid · '.$this->money($r->due), $due, [
                    'url' => url('/invoices'),
                    'extendedProps' => ['calendar' => $due->isPast() ? 'Danger' : 'Warning', 'type' => 'rent'],
                ]);
            });
    }

    /** Completed payments per day (tenants: each of their own payments). */
    private function eventsPayments(Carbon $start, Carbon $end, array $ctx): Collection
    {
        $q = DB::table('payments as pay')
            ->leftJoin('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->leftJoin('tenancies as t', 't.id', '=', 'i.tenancy_id')
            ->where('pay.status', 'completed')->whereNull('pay.deleted_at')
            ->whereBetween('pay.created_at', [$start, $end]);
        $this->scope($q, $ctx, DB::raw('COALESCE(i.company_id, t.company_id)'), 'pay.tenant_id');

        if ($ctx['tenant'] !== null) {
            return $q->get(['pay.id', 'pay.amount', 'pay.created_at'])
                ->map(fn ($r) => $this->event('payments', (string) $r->id, 'Paid '.$this->money($r->amount), $r->created_at));
        }

        return $q->selectRaw('DATE(pay.created_at) as d, COUNT(*) as n, SUM(pay.amount) as total')->groupBy(DB::raw('DATE(pay.created_at)'))->get()
            ->map(fn ($r) => $this->event('payments', $r->d, 'Collected '.$this->money($r->total).' ('.$r->n.')', $r->d, ['url' => url('/payments')]));
    }

    /** Reading window (1st to day N), readings done per property per day, and flagged meter readings. */
    private function eventsReadings(Carbon $start, Carbon $end, array $ctx): Collection
    {
        $out = collect();
        $dueDay = (int) config('calendar.meter_reading_due_day', 5);
        for ($m = $start->copy()->startOfMonth(); $m->lte($end); $m->addMonth()) {
            $out->push($this->event('readings', 'window-'.$m->format('Y-m'), 'Meter readings due (bills go out on the '.($dueDay + 1).'th)', $m->copy(), [
                'end' => $m->copy()->addDays($dueDay)->toDateString(),
                'url' => url('/water'),
                'extendedProps' => ['calendar' => 'Warning', 'type' => 'readings'],
            ]));
        }

        $done = DB::table('water_readings as w')->join('units as u', 'u.id', '=', 'w.unit_id')->join('properties as p', 'p.id', '=', 'u.property_id')
            ->whereBetween('w.reading_date', [$start->toDateString(), $end->toDateString()]);
        $this->scope($done, $ctx, 'p.company_id');
        $out = $out->merge($done->selectRaw('p.id as pid, p.name as pname, w.reading_date as d, COUNT(*) as n')
            ->groupBy('p.id', 'p.name', 'w.reading_date')->get()
            ->map(fn ($r) => $this->event('readings', 'water-'.$r->pid.'-'.$r->d, 'Water read: '.$r->pname.' · '.$r->n.' units', $r->d, ['url' => url('/water')])));

        if (Schema::hasTable('meter_readings')) {
            $flagged = DB::table('meter_readings as mr')->where('mr.status', 'flagged')->whereBetween('mr.read_at', [$start, $end]);
            $this->scope($flagged, $ctx, 'mr.company_id');
            $out = $out->merge($flagged->selectRaw('DATE(mr.read_at) as d, mr.utility, COUNT(*) as n')->groupBy(DB::raw('DATE(mr.read_at)'), 'mr.utility')->get()
                ->map(fn ($r) => $this->event('readings', 'flag-'.$r->utility.'-'.$r->d, $r->n.' '.$r->utility.' reading(s) to check', $r->d, [
                    'extendedProps' => ['calendar' => 'Danger', 'type' => 'readings'],
                ])));
        }

        return $out;
    }

    /** Repairs: scheduled visits, else the day they were reported (open ones). */
    private function eventsRepairs(Carbon $start, Carbon $end, array $ctx, $user): Collection
    {
        // Every request: reported (open), scheduled visits, and completed ones.
        $q = DB::table('maintenances as m')->leftJoin('units as u', 'u.id', '=', 'm.unit_id')
            ->where(fn ($w) => $w->whereBetween('m.scheduled_date', [$start, $end])
                ->orWhereBetween('m.completed_date', [$start, $end])
                ->orWhere(fn ($x) => $x->whereNull('m.scheduled_date')->whereBetween('m.created_at', [$start, $end])));
        $this->scope($q, $ctx, 'm.company_id', 'm.tenant_id');
        if ($user->hasAnyRole(['maintenance', 'cleaning_staff']) && ! $user->hasAnyRole(['sysadmin', 'admin', 'property_manager'])) {
            $q->where('m.assigned_to', $user->id);
        }

        return $q->get(['m.id', 'm.name', 'm.priority', 'm.status', 'm.scheduled_date', 'm.completed_date', 'm.created_at', 'u.unit_number'])
            ->map(function ($r) {
                $done = in_array($r->status, ['completed', 'closed'], true) && $r->completed_date;
                $what = ($r->name ?: 'Repair').($r->unit_number ? ' · '.$r->unit_number : '');

                return $this->event('repairs', (string) $r->id,
                    ($done ? 'Fixed: ' : ($r->scheduled_date ? 'Visit: ' : 'Requested: ')).$what,
                    $done ? $r->completed_date : ($r->scheduled_date ?? $r->created_at),
                    ['url' => url('/maintenance'), 'extendedProps' => ['calendar' => $done ? 'Success' : (in_array($r->priority, ['high', 'urgent']) ? 'Danger' : 'Warning'), 'type' => 'repairs']]);
            });
    }

    /** Move-ins and move-outs. */
    private function eventsMoves(Carbon $start, Carbon $end, array $ctx): Collection
    {
        $q = DB::table('tenancies as t')->leftJoin('units as u', 'u.id', '=', 't.unit_id')->leftJoin('properties as p', 'p.id', '=', 't.property_id')
            ->where(fn ($w) => $w->whereBetween('t.move_in_date', [$start, $end])->orWhereBetween('t.move_out_date', [$start, $end]));
        $this->scope($q, $ctx, 't.company_id', 't.tenant_id');

        return $q->get(['t.id', 't.move_in_date', 't.move_out_date', 'u.unit_number', 'p.name as pname'])
            ->flatMap(function ($r) use ($start, $end) {
                $where = trim(($r->unit_number ?? '').' · '.($r->pname ?? ''), ' ·');
                $out = [];
                if ($r->move_in_date && Carbon::parse($r->move_in_date)->between($start, $end)) {
                    $out[] = $this->event('moves', 'in-'.$r->id, 'Move-in: '.$where, $r->move_in_date, ['url' => url('/tenancies/'.$r->id), 'extendedProps' => ['calendar' => 'Success', 'type' => 'moves']]);
                }
                if ($r->move_out_date && Carbon::parse($r->move_out_date)->between($start, $end)) {
                    $out[] = $this->event('moves', 'out-'.$r->id, 'Move-out: '.$where, $r->move_out_date, ['url' => url('/tenancies/'.$r->id), 'extendedProps' => ['calendar' => 'Warning', 'type' => 'moves']]);
                }

                return $out;
            });
    }

    /** Sharet sweeps to landlords (08:00 / 12:00 / 16:00). */
    private function eventsPayouts(Carbon $start, Carbon $end, array $ctx): Collection
    {
        if (! Schema::hasTable('sharet_sweeps')) {
            return collect();
        }
        $q = DB::table('sharet_sweeps as s')->leftJoin('companies as c', 'c.id', '=', 's.company_id')->whereBetween('s.created_at', [$start, $end]);
        $this->scope($q, $ctx, 's.company_id');

        return $q->get(['s.id', 's.slot', 's.net', 's.status', 's.created_at', 'c.name'])
            ->map(fn ($r) => $this->event('payouts', (string) $r->id, 'Payout '.$this->money($r->net).($ctx['company'] ? '' : ' · '.$r->name).($r->status === 'paid' ? '' : ' ('.$r->status.')'),
                $r->created_at, ['allDay' => false, 'extendedProps' => ['calendar' => $r->status === 'failed' ? 'Danger' : 'Success', 'type' => 'payouts']]));
    }

    /** Open CRM follow-ups (scharge staff; account managers see their own book). */
    private function eventsFollowups(Carbon $start, Carbon $end, array $ctx, $user): Collection
    {
        $q = CrmAccess::followUps(\App\Modules\CRM\Models\CrmFollowUp::query(), $user)
            ->with('company:id,name')->whereIn('status', ['open', 'scheduled'])->whereBetween('due_at', [$start, $end]);

        return $q->get()->map(fn ($f) => $this->event('followups', (string) $f->id,
            ucfirst(str_replace('_', ' ', $f->type)).': '.($f->company?->name ?? $f->title ?? 'Lead'), $f->due_at,
            ['allDay' => false, 'url' => $f->company_id ? url('/crm/companies/'.$f->company_id) : url('/crm/requests'),
                'extendedProps' => ['calendar' => $f->due_at->isPast() ? 'Danger' : 'Primary', 'type' => 'followups']]));
    }

    /** Subscription renewals / trial ends. */
    private function eventsRenewals(Carbon $start, Carbon $end, array $ctx): Collection
    {
        $q = DB::table('company_subscriptions as s')->leftJoin('companies as c', 'c.id', '=', 's.company_id')
            ->whereIn('s.status', ['active', 'trialing', 'trial', 'past_due'])
            ->where(fn ($w) => $w->whereBetween('s.ends_at', [$start, $end])->orWhereBetween('s.trial_ends_at', [$start, $end]));
        $this->scope($q, $ctx, 's.company_id');

        return $q->get(['s.id', 's.ends_at', 's.trial_ends_at', 's.status', 'c.name'])->map(function ($r) use ($start, $end, $ctx) {
            $trial = $r->trial_ends_at && Carbon::parse($r->trial_ends_at)->between($start, $end);

            return $this->event('renewals', (string) $r->id, ($trial ? 'Trial ends' : 'Renews').($ctx['company'] ? '' : ': '.$r->name),
                $trial ? $r->trial_ends_at : $r->ends_at, ['url' => $ctx['company'] ? null : url('/admin/subscriptions/plans')]);
        });
    }

    /** Gate log: visitors per property per day (tenants: their own visitors). */
    private function eventsGate(Carbon $start, Carbon $end, array $ctx): Collection
    {
        $q = DB::table('security as g')->leftJoin('properties as p', 'p.id', '=', 'g.property_id')->whereBetween('g.access_time', [$start, $end]);
        $this->scope($q, $ctx, 'g.company_id', 'g.tenant_id');

        if ($ctx['tenant'] !== null) {
            return $q->get(['g.id', 'g.visitor_name_snapshot', 'g.access_time', 'g.purpose'])
                ->map(fn ($r) => $this->event('gate', (string) $r->id, 'Visitor: '.($r->visitor_name_snapshot ?: 'guest').($r->purpose ? ' · '.$r->purpose : ''), $r->access_time, ['allDay' => false]));
        }

        return $q->selectRaw('p.id as pid, p.name as pname, DATE(g.access_time) as d, COUNT(*) as n')->groupBy('p.id', 'p.name', DB::raw('DATE(g.access_time)'))->get()
            ->map(fn ($r) => $this->event('gate', ($r->pid ?? 0).'-'.$r->d, 'Gate: '.$r->n.' visitor'.($r->n > 1 ? 's' : '').' · '.($r->pname ?? 'property'), $r->d, ['url' => url('/security/logs')]));
    }

    /** Internal meetings and reminders (calendar_events). */
    private function eventsMeetings(Carbon $start, Carbon $end, array $ctx, $user): Collection
    {
        if (! Schema::hasTable('calendar_events')) {
            return collect();
        }

        return \App\Models\CalendarEvent::visibleTo($user)->with('attendees:id,name')
            ->where('starts_at', '<=', $end)->where(fn ($q) => $q->where('starts_at', '>=', $start)->orWhere('ends_at', '>=', $start))
            ->get()->map(fn ($e) => [
                'id' => 'meetings-'.$e->id,
                'title' => ($e->kind === 'reminder' ? '⏰ ' : '').$e->title,
                'start' => $e->starts_at->toIso8601String(),
                'end' => $e->ends_at?->toIso8601String(),
                'allDay' => $e->all_day,
                'extendedProps' => ['calendar' => 'Primary', 'type' => 'meetings', 'meeting' => [
                    'id' => $e->id, 'title' => $e->title, 'description' => $e->description, 'location' => $e->location, 'kind' => $e->kind,
                    'visibility' => $e->visibility, 'starts_at' => $e->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $e->ends_at?->format('Y-m-d\TH:i'),
                    'all_day' => $e->all_day, 'attendees' => $e->attendees->pluck('id'), 'attendee_names' => $e->attendees->pluck('name'),
                    'mine' => (int) $e->created_by === (int) $user->id,
                ]],
            ]);
    }

    // ---- meetings: create / update / delete ----------------------------------------------------

    public function storeMeeting(Request $request)
    {
        abort_unless(Schema::hasTable('calendar_events'), 503, 'Apply database/schema/2026_11_09_calendar_events.sql first.');
        abort_if($request->user()->hasRole('tenant') && ! $request->user()->hasAnyRole(['admin', 'property_manager', 'sysadmin']), 403);
        $data = $this->validateMeeting($request);
        $event = \App\Models\CalendarEvent::create($data + ['company_id' => $request->user()->hasAnyRole(['sysadmin', 'account_manager']) && ! $request->user()->company_id ? null : $request->user()->company_id, 'created_by' => $request->user()->id]);
        $event->attendees()->sync($this->allowedAttendees($request, $data['attendees'] ?? []));

        return response()->json(['message' => 'Meeting saved.', 'id' => $event->id], 201);
    }

    public function updateMeeting(Request $request, \App\Models\CalendarEvent $event)
    {
        abort_unless((int) $event->created_by === (int) $request->user()->id || $request->user()->hasRole('sysadmin'), 403, 'Only the organiser can change this meeting.');
        $data = $this->validateMeeting($request);
        $event->update($data);
        $event->attendees()->sync($this->allowedAttendees($request, $data['attendees'] ?? []));

        return response()->json(['message' => 'Meeting updated.']);
    }

    public function destroyMeeting(Request $request, \App\Models\CalendarEvent $event)
    {
        abort_unless((int) $event->created_by === (int) $request->user()->id || $request->user()->hasRole('sysadmin'), 403, 'Only the organiser can cancel this meeting.');
        $event->delete();

        return response()->json(['message' => 'Meeting cancelled.']);
    }

    /** People this user can invite: their own company's team (staff: the scharge team). */
    public function people(Request $request)
    {
        $u = $request->user();
        $people = \App\Models\User::query()->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 1))
            ->when($u->company_id, fn ($q) => $q->where('company_id', $u->company_id)->whereDoesntHave('roles', fn ($r) => $r->where('name', 'tenant')),
                fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['sysadmin', 'account_manager'])))
            ->orderBy('name')->limit(300)->get(['id', 'name']);

        return response()->json(['people' => $people]);
    }

    private function validateMeeting(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'kind' => 'nullable|in:meeting,reminder',
            'visibility' => 'nullable|in:attendees,company,staff',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'all_day' => 'nullable|boolean',
            'attendees' => 'nullable|array|max:100',
            'attendees.*' => 'integer',
        ]);
        $data['kind'] ??= 'meeting';
        $data['visibility'] ??= 'attendees';
        $data['all_day'] = (bool) ($data['all_day'] ?? false);

        return $data;
    }

    private function allowedAttendees(Request $request, array $ids): array
    {
        $allowed = collect($this->people($request)->getData(true)['people'])->pluck('id')->all();

        return array_values(array_intersect(array_map('intval', $ids), $allowed));
    }
}
