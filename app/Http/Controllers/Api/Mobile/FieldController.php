<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Modules\Security\Models\GatePass;
use App\Services\Gate\Gate;
use App\Services\Gate\GateNotify;
use App\Services\Meters\MeterReadings;
use App\Support\CompanyAccess;
use App\Support\MobileAccess;
use Illuminate\Http\Request;

/**
 * Field work in the scharge app: meter readings with a photo, repairs, and the gate.
 * Meters: the user's companies (CompanyAccess). Repairs: through the unit's company scope. Gate: the Gate service's
 * own rules (guards and managers: their company's units; BnB hosts: units they manage; tenants: their own unit).
 */
class FieldController extends Controller
{
    use Presents;

    public function meters(Request $request)
    {
        $q = $this->meterQuery()->where('status', 'active')->with(['unit:id,unit_number,property_id', 'unit.property:id,name', 'property:id,name'])
            ->when($request->query('utility'), fn ($q, $u) => $q->where('utility', $u))
            ->when($request->boolean('due'), fn ($q) => $q->where('kind', 'manual')->where(fn ($w) => $w->whereNull('last_read_at')->orWhere('last_read_at', '<', now()->startOfMonth())))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('serial_number', 'like', "%$s%")->orWhereHas('unit', fn ($u) => $u->where('unit_number', 'like', "%$s%"))))
            ->orderBy('property_id')->orderBy('unit_id');

        return response()->json(['data' => $q->limit(500)->get()->map(fn ($m) => [
            'id' => $m->id, 'utility' => $m->utility, 'serial_number' => $m->serial_number, 'unit_of_measure' => $m->unit_of_measure,
            'unit' => $m->unit?->unit_number, 'property' => ($m->unit?->property ?? $m->property)?->name,
            'last_reading' => $m->last_reading ?? $m->initial_reading, 'last_read_at' => $m->last_read_at?->toIso8601String(),
            'due' => $m->kind === 'manual' && (! $m->last_read_at || $m->last_read_at->lt(now()->startOfMonth())),
        ])->values()]);
    }

    /** Record one reading with a photo of the meter face. client_ref makes a retry from a phone with bad signal safe. */
    public function record(Request $request, int $id, MeterReadings $readings)
    {
        $meter = $this->meterQuery()->find($id);
        abort_unless($meter, 404, 'No such meter.');
        $data = $request->validate(['reading' => 'required|numeric|min:0', 'read_at' => 'nullable|date', 'client_ref' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:255', 'photo' => 'nullable|image|max:6144']);

        [$row, $created] = $readings->record($meter, (float) $data['reading'], [
            'source' => 'reader_app', 'read_at' => $data['read_at'] ?? null, 'client_ref' => $data['client_ref'] ?? null,
            'recorded_by' => $request->user()->id, 'note' => $data['note'] ?? null, 'photo' => $request->file('photo'),
        ]);

        return response()->json($this->reading($row), $created ? 201 : 200);
    }

    public function readings(Request $request, int $id)
    {
        $meter = $this->meterQuery()->find($id);
        abort_unless($meter, 404, 'No such meter.');

        return response()->json(['data' => MeterReading::where('meter_id', $meter->id)->orderByDesc('read_at')->limit(24)->get()->map(fn ($r) => $this->reading($r))->values()]);
    }

    public function repairs(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'open');
        $q = Maintenance::with('unit.property', 'tenant.user', 'assignedStaff')->whereHas('unit') // the unit's company scope
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['open', 'in_progress', 'pending_parts']))
            ->when(in_array($status, ['completed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $user->id))
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->latest('id');

        return response()->json(['data' => $q->limit(100)->get()->map(fn ($m) => $this->repair($m))->values()]);
    }

    /** Take a job, move it along, finish it with notes and cost. */
    public function updateRepair(Request $request, int $id)
    {
        $repair = Maintenance::whereHas('unit')->find($id);
        abort_unless($repair, 404, 'No such repair.');
        $data = $request->validate(['status' => 'required|in:in_progress,pending_parts,completed,cancelled',
            'resolution_notes' => 'nullable|string|max:2000', 'cost' => 'nullable|numeric|min:0|max:10000000', 'assign_to_me' => 'nullable|boolean']);

        $repair->fill(['status' => $data['status'], 'resolution_notes' => $data['resolution_notes'] ?? $repair->resolution_notes,
            'cost' => $data['cost'] ?? $repair->cost]);
        if ($request->boolean('assign_to_me') || (! $repair->assigned_to && $data['status'] === 'in_progress')) {
            $repair->assigned_to = $request->user()->id;
        }
        if ($data['status'] === 'completed') {
            $repair->completed_date = now();
        }
        $repair->save();

        return response()->json($this->repair($repair->load('unit.property', 'tenant.user', 'assignedStaff')));
    }

    public function passes(Request $request)
    {
        $this->gateAllowed($request);
        $tab = in_array($request->query('tab'), ['inside', 'expected', 'history', 'approve'], true) ? $request->query('tab') : 'expected';
        $user = $request->user();
        $home = MobileAccess::gateAsTenant($user) ? MobileAccess::homeUnitId($user) : null;
        $q = Gate::scopePasses(GatePass::query(), $request->user())->with('unit:id,unit_number,property_id', 'property:id,name');
        match ($tab) {
            'inside' => $q->where('status', 'checked_in')->orderByDesc('checked_in_at'),
            'expected' => $q->where('status', 'issued')->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()))->orderBy('valid_from'),
            'history' => $q->whereIn('status', ['checked_out', 'cancelled'])->orderByDesc('updated_at'),
            // Suggested by someone else for a unit: the person living there (or the gate manager) approves.
            'approve' => $q->where('status', 'suggested')->when(MobileAccess::gateAsTenant($user), fn ($w) => $w->where('unit_id', $home ?? 0)
                ->where('created_by', '!=', $user->id))->orderBy('valid_from'),
        };

        return response()->json(['data' => $q->limit(100)->get()->map(fn ($p) => $this->pass($p))->values(),
            'home_unit_id' => $home,
            'units' => Gate::units($user)->map(fn ($u) => ['id' => $u->id, 'number' => $u->unit_number, 'property' => $u->property?->name,
                'home' => $home === null || (int) $u->id === $home])->values()]);
    }

    /** Guard types the 6-digit code from the guest's SMS. */
    public function verify(Request $request)
    {
        $this->gateAllowed($request);
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        $pass = strlen($code) >= 6 ? Gate::scopePasses(GatePass::where('code', $code), $request->user())->whereIn('status', ['checked_in', 'issued'])
            ->with('unit:id,unit_number,property_id', 'property:id,name')->orderByDesc('id')->first() : null;
        abort_unless($pass, 404, 'No pass with that code for this gate. Ask the guest to call their host.');

        return response()->json($this->pass($pass));
    }

    public function issue(Request $request)
    {
        $this->gateAllowed($request, manage: true);
        $data = $request->validate([
            'unit_id' => 'required|integer',
            'kind' => 'required|in:'.implode(',', array_keys(GatePass::KINDS)),
            'guest_name' => 'required|string|max:160',
            'guest_phone' => ['nullable', 'required_without:guest_email', 'string', 'max:32', function ($a, $v, $fail) {
                if (filled($v) && ! \App\Helpers\PhoneHelper::e164($v)) {
                    $fail('Enter a mobile number like 0712 345 678.');
                }
            }],
            'guest_email' => 'nullable|email|max:191',
            'send_email' => 'nullable|boolean',
            'send_sms' => 'nullable|boolean',
            'guests_count' => 'nullable|integer|min:1|max:50',
            'vehicle_plate' => 'nullable|string|max:20',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);
        $allowed = Gate::scopeUnitIds($request->user());
        abort_unless($allowed === null || in_array((int) $data['unit_id'], $allowed, true), 403, 'You cannot issue passes for that unit.');
        $data['valid_from'] = filled($data['valid_from'] ?? null) ? \Carbon\Carbon::parse($data['valid_from'], 'Africa/Nairobi')->utc() : now();
        $data['valid_until'] = filled($data['valid_until'] ?? null) ? \Carbon\Carbon::parse($data['valid_until'], 'Africa/Nairobi')->utc()
            : ($data['kind'] === 'stay' ? $data['valid_from']->copy()->addDay() : $data['valid_from']->copy()->timezone('Africa/Nairobi')->endOfDay()->utc());

        // A tenant sends codes for the flat they live in; for another unit they hold, the pass waits for its resident.
        $user = $request->user();
        $suggest = MobileAccess::gateAsTenant($user) && (int) $data['unit_id'] !== MobileAccess::homeUnitId($user);
        $pass = CompanyAccess::bypass(fn () => Gate::issue($data, $user));
        if ($suggest) {
            $pass->forceFill(['status' => 'suggested'])->save();
        } else {
            $this->notifyGuest($pass, $request->boolean('send_email', true), $request->boolean('send_sms'));
        }

        return response()->json($this->pass($pass->load('unit', 'property')) + ['needs_approval' => $suggest], 201);
    }

    public function act(Request $request, int $id)
    {
        $this->gateAllowed($request, manage: true);
        $pass = Gate::scopePasses(GatePass::query(), $request->user())->find($id);
        abort_unless($pass, 404, 'No such pass.');
        $action = $request->validate(['action' => 'required|in:checkin,stepout,checkout,cancel,approve,decline', 'vehicle_plate' => 'nullable|string|max:20'])['action'];
        $user = $request->user();
        $asTenant = MobileAccess::gateAsTenant($user);
        if ($asTenant) {
            abort_unless(in_array($action, ['cancel', 'approve', 'decline'], true), 403, 'The guard checks guests in and out.');
        }
        if (in_array($action, ['approve', 'decline'], true)) {
            abort_unless($pass->status === 'suggested', 422, 'This pass is not waiting for approval.');
            // Only the person living there (or the gate manager): never the one who suggested it.
            abort_if($asTenant && ((int) $pass->unit_id !== MobileAccess::homeUnitId($user) || (int) $pass->created_by === (int) $user->id), 403,
                'Only the person living in that unit can approve this pass.');
            $pass->forceFill(['status' => $action === 'approve' ? 'issued' : 'cancelled'])->save();
            if ($action === 'approve') {
                $this->notifyGuest($pass, true, false);
            }

            return response()->json($this->pass($pass->fresh()->load('unit', 'property')));
        }
        abort_if($action === 'cancel' && $pass->status === 'suggested' && $asTenant && (int) $pass->created_by !== (int) $user->id
            && (int) $pass->unit_id !== MobileAccess::homeUnitId($user), 403, 'You cannot cancel this pass.');
        $method = $user->hasAnyRole(['bnb_host', 'tenant']) && ! $user->hasRole('security') ? 'host' : 'guard';
        $err = match ($action) {
            'checkin' => Gate::checkIn($pass, $user, $method, $request->input('vehicle_plate')),
            'stepout' => Gate::stepOut($pass, $user),
            'checkout' => Gate::checkOut($pass, $user, $method),
            'cancel' => $pass->status === 'suggested' ? ($pass->forceFill(['status' => 'cancelled'])->save() ? null : null) : Gate::cancel($pass),
        };
        abort_if($err, 422, (string) $err);

        return response()->json($this->pass($pass->fresh()->load('unit', 'property')));
    }

    /** The guest's code goes by email by default, by SMS when asked, and always by SMS when there is no email. */
    private function notifyGuest(GatePass $pass, bool $email, bool $sms): void
    {
        $email = $email && filled($pass->guest_email);
        $sms = ($sms || ! $email) && filled($pass->guest_phone);
        if ($email || $sms) {
            GateNotify::sendToGuest($pass->load('unit', 'property'), $sms, $email);
        }
    }

    private function gateAllowed(Request $request, bool $manage = false): void
    {
        $u = $request->user();
        abort_unless(Gate::allowed($u), 403, 'Your role does not allow the gate.');
        abort_if($manage && ! ($u->hasAnyRole(['sysadmin', 'bnb_host', 'tenant']) || Gate::staff($u, 'manage')), 403, 'Your role can look up passes but not change them.');
    }

    private function meterQuery()
    {
        $ids = CompanyAccess::ids();

        return Meter::query()->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids ?: [0]));
    }

    private function reading(MeterReading $r): array
    {
        return ['id' => $r->id, 'meter_id' => $r->meter_id, 'reading' => $r->reading, 'previous_reading' => $r->previous_reading,
            'consumption' => $r->consumption, 'charge' => $r->charge !== null ? self::money($r->charge) : null, 'status' => $r->status,
            'flag_reason' => $r->flag_reason, 'read_at' => $r->read_at?->toIso8601String(), 'photo_url' => $r->photo_path ? asset('storage/'.$r->photo_path) : null];
    }
}
