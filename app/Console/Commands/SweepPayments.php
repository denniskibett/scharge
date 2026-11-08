<?php

namespace App\Console\Commands;

use App\Services\Sharet\SharetPayouts;
use Illuminate\Console\Command;

/** Pay landlords what scharge collected for them through Sharet (rail mode). Scheduled 08:00 / 12:00 / 16:00 Nairobi. */
class SweepPayments extends Command
{
    protected $signature = 'payments:sweep {--slot= : Override the slot, e.g. 2026-11-09T08} {--dry-run}';

    protected $description = 'Sweep rail-mode Sharet collections to each landlord company, minus the scharge fee';

    public function handle(SharetPayouts $payouts): int
    {
        $rows = $payouts->sweep($this->option('slot') ?: null, (bool) $this->option('dry-run'));

        $this->table(['Company', 'Status', 'Net (KES)', 'Note'], array_map(
            fn ($r) => [$r['company_id'], $r['status'], isset($r['net']) ? number_format($r['net'], 2) : '', $r['message'] ?? ''],
            $rows
        ));

        return self::SUCCESS;
    }
}
