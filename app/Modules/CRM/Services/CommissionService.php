<?php

namespace App\Modules\CRM\Services;

use App\Models\System;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Account-manager commission = a share of scharge's platform fees on the properties they manage for us.
 *
 * What counts and what doesn't:
 *  - Platform fees: scharge's cut of rent collected through Sharet, taken at each payout sweep (sharet_sweeps.fee).
 *    Only sweeps that were actually paid out count. This is the commission base.
 *  - Subscriptions: scharge's own platform revenue. No account-manager commission.
 *  - Tenant deposits / property sales: commission paid by owners to their agents (units.commission_rate).
 *    Not scharge money, so never part of this.
 *
 * A sweep belongs to a company; its fee is credited to the company's current owner (companies.owner_id) and split
 * across that company's properties pro rata to the rent collected for each, so statements show which property
 * earned what. Rates live in system.settings.crm_commission (no schema change), with per-person overrides.
 */
class CommissionService
{
    public const DEFAULTS = [
        'rate' => 20.0,        // % of platform fees on managed properties
        'overrides' => [],     // user_id => ['rate' => ..]
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
        // Only the current keys are kept, so older plan shapes are dropped on the next save.
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
     * One line per paid sweep per property, for companies with an owner.
     *
     * @param  array<int>|null  $ownerIds  limit to these owners (null = all)
     * @return Collection<int, object{sweep_id:int, swept_at:Carbon, company_id:int, company:string, owner_id:int, property_id:?int, property:string, collected:float, fee:float, rate:float, commission:float}>
     */
    public function lines(Carbon $from, Carbon $to, ?array $ownerIds = null): Collection
    {
        $s = $this->settings();
        $sweeps = DB::table('sharet_sweeps as sw')
            ->join('companies as c', 'c.id', '=', 'sw.company_id')
            ->where('sw.status', 'paid')
            ->where('sw.fee', '>', 0)
            ->whereBetween('sw.created_at', [$from, $to])
            ->whereNotNull('c.owner_id')
            ->when($ownerIds !== null, fn ($q) => $q->whereIn('c.owner_id', $ownerIds))
            ->orderBy('sw.created_at')
            ->get(['sw.id', 'sw.created_at', 'sw.gross', 'sw.fee', 'c.id as company_id', 'c.name as company', 'c.owner_id']);
        if ($sweeps->isEmpty()) {
            return collect();
        }

        // Rent collected per property inside each sweep.
        $byProperty = DB::table('sharet_payment_requests as r')
            ->leftJoin('invoices as i', 'i.id', '=', 'r.invoice_id')
            // invoices.property_id is often empty; the tenancy (or its unit) knows the property.
            ->leftJoin('tenancies as t', 't.id', '=', 'i.tenancy_id')
            ->leftJoin('units as u', 'u.id', '=', 't.unit_id')
            ->leftJoin('properties as p', 'p.id', '=', DB::raw('COALESCE(i.property_id, t.property_id, u.property_id)'))
            ->whereIn('r.sweep_id', $sweeps->pluck('id'))
            ->groupBy('r.sweep_id', 'p.id', 'p.name')
            ->get(['r.sweep_id', 'p.id as property_id', 'p.name as property', DB::raw('SUM(r.amount) as collected')])
            ->groupBy('sweep_id');

        return $sweeps->flatMap(function ($sw) use ($byProperty, $s) {
            $rate = $this->rateFor((int) $sw->owner_id, $s);
            $parts = $byProperty[$sw->id] ?? collect([(object) ['property_id' => null, 'property' => null, 'collected' => $sw->gross]]);
            $total = (float) $parts->sum('collected') ?: (float) $sw->gross ?: 1;

            return $parts->map(function ($p) use ($sw, $rate, $total) {
                $fee = round((float) $sw->fee * (float) $p->collected / $total, 2);

                return (object) [
                    'sweep_id' => $sw->id, 'swept_at' => Carbon::parse($sw->created_at),
                    'company_id' => $sw->company_id, 'company' => $sw->company, 'owner_id' => (int) $sw->owner_id,
                    'property_id' => $p->property_id, 'property' => $p->property ?? 'Unallocated',
                    'collected' => (float) $p->collected, 'fee' => $fee, 'rate' => $rate,
                    'commission' => round($fee * $rate / 100, 2),
                ];
            });
        })->values();
    }

    /** Per owner: rent collected, platform fees, commission, and how many properties earned. */
    public function totalsByOwner(Collection $lines): Collection
    {
        return $lines->groupBy('owner_id')->map(fn ($l) => [
            'collected' => $l->sum('collected'),
            'fees' => $l->sum('fee'),
            'commission' => $l->sum('commission'),
            'properties' => $l->pluck('property_id')->filter()->unique()->count(),
        ]);
    }

    /** Properties each owner manages for scharge today (via the companies they own). */
    public function managedProperties(array $ownerIds): Collection
    {
        return DB::table('properties as p')->join('companies as c', 'c.id', '=', 'p.company_id')
            ->whereIn('c.owner_id', $ownerIds)
            ->selectRaw('c.owner_id, COUNT(*) as total')->groupBy('c.owner_id')->pluck('total', 'owner_id');
    }
}
