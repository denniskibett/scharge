<?php

namespace App\Modules\CRM\Services;

use App\Models\System;
use App\Modules\CRM\Models\CrmFollowUp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Account-manager commission, paid on cash actually collected (paid subscription invoices), never on bookings:
 *
 *   new business   – new_rate % of net revenue from a company in its first `new_months` months as a customer
 *                    (from the day it was marked Won, else its first subscription start);
 *   revenue share  – share_rate % of net revenue after that, while the manager still owns the account.
 *
 * Net = invoice amount without VAT when invoices include it. Revenue is credited to the company's current owner,
 * so reassigning an account moves its future share with it. Rates live in system.settings.crm_commission
 * (no schema change) and can be overridden per manager.
 */
class CommissionService
{
    public const DEFAULTS = [
        'new_rate' => 10.0,        // % of net revenue, first year
        'share_rate' => 5.0,       // % of net revenue, after the first year
        'new_months' => 12,
        'vat_inclusive' => true,   // invoice amounts include VAT
        'vat_rate' => 16.0,
        'overrides' => [],         // user_id => ['new_rate' => .., 'share_rate' => ..]
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
        $settings['crm_commission'] = array_merge($this->settings(), array_intersect_key($values, self::DEFAULTS));
        $system->forceFill(['settings' => $settings])->save();
    }

    /** @return array{new_rate: float, share_rate: float} */
    public function ratesFor(?int $userId, ?array $settings = null): array
    {
        $s = $settings ?? $this->settings();
        $o = $userId ? (array) ($s['overrides'][$userId] ?? []) : [];

        return [
            'new_rate' => (float) (isset($o['new_rate']) && $o['new_rate'] !== '' ? $o['new_rate'] : $s['new_rate']),
            'share_rate' => (float) (isset($o['share_rate']) && $o['share_rate'] !== '' ? $o['share_rate'] : $s['share_rate']),
        ];
    }

    /**
     * Every paid subscription invoice in the period on a company with an owner, with its commission line.
     *
     * @param  array<int>|null  $ownerIds  limit to these owners (null = all)
     * @return Collection<int, object{invoice_id:int, invoice_number:?string, company_id:int, company:string, owner_id:int, paid_at:Carbon, gross:float, net:float, kind:string, rate:float, commission:float}>
     */
    public function lines(Carbon $from, Carbon $to, ?array $ownerIds = null): Collection
    {
        $s = $this->settings();
        $invoices = DB::table('subscription_invoices as i')
            ->join('company_subscriptions as cs', 'cs.id', '=', 'i.company_subscription_id')
            ->join('companies as c', 'c.id', '=', 'cs.company_id')
            ->where('i.status', 'paid')
            ->whereBetween('i.paid_at', [$from, $to])
            ->whereNotNull('c.owner_id')
            ->when($ownerIds !== null, fn ($q) => $q->whereIn('c.owner_id', $ownerIds))
            ->orderBy('i.paid_at')
            ->get(['i.id as invoice_id', 'i.invoice_number', 'i.amount', 'i.paid_at', 'c.id as company_id', 'c.name as company', 'c.owner_id']);
        if ($invoices->isEmpty()) {
            return collect();
        }

        $since = $this->customerSince($invoices->pluck('company_id')->unique()->all());

        return $invoices->map(function ($inv) use ($s, $since) {
            $paidAt = Carbon::parse($inv->paid_at);
            $gross = (float) $inv->amount;
            $net = $s['vat_inclusive'] ? round($gross / (1 + $s['vat_rate'] / 100), 2) : $gross;
            $start = $since[$inv->company_id] ?? $paidAt;
            $kind = $paidAt->lt($start->copy()->addMonthsNoOverflow((int) $s['new_months'])) ? 'new' : 'share';
            $rates = $this->ratesFor((int) $inv->owner_id, $s);
            $rate = $kind === 'new' ? $rates['new_rate'] : $rates['share_rate'];

            return (object) [
                'invoice_id' => $inv->invoice_id, 'invoice_number' => $inv->invoice_number,
                'company_id' => $inv->company_id, 'company' => $inv->company, 'owner_id' => (int) $inv->owner_id,
                'paid_at' => $paidAt, 'gross' => $gross, 'net' => $net, 'kind' => $kind, 'rate' => $rate,
                'commission' => round($net * $rate / 100, 2),
            ];
        });
    }

    /** Per owner: revenue, new-business commission, revenue share, total. */
    public function totalsByOwner(Collection $lines): Collection
    {
        return $lines->groupBy('owner_id')->map(fn ($l) => [
            'revenue' => $l->sum('net'),
            'new' => $l->where('kind', 'new')->sum('commission'),
            'share' => $l->where('kind', 'share')->sum('commission'),
            'total' => $l->sum('commission'),
        ]);
    }

    /** When each company became a customer: first time it was marked Won, else its first subscription start. */
    private function customerSince(array $companyIds): array
    {
        $won = CrmFollowUp::where('type', 'stage')->whereIn('company_id', $companyIds)->where('details->to', 'won')
            ->selectRaw('company_id, MIN(done_at) as at')->groupBy('company_id')->pluck('at', 'company_id');
        $subs = DB::table('company_subscriptions')->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, MIN(starts_at) as at')->groupBy('company_id')->pluck('at', 'company_id');

        return collect($companyIds)->mapWithKeys(fn ($id) => [$id => ($at = $won[$id] ?? $subs[$id] ?? null) ? Carbon::parse($at) : null])
            ->filter()->all();
    }
}
