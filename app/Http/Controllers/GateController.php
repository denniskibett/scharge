<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Modules\Security\Models\GatePass;
use App\Modules\Security\Models\VisitorPlace;
use App\Services\Gate\Gate;
use App\Services\Gate\GateNotify;
use Illuminate\Http\Request;

/**
 * Guests & gate (Gate service): one place for the guard (verify a code, let in / out), and for every business
 * that receives guests — BnB hosts, managers, tenants — to send codes and see who checked in and out.
 */
class GateController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless(Gate::allowed($request->user()), 403, 'Your role does not allow the gate pages.');
    }

    /** Can change things (issue, check in/out). Hosts and tenants can, for their own units. */
    private function canManage(Request $request): bool
    {
        $u = $request->user();

        return $u->hasAnyRole(['sysadmin', 'bnb_host', 'tenant']) || Gate::staff($u, 'manage');
    }

    private function pass(Request $request, GatePass $pass): GatePass
    {
        $this->guard($request);
        abort_unless(Gate::canSee($request->user(), $pass), 404);

        return $pass;
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $user = $request->user();
        $tab = in_array($request->tab, ['inside', 'expected', 'history', 'all'], true) ? $request->tab : 'inside';
        $base = fn () => Gate::scopePasses(GatePass::query(), $user)
            ->when($request->integer('property_id'), fn ($q, $id) => $q->where('property_id', $id))
            ->when($request->integer('unit_id'), fn ($q, $id) => $q->where('unit_id', $id));
        $today = now('Africa/Nairobi')->startOfDay()->utc();

        $stats = [
            'inside' => $base()->where('status', 'checked_in')->where('is_inside', true)->count(),
            'staying' => $base()->where('status', 'checked_in')->count(),
            'expected' => $base()->where('status', 'issued')->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                ->where('valid_from', '<', $today->copy()->addDay())->count(),
            'in_today' => $base()->where('checked_in_at', '>=', $today)->count(),
            'out_today' => $base()->where('checked_out_at', '>=', $today)->count(),
        ];

        $q = $base()->with('unit:id,unit_number', 'property:id,name', 'visitor:id,visit_count');
        match ($tab) {
            'inside' => $q->where('status', 'checked_in')->orderByDesc('is_inside')->orderByDesc('checked_in_at'),
            'expected' => $q->where('status', 'issued')->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()))->orderBy('valid_from'),
            'history' => $q->whereIn('status', ['checked_out', 'cancelled'])->orderByDesc('updated_at'),
            default => $q->orderByDesc('id'),
        };
        if ($s = trim((string) $request->q)) {
            $plate = \App\Modules\Security\Models\Visitor::plate($s);
            $q->where(fn ($w) => $w->where('guest_name', 'like', "%$s%")->orWhere('guest_phone', 'like', "%$s%")
                ->orWhere('code', $s)->when($plate, fn ($x) => $x->orWhere('vehicle_plate', 'like', "%$plate%")));
        }
        $passes = $q->paginate(25)->withQueryString();

        $unitIds = Gate::scopeUnitIds($user);
        $properties = Property::when($unitIds !== null, fn ($p) => $p->whereIn('id', \App\Models\Unit::whereIn('id', $unitIds ?: [0])->select('property_id')))
            ->orderBy('name')->get(['id', 'name']);

        return view('gate.index', compact('passes', 'stats', 'tab', 'properties') + ['canManage' => $this->canManage($request)]);
    }

    /** Guard types the code (or the QR opens /gate/verify?code=…). */
    public function verify(Request $request)
    {
        $this->guard($request);
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        if (strlen($code) < 6) {
            return redirect()->route('gate.index')->with('error', 'Type the 6-digit code from the guest\'s SMS or email.');
        }
        $pass = Gate::scopePasses(GatePass::where('code', $code), $request->user())
            ->orderByRaw("FIELD(status, 'checked_in', 'issued') DESC")->orderByDesc('id')->first();
        if (! $pass) {
            return redirect()->route('gate.index')->with('error', "No pass with code $code for this gate. Ask the guest to call their host.");
        }

        return redirect()->route('gate.passes.show', $pass);
    }

    public function create(Request $request)
    {
        $this->guard($request);
        abort_unless($this->canManage($request), 403);
        $units = Gate::units($request->user());
        $prefill = [];
        if ($again = $request->integer('again')) { // "New pass" for a returning visitor
            $old = GatePass::find($again);
            if ($old && Gate::canSee($request->user(), $old)) {
                $prefill = $old->only(['unit_id', 'kind', 'guest_name', 'guest_phone', 'guest_email', 'guests_count', 'vehicle_plate', 'vehicle_desc']);
            }
        }
        $prefill['unit_id'] ??= $request->integer('unit_id') ?: ($units->count() === 1 ? $units->first()->id : null);
        $prefill['kind'] ??= $request->user()->hasRole('bnb_host') ? 'stay' : 'visitor';

        return view('gate.create', compact('units', 'prefill'));
    }

    public function store(Request $request)
    {
        $this->guard($request);
        abort_unless($this->canManage($request), 403);
        $data = $request->validate([
            'unit_id' => 'required|integer|exists:units,id',
            'kind' => 'required|in:'.implode(',', array_keys(GatePass::KINDS)),
            'guest_name' => 'required|string|max:160',
            'guest_phone' => ['required', 'string', 'max:32', function ($a, $v, $fail) {
                if (! \App\Helpers\PhoneHelper::e164($v)) {
                    $fail('Enter a mobile number like 0712 345 678 or +44 7700 900123.');
                }
            }],
            'guest_email' => 'nullable|email|max:191',
            'guests_count' => 'nullable|integer|min:1|max:50',
            'vehicle_plate' => 'nullable|string|max:20',
            'vehicle_desc' => 'nullable|string|max:80',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'notes' => 'nullable|string|max:500',
            'send_sms' => 'nullable|boolean',
            'send_email' => 'nullable|boolean',
            'check_in_now' => 'nullable|boolean',
        ]);
        abort_unless(in_array((int) $data['unit_id'], Gate::scopeUnitIds($request->user()) ?? [(int) $data['unit_id']], true), 403);
        foreach (['valid_from', 'valid_until'] as $k) { // the form is in Nairobi time
            $data[$k] = filled($data[$k] ?? null) ? \Carbon\Carbon::parse($data[$k], 'Africa/Nairobi')->utc() : null;
        }
        $data['valid_until'] ??= match ($data['kind']) {
            'stay' => $data['valid_from']->copy()->addDay(),
            default => $data['valid_from']->copy()->timezone('Africa/Nairobi')->endOfDay()->utc(),
        };

        $pass = Gate::issue($data, $request->user());
        $sent = GateNotify::sendToGuest($pass, $request->boolean('send_sms', true), $request->boolean('send_email', true));
        $msg = 'Pass '.$pass->code.' created.';
        if ($sent['sms'] || $sent['email']) {
            $msg .= ' Sending it by '.collect(['SMS' => $sent['sms'], 'email' => $sent['email']])->filter()->keys()->implode(' and ').'.';
        }
        if ($request->boolean('check_in_now')) { // walk-in at the gate
            $err = Gate::checkIn($pass, $request->user(), $this->method($request), $data['vehicle_plate'] ?? null);
            $msg .= $err ? ' Not checked in: '.$err : ' Checked in.';
        }

        return redirect()->route('gate.passes.show', $pass)->with('success', $msg);
    }

    public function show(Request $request, GatePass $pass)
    {
        $pass = $this->pass($request, $pass)->load('unit.property', 'property', 'company', 'creator', 'visitor', 'logs.verifiedByUser');
        $place = VisitorPlace::where('visitor_id', $pass->visitor_id)->where('unit_id', $pass->unit_id)->first();
        $others = GatePass::where('visitor_id', $pass->visitor_id)->where('unit_id', $pass->unit_id)->where('id', '!=', $pass->id)
            ->latest('id')->limit(5)->get();

        return view('gate.show', compact('pass', 'place', 'others') + ['canManage' => $this->canManage($request)]);
    }

    public function act(Request $request, GatePass $pass)
    {
        $this->pass($request, $pass);
        abort_unless($this->canManage($request), 403);
        $action = $request->validate(['action' => 'required|in:checkin,stepout,checkout,cancel,resend', 'vehicle_plate' => 'nullable|string|max:20'])['action'];
        $user = $request->user();
        $err = match ($action) {
            'checkin' => Gate::checkIn($pass, $user, $this->method($request), $request->vehicle_plate),
            'stepout' => Gate::stepOut($pass, $user),
            'checkout' => Gate::checkOut($pass, $user, $this->method($request)),
            'cancel' => Gate::cancel($pass),
            'resend' => $pass->isOpen() ? null : 'This pass is no longer valid.',
        };
        if ($err) {
            return back()->with('error', $err);
        }
        if ($action === 'resend') {
            GateNotify::sendToGuest($pass->load('unit', 'property'));
        }

        return back()->with('success', match ($action) {
            'checkin' => $pass->guest_name.' checked in.',
            'stepout' => $pass->guest_name.' stepped out. The pass stays valid until '.$pass->valid_until?->timezone('Africa/Nairobi')->format('j M H:i').'.',
            'checkout' => $pass->guest_name.' checked out.',
            'cancel' => 'Pass cancelled.',
            'resend' => 'Code sent again.',
        });
    }

    /** Returning visitor for the form: what this business knows about the phone number. */
    public function lookup(Request $request)
    {
        $this->guard($request);

        return response()->json(['visitor' => Gate::lookup($request->user(), $request->phone, $request->integer('unit_id') ?: null)]);
    }

    /** Everyone who has visited this business's units (the visitor <-> unit pivot). */
    public function visitors(Request $request)
    {
        $this->guard($request);
        $ids = Gate::scopeUnitIds($request->user());
        $q = VisitorPlace::with('visitor', 'unit:id,unit_number', 'property:id,name')
            ->when($ids !== null, fn ($w) => $w->whereIn('unit_id', $ids ?: [0]))
            ->when($request->integer('property_id'), fn ($w, $id) => $w->where('property_id', $id));
        if ($s = trim((string) $request->q)) {
            $q->where(fn ($w) => $w->whereHas('visitor', fn ($v) => $v->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhere('last_vehicle_plate', 'like', '%'.\App\Modules\Security\Models\Visitor::plate($s).'%'));
        }
        $places = $q->orderByDesc('last_check_in_at')->orderByDesc('updated_at')->paginate(30)->withQueryString();

        return view('gate.visitors', compact('places') + ['canManage' => $this->canManage($request)]);
    }

    public function block(Request $request, VisitorPlace $place)
    {
        $this->guard($request);
        abort_unless($this->canManage($request), 403);
        $ids = Gate::scopeUnitIds($request->user());
        abort_unless($ids === null || in_array((int) $place->unit_id, $ids, true), 404);
        $place->forceFill(['is_blocked' => ! $place->is_blocked])->save();

        return back()->with('success', $place->visitor?->name.($place->is_blocked ? ' is blocked for this unit.' : ' is no longer blocked.'));
    }

    private function method(Request $request): string
    {
        $u = $request->user();

        return $u->hasAnyRole(['bnb_host', 'tenant']) && ! $u->hasRole('security') ? 'host' : 'guard';
    }
}
