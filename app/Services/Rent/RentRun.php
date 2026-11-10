<?php

namespace App\Services\Rent;

use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\WaterReading;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The monthly rent run: one "monthly" invoice per active tenancy per month, for one company.
 *
 * Lines: rent, service charge, garbage, security (the unit's amounts), and water (the flat charge, or the previous
 * month's meter reading for metered units). A tenancy is skipped when it already has any invoice for that month
 * (monthly, or the move-in invoice), so running twice, or after a manual invoice, never bills twice.
 * Runs from `rent:invoice` (scheduled daily; acts from the company's invoice day) and the Invoices › Generate all button.
 */
class RentRun
{
    public function __construct(private BillNotifier $notifier)
    {
    }

    /**
     * @return array{created: int, skipped: int, total: float, invoices: Invoice[], lines: array<int, array>}
     */
    public function run(int $companyId, CarbonImmutable $month, bool $dryRun = false, bool $notify = true): array
    {
        $month = $month->startOfMonth();
        $result = ['created' => 0, 'skipped' => 0, 'total' => 0.0, 'invoices' => [], 'lines' => []];

        foreach ($this->tenancies($companyId, $month)->get() as $tenancy) {
            if ($this->alreadyBilled($tenancy->id, $month)) {
                $result['skipped']++;

                continue;
            }

            $lines = $this->lines($tenancy, $month);
            $total = round(array_sum(array_column($lines, 'amount')), 2);
            if ($total <= 0) {
                $result['skipped']++;

                continue;
            }

            if ($dryRun) {
                $result['created']++;
                $result['total'] += $total;
                $result['lines'][$tenancy->id] = $lines;

                continue;
            }

            $invoice = DB::transaction(function () use ($tenancy, $month, $lines, $total, $companyId) {
                // Lock the tenancy so two runs at once cannot both create the month's invoice.
                Tenancy::whereKey($tenancy->id)->lockForUpdate()->first();
                if ($this->alreadyBilled($tenancy->id, $month)) {
                    return null;
                }

                $invoice = Invoice::create([
                    'tenancy_id' => $tenancy->id,
                    'invoice_type' => 'monthly',
                    'billing_month' => $month->toDateString(),
                    'total_amount' => $total,
                    'total_paid' => 0,
                    'status' => 'unpaid',
                ]);
                $invoice->forceFill(['company_id' => $companyId])->saveQuietly();
                foreach ($lines as $line) {
                    $invoice->items()->create($line + ['company_id' => $companyId]);
                }

                return $invoice;
            });

            if (! $invoice) {
                $result['skipped']++;

                continue;
            }

            $result['created']++;
            $result['total'] += $total;
            $result['invoices'][] = $invoice;

            if ($notify) {
                try {
                    $this->notifier->billReady($invoice);
                } catch (\Throwable $e) {
                    Log::warning('Rent run: bill message failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $result['total'] = round($result['total'], 2);

        return $result;
    }

    /** Active tenancies of a company that cover the month (moved in by its last day, not moved out before its first). */
    public function tenancies(int $companyId, CarbonImmutable $month): Builder
    {
        return Tenancy::query()
            ->with(['unit.property', 'tenant.user'])
            ->where('status', 'active')
            ->whereDate('move_in_date', '<=', $month->endOfMonth()->toDateString())
            ->where(fn ($q) => $q->whereNull('move_out_date')->orWhereDate('move_out_date', '>=', $month->toDateString()))
            ->where(fn ($q) => self::ownedBy($q, $companyId))
            ->orderBy('id');
    }

    /** A tenancy belongs to a company directly, or through its unit, or the unit's property (older rows have no company_id). */
    public static function ownedBy($query, int $companyId)
    {
        return $query->where('company_id', $companyId)
            ->orWhere(fn ($q) => $q->whereNull('company_id')->whereHas('unit', fn ($u) => $u->where('company_id', $companyId)
                ->orWhere(fn ($u) => $u->whereNull('company_id')->whereHas('property', fn ($p) => $p->where('company_id', $companyId)))));
    }

    /** Tenancies of a company (any status). */
    public static function ownedByQuery(int $companyId): Builder
    {
        return Tenancy::query()->where(fn ($q) => self::ownedBy($q, $companyId));
    }

    /** The landlord company of a tenancy: its own company_id, else the unit's, else the property's. */
    public static function companyOf(\App\Modules\Properties\Models\Tenancy $tenancy): ?int
    {
        $id = $tenancy->company_id ?? $tenancy->unit?->company_id ?? $tenancy->unit?->property?->company_id;

        return $id ? (int) $id : null;
    }

    private function alreadyBilled(int $tenancyId, CarbonImmutable $month): bool
    {
        return Invoice::where('tenancy_id', $tenancyId)
            ->whereIn('invoice_type', ['monthly', 'move_in'])
            ->whereDate('billing_month', '>=', $month->toDateString())
            ->whereDate('billing_month', '<=', $month->endOfMonth()->toDateString())
            ->exists();
    }

    /** @return array<int, array{item_type: string, description: string, amount: float, water_units_used?: float}> */
    public function lines(Tenancy $tenancy, CarbonImmutable $month): array
    {
        $unit = $tenancy->unit;
        if (! $unit) {
            return [];
        }
        $label = $month->format('F Y');
        $lines = [];
        $add = function (string $type, string $description, $amount, array $extra = []) use (&$lines) {
            $amount = round((float) $amount, 2);
            if ($amount > 0) {
                $lines[] = ['item_type' => $type, 'description' => $description, 'amount' => $amount] + $extra;
            }
        };

        $add('rent', "Rent - {$label}", $unit->rent_amount);
        $add('service', "Service charge - {$label}", $unit->service_charge);
        $add('garbage', "Garbage collection - {$label}", $unit->garbage_charge);
        $add('security', "Security - {$label}", $unit->security_charge);

        if ($unit->water_billing_type === 'flat') {
            $add('water', "Water (flat rate) - {$label}", $unit->water_charge);
        } else {
            // Metered: last month's reading is billed on this month's invoice. Only that month's reading, so an
            // old reading is never billed twice; a missing reading leaves water off (add it from Water readings).
            $readingMonth = $month->subMonth();
            $reading = class_exists(WaterReading::class) ? WaterReading::where('unit_id', $unit->id)
                ->whereYear('reading_date', $readingMonth->year)->whereMonth('reading_date', $readingMonth->month)
                ->orderByDesc('reading_date')->first() : null;
            if ($reading) {
                $used = (float) ($reading->consumption ?: max(0, (float) $reading->current_reading - (float) $reading->previous_reading));
                $rate = (float) ($unit->custom_water_rate ?? $unit->property?->water_rate ?? 0);
                $add('water', sprintf('Water - %s reading: %s m³ at KES %s', $readingMonth->format('M Y'), rtrim(rtrim(number_format($used, 2), '0'), '.'), number_format($rate, 2)),
                    $used * $rate, ['water_units_used' => $used]);
            }
        }

        return $lines;
    }
}
