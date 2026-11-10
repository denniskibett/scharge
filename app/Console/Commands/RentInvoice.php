<?php

namespace App\Console\Commands;

use App\Services\Rent\RentRun;
use App\Services\Rent\RentSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Monthly rent invoices for every company with Integrations › Rent automation switched on. Scheduled daily: a company
 * is billed from its invoice day onwards (a missed day catches up), and never twice for a month (RentRun).
 */
class RentInvoice extends Command
{
    protected $signature = 'rent:invoice {--company= : One company id} {--month= : Billing month YYYY-MM (bills now, whatever the invoice day)} {--dry-run : Show what would be billed}';

    protected $description = 'Create this month\'s rent invoices and send the bills (rent automation)';

    public function handle(RentRun $run): int
    {
        $today = RentSettings::today();
        $forced = $this->option('month');
        if ($forced && ! preg_match('/^\d{4}-\d{2}$/', $forced)) {
            $this->error('--month must look like 2026-12.');

            return self::INVALID;
        }
        $month = $forced ? CarbonImmutable::createFromFormat('Y-m-d', $forced.'-01', RentSettings::TZ) : $today->startOfMonth();

        $rows = [];
        foreach (RentSettings::enabled() as $settings) {
            if ($this->option('company') && (int) $this->option('company') !== $settings->companyId) {
                continue;
            }
            if (! $forced && $today->day < $settings->invoiceDay) {
                $rows[] = [$settings->companyId, 'waiting', '', '', 'Bills on day '.$settings->invoiceDay];

                continue;
            }
            $r = $run->run($settings->companyId, $month, (bool) $this->option('dry-run'));
            $rows[] = [$settings->companyId, $this->option('dry-run') ? 'dry run' : 'done', $r['created'], number_format($r['total'], 2), $r['skipped'].' already billed or nothing to bill'];
        }

        $this->table(['Company', 'Status', 'Invoices', 'Total (KES)', 'Note'], $rows);

        return self::SUCCESS;
    }
}
