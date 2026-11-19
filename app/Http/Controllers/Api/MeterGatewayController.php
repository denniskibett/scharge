<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Services\Integrations;
use App\Services\Meters\MeterReadings;
use Illuminate\Http\Request;

/**
 * Integrations › Smart meters & IoT: a meter vendor's cloud (or a building gateway) posts readings for any of the
 * company's meters by serial number, with the company's gateway key:
 *   POST /api/v1/meter-readings   Authorization: Bearer mgw_<company>_<key>
 *   {"serial": "58100012345", "reading": 1234.5, "read_at": "2026-11-20T08:00:00+03:00", "client_ref": "msg-991"}
 *   or {"readings": [ … up to 500 … ]}
 * client_ref makes a re-sent message safe (stored once). Per-meter device tokens (mtr_…) keep working at /smart/readings.
 */
class MeterGatewayController extends Controller
{
    public function store(Request $request, MeterReadings $readings)
    {
        $token = (string) $request->bearerToken();
        if (! preg_match('/^mgw_(\d+)_[A-Za-z0-9]{32}$/', $token, $m)) {
            return response()->json(['error' => 'invalid_gateway_key', 'message' => 'Send the gateway key from Integrations › Smart meters as "Authorization: Bearer mgw_…".'], 401);
        }
        $companyId = (int) $m[1];
        $row = Integrations::for($companyId, 'smart_meters');
        if (! $row || ! hash_equals((string) $row->get('gateway_key'), $token)) {
            return response()->json(['error' => 'invalid_gateway_key', 'message' => 'That gateway key is not valid, or Smart meters is switched off.'], 401);
        }

        $items = $request->has('readings') ? (array) $request->input('readings') : [$request->only(['serial', 'reading', 'read_at', 'client_ref'])];
        validator(['readings' => $items], [
            'readings' => 'required|array|min:1|max:500',
            'readings.*.serial' => 'required|string|max:80',
            'readings.*.reading' => 'required|numeric|min:0',
            'readings.*.read_at' => 'nullable|date',
            'readings.*.client_ref' => 'nullable|string|max:80',
        ])->validate();

        $meters = Meter::where('company_id', $companyId)->where('status', 'active')
            ->whereIn('serial_number', collect($items)->pluck('serial')->map(fn ($s) => (string) $s)->unique())->get()->keyBy('serial_number');
        $out = [];
        foreach ($items as $i => $item) {
            $meter = $meters[(string) $item['serial']] ?? null;
            if (! $meter) {
                $out[] = ['index' => $i, 'serial' => $item['serial'], 'ok' => false, 'error' => 'unknown_meter'];

                continue;
            }
            [$reading, $created] = $readings->record($meter, (float) $item['reading'], [
                'source' => 'smart', 'read_at' => $item['read_at'] ?? null, 'client_ref' => $item['client_ref'] ?? null, 'raw' => $item,
            ]);
            $out[] = ['index' => $i, 'serial' => $item['serial'], 'ok' => true, 'created' => $created, 'id' => $reading->id, 'status' => $reading->status];
        }

        return response()->json(['accepted' => collect($out)->where('ok', true)->count(), 'unknown' => collect($out)->where('ok', false)->count(), 'results' => $out]);
    }
}
