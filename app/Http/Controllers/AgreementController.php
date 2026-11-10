<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyAgreement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Settings › Data-processing agreement (docs/COMPANY_WEBSITE.md): the company's data is its own and scharge only
 * processes it. The admin reads it and accepts it; who, which role, when and from where is recorded. Text:
 * resources/legal/dpa.md (version CompanyAgreement::DPA_VERSION).
 */
class AgreementController extends Controller
{
    public function show(Request $request): View
    {
        $company = $this->company($request);

        return view('brand.dpa', [
            'company' => $company,
            'html' => Str::markdown(str_replace('{company}', e($company->name), file_get_contents(resource_path('legal/dpa.md'))), ['html_input' => 'escape']),
            'accepted' => CompanyAgreement::dpaAccepted($company->id),
            'q' => (int) $request->user()->company_id === (int) $company->id ? [] : ['company' => $company->id],
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        abort_unless((int) $request->user()->company_id === (int) $company->id, 403, 'The company accepts its own agreement.');
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'title' => ['required', 'string', 'max:120'], 'agree' => ['accepted']],
            ['agree.accepted' => 'Tick the box to accept.']);
        CompanyAgreement::firstOrCreate(['company_id' => $company->id, 'agreement' => 'dpa', 'version' => CompanyAgreement::DPA_VERSION], [
            'accepted_by' => $request->user()->id, 'accepted_name' => $data['name'], 'accepted_title' => $data['title'], 'ip' => $request->ip(), 'accepted_at' => now(),
        ]);

        return back()->with('success', 'Accepted. A record is kept with your name, role and the date.');
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        if ($request->filled('company') && $user->hasAnyRole(['sysadmin', 'account_manager'])) {
            $company = Company::withoutGlobalScopes()->findOrFail((int) $request->input('company'));
            abort_unless($user->hasRole('sysadmin') || (int) $company->owner_id === (int) $user->id, 403);

            return $company;
        }
        abort_unless($user->company_id && $user->hasAnyRole(['admin', 'sysadmin']), 403, 'Only your company admin can accept agreements.');

        return Company::withoutGlobalScopes()->findOrFail($user->company_id);
    }
}
