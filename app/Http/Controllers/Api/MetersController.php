<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Unit;
use App\Models\User;
use App\Services\Meters\MeterReadings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Meter readings API (water & electricity, manual and smart). All calls are scoped to the key's company.
 *
 *   POST   /api/v1/auth/token                 reader app sign-in → personal key (scopes read, meters)
 *   DELETE /api/v1/auth/token                 sign out (deletes that key)
 *   GET    /api/v1/meters                     list (filters: utility, property_id, unit_id, kind, due=1)
 *   POST   /api/v1/meters                     register a meter                     [meters]
 *   GET    /api/v1/meters/{id}
 *   GET    /api/v1/meters/{id}/readings
 *   POST   /api/v1/meters/{id}/readings       record one reading (+ photo)         [meters]
 *   POST   /api/v1/readings/batch             up to 500 readings (offline sync / gateways) [meters]
 *   POST   /api/v1/meters/{id}/device-token   issue a smart-meter token (once)     [meters]
 *   POST   /api/v1/smart/readings             smart meter pushes with "Bearer mtr_…"
 */
class MetersController extends Controller
{
    public function __construct(private MeterReadings $readings)
    {
    }

    public function token(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'device_name' => 'required|string|max:60']);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'invalid_credentials', 'message' => 'Wrong email or password.'], 401);
        }
        if (! $user->company_id || ! $user->hasAnyRole(['meter_reader', 'property_manager', 'admin', 'maintenance', 'security'])) {
            return response()->json(['error' => 'not_allowed', 'message' => 'This account cannot use the meter reader app.'], 403);
        }

        $key = new ApiKey(['company_id' => $user->company_id, 'user_id' => $user->id, 'name' => 'Reader app · '.$data['device_name'],
            'environment' => 'live', 'scopes' => ['read', 'meters'], 'enabled' => true, 'allowed_ips' => []]);
        $plain = $key->issue();
        $key->save();

        return response()->json(['token' => $plain, 'user' => ['id' => $user->id, 'name' => $user->name], 'company' => $user->company?->name, 'scopes' => $key->scopes], 201);
    }

    public function revoke(Request $request)
    {
        $key = $this->key($request);
        abort_unless($key->user_id && str_starts_with($key->name, 'Reader app'), 403, 'Only reader-app sessions can be signed out here.');
        $key->delete();

        return response()->json(['revoked' => true]);
    }

    public function index(Request $request)
    {
        $q = Meter::where('company_id', $this->companyId($request))->where('status', 'active')
            ->with(['unit:id,unit_number,property_id', 'unit.property:id,name', 'property:id,name'])
            ->when($request->query('utility'), fn ($q, $u) => $q->where('utility', $u))
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->query('property_id'), fn ($q, $p) => $q->where(fn ($w) => $w->where('property_id', $p)->orWhereHas('unit', fn ($u) => $u->where('property_id', $p))))
            ->when($request->query('unit_id'), fn ($q, $u) => $q->where('unit_id', $u))
            // due=1: manual meters not read yet this month (a reader's route)
            ->when($request->boolean('due'), fn ($q) => $q->where('kind', 'manual')->where(fn ($w) => $w->whereNull('last_read_at')->orWhere('last_read_at', '<', now()->startOfMonth())))
            ->orderBy('property_id')->orderBy('unit_id');

        return response()->json(['data' => $q->limit(1000)->get()->map(fn ($m) => $this->presentMeter($m))->values()]);
    }

    public function store(Request $request)
    {
        $company = $this->companyId($request);
        $data = $request->validate([
            'unit_id' => 'nullable|integer',
            'property_id' => 'nullable|integer',
            'utility' => ['required', Rule::in(array_keys(Meter::UTILITIES))],
            'kind' => 'nullable|in:manual,smart',
            'billing' => 'nullable|in:postpaid,prepaid',
            'serial_number' => ['required', 'string', 'max:64', Rule::unique('meters')->where(fn ($q) => $q->where('company_id', $company)->where('utility', $request->input('utility')))],
            'make' => 'nullable|string|max:80',
            'model' => 'nullable|string|max:80',
            'multiplier' => 'nullable|numeric|min:0.0001',
            'rate_per_unit' => 'nullable|numeric|min:0',
            'initial_reading' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $unit = isset($data['unit_id']) ? Unit::with('property:id,company_id')->find($data['unit_id']) : null;
        abort_if(isset($data['unit_id']) && (! $unit || (int) $unit->property?->company_id !== $company), 422, 'That unit is not in your company.');
        if (! $unit && isset($data['property_id'])) {
            abort_unless(\App\Models\Property::where('company_id', $company)->whereKey($data['property_id'])->exists(), 422, 'That property is not in your company.');
        }
        abort_if(! $unit && empty($data['property_id']), 422, 'Give a unit_id, or a property_id for a common-area meter.');

        $meter = Meter::create($data + [
            'company_id' => $company,
            'property_id' => $unit?->property_id ?? $data['property_id'],
            'kind' => $data['kind'] ?? 'manual',
            'billing' => $data['billing'] ?? 'postpaid',
            'unit_of_measure' => Meter::UTILITIES[$data['utility']],
            'multiplier' => $data['multiplier'] ?? 1,
            'initial_reading' => $data['initial_reading'] ?? 0,
            'status' => 'active',
        ]);

        return response()->json($this->presentMeter($meter->load('unit.property', 'property')), 201);
    }

    public function show(Request $request, int $id)
    {
        return response()->json($this->presentMeter($this->meter($request, $id)->load('unit.property', 'property')));
    }

    public function readingsIndex(Request $request, int $id)
    {
        $meter = $this->meter($request, $id);
        $rows = MeterReading::where('meter_id', $meter->id)->orderByDesc('read_at')->limit(min(500, (int) $request->query('limit', 100)))->get();

        return response()->json(['data' => $rows->map(fn ($r) => $this->presentReading($r))->values()]);
    }

    public function record(Request $request, int $id)
    {
        $meter = $this->meter($request, $id);
        $data = $request->validate($this->readingRules() + ['photo' => 'nullable|image|max:6144', 'photo_base64' => 'nullable|string']);

        [$row, $created] = $this->readings->record($meter, (float) $data['reading'], [
            'source' => $this->key($request)->user_id && str_starts_with($this->key($request)->name, 'Reader app') ? 'reader_app' : 'api',
            'read_at' => $data['read_at'] ?? null,
            'client_ref' => $data['client_ref'] ?? null,
            'recorded_by' => $this->key($request)->user_id,
            'note' => $data['note'] ?? null,
            'photo' => $request->file('photo'),
            'photo_base64' => $data['photo_base64'] ?? null,
        ]);

        return response()->json($this->presentReading($row), $created ? 201 : 200)->header('Idempotent-Replay', $created ? 'false' : 'true');
    }

    public function batch(Request $request)
    {
        $company = $this->companyId($request);
        $data = $request->validate([
            'readings' => 'required|array|min:1|max:500',
            'readings.*.meter_id' => 'nullable|integer',
            'readings.*.serial_number' => 'nullable|string|max:64',
            'readings.*.utility' => ['nullable', Rule::in(array_keys(Meter::UTILITIES))],
            'readings.*.reading' => 'required|numeric|min:0',
            'readings.*.read_at' => 'nullable|date',
            'readings.*.client_ref' => 'nullable|string|max:80',
            'readings.*.note' => 'nullable|string|max:255',
        ]);
        $key = $this->key($request);

        $results = collect($data['readings'])->map(function ($item, $i) use ($company, $key) {
            $meter = Meter::where('company_id', $company)->where('status', 'active')
                ->when($item['meter_id'] ?? null, fn ($q, $id) => $q->whereKey($id),
                    fn ($q) => $q->where('serial_number', $item['serial_number'] ?? '')->when($item['utility'] ?? null, fn ($w, $u) => $w->where('utility', $u)))
                ->first();
            if (! $meter) {
                return ['index' => $i, 'ok' => false, 'error' => 'meter_not_found'];
            }
            try {
                [$row, $created] = $this->readings->record($meter, (float) $item['reading'], [
                    'source' => $meter->kind === 'smart' ? 'smart' : ($key->user_id ? 'reader_app' : 'api'),
                    'read_at' => $item['read_at'] ?? null, 'client_ref' => $item['client_ref'] ?? null,
                    'recorded_by' => $key->user_id, 'note' => $item['note'] ?? null,
                ]);

                return ['index' => $i, 'ok' => true, 'created' => $created, 'reading' => $this->presentReading($row)];
            } catch (\Throwable $e) {
                return ['index' => $i, 'ok' => false, 'error' => 'failed', 'message' => $e->getMessage()];
            }
        });

        return response()->json(['accepted' => $results->where('ok', true)->count(), 'failed' => $results->where('ok', false)->count(), 'results' => $results->values()]);
    }

    public function deviceToken(Request $request, int $id)
    {
        $meter = $this->meter($request, $id);

        return response()->json(['meter_id' => $meter->id, 'device_token' => $meter->issueDeviceToken(),
            'message' => 'Store this on the device now; it will not be shown again. Issuing a new one disables the old one.'], 201);
    }

    /** Smart meter: Authorization: Bearer mtr_… ; body {reading, read_at?, client_ref?} or {readings: [...]} */
    public function smart(Request $request)
    {
        $meter = Meter::findByDeviceToken($request->bearerToken());
        if (! $meter) {
            return response()->json(['error' => 'invalid_device_token', 'message' => 'Send this meter\'s device token as "Authorization: Bearer mtr_…".'], 401);
        }

        $items = $request->has('readings') ? (array) $request->input('readings') : [$request->only(['reading', 'read_at', 'client_ref'])];
        validator(['readings' => $items], [
            'readings' => 'required|array|min:1|max:500',
            'readings.*.reading' => 'required|numeric|min:0',
            'readings.*.read_at' => 'nullable|date',
            'readings.*.client_ref' => 'nullable|string|max:80',
        ])->validate();

        $out = [];
        foreach ($items as $item) {
            [$row, $created] = $this->readings->record($meter, (float) $item['reading'], [
                'source' => 'smart', 'read_at' => $item['read_at'] ?? null, 'client_ref' => $item['client_ref'] ?? null, 'raw' => $item,
            ]);
            $out[] = ['id' => $row->id, 'created' => $created, 'status' => $row->status];
        }

        return response()->json(['meter_id' => $meter->id, 'received' => count($out), 'results' => $out]);
    }

    private function readingRules(): array
    {
        return ['reading' => 'required|numeric|min:0', 'read_at' => 'nullable|date', 'client_ref' => 'nullable|string|max:80', 'note' => 'nullable|string|max:255'];
    }

    private function key(Request $request): ApiKey
    {
        return $request->attributes->get('api_key');
    }

    private function companyId(Request $request): int
    {
        return (int) $this->key($request)->company_id;
    }

    private function meter(Request $request, int $id): Meter
    {
        $meter = Meter::where('company_id', $this->companyId($request))->find($id);
        abort_unless($meter, 404, 'No such meter for this company.');

        return $meter;
    }

    private function presentMeter(Meter $m): array
    {
        return [
            'id' => $m->id, 'object' => 'meter', 'utility' => $m->utility, 'kind' => $m->kind, 'billing' => $m->billing,
            'serial_number' => $m->serial_number, 'make' => $m->make, 'model' => $m->model, 'unit_of_measure' => $m->unit_of_measure,
            'multiplier' => $m->multiplier, 'rate_per_unit' => $m->rate(),
            'unit' => $m->unit ? ['id' => $m->unit->id, 'number' => $m->unit->unit_number] : null,
            'property' => ($p = $m->unit?->property ?? $m->property) ? ['id' => $p->id, 'name' => $p->name] : null,
            'last_reading' => $m->last_reading ?? $m->initial_reading, 'last_read_at' => $m->last_read_at?->toIso8601String(),
            'has_device_token' => (bool) $m->device_token_hash, 'status' => $m->status,
        ];
    }

    private function presentReading(MeterReading $r): array
    {
        return [
            'id' => $r->id, 'object' => 'meter_reading', 'meter_id' => $r->meter_id, 'utility' => $r->utility,
            'reading' => $r->reading, 'previous_reading' => $r->previous_reading, 'consumption' => $r->consumption,
            'rate_applied' => $r->rate_applied, 'charge' => $r->charge, 'read_at' => $r->read_at?->toIso8601String(),
            'source' => $r->source, 'status' => $r->status, 'flag_reason' => $r->flag_reason, 'client_ref' => $r->client_ref,
            'photo_url' => $r->photo_path ? asset('storage/'.$r->photo_path) : null, 'water_reading_id' => $r->water_reading_id,
        ];
    }
}
