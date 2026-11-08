<?php

namespace App\Console\Commands;

use App\Models\MpesaStk;
use App\Models\Payment;
use App\Services\MpesaSettlementService;
use Illuminate\Console\Command;

/**
 * Re-runs settlement for STK pushes that M-Pesa confirmed (ResultCode 0) but
 * that never became a Payment, e.g. the callback errored half-way or the
 * wallet write failed. Settlement is idempotent, so this is safe to run often.
 */
class ReconcileMpesaPayments extends Command
{
    protected $signature = 'mpesa:reconcile {--days=7 : Only look at STK pushes from the last N days} {--dry-run}';

    protected $description = 'Settle successful M-Pesa STK pushes that have no Payment yet';

    public function handle(MpesaSettlementService $settlement): int
    {
        $unsettled = MpesaStk::query()
            ->where('response_code', '0')
            ->whereNotNull('invoice_id')
            ->whereNotNull('mpesa_receipt_number')
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->get();

        // Filtered in PHP: mpesa_stks and payments use different collations
        // (utf8mb4_0900_ai_ci vs utf8mb4_unicode_ci), so a SQL subquery errors.
        $settledRefs = Payment::query()
            ->whereIn('transaction_reference', $unsettled->pluck('checkout_request_id'))
            ->pluck('transaction_reference')
            ->flip();
        $unsettled = $unsettled->reject(fn ($stk) => isset($settledRefs[$stk->checkout_request_id]));

        $this->info("Unsettled successful STK pushes: {$unsettled->count()}");

        $settled = 0;
        foreach ($unsettled as $stk) {
            if ($this->option('dry-run')) {
                $this->line("  would settle STK #{$stk->id} ({$stk->mpesa_receipt_number}, KES {$stk->amount})");

                continue;
            }

            $result = $settlement->settle($stk);
            $settled += $result['success'] ? 1 : 0;
            $this->line(sprintf('  STK #%d %s: %s', $stk->id, $stk->mpesa_receipt_number, $result['success'] ? 'settled' : $result['message']));
        }

        $this->info("Settled: {$settled}");

        return self::SUCCESS;
    }
}
