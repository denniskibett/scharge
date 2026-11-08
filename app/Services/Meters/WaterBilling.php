<?php

namespace App\Services\Meters;

use App\Models\Unit;
use App\Models\WaterReading;
use Carbon\Carbon;

/**
 * Creates the monthly water billing row (water_readings) from a meter reading, with the same rules
 * as WaterReadingController::store(): one row per unit per month, consumption from the unit's last
 * billed reading, rate from the unit or its property, flat-rate units charged their flat amount.
 */
class WaterBilling
{
    /** @return array{0: ?WaterReading, 1: string} the row (null if not created) and why */
    public function recordMonthly(Unit $unit, float $reading, Carbon $date, ?int $recordedBy = null, ?string $notes = null): array
    {
        $existing = WaterReading::where('unit_id', $unit->id)
            ->whereYear('reading_date', $date->year)->whereMonth('reading_date', $date->month)->first();
        if ($existing) {
            return [null, 'month_already_recorded'];
        }

        $latest = WaterReading::where('unit_id', $unit->id)->orderBy('reading_date', 'desc')->first();
        $previous = (float) ($latest ? $latest->current_reading : ($unit->current_water_reading ?? 0));
        if ($reading < $previous) {
            return [null, 'lower_than_last_billed'];
        }

        $consumption = $reading - $previous;
        $rate = (float) ($unit->custom_water_rate ?? $unit->property->water_rate ?? 50);
        $flat = $unit->water_billing_type === 'flat';

        $row = WaterReading::create([
            'unit_id' => $unit->id,
            'previous_reading' => $previous,
            'current_reading' => $reading,
            'consumption' => $flat ? 0 : $consumption,
            'rate_applied' => $rate,
            'charge' => $flat ? ($unit->water_charge ?? 0) : $consumption * $rate,
            'billing_type' => $unit->water_billing_type ?? 'consumption',
            'reading_date' => $date->toDateString(),
            'recorded_by' => $recordedBy,
            'notes' => $notes,
        ]);

        // Keep the unit's cached last two readings in step (as WaterReadingController does).
        $lastTwo = WaterReading::where('unit_id', $unit->id)->orderBy('reading_date', 'desc')->take(2)->get();
        $unit->update([
            'previous_water_reading' => $lastTwo->count() >= 2 ? $lastTwo[1]->current_reading : 0,
            'current_water_reading' => $lastTwo[0]->current_reading,
            'last_reading_date' => $lastTwo[0]->reading_date,
        ]);

        return [$row, 'created'];
    }
}
