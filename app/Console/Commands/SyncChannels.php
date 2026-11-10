<?php

namespace App\Console\Commands;

use App\Models\UnitChannel;
use App\Services\Bookings\ChannelSync;
use Illuminate\Console\Command;

/** Read every unit's Airbnb / Booking.com calendar (only once Channel sync is switched on). Scheduled every 30 minutes. */
class SyncChannels extends Command
{
    protected $signature = 'bookings:sync {--force : Run even while Channel sync is switched off (testing)}';

    protected $description = 'Import Airbnb and Booking.com calendars into unit bookings';

    public function handle(ChannelSync $sync): int
    {
        if (! ChannelSync::enabled() && ! $this->option('force')) {
            $this->line('Channel sync is switched off (Integrations › Channel sync).');

            return self::SUCCESS;
        }
        foreach (UnitChannel::whereNotNull('import_url')->get() as $channel) {
            $r = $sync->sync($channel);
            $this->line(sprintf('unit %d %s: %s, %d new, %d cancelled, %d clashes', $channel->unit_id, $channel->channel, $channel->fresh()->last_status, $r['imported'], $r['cancelled'], $r['clashes']));
        }

        return self::SUCCESS;
    }
}
