<?php

namespace App\Services\Rent;

use App\Models\Invoice;
use App\Models\RentMessage;
use Carbon\CarbonImmutable;

/**
 * Arrears reminders for one company: for each active tenancy with unpaid bills past their due date, at most one
 * message per run, at the company's reminder days (e.g. 1, 7 and 14 days late). The stage is counted from the oldest
 * unpaid bill; the message gives the total owed. Each stage is sent once (rent_messages).
 *
 * Due date: monthly bills on the company's due day of the billing month; move-in bills on the move-in date.
 */
class ArrearsReminders
{
    public function __construct(private BillNotifier $notifier)
    {
    }

    /** @return array{sent: int, failed: int, no_phone: int, due: array<int, array{tenancy_id: int, stage: int, days_late: int, owed: float}>} */
    public function run(RentSettings $settings, ?CarbonImmutable $today = null, bool $dryRun = false): array
    {
        $today ??= RentSettings::today();
        $result = ['sent' => 0, 'failed' => 0, 'no_phone' => 0, 'due' => []];
        if (! $settings->reminderDays) {
            return $result;
        }

        $invoices = Invoice::query()
            ->with(['tenancy.tenant.user', 'tenancy.unit.property'])
            ->whereIn('invoice_type', ['monthly', 'move_in'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereColumn('total_paid', '<', 'total_amount')
            ->whereHas('tenancy', fn ($t) => $t->where('status', 'active')->where(fn ($q) => RentRun::ownedBy($q, $settings->companyId)))
            ->orderBy('billing_month')->orderBy('id')
            ->get()
            ->filter(fn (Invoice $i) => $i->billing_month && $this->dueDate($settings, $i)->lte($today))
            ->groupBy('tenancy_id');

        foreach ($invoices as $tenancyInvoices) {
            $oldest = $tenancyInvoices->first();
            $daysLate = (int) $this->dueDate($settings, $oldest)->diffInDays($today);
            $stage = collect($settings->reminderDays)->filter(fn ($d) => $d <= $daysLate)->last();
            if ($stage === null || RentMessage::where('invoice_id', $oldest->id)->where('kind', 'reminder')->where('stage', $stage)->exists()) {
                continue;
            }

            $owed = round($tenancyInvoices->sum(fn ($i) => $this->notifier->balance($i)), 2);
            $result['due'][] = ['tenancy_id' => (int) $oldest->tenancy_id, 'stage' => (int) $stage, 'days_late' => $daysLate, 'owed' => $owed];
            if ($dryRun) {
                continue;
            }

            $row = $this->notifier->remind($settings, $tenancyInvoices->values(), (int) $stage, $daysLate);
            if ($row) {
                $key = $row->status === 'sent' ? 'sent' : ($row->status === 'no_phone' ? 'no_phone' : 'failed');
                $result[$key]++;
            }
        }

        return $result;
    }

    public function dueDate(RentSettings $settings, Invoice $invoice): CarbonImmutable
    {
        $date = CarbonImmutable::parse(substr((string) $invoice->getRawOriginal('billing_month'), 0, 10), RentSettings::TZ);

        return $invoice->invoice_type === 'move_in' ? $date : $settings->dueDate($date);
    }
}
