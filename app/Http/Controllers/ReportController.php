<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Reports\PortfolioReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports & analytics: billing, collections, arrears, occupancy, expenses and water for the companies
 * the viewer may see. Company staff see their own company; account managers see the companies they own;
 * scharge sysadmins see everything or one company they pick.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$report, $filters] = $this->report($request);

        return view('reports.index', $filters + ['data' => $report->all(), 'periods' => PortfolioReport::PERIODS]);
    }

    /** Per-property performance (and the headline numbers) as CSV for the same filters. */
    public function export(Request $request): StreamedResponse
    {
        [$report] = $this->report($request);
        $kpis = $report->kpis();
        $rows = $report->properties();
        $name = 'scharge-report-'.$report->from->format('Ymd').'-'.$report->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report, $kpis, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', $report->from->toDateString().' to '.$report->to->toDateString()]);
            foreach (['billed' => 'Billed (KES)', 'collected' => 'Collected (KES)', 'collection_rate' => 'Collection rate (%)', 'arrears' => 'Arrears now (KES)',
                'occupancy' => 'Occupancy (%)', 'expenses' => 'Expenses (KES)', 'net' => 'Net (KES)'] as $k => $label) {
                fputcsv($out, [$label, $kpis[$k]]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Property', 'Company', 'Location', 'Units', 'Occupied', 'Occupancy %', 'Monthly rent roll', 'Billed', 'Collection rate %', 'Collected', 'Arrears now']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['company'], $r['location'], $r['units'], $r['occupied'], $r['occupancy'], $r['rent_roll'], $r['billed'], $r['collection_rate'], $r['collected'], $r['arrears']]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Older links (/reports/financial, /invoices, /payments) open the one reports page. */
    public function legacy(Request $request)
    {
        return redirect()->route('reports.index', $request->query());
    }

    /** @return array{0: PortfolioReport, 1: array} */
    private function report(Request $request): array
    {
        $user = $request->user();
        $period = array_key_exists($request->query('period'), PortfolioReport::PERIODS) ? $request->query('period') : 'last_12';

        // Which companies this person may report on.
        if ($user->hasRole('sysadmin')) {
            $allowed = null;
            $choices = Company::query()->where(fn ($q) => $q->whereNull('lifecycle')->orWhere('lifecycle', '!=', 'prospect'))->orderBy('name')->get(['id', 'name']);
        } elseif ($user->hasRole('account_manager') && ! $user->company_id) {
            $choices = Company::where('owner_id', $user->id)->orderBy('name')->get(['id', 'name']);
            $allowed = $choices->pluck('id')->all();
        } else {
            abort_unless($user->company_id, 403, 'Your account is not linked to a company yet.');
            $allowed = [(int) $user->company_id];
            $choices = collect();
        }

        $companyId = $request->integer('company') ?: null;
        if ($companyId && $allowed !== null && ! in_array($companyId, $allowed, true)) {
            $companyId = null; // not theirs: ignore
        }
        $companyIds = $companyId ? [$companyId] : $allowed;

        $properties = DB::table('properties')->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', $companyIds ?: [0]))->orderBy('name')->get(['id', 'name']);
        $propertyId = $request->integer('property') ?: null;
        if ($propertyId && ! $properties->contains('id', $propertyId)) {
            $propertyId = null;
        }

        $report = new PortfolioReport($companyIds, $propertyId, $period, $request->query('from'), $request->query('to'));

        return [$report, [
            'period' => $period,
            'companyId' => $companyId,
            'propertyId' => $propertyId,
            'companies' => $choices,
            'properties' => $properties,
            'scopeLabel' => $propertyId ? $properties->firstWhere('id', $propertyId)->name
                : ($companyId ? optional($choices->firstWhere('id', $companyId))->name ?? 'Your company'
                : ($allowed === null ? 'All companies' : ($user->company_id ? optional($user->company)->name ?? 'Your company' : 'Your companies'))),
        ]];
    }
}
