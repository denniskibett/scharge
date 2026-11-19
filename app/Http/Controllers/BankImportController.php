<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Modules\Payments\Models\Invoice;
use App\Services\Connectors\BankStatements;
use App\Services\Connectors\Hub;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Payments › Bank statement import (docs/INTEGRATIONS.md): upload, review the matches, record. */
class BankImportController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->company($request);
        $installed = Hub::installed('bank_statement_lines');
        $status = in_array($request->query('show'), ['suggested', 'new', 'posted', 'ignored'], true) ? $request->query('show') : 'open';
        $lines = $installed ? DB::table('bank_statement_lines')->where('company_id', $company->id)
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['suggested', 'new']), fn ($q) => $q->where('status', $status))
            ->orderByRaw("status = 'suggested' desc")->orderByDesc('posted_on')->orderByDesc('id')->paginate(50)->withQueryString() : collect();
        $counts = $installed ? DB::table('bank_statement_lines')->where('company_id', $company->id)->selectRaw('status, count(*) n, sum(amount) total')
            ->groupBy('status')->get()->keyBy('status') : collect();
        $invoices = $installed ? CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()
            ->whereIn('id', collect($lines->items() ?? [])->pluck('invoice_id')->filter())
            ->with('tenancy.tenant.user', 'tenancy.unit.property')->get()->keyBy('id')) : collect();
        $open = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()
            ->where(fn ($q) => $q->forCompany($company->id)->orWhereIn('tenancy_id', RentRun::ownedByQuery($company->id)->select('id')))
            ->whereColumn('total_paid', '<', 'total_amount')->whereNotIn('status', ['cancelled', 'void'])
            ->with('tenancy.tenant.user', 'tenancy.unit')->latest('id')->limit(500)->get());

        return view('integrations.bank-import', compact('company', 'installed', 'status', 'lines', 'counts', 'invoices', 'open'));
    }

    public function store(Request $request)
    {
        $company = $this->company($request);
        abort_unless(Hub::installed('bank_statement_lines'), 503, 'Apply database/schema/2026_11_20_integrations_more.sql first.');
        $request->validate(['statement' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx']]);
        $file = $request->file('statement');
        try {
            $lines = BankStatements::parse($file->getRealPath(), $file->getClientOriginalName());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }
        $bank = \App\Services\Integrations::for($company->id, 'bank_import')?->get('bank');
        $r = BankStatements::import($company->id, $lines, 'upload', $bank, mb_substr($file->getClientOriginalName(), 0, 190), $request->user()->id);

        return back()->with('status', count($lines)." deposits read: {$r['added']} new, {$r['suggested']} matched for you to confirm"
            .($r['posted'] ? ", {$r['posted']} recorded at once" : '').'.'.(count($lines) > $r['added'] ? ' '.(count($lines) - $r['added']).' were already imported.' : ''));
    }

    public function post(Request $request, int $line)
    {
        $company = $this->company($request);
        $data = $request->validate(['invoice_id' => ['required', 'integer']]);
        $ok = BankStatements::post($company->id, $line, (int) $data['invoice_id'], $request->user()->id);

        return back()->with('status', $ok ? 'Recorded. The tenant gets a receipt.' : 'Not recorded: that deposit or bill is not yours, or it was already recorded.');
    }

    public function ignore(Request $request, int $line)
    {
        $company = $this->company($request);
        DB::table('bank_statement_lines')->where('company_id', $company->id)->where('id', $line)->where('status', '!=', 'posted')
            ->update(['status' => $request->boolean('undo') ? 'new' : 'ignored', 'updated_at' => now()]);

        return back()->with('status', $request->boolean('undo') ? 'Back in the list.' : 'Marked as not rent.');
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        if ($user->hasRole('sysadmin') && $request->filled('company')) {
            return Company::findOrFail((int) $request->input('company'));
        }
        abort_unless($user->company_id, 403, 'Your account is not linked to a company yet.');

        return Company::findOrFail($user->company_id);
    }
}
