<?php

namespace App\Console\Commands;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Meters\WaterBilling;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Smart water meters report many times a day; billing needs one reading per unit per month.
 * On the 1st this posts each smart water meter's last accepted reading of the previous month
 * as that month's water_readings row (skipped if the month already has one).
 */
class PostSmartWaterReadings extends Command
{
    protected $signature = 'meters:post-water {--month= : YYYY-MM, default last month}';

    protected $description = "Post smart water meters' month-end readings to water billing";

    public function handle(WaterBilling $water): int
    {
        $month = $this->option('month') ? Carbon::parse($this->option('month').'-01') : now('Africa/Nairobi')->subMonthNoOverflow()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $posted = 0;

        Meter::where('utility', 'water')->where('kind', 'smart')->where('status', 'active')->whereNotNull('unit_id')->with('unit.property')
            ->each(function (Meter $meter) use ($water, $month, $end, &$posted) {
                $last = MeterReading::where('meter_id', $meter->id)->where('status', 'accepted')
                    ->whereBetween('read_at', [$month, $end])->orderByDesc('read_at')->first();
                if (! $last || ! $meter->unit) {
                    return;
                }
                [$row, $why] = $water->recordMonthly($meter->unit, $last->reading, $end->copy()->startOfDay(), null, 'Smart meter '.$meter->serial_number.' month-end');
                if ($row) {
                    $last->forceFill(['water_reading_id' => $row->id])->save();
                    $posted++;
                } else {
                    $this->line("  meter {$meter->id}: {$why}");
                }
            });

        $this->info("Posted {$posted} smart water reading(s) for ".$month->format('F Y').'.');

        return self::SUCCESS;
    }
}
