<?php

namespace App\Http\Controllers;

use App\Services\Reports\PortfolioReport;
use App\Support\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Header search (Breve's quick switcher, shown inline under the header box): jump to a page or open a
 * property, unit, tenant, invoice, team member or company by typing. Only what the person may see,
 * only in their companies. GET /search?q= → { results: [{ group, label, sub, url, icon }] }
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $results = $this->pages($user, $q);

        if (mb_strlen($q) >= 2 && ! $user->hasRole('tenant')) {
            $ids = PortfolioReport::companyIdsFor($user);
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $scope = fn ($query, $col) => $ids === null ? $query : $query->whereIn($col, $ids ?: [0]);

            $groups = [
                ['properties.view', 'Properties', 'building-office-2', fn () => $scope(DB::table('properties'), 'company_id')
                    ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('location', 'like', $like))
                    ->orderBy('name')->limit(self::PER_GROUP)->get()
                    ->map(fn ($p) => [$p->name, $p->location ?? '', route('properties.show', $p->id)])],

                ['properties.view', 'Units', 'home-modern', fn () => $scope(DB::table('units as u')->leftJoin('properties as p', 'p.id', '=', 'u.property_id'), DB::raw('COALESCE(u.company_id, p.company_id)'))
                    ->where('u.unit_number', 'like', $like)->orderBy('u.unit_number')->limit(self::PER_GROUP)
                    ->get(['u.id', 'u.unit_number', 'u.status', 'p.name as property'])
                    ->map(fn ($u) => [$u->unit_number, trim(($u->property ?? '').' · '.ucfirst((string) $u->status), ' ·'), route('units.show', $u->id)])],

                ['tenants.view', 'Tenants', 'users', fn () => $scope(DB::table('tenants as t')->join('users as us', 'us.id', '=', 't.user_id'), 't.company_id')
                    ->where(fn ($w) => $w->where('us.name', 'like', $like)->orWhere('us.first_name', 'like', $like)->orWhere('us.last_name', 'like', $like)
                        ->orWhere('us.phone', 'like', $like)->orWhere('us.email', 'like', $like)->orWhere('t.id_number', 'like', $like))
                    ->limit(self::PER_GROUP)->get(['t.id', 'us.name', 'us.first_name', 'us.last_name', 'us.phone'])
                    ->map(fn ($t) => [trim($t->first_name.' '.$t->last_name) ?: $t->name, (string) $t->phone, route('tenants.show', $t->id)])],

                ['invoices.view', 'Invoices', 'document-text', fn () => $scope(DB::table('invoices as i')->leftJoin('tenancies as tn', 'tn.id', '=', 'i.tenancy_id')
                        ->leftJoin('units as tu', 'tu.id', '=', 'tn.unit_id')->leftJoin('tenants as t', 't.id', '=', 'tn.tenant_id')->leftJoin('users as us', 'us.id', '=', 't.user_id'),
                        DB::raw('COALESCE(i.company_id, tn.company_id, tu.company_id)'))
                    ->where(fn ($w) => $w->where('i.id', ltrim(preg_replace('/\D/', '', $q), '0') ?: -1)->orWhere('tu.unit_number', 'like', $like)->orWhere('us.name', 'like', $like))
                    ->orderByDesc('i.billing_month')->limit(self::PER_GROUP)
                    ->get(['i.id', 'i.billing_month', 'i.total_amount', 'i.status', 'tu.unit_number'])
                    ->map(fn ($i) => ['Invoice #'.$i->id.($i->unit_number ? ' · '.$i->unit_number : ''),
                        date('M Y', strtotime((string) $i->billing_month)).' · KES '.number_format((float) $i->total_amount).' · '.ucfirst((string) $i->status), route('invoices.show', $i->id)])],

                ['team.view', 'Team', 'user-group', fn () => $scope(DB::table('users as u'), 'u.company_id')
                    ->whereExists(fn ($e) => $e->from('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->whereColumn('mr.model_id', 'u.id')->whereNotIn('r.name', ['tenant', 'guest']))
                    ->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.email', 'like', $like)->orWhere('u.phone', 'like', $like))
                    ->limit(self::PER_GROUP)->get(['u.id', 'u.name', 'u.email'])
                    ->map(fn ($u) => [$u->name, $u->email, $user->hasRole('sysadmin') ? route('staff.show', $u->id) : route('team.index')])],

                ['companies.view', 'Companies', 'building-office', fn () => DB::table('companies as c')
                    ->when($ids !== null, fn ($w) => $w->whereIn('c.id', $ids ?: [0]))
                    ->where(fn ($w) => $w->where('c.name', 'like', $like)->orWhere('c.email', 'like', $like)->orWhere('c.phone', 'like', $like))
                    ->orderBy('c.name')->limit(self::PER_GROUP)->get(['c.id', 'c.name', 'c.lifecycle', 'c.location'])
                    ->map(fn ($c) => [$c->name, trim(($c->lifecycle === 'prospect' ? 'Prospect' : 'Customer').' · '.($c->location ?? ''), ' ·'),
                        $c->lifecycle === 'prospect' ? route('crm.companies.show', $c->id) : route('admin.companies.show', $c->id)])],
            ];

            foreach ($groups as [$permission, $group, $icon, $query]) {
                if (! $user->can($permission)) {
                    continue;
                }
                try {
                    foreach ($query() as [$label, $sub, $url]) {
                        $results[] = compact('group', 'label', 'sub', 'url', 'icon');
                    }
                } catch (\Throwable $e) {
                    report($e); // one broken group must not break the search box
                }
            }
        }

        return response()->json(['results' => $results])->header('Cache-Control', 'private, no-store');
    }

    /** Sidebar pages this person can open, filtered by the query. */
    private function pages($user, string $q): array
    {
        $pages = [
            ['Dashboard', '/dashboard', 'dashboard', 'squares-2x2'], ['Calendar', '/calendar', 'calendar', 'calendar-days'], ['Reports', '/reports', 'reports', 'chart-bar'],
            ['Properties', '/properties', 'properties', 'building-office-2'], ['Units', '/units', 'units', 'home-modern'], ['Tenants', '/tenants', 'tenants', 'users'],
            ['Tenancies', '/tenancies', 'tenancies', 'clipboard-document-list'], ['Water readings', '/water', 'water_readings', 'beaker'],
            ['Water reports', '/water?tab=reports', 'water_reports', 'chart-bar'], ['Repairs', '/maintenance', 'maintenance', 'wrench-screwdriver'],
            ['Gate & visitors', '/security/logs', 'security_logs', 'shield-check'], ['Invoices', '/invoices', 'invoices', 'document-text'],
            ['Payments', '/payments', 'payments', 'banknotes'], ['Expenses', '/expenses', 'expenses', 'receipt-percent'], ['Payees', '/payees', 'payees', 'user-circle'],
            ['Send SMS', '/sms/broadcast', 'sms_send', 'paper-airplane'], ['SMS history', '/sms/history', 'sms_history', 'chat-bubble-left-ellipsis'],
            ['SMS templates', '/sms/templates', 'sms_templates', 'document-text'], ['Pipeline', '/crm', 'crm_pipeline', 'funnel'],
            ['Follow-ups', '/crm/follow-ups', 'crm_follow_ups', 'clock'], ['Requests', '/crm/requests', 'crm_demos', 'inbox-arrow-down'],
            ['Waitlist', '/crm/waitlist', 'crm_waitlist', 'queue-list'], ['Feedback', '/crm/feedback', 'crm_feedback', 'star'],
            ['Website analytics', '/admin/website-analytics', 'analytics', 'globe-alt'], ['Companies', '/admin/companies', 'companies', 'building-office'],
            ['Building claims', '/admin/property-claims', 'property_claims', 'clipboard-document-check'], ['Integrations', '/integrations', 'integrations', 'puzzle-piece'],
            ['API keys', '/api-keys', 'api_keys', 'key'], ['SMS settings', '/sms/settings', 'sms_settings', 'adjustments-horizontal'],
            ['Team & access', '/team', 'team', 'user-group'], ['Roles & permissions', '/roles', 'roles', 'lock-closed'], ['System', '/system', 'system_settings', 'cog-6-tooth'],
        ];
        $allowed = Access::menuFor($user); // null = role fallback: show what the sidebar would
        $out = [];
        foreach ($pages as [$label, $url, $menu, $icon]) {
            $open = in_array($menu, ['dashboard', 'calendar'], true);
            // No permission list (e.g. tenants, who use role defaults): only the pages every role has.
            if (! $open && (! is_array($allowed) || ! in_array($menu, $allowed, true))) {
                continue;
            }
            if ($q !== '' && ! str_contains(mb_strtolower($label), mb_strtolower($q))) {
                continue;
            }
            $out[] = ['group' => 'Go to', 'label' => $label, 'sub' => 'Page', 'url' => $url, 'icon' => $icon];
        }

        return array_slice($out, 0, $q === '' ? 8 : 6);
    }
}
