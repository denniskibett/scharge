<?php

namespace App\Modules\CRM\Services;

use App\Models\System;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Account-manager commission.
 *
 * scharge's model is a platform fee per unit per month, whatever the unit's occupancy or rent collected
 * (company_subscriptions → subscription_invoices: units × plan price_per_unit). scharge bills that fee to the property
 * manager (the customer company); account managers are scharge staff who manage those properties for us, and scharge
 * pays them a share of the fee once the property manager has settled the bill (subscription_invoices.status = paid).
 *
 * Never part of it: tenant deposits or property sales, where any commission is paid by owners to their agents
 * (units.commission_rate) and isn't scharge money.
 *
 * Each paid bill is credited to the company's current owner (companies.owner_id) and split across the company's
 * properties by unit count, so statements show which property earned what. Settings live in
 * system.settings.crm_commission (no schema change), with per-person overrides.
 */
class CommissionService
{
    public const DEFAULTS = [
        'rate' => 20.0,                    // % of the per-unit platform fee
        'pipeline_price_per_unit' => 100,  // KES / unit / month, to value deals that only have an estimated unit count
        'overrides' => [],                 // user_id => ['rate' => ..]
    ];

    public function settings(): array
    {
        $saved = (array) data_get(System::first()?->settings, 'crm_commission', []);

        return array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    public function save(array $values): void
    {
        $system = System::first() ?? System::create(['name' => config('app.name')]);
        $settings = $system->settings ?? [];
        // Only current keys are kept, so older plan shapes are dropped on the next save.
        $settings['crm_commission'] = array_merge($this->settings(), array_intersect_key($values, self::DEFAULTS));
        $system->forceFill(['settings' => $settings])->save();
    }

    public function rateFor(?int $userId, ?array $settings = null): float
    {
        $s = $settings ?? $this->settings();
        $override = $userId ? ($s['overrides'][$userId]['rate'] ?? null) : null;

        return (float) ($override !== null && $override !== '' ? $override : $s['rate']);
    }

    /**
     * One line per settled (paid) platform-fee bill per property, for companies with an owner.
     *
     * @param  array<int>|null  $ownerIds  limit to these owners (null = all)
     * @return Collection<int, object{invoice_id:int, invoice_number:?string, paid_at:Carbon, period:?string, company_id:int, company:string, owner_id:int, property_id:?int, property:string, units:int, price_per_unit:?float, fee:float, rate:float, commission:float}>
     */
    public function lines(Carbon $from, Carbon $to, ?array $ownerIds = null): Collection
    {
        $s = $this->settings();
        $bills = DB::table('subscription_invoices as i')
            ->join('company_subscriptions as cs', 'cs.id', '=', 'i.company_subscription_id')
            ->join('companies as c', 'c.id', '=', 'cs.company_id')
            ->where('i.status', 'paid')
            ->where('i.amount', '>', 0)
            ->whereBetween('i.paid_at', [$from, $to])
            ->whereNotNull('c.owner_id')
            ->when($ownerIds !== null, fn ($q) => $q->whereIn('c.owner_id', $ownerIds))
            ->orderBy('i.paid_at')
            ->get(['i.id', 'i.invoice_number', 'i.amount', 'i.paid_at', 'i.invoice_json', 'c.id as company_id', 'c.name as company', 'c.owner_id']);
        if ($bills->isEmpty()) {
            return collect();
        }

        // Units per property today, for every company billed (occupied or not: the fee is per unit regardless).
        $unitsByProperty = DB::table('units as u')->leftJoin('properties as p', 'p.id', '=', 'u.property_id')
            ->whereIn('u.company_id', $bills->pluck('company_id')->unique())
            ->groupBy('u.company_id', 'u.property_id', 'p.name')
            ->get(['u.company_id', 'u.property_id', 'p.name as property', DB::raw('COUNT(*) as units')])
            ->groupBy('company_id');

        return $bills->flatMap(function ($bill) use ($unitsByProperty, $s) {
            $json = json_decode((string) $bill->invoice_json, true) ?: [];
            $rate = $this->rateFor((int) $bill->owner_id, $s);
            $amount = (float) $bill->amount;
            $parts = $unitsByProperty[$bill->company_id] ?? collect([(object) ['property_id' => null, 'property' => null, 'units' => (int) ($json['unit_count'] ?? 0)]]);
            $totalUnits = max(1, (int) $parts->sum('units'));
            $period = isset($json['billing_start']) ? Carbon::parse($json['billing_start'])->format('M Y') : null;

            return $parts->map(function ($p) use ($bill, $json, $rate, $amount, $totalUnits, $parts, $period) {
                // Split by unit count; a single part takes the whole bill so rounding never loses a shilling.
                $fee = $parts->count() === 1 ? $amount : round($amount * (int) $p->units / $totalUnits, 2);

                return (object) [
                    'invoice_id' => $bill->id, 'invoice_number' => $bill->invoice_number,
                    'paid_at' => Carbon::parse($bill->paid_at), 'period' => $period,
                    'company_id' => $bill->company_id, 'company' => $bill->company, 'owner_id' => (int) $bill->owner_id,
                    'property_id' => $p->property_id, 'property' => $p->property ?? 'Unallocated',
                    'units' => (int) $p->units, 'price_per_unit' => isset($json['price_per_unit']) ? (float) $json['price_per_unit'] : null,
                    'fee' => $fee, 'rate' => $rate, 'commission' => round($fee * $rate / 100, 2),
                ];
            });
        })->values();
    }

    /** Per owner: settled platform fees, commission, units and properties they earned on. */
    public function totalsByOwner(Collection $lines): Collection
    {
        return $lines->groupBy('owner_id')->map(fn ($l) => [
            'fees' => $l->sum('fee'),
            'commission' => $l->sum('commission'),
            'properties' => $l->pluck('property_id')->filter()->unique()->count(),
        ]);
    }

    /** Units and properties each owner manages for scharge today (via the companies they own). */
    public function managed(array $ownerIds): Collection
    {
        if (! $ownerIds) {
            return collect();
        }

        return DB::table('units as u')->join('companies as c', 'c.id', '=', 'u.company_id')
            ->whereIn('c.owner_id', $ownerIds)
            ->selectRaw('c.owner_id, COUNT(*) as units, COUNT(DISTINCT u.property_id) as properties')
            ->groupBy('c.owner_id')->get()->keyBy('owner_id');
    }

    /** Expected monthly platform fee of a deal: the value staff entered, else estimated units × pipeline price. */
    public function dealValue($company, ?array $settings = null): int
    {
        $set = (int) preg_replace('/\D/', '', (string) $company?->profile('deal_value'));
        if ($set > 0) {
            return $set;
        }
        $units = (int) $company?->profile('estimated_units');

        return $units > 0 ? $units * (int) ($settings ?? $this->settings())['pipeline_price_per_unit'] : 0;
    }
}
