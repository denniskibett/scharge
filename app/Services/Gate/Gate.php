<?php

namespace App\Services\Gate;

use App\Helpers\PhoneHelper;
use App\Http\Middleware\CheckAccess;
use App\Models\Unit;
use App\Models\UnitManagement;
use App\Models\User;
use App\Modules\Security\Models\GatePass;
use App\Modules\Security\Models\SecurityLog;
use App\Modules\Security\Models\Visitor;
use App\Modules\Security\Models\VisitorPlace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The gate & visitor journey, for every kind of business:
 *   issue()    the unit's business (BnB host, manager, tenant, or the guard for a walk-in) creates a pass:
 *              6-digit code + private link, sent to the guest by SMS and email (GateNotify).
 *   checkIn()  the guard verifies the code (or the host / the guest's own phone confirms arrival): a SecurityLog
 *              entry row is opened, the visitor <-> unit pivot (visitor_places) counts the visit.
 *   stepOut()  a staying guest leaves for a while: the entry row is closed, the pass stays valid.
 *   checkOut() the visit/stay ends (guard, host, or the guest's link): rows closed, pivot gets last_check_out_at.
 * Who sees what: scopeUnitIds() — sysadmin everything, company staff their company's buildings and units,
 * a BnB host their hosted units, a tenant their own unit.
 */
class Gate
{
    /** May open the gate pages at all. */
    public static function allowed(?User $user): bool
    {
        return $user && ($user->hasRole('sysadmin') || $user->hasAnyRole(['bnb_host', 'tenant']) || self::staff($user, 'view'));
    }

    /** Company staff with the security area (guards, admins, managers). */
    public static function staff(User $user, string $action = 'view'): bool
    {
        if ($user->hasRole('sysadmin')) {
            return true;
        }
        if (! $user->company_id) {
            return false;
        }

        return CheckAccess::catalogueInstalled()
            ? $user->can('security.'.$action)
            : $user->hasAnyRole(['admin', 'property_manager', 'security']);
    }

    public static function isGuard(User $user): bool
    {
        return $user->hasRole('security') || ($user->hasRole('sysadmin'));
    }

    /** Unit ids this user may see passes for; null = all (sysadmin). */
    public static function scopeUnitIds(User $user): ?array
    {
        if ($user->hasRole('sysadmin')) {
            return null;
        }
        $ids = collect();
        if ($user->company_id && self::staff($user, 'view')) {
            $cid = $user->company_id;
            $ids = $ids->merge(Unit::where(fn ($q) => $q->where('company_id', $cid)
                ->orWhereIn('property_id', DB::table('properties')->where('company_id', $cid)->select('id')))->pluck('id'));
        }
        if ($user->hasRole('bnb_host') && $user->company_id) {
            $ids = $ids->merge(UnitManagement::where('company_id', $user->company_id)->where('role', 'bnb_host')
                ->where('status', 'active')->pluck('unit_id'));
        }
        if ($user->hasRole('tenant')) {
            $ids = $ids->merge(DB::table('tenancies')->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
                ->where('tenants.user_id', $user->id)->where('tenancies.status', 'active')->pluck('tenancies.unit_id'));
        }

        return $ids->map(fn ($i) => (int) $i)->unique()->values()->all();
    }

    public static function scopePasses($query, User $user)
    {
        $ids = self::scopeUnitIds($user);

        return $ids === null ? $query : $query->whereIn('unit_id', $ids ?: [0]);
    }

    public static function canSee(User $user, GatePass $pass): bool
    {
        $ids = self::scopeUnitIds($user);

        return $ids === null || in_array((int) $pass->unit_id, $ids, true);
    }

    /** Units the user may issue passes for, with their property, for the form. */
    public static function units(User $user)
    {
        $ids = self::scopeUnitIds($user);

        return Unit::with('property:id,name')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))
            ->orderBy('property_id')->orderBy('unit_number')->get(['id', 'unit_number', 'property_id', 'company_id', 'stay_type']);
    }

    /** The business a guest at this unit is visiting: the BnB host if hosted, else the unit's / building's company. */
    public static function companyFor(Unit $unit): ?int
    {
        $host = UnitManagement::hostOf($unit->id);
        $id = $host?->company_id ?? $unit->company_id ?? $unit->property?->company_id;

        return $id ? (int) $id : null;
    }

    public static function newCode(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = (string) random_int(100000, 999999);
            if (! GatePass::where('code', $code)->whereIn('status', ['issued', 'checked_in'])->exists()) {
                return $code;
            }
        }

        return (string) random_int(1000000, 9999999); // practically never: 7 digits
    }

    /** Find or create the visitor by phone (else email), keeping their details current. */
    public static function visitor(array $d, ?int $companyId, ?int $propertyId): Visitor
    {
        $phone = PhoneHelper::e164($d['guest_phone'] ?? null) ?? (filled($d['guest_phone'] ?? null) ? trim($d['guest_phone']) : null);
        $visitor = Visitor::findByPhone($phone);
        if (! $visitor && filled($d['guest_email'] ?? null)) {
            $visitor = Visitor::where('email', $d['guest_email'])->orderByDesc('last_visit_at')->first();
        }
        $type = match ($d['kind'] ?? 'visitor') {
            'stay' => 'guest', 'delivery' => 'delivery', 'contractor' => 'contractor', default => 'one_time',
        };
        if (! $visitor) {
            return Visitor::create([
                'name' => $d['guest_name'], 'phone' => $phone, 'email' => $d['guest_email'] ?? null,
                'id_number' => $d['id_number'] ?? null, 'visitor_type' => $type, 'is_registered' => 1, 'is_active' => 1,
                'company_id' => $companyId, 'property_id' => $propertyId,
            ]);
        }
        $visitor->fill(array_filter([
            'name' => $d['guest_name'] ?? null, 'phone' => $visitor->phone ?: $phone,
            'email' => $d['guest_email'] ?? null, 'id_number' => $d['id_number'] ?? null,
        ]));
        if ($visitor->visitor_type === 'one_time' && $type !== 'one_time') {
            $visitor->visitor_type = $type;
        }
        $visitor->save();

        return $visitor;
    }

    /** Create the pivot row for visitor <-> unit if new, and keep the car / last pass current. */
    public static function place(Visitor $visitor, Unit $unit, ?int $companyId, array $set = []): VisitorPlace
    {
        $place = VisitorPlace::firstOrNew(['visitor_id' => $visitor->id, 'unit_id' => $unit->id]);
        $place->property_id = $unit->property_id;
        $place->company_id = $place->company_id ?? $companyId;
        $place->fill(array_filter($set, fn ($v) => $v !== null));
        $place->save();

        return $place;
    }

    public static function issue(array $d, ?User $by): GatePass
    {
        $unit = Unit::with('property')->findOrFail($d['unit_id']);
        $companyId = self::companyFor($unit);

        return DB::transaction(function () use ($d, $by, $unit, $companyId) {
            $visitor = self::visitor($d, $companyId, $unit->property_id);
            $plate = Visitor::plate($d['vehicle_plate'] ?? null);
            $pass = GatePass::create([
                'code' => self::newCode(),
                'token' => Str::random(40),
                'visitor_id' => $visitor->id,
                'unit_id' => $unit->id,
                'property_id' => $unit->property_id,
                'company_id' => $companyId,
                'kind' => $d['kind'] ?? 'visitor',
                'status' => 'issued',
                'guest_name' => $d['guest_name'],
                'guest_phone' => $visitor->phone,
                'guest_email' => $d['guest_email'] ?? $visitor->email,
                'guests_count' => max(1, (int) ($d['guests_count'] ?? 1)),
                'vehicle_plate' => $plate,
                'vehicle_desc' => $d['vehicle_desc'] ?? null,
                'valid_from' => $d['valid_from'] ?? now(),
                'valid_until' => $d['valid_until'] ?? null,
                'notes' => $d['notes'] ?? null,
                'created_by' => $by?->id,
            ]);
            if ($plate) {
                $visitor->rememberVehicle($plate, $d['vehicle_desc'] ?? null);
            }
            self::place($visitor, $unit, $companyId, ['last_pass_id' => $pass->id, 'last_vehicle_plate' => $plate]);

            return $pass;
        });
    }

    /** Guard lets them in (or host/guest confirms arrival). Returns an error message or null. */
    public static function checkIn(GatePass $pass, ?User $by, string $method = 'guard', ?string $plate = null): ?string
    {
        if (! $pass->isOpen()) {
            return 'This pass is '.str_replace('_', ' ', $pass->status).'.';
        }
        if ($pass->isExpired()) {
            return 'This pass expired on '.$pass->valid_until->timezone('Africa/Nairobi')->format('j M, H:i').'.';
        }
        if ($pass->is_inside) {
            return 'Already inside since '.$pass->logs()->whereNull('exit_time')->latest('access_time')->value('access_time')?->timezone('Africa/Nairobi')->format('H:i').'.';
        }
        $place = VisitorPlace::where('visitor_id', $pass->visitor_id)->where('unit_id', $pass->unit_id)->first();
        if ($place?->is_blocked) {
            return 'This visitor is blocked for this unit. Call the host.';
        }

        $first = $pass->status === 'issued';
        DB::transaction(function () use ($pass, $by, $method, $plate, $first) {
            $plate = Visitor::plate($plate) ?? $pass->vehicle_plate;
            $unit = $pass->unit()->with('property')->first();
            SecurityLog::create([
                'company_id' => $unit->property?->company_id ?? $pass->company_id,
                'property_id' => $pass->property_id,
                'unit_id' => $pass->unit_id,
                'tenant_id' => $unit->activeTenancy?->tenant_id,
                'visitor_id' => $pass->visitor_id,
                'gate_pass_id' => $pass->id,
                'verified_by_user_id' => $by?->id,
                'visitor_name_snapshot' => $pass->guest_name,
                'visitor_phone_snapshot' => $pass->guest_phone,
                'vehicle_registration_snapshot' => $plate,
                'access_type' => match ($pass->kind) { 'delivery' => 'delivery', 'contractor' => 'contractor', default => 'guest' },
                'status' => 'approved',
                'access_time' => now(),
                'purpose' => $pass->kindLabel().' · code '.$pass->code,
                'notes' => $method === 'self' ? 'Checked in by the guest from their phone' : null,
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 250),
            ]);
            $prevPlate = $pass->vehicle_plate;
            $pass->forceFill(array_filter([
                'status' => 'checked_in',
                'is_inside' => true,
                'checked_in_at' => $first ? now() : null,
                'checked_in_by' => $first ? $by?->id : null,
                'check_in_method' => $first ? $method : null,
                'vehicle_plate' => $plate,
            ], fn ($v) => $v !== null))->save();

            $visitor = $pass->visitor;
            if ($plate) {
                $visitor?->rememberVehicle($plate, $plate === $prevPlate ? $pass->vehicle_desc : null);
                if (! $first) {
                    VisitorPlace::where('visitor_id', $pass->visitor_id)->where('unit_id', $pass->unit_id)->update(['last_vehicle_plate' => $plate]);
                }
            }
            if ($first && $visitor) {
                $visitor->forceFill(['visit_count' => $visitor->visit_count + 1, 'last_visit_at' => now()])->save();
                $place = self::place($visitor, $unit, $pass->company_id, ['last_pass_id' => $pass->id, 'last_vehicle_plate' => $plate, 'last_check_in_at' => now()]);
                $place->forceFill(['visit_count' => $place->visit_count + 1, 'first_visit_at' => $place->first_visit_at ?? now()])->save();
            }
        });
        if ($first) {
            GateNotify::hostEvent($pass->fresh(), 'in');
        }

        return null;
    }

    /** A staying guest leaves for a while; the pass stays valid. */
    public static function stepOut(GatePass $pass, ?User $by): ?string
    {
        if ($pass->status !== 'checked_in' || ! $pass->is_inside) {
            return 'Not inside right now.';
        }
        self::closeLogs($pass, $by);
        $pass->forceFill(['is_inside' => false])->save();

        return null;
    }

    /** The visit or stay is over. */
    public static function checkOut(GatePass $pass, ?User $by, string $method = 'guard'): ?string
    {
        if ($pass->status === 'checked_out') {
            return 'Already checked out at '.$pass->checked_out_at?->timezone('Africa/Nairobi')->format('j M, H:i').'.';
        }
        if ($pass->status !== 'checked_in') {
            return $pass->status === 'issued' ? 'Not checked in yet.' : 'This pass is '.$pass->status.'.';
        }
        DB::transaction(function () use ($pass, $by, $method) {
            self::closeLogs($pass, $by, $method === 'self' ? 'Checked out by the guest from their phone' : null);
            $pass->forceFill(['status' => 'checked_out', 'is_inside' => false, 'checked_out_at' => now(),
                'checked_out_by' => $by?->id, 'check_out_method' => $method])->save();
            VisitorPlace::where('visitor_id', $pass->visitor_id)->where('unit_id', $pass->unit_id)
                ->update(['last_check_out_at' => now(), 'updated_at' => now()]);
        });
        GateNotify::hostEvent($pass->fresh(), 'out');

        return null;
    }

    public static function cancel(GatePass $pass): ?string
    {
        if ($pass->status !== 'issued') {
            return 'Only a pass that has not been used can be cancelled.';
        }
        $pass->forceFill(['status' => 'cancelled'])->save();

        return null;
    }

    private static function closeLogs(GatePass $pass, ?User $by, ?string $note = null): void
    {
        foreach ($pass->logs()->whereNull('exit_time')->get() as $log) {
            $log->forceFill([
                'exit_time' => now(),
                'duration_hours' => (int) $log->access_time->diffInHours(now()),
                'status' => 'completed',
                'notes' => trim(($log->notes ? $log->notes.' · ' : '').($note ?? ($by ? 'Out: '.$by->name : ''))) ?: null,
            ])->save();
        }
    }

    /** What a returning visitor looks like to this user: only places within their scope. */
    public static function lookup(User $user, ?string $phone, ?int $unitId = null): ?array
    {
        $visitor = Visitor::findByPhone($phone);
        if (! $visitor) {
            return null;
        }
        $ids = self::scopeUnitIds($user);
        $places = $visitor->places()->with('unit:id,unit_number,property_id', 'property:id,name')
            ->when($ids !== null, fn ($q) => $q->whereIn('unit_id', $ids ?: [0]))->orderByDesc('last_check_in_at')->get();
        if ($places->isEmpty()) {
            return null; // known elsewhere, but not to this business: share nothing
        }
        $here = $unitId ? $places->firstWhere('unit_id', $unitId) : null;

        return [
            'name' => $visitor->name,
            'email' => $visitor->email,
            'vehicles' => collect($visitor->vehicles ?? [])->map(fn ($v) => is_array($v) ? $v : ['plate' => $v])->values(),
            'plate' => $here?->last_vehicle_plate ?? $places->first()->last_vehicle_plate,
            'visits_here' => (int) ($here?->visit_count ?? 0),
            'visits' => (int) $places->sum('visit_count'),
            'blocked' => (bool) $here?->is_blocked,
            'places' => $places->map(fn ($p) => [
                'unit' => $p->unit?->unit_number, 'property' => $p->property?->name, 'visits' => $p->visit_count,
                'last_in' => $p->last_check_in_at?->timezone('Africa/Nairobi')->format('j M Y'),
                'last_out' => $p->last_check_out_at?->timezone('Africa/Nairobi')->format('j M Y'),
            ])->take(5)->values(),
        ];
    }
}
