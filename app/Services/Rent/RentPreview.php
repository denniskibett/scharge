<?php

namespace App\Services\Rent;

use App\Models\CompanyIntegration;

/** Integrations › Rent automation › Test: what the next run would bill and who is due a reminder. Sends nothing. */
class RentPreview
{
    /** @return array{0: bool, 1: string} */
    public static function test(CompanyIntegration $row): array
    {
        if (! $row->company_id) {
            return [false, 'Rent automation is set per company.'];
        }

        $settings = RentSettings::fromRow($row);
        $today = RentSettings::today();
        $month = $today->startOfMonth();
        $bill = app(RentRun::class)->run($settings->companyId, $month, dryRun: true);
        $remind = app(ArrearsReminders::class)->run($settings, $today, dryRun: true);

        return [true, sprintf('%s: %d %s to create (KES %s), %d already billed. %d %s due a reminder today (KES %s owed). Nothing was sent.',
            $month->format('F Y'), $bill['created'], $bill['created'] === 1 ? 'invoice' : 'invoices', number_format($bill['total']), $bill['skipped'],
            count($remind['due']), count($remind['due']) === 1 ? 'tenant' : 'tenants', number_format(array_sum(array_column($remind['due'], 'owed'))))];
    }
}
