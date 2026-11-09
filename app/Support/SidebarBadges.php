<?php

namespace App\Support;

use App\Http\Middleware\CheckAccess;
use App\Services\Reports\PortfolioReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What is waiting for this person, per sidebar entry (keys = the menu items' `page` in partials/sidebar).
 * Role-specific: each count only covers what the person's role works on and their own scope
 * (sysadmin: everything; account manager: their companies; company staff: their company; a maintenance or
 * cleaning person: jobs assigned to them; a tenant: their own bills; a BnB host: their own units).
 *
 * page => ['n' => count, 'urgent' => blink the dot, 'title' => tooltip]. Cached 60 s per person.
 */
class SidebarBadges
{
    public static function for($user): array
    {
        if (! $user) {
            return [];
        }

        return Cache::remember('sidebar-badges:'.$user->id, 60, function () use ($user) {
            try {
                return array_filter((new self($user))->all(), fn ($b) => $b['n'] > 0);
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        });
    }

    /** Total for the header bell, and whether any of it is urgent. */
    public static function total($user): array
    {
        $b = self::for($user);

        return ['n' => array_sum(array_column($b, 'n')), 'urgent' => (bool) array_filter(array_column($b, 'urgent'))];
    }

    private ?array $ids;

    private function __construct(private $user)
    {
        $this->ids = PortfolioReport::companyIdsFor($user); // null = all (sysadmin)
    }

    private function can(string $perm): bool
    {
        return $this->user->hasRole('sysadmin') || ! CheckAccess::catalogueInstalled() || $this->user->can($perm);
    }

    private function scoped($q, $column)
    {
        return $this->ids === null ? $q : $q->whereIn($column, $this->ids ?: [0]);
    }

    private function badge(int $n, string $title, bool $urgent = false): array
    {
        return ['n' => $n, 'urgent' => $urgent, 'title' => $title];
    }

    /** page => [label, url] for the header bell's list. */
    private const LINKS = [
        'maintenance' => ['Repairs', '/maintenance'],
        'invoices' => ['Invoices', '/invoices'],
        'waterReadings' => ['Water readings', '/water'],
        'gate' => ['Guests & gate', '/gate'],
        'crmFollowUps' => ['Follow-ups', '/crm/follow-ups'],
        'crmDemos' => ['Requests', '/crm/requests'],
        'propertyClaims' => ['Building claims', '/admin/property-claims'],
    ];

    private function all(): array
    {
        $out = $this->counts();
        foreach ($out as $page => &$b) {
            [$b['label'], $b['url']] = self::LINKS[$page] ?? [ucfirst($page), '#'];
        }

        return $out;
    }

    private function counts(): array
    {
        $u = $this->user;
        $out = [];

        // Repairs: maintenance / cleaning people see the jobs assigned to them; managers their company's open jobs.
        if ($this->can('maintenance.view') && Schema::hasTable('maintenances')) {
            $q = DB::table('maintenances')->whereIn('status', ['open', 'in_progress', 'pending_parts']);
            if ($u->hasAnyRole(['maintenance', 'cleaning_staff']) && ! $u->hasAnyRole(['admin', 'property_manager', 'sysadmin'])) {
                $q->where('assigned_to', $u->id);
                $label = 'assigned to you';
            } elseif ($u->hasRole('tenant') && ! $u->hasAnyRole(['admin', 'property_manager', 'sysadmin'])) {
                $q->whereIn('tenant_id', DB::table('tenants')->where('user_id', $u->id)->select('id'));
                $label = 'of yours still open';
            } else {
                $this->scoped($q, 'company_id');
                $label = 'open';
            }
            $n = $q->count();
            $urgent = (clone $q)->where('priority', 'high')->exists() || (clone $q)->where('priority', 'urgent')->exists();
            $out['maintenance'] = $this->badge($n, "{$n} repair".($n === 1 ? '' : 's')." {$label}", $urgent);
        }

        // Invoices: unpaid bills. A tenant sees their own.
        if ($this->can('invoices.view') && Schema::hasTable('invoices')) {
            $q = DB::table('invoices as i')->leftJoin('tenancies as tn', 'tn.id', '=', 'i.tenancy_id')->whereIn('i.status', ['unpaid', 'partial', 'overdue']);
            if ($u->hasRole('tenant') && ! $u->hasAnyRole(['admin', 'accountant', 'property_manager', 'sysadmin'])) {
                $q->whereIn('i.tenancy_id', DB::table('tenancies')->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')->where('tenants.user_id', $u->id)->select('tenancies.id'));
                $n = $q->count();
                $out['invoices'] = $this->badge($n, "{$n} bill".($n === 1 ? '' : 's').' to pay', $n > 0);
            } elseif (! $u->hasAnyRole(['meter_reader', 'maintenance', 'cleaning_staff', 'security'])) {
                $this->scoped($q, DB::raw('COALESCE(i.company_id, tn.company_id)'));
                $n = $q->count();
                $out['invoices'] = $this->badge($n, "{$n} unpaid invoice".($n === 1 ? '' : 's'));
            }
        }

        // Water readings: units not read this month (meter readers and managers).
        if ($this->can('water.view') && $u->hasAnyRole(['meter_reader', 'admin', 'property_manager', 'sysadmin']) && Schema::hasTable('water_readings')) {
            $units = $this->scoped(DB::table('units as u')->leftJoin('properties as p', 'p.id', '=', 'u.property_id')
                ->where(fn ($w) => $w->whereNull('u.is_active')->orWhere('u.is_active', 1)), DB::raw('COALESCE(u.company_id, p.company_id)'));
            $read = DB::table('water_readings')->where('reading_date', '>=', now()->startOfMonth())->where(fn ($w) => $w->whereNull('is_initial')->orWhere('is_initial', 0))->select('unit_id');
            $n = (clone $units)->whereNotIn('u.id', $read)->count();
            $late = now()->day > 25;
            $out['waterReadings'] = $this->badge($n, "{$n} unit".($n === 1 ? '' : 's').' not read this month', $late && $n > 0);
        }

        // Guests & gate: expected today, and anyone past the end of their stay. Hosts / tenants: their units.
        if (Schema::hasTable('gate_passes') && \App\Services\Gate\Gate::allowed($u)) {
            $base = fn () => \App\Services\Gate\Gate::scopePasses(DB::table('gate_passes'), $u);
            $today = now('Africa/Nairobi')->startOfDay()->utc();
            $expected = $base()->where('status', 'issued')->where('valid_from', '<', $today->copy()->addDay())
                ->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()))->count();
            $overstay = $base()->where('status', 'checked_in')->whereNotNull('valid_until')->where('valid_until', '<', now())->count();
            $n = $expected + $overstay;
            $out['gate'] = $this->badge($n, trim(($expected ? "{$expected} expected today" : '').($overstay ? ($expected ? ', ' : '')."{$overstay} past their stay" : '')), $overstay > 0);
        }

        // CRM: my overdue + due-today follow-ups; open requests (demos, API keys, viewings).
        if ($this->can('crm.view') && Schema::hasTable('crm_follow_ups')) {
            $mine = fn () => \App\Modules\CRM\Support\CrmAccess::followUps(\App\Modules\CRM\Models\CrmFollowUp::query(), $u);
            $overdue = $mine()->overdue()->count();
            $today = $mine()->dueToday()->count();
            $out['crmFollowUps'] = $this->badge($overdue + $today, trim(($overdue ? "{$overdue} overdue" : '').($today ? ($overdue ? ', ' : '')."{$today} due today" : '')), $overdue > 0);
            $req = $mine()->whereIn('type', ['demo', 'api_keys', 'viewing'])->where('status', 'open')->count();
            $out['crmDemos'] = $this->badge($req, "{$req} new request".($req === 1 ? '' : 's'), $req > 0);
        }

        // Building claims waiting for approval (scharge staff).
        if ($u->hasAnyRole(['sysadmin', 'account_manager']) && Schema::hasTable('property_claims')) {
            $n = $this->scoped(DB::table('property_claims')->where('status', 'pending'), 'company_id')->count();
            $out['propertyClaims'] = $this->badge($n, "{$n} claim".($n === 1 ? '' : 's').' to review', $n > 0);
        }

        return $out;
    }
}
