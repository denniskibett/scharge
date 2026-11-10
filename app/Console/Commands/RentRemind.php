<?php

namespace App\Console\Commands;

use App\Services\Rent\ArrearsReminders;
use App\Services\Rent\RentSettings;
use Illuminate\Console\Command;

/** Arrears reminders for every company with Rent automation switched on. Scheduled daily at 10:00 Nairobi. */
class RentRemind extends Command
{
    protected $signature = 'rent:remind {--company= : One company id} {--dry-run : List who would be reminded}';

    protected $description = 'Add late penalties (if the owner switched them on) and remind tenants with overdue rent';

    public function handle(ArrearsReminders $reminders, \App\Services\Rent\LatePenalties $penalties): int
    {
        $rows = [];
        foreach (RentSettings::enabled() as $settings) {
            if ($this->option('company') && (int) $this->option('company') !== $settings->companyId) {
                continue;
            }
            // Penalties first (only where the owner switched them on), so the reminder shows the new total.
            $p = $penalties->run($settings, null, (bool) $this->option('dry-run'));
            $r = $reminders->run($settings, null, (bool) $this->option('dry-run'));
            $rows[] = [$settings->companyId, count($r['due']), $r['sent'], $r['failed'], $r['no_phone'],
                number_format(array_sum(array_column($r['due'], 'owed')), 2), count($p['due']).' (KES '.number_format($p['total']).')'];
        }

        $this->table(['Company', 'Due a reminder', 'Sent', 'Failed', 'No phone', 'Owed (KES)', 'Penalties'], $rows);

        return self::SUCCESS;
    }
}
