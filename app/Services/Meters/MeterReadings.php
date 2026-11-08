<?php

namespace App\Services\Meters;

use App\Models\Meter;
use App\Models\MeterReading;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Records a meter reading from any source (reader app, smart meter, API, web).
 *  - idempotent on (meter, client_ref): a re-sent offline reading or device message returns the original;
 *  - consumption is against the latest earlier reading (out-of-order syncs are fine), times the multiplier;
 *  - flags (but keeps) readings lower than the previous one, and usage over 3x the meter's 90-day daily
 *    average, so a person checks them before billing;
 *  - water readings taken by a person become the month's water_readings billing row (smart water meters
 *    are posted once a month by `meters:post-water`).
 */
class MeterReadings
{
    public const ANOMALY_FACTOR = 3;

    public function __construct(private WaterBilling $water)
    {
    }

    /**
     * @param  array{source: string, read_at?: ?string, client_ref?: ?string, recorded_by?: ?int, note?: ?string, photo?: ?UploadedFile, photo_base64?: ?string, raw?: ?array}  $o
     * @return array{0: MeterReading, 1: bool} the reading and whether it was newly created
     */
    public function record(Meter $meter, float $reading, array $o): array
    {
        $clientRef = isset($o['client_ref']) ? mb_substr((string) $o['client_ref'], 0, 80) : null;
        if ($clientRef && ($existing = MeterReading::where('meter_id', $meter->id)->where('client_ref', $clientRef)->first())) {
            return [$existing, false];
        }

        $readAt = isset($o['read_at']) && $o['read_at'] ? Carbon::parse($o['read_at']) : now();
        if ($readAt->isAfter(now()->addMinutes(10))) {
            $readAt = now();   // device clocks drift; never store readings in the future
        }

        return DB::transaction(function () use ($meter, $reading, $o, $clientRef, $readAt) {
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->first();

            $prev = MeterReading::where('meter_id', $meter->id)->where('read_at', '<=', $readAt)->where('status', '!=', 'rejected')
                ->orderByDesc('read_at')->orderByDesc('id')->first();
            $previous = $prev?->reading ?? (float) $meter->initial_reading;
            $status = 'accepted';
            $flag = null;

            if ($reading < $previous) {
                $status = 'flagged';
                $flag = 'lower_than_previous';
                $consumption = 0.0;
            } else {
                $consumption = round(($reading - $previous) * ($meter->multiplier ?: 1), 3);
                if ($this->unusual($meter, $consumption, $prev?->read_at ?? $meter->created_at, $readAt)) {
                    $status = 'flagged';
                    $flag = 'unusual_consumption';
                }
            }

            $rate = $meter->rate();
            $row = MeterReading::create([
                'meter_id' => $meter->id,
                'company_id' => $meter->company_id,
                'unit_id' => $meter->unit_id,
                'utility' => $meter->utility,
                'reading' => $reading,
                'previous_reading' => $previous,
                'consumption' => $consumption,
                'rate_applied' => $rate,
                'charge' => $rate !== null ? round($consumption * $rate, 2) : null,
                'read_at' => $readAt,
                'source' => $o['source'],
                'recorded_by' => $o['recorded_by'] ?? null,
                'photo_path' => $this->storePhoto($meter, $o),
                'status' => $status,
                'flag_reason' => $flag,
                'client_ref' => $clientRef,
                'note' => isset($o['note']) ? mb_substr((string) $o['note'], 0, 255) : null,
                'raw' => $o['raw'] ?? null,
            ]);

            if ($status === 'accepted' && (! $meter->last_read_at || $readAt->greaterThanOrEqualTo($meter->last_read_at))) {
                $meter->forceFill(['last_reading' => $reading, 'last_read_at' => $readAt])->save();
            }

            // A person's water reading is the month's billing reading (not flagged ones: those wait for review).
            if ($meter->utility === 'water' && $meter->unit && $status === 'accepted' && $o['source'] !== 'smart') {
                [$water] = $this->water->recordMonthly($meter->unit, $reading, $readAt, $o['recorded_by'] ?? null,
                    'Meter '.$meter->serial_number.' via '.$o['source']);
                if ($water) {
                    $row->forceFill(['water_reading_id' => $water->id])->save();
                }
            }

            return [$row, true];
        });
    }

    /** Daily usage above ANOMALY_FACTOR x the meter's average over the last 90 days. */
    private function unusual(Meter $meter, float $consumption, $since, Carbon $readAt): bool
    {
        $days = max(1 / 24, Carbon::parse($since)->diffInMinutes($readAt) / 1440);
        $rate = $consumption / $days;

        $history = MeterReading::where('meter_id', $meter->id)->where('status', 'accepted')
            ->where('read_at', '>=', $readAt->copy()->subDays(90))->where('read_at', '<', $readAt)
            ->orderBy('read_at')->get(['reading', 'read_at']);
        if ($history->count() < 3) {
            return false;   // not enough history to judge
        }
        $span = max(1, $history->first()->read_at->diffInMinutes($history->last()->read_at) / 1440);
        $avg = (($history->last()->reading - $history->first()->reading) * ($meter->multiplier ?: 1)) / $span;

        return $avg > 0 && $rate > self::ANOMALY_FACTOR * $avg;
    }

    private function storePhoto(Meter $meter, array $o): ?string
    {
        $dir = 'meter-photos/'.$meter->company_id.'/'.now()->format('Y/m');
        if (($o['photo'] ?? null) instanceof UploadedFile) {
            return $o['photo']->store($dir, 'public');
        }
        if (! empty($o['photo_base64'])) {
            $data = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $o['photo_base64']), true);
            if ($data !== false && strlen($data) <= 6 * 1024 * 1024 && @getimagesizefromstring($data)) {
                $path = $dir.'/'.$meter->id.'-'.uniqid().'.jpg';
                Storage::disk('public')->put($path, $data);

                return $path;
            }
        }

        return null;
    }
}
