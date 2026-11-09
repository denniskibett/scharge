<?php

namespace App\Support;

use App\Services\Reports\PortfolioReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The stat cards at the top of each sidebar page (resources/views/partials/card/*.blade.php).
 * Figures are scoped like Reports: own company, an account manager's companies, or everything for sysadmin.
 * Each card: label, value, icon (App\Support\Icons), tone, hint, and an optional change vs last month.
 */
class PageCards
{
    private ?array $ids;
    private CarbonImmutable $now;
    private CarbonImmutable $monthStart;
    private CarbonImmutable $lastStart;
    private CarbonImmutable $lastEnd;

    public function __construct($user)
    {
        $this->ids = $user ? PortfolioReport::companyIdsFor($user) : [];
        $this->now = CarbonImmutable::now();
        $this->monthStart = $this->now->startOfMonth();
        $this->lastStart = $this->now->subMonthNoOverflow()->startOfMonth();
        $this->lastEnd = $this->now->subMonthNoOverflow()->endOfMonth();
    }

    /** Cards for a page key, cached for two minutes per scope. */
    public static function for(string $page): array
    {
        $self = new self(auth()->user());
        $method = 'page'.str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $page)));
        if (! method_exists($self, $method)) {
            return [];
        }
        $key = 'page-cards:'.$page.':'.($self->ids === null ? 'all' : implode(',', $self->ids)).':'.$self->now->format('YmdH').intdiv((int) $self->now->format('i'), 2);

        try {
            return Cache::remember($key, 120, fn () => $self->{$method}());
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    // ------------------------------------------------------------------ helpers

    private function card(string $label, $value, string $icon, string $tone = 'brand', ?string $hint = null, ?float $delta = null, bool $upIsGood = true): array
    {
        return compact('label', 'value', 'icon', 'tone', 'hint', 'delta', 'upIsGood');
    }

    private function scope(Builder $q, $column): Builder
    {
        return $this->ids === null ? $q : $q->whereIn($column, $this->ids ?: [0]);
    }

    private function has(string $table): bool
    {
        static $seen = [];

        return $seen[$table] ??= Schema::hasTable($table);
    }

    private static function change(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    private static function kes(float $v): string
    {
        return 'KES '.($v >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 2), '0'), '.').'M' : number_format($v));
    }

    private static function n($v): string
    {
        return number_format((float) $v);
    }

    /** this month vs last month for a query + date column (+ optional sum column). */
    private function mom(callable $base, string $dateCol, ?string $sumCol = null): array
    {
        $agg = fn (Builder $q) => (float) ($sumCol ? $q->sum($sumCol) : $q->count());
        $now = $agg($base()->where($dateCol, '>=', $this->monthStart));
        $before = $agg($base()->whereBetween($dateCol, [$this->lastStart, $this->lastEnd]));

        return [$now, self::change($now, $before)];
    }

    private function units(): Builder
    {
        return $this->scope(DB::table('units as u')->leftJoin('properties as pr', 'pr.id', '=', 'u.property_id')
            ->where(fn ($q) => $q->whereNull('u.is_active')->orWhere('u.is_active', 1)), DB::raw('COALESCE(u.company_id, pr.company_id)'));
    }

    private function invoices(): Builder
    {
        return $this->scope(DB::table('invoices as i')->leftJoin('tenancies as tn', 'tn.id', '=', 'i.tenancy_id')
            ->leftJoin('units as tu', 'tu.id', '=', 'tn.unit_id')->leftJoin('properties as tp', 'tp.id', '=', 'tu.property_id'),
            DB::raw('COALESCE(i.company_id, tn.company_id, tu.company_id, tp.company_id)'));
    }

    private function payments(): Builder
    {
        return $this->scope(DB::table('payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->leftJoin('tenancies as tn', 'tn.id', '=', 'i.tenancy_id')->leftJoin('units as tu', 'tu.id', '=', 'tn.unit_id')
            ->leftJoin('properties as tp', 'tp.id', '=', 'tu.property_id')
            ->where('p.status', 'completed')->whereNull('p.deleted_at'), DB::raw('COALESCE(i.company_id, tn.company_id, tu.company_id, tp.company_id)'));
    }

    private function smsLogs(): Builder
    {
        $q = DB::table('sms_logs as l')->leftJoin('sms_campaigns as c', 'c.id', '=', 'l.campaign_id')->leftJoin('users as cu', 'cu.id', '=', 'c.created_by');

        return $this->scope($q, 'cu.company_id');
    }

    private function team(): Builder
    {
        $q = DB::table('users as u')->whereExists(fn ($e) => $e->from('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->whereColumn('mr.model_id', 'u.id')->whereNotIn('r.name', ['tenant', 'guest', 'bnb_host']));

        return $this->ids === null ? $q : $q->whereIn('u.company_id', $this->ids ?: [0]);
    }

    // ------------------------------------------------------------------ pages: run the properties

    private function pageProperties(): array
    {
        $props = $this->scope(DB::table('properties as pr'), 'pr.company_id')->count();
        $u = $this->units()->selectRaw("COUNT(*) as total, SUM(CASE WHEN u.status='occupied' THEN 1 ELSE 0 END) as occ, SUM(CASE WHEN u.status='occupied' THEN COALESCE(u.rent_amount,0) ELSE 0 END) as roll")->first();

        return [
            $this->card('Properties', self::n($props), 'building-office-2', 'brand', 'Buildings you manage'),
            $this->card('Units', self::n($u->total), 'home-modern', 'sky', self::n($u->occ).' let · '.self::n($u->total - $u->occ).' vacant'),
            $this->card('Occupancy', $u->total ? round($u->occ / $u->total * 100, 1).'%' : '—', 'chart-pie', 'green', 'Units let today'),
            $this->card('Monthly rent roll', self::kes((float) $u->roll), 'banknotes', 'amber', 'Rent on let units'),
        ];
    }

    private function pageUnits(): array
    {
        $u = $this->units()->selectRaw("COUNT(*) as total, SUM(CASE WHEN u.status='occupied' THEN 1 ELSE 0 END) as occ, AVG(NULLIF(u.rent_amount,0)) as avg_rent, SUM(CASE WHEN u.stay_type IN ('short','bnb','short_stay') THEN 1 ELSE 0 END) as bnb")->first();

        return [
            $this->card('Total units', self::n($u->total), 'home-modern', 'brand', 'Across all properties'),
            $this->card('Occupied', self::n($u->occ), 'user-group', 'green', $u->total ? round($u->occ / $u->total * 100, 1).'% occupancy' : null),
            $this->card('Vacant', self::n($u->total - $u->occ), 'key', 'amber', 'Ready to let'),
            $this->card('Average rent', self::kes((float) $u->avg_rent), 'banknotes', 'sky', 'Per unit per month'),
        ];
    }

    private function tenancies(): Builder
    {
        return $this->scope(DB::table('tenancies as tn')->leftJoin('units as tu', 'tu.id', '=', 'tn.unit_id'), DB::raw('COALESCE(tn.company_id, tu.company_id)'));
    }

    private function pageTenants(): array
    {
        $active = $this->tenancies()->where('tn.status', 'active')->distinct()->count('tn.tenant_id');
        [$new, $newD] = $this->mom(fn () => $this->tenancies(), 'tn.created_at');
        $out = $this->tenancies()->where('tn.status', 'ended')->where('tn.updated_at', '>=', $this->monthStart)->count();
        $owing = $this->invoices()->whereRaw('i.total_amount > COALESCE(i.total_paid,0)')->where('tn.status', 'active')->distinct()->count('tn.tenant_id');

        return [
            $this->card('Active tenants', self::n($active), 'users', 'brand', 'Living in a unit now'),
            $this->card('Moved in', self::n($new), 'arrow-right', 'green', 'This month', $newD),
            $this->card('Moved out', self::n($out), 'arrow-up-tray', 'amber', 'This month', null, false),
            $this->card('Owing rent', self::n($owing), 'exclamation-triangle', 'red', 'Active tenants with a balance'),
        ];
    }

    private function pageTenancies(): array
    {
        $active = $this->tenancies()->where('tn.status', 'active')->count();
        [$started, $sD] = $this->mom(fn () => $this->tenancies(), 'tn.move_in_date');
        $ended = $this->tenancies()->where('tn.status', 'ended')->count();
        $total = $this->tenancies()->count();

        return [
            $this->card('Active tenancies', self::n($active), 'clipboard-document-list', 'brand', 'Running leases'),
            $this->card('Started', self::n($started), 'sparkles', 'green', 'This month', $sD),
            $this->card('Ended', self::n($ended), 'arrow-up-tray', 'amber', 'All time'),
            $this->card('All tenancies', self::n($total), 'document-text', 'sky', 'Including past ones'),
        ];
    }

    private function water(): Builder
    {
        return $this->scope(DB::table('water_readings as w')->join('units as u', 'u.id', '=', 'w.unit_id')
            ->where(fn ($q) => $q->whereNull('w.is_initial')->orWhere('w.is_initial', 0)), DB::raw('COALESCE(w.company_id, u.company_id)'));
    }

    private function pageWater(): array
    {
        [$reads, $rD] = $this->mom(fn () => $this->water(), 'w.reading_date');
        [$cons, $cD] = $this->mom(fn () => $this->water(), 'w.reading_date', 'w.consumption');
        [$charge, $chD] = $this->mom(fn () => $this->water(), 'w.reading_date', 'w.charge');
        $occupied = $this->units()->where('u.status', 'occupied')->count();
        $read = $this->water()->where('w.reading_date', '>=', $this->monthStart)->distinct()->count('w.unit_id');

        return [
            $this->card('Readings', self::n($reads), 'pencil-square', 'brand', 'This month', $rD),
            $this->card('Water used', self::n($cons).' units', 'beaker', 'sky', 'This month', $cD, false),
            $this->card('Water charged', self::kes($charge), 'banknotes', 'green', 'This month', $chD),
            $this->card('Still to read', self::n(max(0, $occupied - $read)), 'clock', 'amber', 'Occupied units not read this month'),
        ];
    }

    private function pageWaterReports(): array
    {
        return $this->pageWater();
    }

    private function pageMaintenance(): array
    {
        $base = fn () => $this->scope(DB::table('maintenances as m'), 'm.company_id');
        $closed = ['completed', 'resolved', 'closed', 'cancelled', 'done'];
        $open = $base()->where(fn ($q) => $q->whereNull('m.status')->orWhereNotIn('m.status', $closed))->count();
        $progress = $base()->whereIn('m.status', ['in_progress', 'assigned', 'scheduled'])->count();
        [$done, $dD] = $this->mom(fn () => $base()->whereIn('m.status', ['completed', 'resolved', 'closed', 'done']), 'm.updated_at');
        [$cost, $cD] = $this->mom($base, 'm.created_at', 'm.cost');

        return [
            $this->card('Open requests', self::n($open), 'wrench-screwdriver', 'amber', 'Waiting or being fixed'),
            $this->card('In progress', self::n($progress), 'clock', 'brand', 'Assigned or scheduled'),
            $this->card('Completed', self::n($done), 'check-circle', 'green', 'This month', $dD),
            $this->card('Repair costs', self::kes($cost), 'banknotes', 'red', 'This month', $cD, false),
        ];
    }

    private function pageSecurity(): array
    {
        $base = fn () => $this->scope(DB::table('security as s'), 's.company_id');
        $today = $base()->where('s.access_time', '>=', $this->now->startOfDay())->count();
        $week = $base()->where('s.access_time', '>=', $this->now->startOfWeek())->count();
        $inside = $base()->whereNull('s.exit_time')->where('s.access_time', '>=', $this->now->subDay())->count();
        [$month, $mD] = $this->mom($base, 's.access_time');

        return [
            $this->card('Visitors today', self::n($today), 'user', 'brand', $this->now->format('D j M')),
            $this->card('Still inside', self::n($inside), 'shield-check', 'amber', 'Checked in, not out'),
            $this->card('This week', self::n($week), 'calendar-days', 'sky', 'Since Monday'),
            $this->card('This month', self::n($month), 'chart-bar', 'green', 'Gate entries', $mD),
        ];
    }

    // ------------------------------------------------------------------ pages: money

    private function pageInvoices(): array
    {
        [$billed, $bD] = $this->mom(fn () => $this->invoices(), 'i.billing_month', 'i.total_amount');
        $unpaid = $this->invoices()->whereRaw('i.total_amount > COALESCE(i.total_paid,0)');
        $count = (clone $unpaid)->count();
        $owed = (float) (clone $unpaid)->sum(DB::raw('i.total_amount - COALESCE(i.total_paid,0)'));
        $paid = $this->invoices()->where('i.status', 'paid')->where('i.updated_at', '>=', $this->monthStart)->count();

        return [
            $this->card('Billed', self::kes($billed), 'document-text', 'brand', 'This month', $bD),
            $this->card('Unpaid invoices', self::n($count), 'exclamation-triangle', 'amber', 'Not fully paid', null, false),
            $this->card('Outstanding', self::kes($owed), 'banknotes', 'red', 'Owed on all invoices'),
            $this->card('Paid', self::n($paid), 'check-circle', 'green', 'Settled this month'),
        ];
    }

    private function pagePayments(): array
    {
        [$sum, $sD] = $this->mom(fn () => $this->payments(), 'p.created_at', 'p.amount');
        [$cnt, $cD] = $this->mom(fn () => $this->payments(), 'p.created_at');
        $mpesa = (float) $this->payments()->where('p.created_at', '>=', $this->monthStart)->whereIn('p.payment_method', ['mpesa', 'mpesa_paybill', 'sharet'])->sum('p.amount');

        return [
            $this->card('Collected', self::kes($sum), 'banknotes', 'green', 'This month', $sD),
            $this->card('Payments', self::n($cnt), 'credit-card', 'brand', 'This month', $cD),
            $this->card('Average payment', self::kes($cnt ? $sum / $cnt : 0), 'chart-bar', 'sky', 'This month'),
            $this->card('Paid by M-Pesa', $sum > 0 ? round($mpesa / $sum * 100).'%' : '—', 'device-phone-mobile', 'amber', 'Share of this month'),
        ];
    }

    private function expenses(): Builder
    {
        return $this->scope(DB::table('expenses as e'), 'e.company_id');
    }

    private function pageExpenses(): array
    {
        [$spent, $sD] = $this->mom(fn () => $this->expenses(), 'e.expense_date', 'e.amount');
        $pending = $this->expenses()->where('e.status', 'pending')->count();
        $ytd = (float) $this->expenses()->where('e.expense_date', '>=', $this->now->startOfYear())->sum('e.amount');
        $payees = $this->expenses()->whereNotNull('e.payee_id')->distinct()->count('e.payee_id');

        return [
            $this->card('Spent', self::kes($spent), 'receipt-percent', 'red', 'This month', $sD, false),
            $this->card('Awaiting approval', self::n($pending), 'clock', 'amber', 'Pending expenses'),
            $this->card('Year to date', self::kes($ytd), 'chart-bar', 'brand', 'Since 1 January'),
            $this->card('Payees used', self::n($payees), 'user-circle', 'sky', 'Paid at least once'),
        ];
    }

    private function pagePayees(): array
    {
        $all = $this->has('payees') ? DB::table('payees')->count() : 0;
        $paidMonth = $this->expenses()->where('e.expense_date', '>=', $this->monthStart)->whereNotNull('e.payee_id')->distinct()->count('e.payee_id');
        [$sum, $sD] = $this->mom(fn () => $this->expenses()->whereNotNull('e.payee_id'), 'e.expense_date', 'e.amount');
        $top = $this->expenses()->whereNotNull('e.payee_id')->join('payees as py', 'py.id', '=', 'e.payee_id')
            ->selectRaw('py.name as name, SUM(e.amount) as total')->groupBy('name')->orderByDesc('total')->first();

        return [
            $this->card('Payees', self::n($all), 'user-circle', 'brand', 'Suppliers and service providers'),
            $this->card('Paid this month', self::n($paidMonth), 'check-circle', 'green', 'Payees with a payment'),
            $this->card('Paid out', self::kes($sum), 'banknotes', 'red', 'This month', $sD, false),
            $this->card('Biggest payee', $top->name ?? '—', 'star', 'amber', $top ? self::kes((float) $top->total).' all time' : 'No payments yet'),
        ];
    }

    // ------------------------------------------------------------------ pages: tenant messages

    private function pageSms(): array
    {
        [$sent, $sD] = $this->mom(fn () => $this->smsLogs()->whereIn('l.status', ['sent', 'delivered']), 'l.created_at');
        $monthAll = $this->smsLogs()->where('l.created_at', '>=', $this->monthStart)->count();
        $failed = $this->smsLogs()->where('l.created_at', '>=', $this->monthStart)->where('l.status', 'failed')->count();
        $templates = $this->has('sms_templates') ? $this->scope(DB::table('sms_templates as t'), 't.company_id')->count() : 0;

        return [
            $this->card('Messages sent', self::n($sent), 'paper-airplane', 'brand', 'This month', $sD),
            $this->card('Success rate', $monthAll ? round(($monthAll - $failed) / $monthAll * 100, 1).'%' : '—', 'check-circle', 'green', 'Sent without errors'),
            $this->card('Failed', self::n($failed), 'x-circle', 'red', 'This month', null, false),
            $this->card('Templates', self::n($templates), 'document-text', 'sky', 'Saved messages'),
        ];
    }

    private function pageSmsHistory(): array
    {
        return $this->pageSms();
    }

    private function pageSmsTemplates(): array
    {
        return $this->pageSms();
    }

    private function pageSmsSettings(): array
    {
        return $this->pageSms();
    }

    // ------------------------------------------------------------------ pages: sales & accounts (staff)

    private function companies(): Builder
    {
        $q = DB::table('companies as c');

        return $this->ids === null ? $q : $q->whereIn('c.id', $this->ids ?: [0]);
    }

    private function followUps(): Builder
    {
        $q = DB::table('crm_follow_ups as f');

        return $this->ids === null ? $q : $q->whereIn('f.company_id', $this->ids ?: [0]);
    }

    private function pageCrm(): array
    {
        $prospects = $this->companies()->where('c.lifecycle', 'prospect')->count();
        $customers = $this->companies()->where(fn ($q) => $q->whereNull('c.lifecycle')->orWhere('c.lifecycle', '!=', 'prospect'))->count();
        [$won, $wD] = $this->mom(fn () => $this->companies()->where(fn ($q) => $q->whereNull('c.lifecycle')->orWhere('c.lifecycle', '!=', 'prospect')), 'c.created_at');
        $due = $this->followUps()->where('f.status', 'open')->where('f.due_at', '<=', $this->now->endOfDay())->count();

        return [
            $this->card('Prospects', self::n($prospects), 'funnel', 'brand', 'In the pipeline'),
            $this->card('Customers', self::n($customers), 'building-office', 'green', 'Signed companies'),
            $this->card('Won', self::n($won), 'sparkles', 'amber', 'This month', $wD),
            $this->card('Follow-ups due', self::n($due), 'clock', 'red', 'Today or overdue', null, false),
        ];
    }

    private function pageCrmFollowUps(): array
    {
        $open = $this->followUps()->where('f.status', 'open');
        $overdue = (clone $open)->where('f.due_at', '<', $this->now->startOfDay())->count();
        $today = (clone $open)->whereBetween('f.due_at', [$this->now->startOfDay(), $this->now->endOfDay()])->count();
        [$done, $dD] = $this->mom(fn () => $this->followUps()->where('f.status', 'done')->where('f.type', '!=', 'import'), 'f.done_at');

        return [
            $this->card('Open', self::n((clone $open)->count()), 'clipboard-document-list', 'brand', 'Still to do'),
            $this->card('Due today', self::n($today), 'calendar-days', 'amber', $this->now->format('D j M')),
            $this->card('Overdue', self::n($overdue), 'exclamation-triangle', 'red', 'Past their date', null, false),
            $this->card('Done', self::n($done), 'check-circle', 'green', 'This month', $dD),
        ];
    }

    private function pageCrmRequests(): array
    {
        $req = fn () => $this->followUps()->whereIn('f.type', ['demo', 'api_keys', 'listing_alert', 'request', 'callback']);
        [$new, $nD] = $this->mom($req, 'f.created_at');

        return [
            $this->card('Open requests', self::n($req()->where('f.status', 'open')->count()), 'inbox-arrow-down', 'brand', 'Waiting for a reply'),
            $this->card('New', self::n($new), 'sparkles', 'green', 'This month', $nD),
            $this->card('Demos asked', self::n($req()->where('f.type', 'demo')->count()), 'calendar-days', 'amber', 'All time'),
            $this->card('Handled', self::n($req()->where('f.status', 'done')->count()), 'check-circle', 'sky', 'Closed requests'),
        ];
    }

    private function pageCrmWaitlist(): array
    {
        $base = fn () => DB::table('waitlist_entries as w');
        [$new, $nD] = $this->mom($base, 'w.created_at');

        return [
            $this->card('On the waitlist', self::n($base()->count()), 'queue-list', 'brand', 'All sign-ups'),
            $this->card('Joined', self::n($new), 'sparkles', 'green', 'This month', $nD),
            $this->card('Waiting', self::n($base()->where(fn ($q) => $q->whereNull('w.status')->orWhereIn('w.status', ['waiting', 'pending', 'new']))->count()), 'clock', 'amber', 'Not contacted yet'),
            $this->card('Converted', self::n($base()->whereNotNull('w.company_id')->count()), 'check-circle', 'sky', 'Became a company'),
        ];
    }

    private function pageCrmFeedback(): array
    {
        $fb = fn () => $this->followUps()->where('f.type', 'feedback');
        [$new, $nD] = $this->mom($fb, 'f.created_at');
        $avg = $fb()->whereNotNull('f.score')->avg('f.score');

        return [
            $this->card('Feedback', self::n($fb()->count()), 'star', 'brand', 'All responses'),
            $this->card('New', self::n($new), 'sparkles', 'green', 'This month', $nD),
            $this->card('Average score', $avg ? round($avg, 1).' / 10' : '—', 'chart-bar', 'amber', 'From scored replies'),
            $this->card('Open', self::n($fb()->where('f.status', 'open')->count()), 'chat-bubble-left-ellipsis', 'sky', 'Needs a reply'),
        ];
    }

    private function pageCompanies(): array
    {
        $customers = fn () => $this->companies()->where(fn ($q) => $q->whereNull('c.lifecycle')->orWhere('c.lifecycle', '!=', 'prospect'));
        [$new, $nD] = $this->mom($customers, 'c.created_at');
        $active = $customers()->whereIn('c.subscription_status', ['active', 'trial', 'trialing'])->count();
        $units = $this->units()->count();

        return [
            $this->card('Companies', self::n($customers()->count()), 'building-office', 'brand', 'Customers on scharge'),
            $this->card('Subscribed', self::n($active), 'check-circle', 'green', 'Active or on trial'),
            $this->card('Joined', self::n($new), 'sparkles', 'amber', 'This month', $nD),
            $this->card('Units managed', self::n($units), 'home-modern', 'sky', 'Across their properties'),
        ];
    }

    private function pagePropertyClaims(): array
    {
        $base = fn () => $this->has('property_claims') ? $this->scope(DB::table('property_claims as pc'), 'pc.company_id') : DB::table('properties')->whereRaw('1=0');

        return [
            $this->card('Pending', self::n($base()->where('pc.status', 'pending')->count()), 'clock', 'amber', 'Waiting for review'),
            $this->card('Approved', self::n($base()->where('pc.status', 'approved')->count()), 'check-circle', 'green', 'Management confirmed'),
            $this->card('Rejected', self::n($base()->where('pc.status', 'rejected')->count()), 'x-circle', 'red', 'Not approved'),
            $this->card('Shared buildings', self::n(DB::table('properties')->where('claim_status', '!=', 'managed')->count()), 'building-office-2', 'brand', 'With hosts but no manager yet'),
        ];
    }

    // ------------------------------------------------------------------ pages: setup

    private function pageIntegrations(): array
    {
        $user = auth()->user();
        $cid = $user && ! $user->hasRole('sysadmin') ? $user->company_id : null;
        $rows = $this->has('company_integrations')
            ? DB::table('company_integrations')->when($cid, fn ($q) => $q->where('company_id', $cid), fn ($q) => $q->whereNull('company_id'))->get()
            : collect();
        $available = collect(config('integrations'))->filter(fn ($p) => $p['scope'] === ($cid ? 'company' : 'platform'))->count();

        return [
            $this->card('Available', self::n($available), 'puzzle-piece', 'brand', 'Integrations you can set up'),
            $this->card('Connected', self::n($rows->count()), 'check-circle', 'green', 'Settings saved'),
            $this->card('Switched on', self::n($rows->where('enabled', 1)->count()), 'signal', 'sky', 'In use now'),
            $this->card('Last test failed', self::n($rows->where('last_test_ok', 0)->whereNotNull('last_tested_at')->count()), 'exclamation-triangle', 'red', 'Check these', null, false),
        ];
    }

    private function pageApiKeys(): array
    {
        $base = fn () => $this->has('api_keys') ? $this->scope(DB::table('api_keys as k'), 'k.company_id') : DB::table('users')->whereRaw('1=0');

        return [
            $this->card('Active keys', self::n($base()->where('k.enabled', 1)->count()), 'key', 'brand', 'Switched on'),
            $this->card('Live keys', self::n($base()->where('k.enabled', 1)->where('k.environment', 'live')->count()), 'signal', 'green', 'Production'),
            $this->card('Used this week', self::n($base()->where('k.last_used_at', '>=', $this->now->subDays(7))->count()), 'clock', 'sky', 'Called the API'),
            $this->card('Revoked', self::n($base()->where('k.enabled', 0)->count()), 'no-symbol', 'red', 'Switched off', null, false),
        ];
    }

    private function pageTeam(): array
    {
        $total = $this->team()->count();
        $invited = $this->team()->whereIn('u.email', DB::table('password_reset_tokens')->select('email'))->count();
        $off = $this->team()->where('u.status', 1)->count();
        $active = max(0, $total - $invited - $off);
        [$new, $nD] = $this->mom(fn () => $this->team(), 'u.created_at');
        $roles = DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->join('users as u', 'u.id', '=', 'mr.model_id')
            ->whereNotIn('r.name', ['tenant', 'guest', 'bnb_host'])->when($this->ids !== null, fn ($q) => $q->whereIn('u.company_id', $this->ids ?: [0]))
            ->distinct()->count('r.id');

        return [
            $this->card('Team members', self::n($total), 'user-group', 'brand', 'Staff with a role'),
            $this->card('Active', self::n($active), 'check-circle', 'green', 'Can sign in'),
            $this->card('Invited', self::n($invited), 'envelope', 'amber', 'Password not set yet'),
            $this->card('Joined', self::n($new), 'sparkles', 'sky', 'This month', $nD),
            $this->card('Roles in use', self::n($roles), 'lock-closed', 'brand', 'Across the team'),
        ];
    }

    private function pageStaff(): array
    {
        return $this->pageTeam();
    }

    private function pageRoles(): array
    {
        $roles = DB::table('roles')->count();
        $builtIn = count(RoleDescriptions::DESCRIPTIONS);
        $people = DB::table('model_has_roles')->distinct()->count('model_id');
        $perms = DB::table('permissions')->count();

        return [
            $this->card('Roles', self::n($roles), 'lock-closed', 'brand', 'Built-in and custom'),
            $this->card('Custom roles', self::n(max(0, $roles - DB::table('roles')->whereIn('name', array_keys(RoleDescriptions::DESCRIPTIONS))->count())), 'sparkles', 'green', 'Made by you'),
            $this->card('People with a role', self::n($people), 'user-group', 'sky', 'All users'),
            $this->card('Permissions', self::n($perms), 'shield-check', 'amber', 'View, manage and delete per area'),
        ];
    }

    private function pageSystem(): array
    {
        return [
            $this->card('Companies', self::n(DB::table('companies')->where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->count()), 'building-office', 'brand', 'Customers'),
            $this->card('Users', self::n(DB::table('users')->count()), 'users', 'green', 'All accounts'),
            $this->card('Properties', self::n(DB::table('properties')->count()), 'building-office-2', 'sky', 'Buildings'),
            $this->card('Units', self::n(DB::table('units')->count()), 'home-modern', 'amber', 'All units'),
        ];
    }
}
