<?php

namespace App\Services\Rent;

use App\Models\CompanyIntegration;
use Carbon\CarbonImmutable;

/**
 * A company's rent automation settings: Integrations › "Rent automation" (provider rent_automation, config/integrations.php).
 * Off until the company switches it on. Read by RentRun (monthly invoices) and ArrearsReminders.
 */
class RentSettings
{
    public const PROVIDER = 'rent_automation';

    public const TZ = 'Africa/Nairobi';

    public function __construct(
        public readonly int $companyId,
        public readonly int $invoiceDay,
        public readonly int $dueDay,
        public readonly bool $sendBill,
        /** @var int[] days after the due date, ascending */
        public readonly array $reminderDays,
        public readonly string $channel,
        /** Late penalty, switched on by the owner: off | percent | fixed */
        public readonly string $penaltyType = 'off',
        public readonly float $penaltyAmount = 0,
        public readonly int $penaltyAfterDays = 5,
    ) {
    }

    public static function for(int $companyId): ?self
    {
        $row = self::installed() ? CompanyIntegration::where('provider', self::PROVIDER)->where('enabled', true)
            ->where('company_id', $companyId)->latest('id')->first() : null;

        return $row ? self::fromRow($row) : null;
    }

    public static function fromRow(CompanyIntegration $row): self
    {
        $day = fn ($v, $default) => max(1, min(28, (int) ($v ?: $default)));
        $days = collect(preg_split('/[\s,]+/', (string) $row->get('reminder_days', '1,7,14')))
            ->filter(fn ($d) => is_numeric($d) && (int) $d >= 0 && (int) $d <= 90)->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        return new self(
            companyId: (int) $row->company_id,
            invoiceDay: $day($row->get('invoice_day'), 1),
            dueDay: $day($row->get('due_day'), 5),
            sendBill: $row->get('send_bill', 'yes') === 'yes',
            reminderDays: array_slice($days, 0, 6),
            channel: in_array($row->get('channel'), ['sms', 'whatsapp'], true) ? $row->get('channel') : 'sms',
            penaltyType: in_array($row->get('penalty'), ['percent', 'fixed'], true) && (float) $row->get('penalty_amount') > 0 ? $row->get('penalty') : 'off',
            penaltyAmount: max(0, min($row->get('penalty') === 'percent' ? 50 : 1000000, (float) $row->get('penalty_amount'))),
            penaltyAfterDays: max(1, min(90, (int) ($row->get('penalty_after_days') ?: 5))),
        );
    }

    /** Every company that has switched rent automation on. @return self[] */
    public static function enabled(): array
    {
        if (! self::installed()) {
            return [];
        }

        return CompanyIntegration::where('provider', self::PROVIDER)->where('enabled', true)->whereNotNull('company_id')
            ->get()->unique('company_id')->map(fn ($row) => self::fromRow($row))->values()->all();
    }

    /** Read once per call (cheap), not cached: the scheduler must see a switch-off at once. */
    private static function installed(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('company_integrations');
    }

    /** The day a month's rent is due, e.g. 5 December for December. */
    public function dueDate(CarbonImmutable $month): CarbonImmutable
    {
        return $month->startOfMonth()->setDay($this->dueDay);
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }
}
