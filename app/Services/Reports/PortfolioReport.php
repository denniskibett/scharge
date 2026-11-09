<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numbers for the Reports & analytics page and the dashboard summary.
 *
 * Scope: a list of company ids (null = every company, scharge staff only) and an optional property.
 * Invoices and payments often have no company_id of their own, so the owning company and property come
 * from the invoice first, then its tenancy (same rule as Invoice::owningCompanyId()).
 * All GROUP BYs use select aliases so the queries run on MySQL 8/9 and MariaDB 10.3 (live).
 */
class PortfolioReport
{
    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_3' => 'Last 3 months',
        'last_6' => 'Last 6 months',
        'last_12' => 'Last 12 months',
        'ytd' => 'This year',
        'custom' => 'Custom dates',
    ];

    /** Owning company / property of an invoice: invoice, then tenancy, then the tenancy's unit. */
    private const CO = 'COALESCE(i.company_id, tn.company_id, tu.company_id, tp.company_id)';
    private const PROP = 'COALESCE(i.property_id, tn.property_id, tu.property_id)';

    private const CLOSED_MAINTENANCE = ['completed', 'resolved', 'closed', 'cancelled', 'done'];

    public CarbonImmutable $from;
    public CarbonImmutable $to;

    /** @param array<int>|null $companyIds */
    public function __construct(public ?array $companyIds, public ?int $propertyId = null, string $period = 'last_12', ?string $from = null, ?string $to = null)
    {
        [$this->from, $this->to] = self::range($period, $from, $to);
    }

    /**
     * Companies a user may report on: null = all (scharge sysadmin), the companies an account manager
     * owns, or the user's own company. [] = none.
     *
     * @return array<int>|null
     */
    public static function companyIdsFor($user): ?array
    {
        if ($user->hasRole('sysadmin')) {
            return null;
        }
        if ($user->hasRole('account_manager') && ! $user->company_id) {
            return DB::table('companies')->where('owner_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $user->company_id ? [(int) $user->company_id] : [];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function range(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'this_month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'last_3' => [$now->subMonthsNoOverflow(2)->startOfMonth(), $now->endOfDay()],
            'last_6' => [$now->subMonthsNoOverflow(5)->startOfMonth(), $now->endOfDay()],
            'ytd' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => self::custom($from, $to, $now),
            default => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay()],
        };
    }

    private static function custom(?string $from, ?string $to, CarbonImmutable $now): array
    {
        try {
            $a = $from ? CarbonImmutable::parse($from)->startOfDay() : $now->subMonthsNoOverflow(11)->startOfMonth();
            $b = $to ? CarbonImmutable::parse($to)->endOfDay() : $now->endOfDay();
        } catch (\Throwable) {
            return [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfDay()];
        }
        if ($a->greaterThan($b)) {
            [$a, $b] = [$b->startOfDay(), $a->endOfDay()];
        }

        return [$a->max($b->subYears(3)), $b]; // at most three years at a time
    }

    // ---------------------------------------------------------------- base queries

    private function invoices(): Builder
    {
        return $this->scoped(DB::table('invoices as i'));
    }

    /** Joins an invoice (alias i) to its tenancy and unit, and applies the company/property scope. */
    private function scoped(Builder $q): Builder
    {
        return $q->leftJoin('tenancies as tn', 'tn.id', '=', 'i.tenancy_id')
            ->leftJoin('units as tu', 'tu.id', '=', 'tn.unit_id')
            ->leftJoin('properties as tp', 'tp.id', '=', 'tu.property_id')
            ->when($this->companyIds !== null, fn ($q) => $q->whereIn(DB::raw(self::CO), $this->companyIds ?: [0]))
            ->when($this->propertyId, fn ($q) => $q->where(DB::raw(self::PROP), $this->propertyId));
    }

    private function billedInPeriod(): Builder
    {
        return $this->invoices()->whereBetween('i.billing_month', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    private function payments(): Builder
    {
        return $this->scoped(DB::table('payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id'))
            ->where('p.status', 'completed')->whereNull('p.deleted_at')
            ->whereBetween('p.created_at', [$this->from, $this->to]);
    }

    private function units(): Builder
    {
        return DB::table('units as u')->leftJoin('properties as pr', 'pr.id', '=', 'u.property_id')
            ->where(fn ($q) => $q->whereNull('u.is_active')->orWhere('u.is_active', 1))
            ->when($this->companyIds !== null, fn ($q) => $q->whereIn(DB::raw('COALESCE(u.company_id, pr.company_id)'), $this->companyIds ?: [0]))
            ->when($this->propertyId, fn ($q) => $q->where('u.property_id', $this->propertyId));
    }

    // ---------------------------------------------------------------- figures

    /** Headline numbers for the period (arrears and occupancy are as of today). */
    public function kpis(): array
    {
        $bill = $this->billedInPeriod()->selectRaw('COALESCE(SUM(i.total_amount),0) as billed, COALESCE(SUM(LEAST(i.total_paid, i.total_amount)),0) as settled, COUNT(*) as n')->first();
        $collected = (float) $this->payments()->sum('p.amount');
        $arrears = (float) $this->invoices()->whereRaw('i.total_amount > COALESCE(i.total_paid,0)')->sum(DB::raw('i.total_amount - COALESCE(i.total_paid,0)'));
        $units = $this->units()->selectRaw("COUNT(*) as total, SUM(CASE WHEN u.status = 'occupied' THEN 1 ELSE 0 END) as occupied")->first();
        $expenses = $this->expensesTotal();
        $water = $this->waterTotals();

        $billed = (float) $bill->billed;
        $total = (int) $units->total;

        return [
            'billed' => $billed,
            'invoices' => (int) $bill->n,
            'settled' => (float) $bill->settled,
            'collection_rate' => $billed > 0 ? round($bill->settled / $billed * 100, 1) : null,
            'collected' => $collected,
            'arrears' => $arrears,
            'units' => $total,
            'occupied' => (int) $units->occupied,
            'vacant' => $total - (int) $units->occupied,
            'occupancy' => $total > 0 ? round($units->occupied / $total * 100, 1) : null,
            'expenses' => $expenses,
            'net' => $collected - $expenses,
            'water_units' => $water['consumption'],
            'water_billed' => $water['charge'],
            'maintenance_open' => $this->maintenanceOpen(),
        ];
    }

    /** Billed (by billing month) and collected (by payment date), month by month. */
    public function monthly(): array
    {
        $months = [];
        for ($m = $this->from->startOfMonth(), $i = 0; $m->lessThanOrEqualTo($this->to) && $i < 36; $m = $m->addMonth(), $i++) {
            $months[$m->format('Y-m')] = ['month' => $m->format('M y'), 'billed' => 0.0, 'collected' => 0.0, 'expenses' => 0.0];
        }
        foreach ($this->billedInPeriod()->selectRaw("DATE_FORMAT(i.billing_month, '%Y-%m') as ym, SUM(i.total_amount) as total")->groupBy('ym')->get() as $r) {
            isset($months[$r->ym]) && $months[$r->ym]['billed'] = (float) $r->total;
        }
        foreach ($this->payments()->selectRaw("DATE_FORMAT(p.created_at, '%Y-%m') as ym, SUM(p.amount) as total")->groupBy('ym')->get() as $r) {
            isset($months[$r->ym]) && $months[$r->ym]['collected'] = (float) $r->total;
        }
        if ($this->hasTable('expenses')) {
            foreach ($this->expenses()->selectRaw("DATE_FORMAT(e.expense_date, '%Y-%m') as ym, SUM(e.amount) as total")->groupBy('ym')->get() as $r) {
                isset($months[$r->ym]) && $months[$r->ym]['expenses'] = (float) $r->total;
            }
        }

        return array_values($months);
    }

    /** Money received in the period by how it was paid. */
    public function paymentMethods(): array
    {
        $labels = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'wallet' => 'Wallet credit', 'bank' => 'Bank', 'sharet' => 'Sharet', 'card' => 'Card', 'cheque' => 'Cheque', 'airtel' => 'Airtel Money'];

        return $this->payments()->selectRaw("COALESCE(NULLIF(p.payment_method, ''), 'other') as method, SUM(p.amount) as total, COUNT(*) as n")
            ->groupBy('method')->orderByDesc('total')->get()
            ->map(fn ($r) => ['method' => $labels[$r->method] ?? ucfirst(str_replace('_', ' ', $r->method)), 'total' => (float) $r->total, 'count' => (int) $r->n])
            ->all();
    }

    /** Unpaid balances by how long ago they were billed (as of today). */
    public function aging(): array
    {
        $rows = $this->invoices()->whereRaw('i.total_amount > COALESCE(i.total_paid,0)')
            ->selectRaw("CASE WHEN DATEDIFF(CURDATE(), i.billing_month) <= 30 THEN 'b1'
                WHEN DATEDIFF(CURDATE(), i.billing_month) <= 60 THEN 'b2'
                WHEN DATEDIFF(CURDATE(), i.billing_month) <= 90 THEN 'b3' ELSE 'b4' END as bucket,
                SUM(i.total_amount - COALESCE(i.total_paid,0)) as total, COUNT(*) as n")
            ->groupBy('bucket')->get()->keyBy('bucket');
        $names = ['b1' => '0–30 days', 'b2' => '31–60 days', 'b3' => '61–90 days', 'b4' => 'Over 90 days'];

        return collect($names)->map(fn ($label, $k) => ['bucket' => $label, 'total' => (float) ($rows[$k]->total ?? 0), 'count' => (int) ($rows[$k]->n ?? 0)])->values()->all();
    }

    /** The tenancies owing the most right now. */
    public function topArrears(int $limit = 10): array
    {
        return $this->invoices()->whereRaw('i.total_amount > COALESCE(i.total_paid,0)')->whereNotNull('i.tenancy_id')
            ->leftJoin('tenants as t', 't.id', '=', 'tn.tenant_id')->leftJoin('users as us', 'us.id', '=', 't.user_id')
            ->leftJoin('properties as pr', 'pr.id', '=', DB::raw(self::PROP))
            ->selectRaw("i.tenancy_id as tenancy_id, MAX(COALESCE(NULLIF(TRIM(CONCAT_WS(' ', us.first_name, us.last_name)), ''), us.name)) as tenant,
                MAX(us.phone) as phone, MAX(tu.unit_number) as unit, MAX(pr.name) as property,
                MAX(tn.status) as tenancy_status, SUM(i.total_amount - COALESCE(i.total_paid,0)) as balance, COUNT(*) as invoices, MIN(i.billing_month) as oldest")
            ->groupBy('tenancy_id')->orderByDesc('balance')->limit($limit)->get()
            ->map(fn ($r) => (array) $r + ['balance' => (float) $r->balance])->all();
    }

    /** One row per property: units, occupancy, billed, collected, arrears. */
    public function properties(): array
    {
        $props = DB::table('properties as pr')
            ->when($this->companyIds !== null, fn ($q) => $q->whereIn('pr.company_id', $this->companyIds ?: [0]))
            ->when($this->propertyId, fn ($q) => $q->where('pr.id', $this->propertyId))
            ->leftJoin('companies as c', 'c.id', '=', 'pr.company_id')
            ->orderBy('pr.name')->get(['pr.id', 'pr.name', 'pr.location', 'c.name as company']);

        $units = $this->units()->selectRaw("u.property_id as pid, COUNT(*) as total, SUM(CASE WHEN u.status = 'occupied' THEN 1 ELSE 0 END) as occupied, SUM(CASE WHEN u.status = 'occupied' THEN COALESCE(u.rent_amount,0) ELSE 0 END) as rent_roll")
            ->groupBy('pid')->get()->keyBy('pid');
        $billed = $this->billedInPeriod()->selectRaw(''.self::PROP.' as pid, SUM(i.total_amount) as billed, SUM(LEAST(i.total_paid, i.total_amount)) as settled')
            ->groupBy('pid')->get()->keyBy('pid');
        $collected = $this->payments()->selectRaw(''.self::PROP.' as pid, SUM(p.amount) as total')->groupBy('pid')->get()->keyBy('pid');
        $arrears = $this->invoices()->whereRaw('i.total_amount > COALESCE(i.total_paid,0)')
            ->selectRaw(''.self::PROP.' as pid, SUM(i.total_amount - COALESCE(i.total_paid,0)) as total')->groupBy('pid')->get()->keyBy('pid');

        return $props->map(function ($p) use ($units, $billed, $collected, $arrears) {
            $u = $units[$p->id] ?? null;
            $b = $billed[$p->id] ?? null;
            $total = (int) ($u->total ?? 0);
            $bill = (float) ($b->billed ?? 0);

            return [
                'id' => $p->id, 'name' => $p->name, 'location' => $p->location, 'company' => $p->company,
                'units' => $total, 'occupied' => (int) ($u->occupied ?? 0),
                'occupancy' => $total ? round(($u->occupied ?? 0) / $total * 100, 1) : null,
                'rent_roll' => (float) ($u->rent_roll ?? 0),
                'billed' => $bill,
                'collection_rate' => $bill > 0 ? round(($b->settled ?? 0) / $bill * 100, 1) : null,
                'collected' => (float) ($collected[$p->id]->total ?? 0),
                'arrears' => (float) ($arrears[$p->id]->total ?? 0),
            ];
        })->all();
    }

    /** Expenses in the period by category. */
    public function expenseCategories(): array
    {
        if (! $this->hasTable('expenses')) {
            return [];
        }

        return $this->expenses()->leftJoin('expense_categories as ec', 'ec.id', '=', 'e.expense_category_id')
            ->selectRaw("COALESCE(ec.name, 'Uncategorised') as category, SUM(e.amount) as total")
            ->groupBy('category')->orderByDesc('total')->get()
            ->map(fn ($r) => ['category' => $r->category, 'total' => (float) $r->total])->all();
    }

    /** Everything the page needs, in one array. */
    public function all(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'kpis' => $this->kpis(),
            'monthly' => $this->monthly(),
            'methods' => $this->paymentMethods(),
            'aging' => $this->aging(),
            'top_arrears' => $this->topArrears(),
            'properties' => $this->properties(),
            'expense_categories' => $this->expenseCategories(),
        ];
    }

    // ---------------------------------------------------------------- helpers

    private function expenses(): Builder
    {
        return DB::table('expenses as e')
            ->whereBetween('e.expense_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->where(fn ($q) => $q->whereNull('e.status')->orWhereNotIn('e.status', ['rejected', 'cancelled', 'void']))
            ->when($this->companyIds !== null, fn ($q) => $q->whereIn('e.company_id', $this->companyIds ?: [0]))
            ->when($this->propertyId, fn ($q) => $q->where('e.property_id', $this->propertyId));
    }

    private function expensesTotal(): float
    {
        return $this->hasTable('expenses') ? (float) $this->expenses()->sum('e.amount') : 0.0;
    }

    private function waterTotals(): array
    {
        if (! $this->hasTable('water_readings')) {
            return ['consumption' => 0.0, 'charge' => 0.0];
        }
        $r = DB::table('water_readings as w')->join('units as u', 'u.id', '=', 'w.unit_id')
            ->where(fn ($q) => $q->whereNull('w.is_initial')->orWhere('w.is_initial', 0))
            ->whereBetween('w.reading_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->when($this->companyIds !== null, fn ($q) => $q->whereIn(DB::raw('COALESCE(w.company_id, u.company_id)'), $this->companyIds ?: [0]))
            ->when($this->propertyId, fn ($q) => $q->where('u.property_id', $this->propertyId))
            ->selectRaw('COALESCE(SUM(w.consumption),0) as consumption, COALESCE(SUM(w.charge),0) as charge')->first();

        return ['consumption' => (float) $r->consumption, 'charge' => (float) $r->charge];
    }

    private function maintenanceOpen(): int
    {
        if (! $this->hasTable('maintenances')) {
            return 0;
        }

        return DB::table('maintenances as m')
            ->where(fn ($q) => $q->whereNull('m.status')->orWhereNotIn('m.status', self::CLOSED_MAINTENANCE))
            ->when($this->companyIds !== null, fn ($q) => $q->whereIn('m.company_id', $this->companyIds ?: [0]))
            ->when($this->propertyId, fn ($q) => $q->where('m.property_id', $this->propertyId))
            ->count();
    }

    private function hasTable(string $table): bool
    {
        static $seen = [];

        return $seen[$table] ??= Schema::hasTable($table);
    }
}
