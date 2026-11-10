<?php

namespace App\Services\Rent;

use App\Models\Invoice;
use App\Models\RentHold;
use App\Models\RentMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Late payment penalties, only for companies that switched them on (Integrations › Rent automation › Late payment
 * penalty). Once per monthly bill, when it is the set number of days late: a percentage of what is still unpaid on that
 * bill, or a fixed amount. Added as an "other" line on the same bill. Never for a paused tenancy. Recorded in
 * rent_messages (kind "penalty") so it is never added twice.
 */
class LatePenalties
{
    /** @return array{added: int, total: float, due: array<int, array{invoice_id: int, amount: float}>} */
    public function run(RentSettings $settings, ?CarbonImmutable $today = null, bool $dryRun = false): array
    {
        $today ??= RentSettings::today();
        $result = ['added' => 0, 'total' => 0.0, 'due' => []];
        if ($settings->penaltyType === 'off') {
            return $result;
        }
        $paused = RentHold::activeTenancyIds($settings->companyId);
        $reminders = app(ArrearsReminders::class);

        $invoices = Invoice::query()->where('invoice_type', 'monthly')->whereIn('status', ['unpaid', 'partial'])
            ->whereColumn('total_paid', '<', 'total_amount')
            ->whereHas('tenancy', fn ($t) => $t->where('status', 'active')->where(fn ($q) => RentRun::ownedBy($q, $settings->companyId)))
            ->whereNotIn('tenancy_id', $paused ?: [0])
            ->whereNotIn('id', RentMessage::where('kind', 'penalty')->select('invoice_id'))
            ->get()
            ->filter(fn (Invoice $i) => $reminders->dueDate($settings, $i)->diffInDays($today, false) >= $settings->penaltyAfterDays);

        foreach ($invoices as $invoice) {
            $unpaid = (float) $invoice->total_amount - (float) $invoice->total_paid;
            $amount = round($settings->penaltyType === 'percent' ? $unpaid * $settings->penaltyAmount / 100 : $settings->penaltyAmount, 2);
            if ($amount <= 0) {
                continue;
            }
            $result['due'][] = ['invoice_id' => $invoice->id, 'amount' => $amount];
            if ($dryRun) {
                $result['total'] += $amount;

                continue;
            }

            $added = DB::transaction(function () use ($invoice, $amount, $settings, $unpaid) {
                try {
                    RentMessage::create(['company_id' => $settings->companyId, 'tenancy_id' => $invoice->tenancy_id, 'invoice_id' => $invoice->id,
                        'kind' => 'penalty', 'stage' => 0, 'status' => 'added', 'balance' => $amount,
                        'message' => 'Late payment penalty of KES '.number_format($amount, 2).' added.']);
                } catch (UniqueConstraintViolationException) {
                    return false;
                }
                $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
                $label = $settings->penaltyType === 'percent'
                    ? sprintf('Late payment penalty (%s%% of KES %s unpaid)', rtrim(rtrim(number_format($settings->penaltyAmount, 2), '0'), '.'), number_format($unpaid))
                    : 'Late payment penalty';
                $invoice->items()->create(['item_type' => 'other', 'description' => $label, 'amount' => $amount, 'company_id' => $settings->companyId]);
                $invoice->total_amount = round((float) $invoice->total_amount + $amount, 2);
                $invoice->status = (float) $invoice->total_paid > 0 ? 'partial' : 'unpaid';
                $invoice->save();

                return true;
            });
            if ($added) {
                $result['added']++;
                $result['total'] += $amount;
            }
        }
        $result['total'] = round($result['total'], 2);

        return $result;
    }
}
