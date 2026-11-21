<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Properties\PropertyOnboarding;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Onboarding from the CSV template: download, upload (dry run preview), confirm.
 * Company users import for their own company; account managers for the companies they own; sysadmins for any customer.
 */
class PropertyOnboardingController extends Controller
{
    public function __construct(private PropertyOnboarding $onboarding) {}

    public function template()
    {
        return response($this->onboarding->template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="scharge-onboarding-template.csv"',
        ]);
    }

    /** The Excel template. Also served without signing in (routes: onboarding.template.public) so prospects can fill it before a demo. */
    public function templateXlsx()
    {
        return response($this->onboarding->templateXlsx(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="scharge-onboarding-template.xlsx"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function create(Request $request)
    {
        $upload = $request->session()->get('onboarding');
        $report = $upload['report'] ?? null;

        return view('properties.onboard', [
            'columns' => PropertyOnboarding::COLUMNS,
            'roles' => PropertyOnboarding::ROLES,
            'companies' => $this->companies($request),
            'report' => $report,
            'upload' => $upload,
            'done' => $request->session()->get('onboarding_done'),
        ]);
    }

    /** Upload: read the file and show what would happen. Nothing is saved yet. */
    public function preview(Request $request)
    {
        $companies = $this->companies($request);
        $data = $request->validate([
            'file' => 'required|file|max:5120|extensions:csv,txt,xlsx,xls',
            'role' => 'required|in:'.implode(',', array_keys(PropertyOnboarding::ROLES)),
            'company_id' => 'nullable|integer',
        ]);
        $company = $this->company($request, $companies, $data['company_id'] ?? null);

        $this->forget($request);
        $ext = PropertyOnboarding::isWorkbook($request->file('file')->getRealPath()) ? 'xlsx' : 'csv';
        $path = $request->file('file')->storeAs('onboarding', Str::uuid().'.'.$ext, 'local');
        $read = $this->onboarding->read(Storage::disk('local')->path($path));
        if ($ext === 'xlsx' && ! $read['rows'] && ! $read['unknown'] && $read['missing']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'That Excel file could not be read. Save it again from Excel (File › Save As › Excel Workbook) or as CSV, and upload it again.']);
        }
        if ($read['missing']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'The file needs the columns '.implode(' and ', $read['missing']).'. Download the template and keep its first row.']);
        }
        if (! $read['rows']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'The file has no rows under the headings.']);
        }

        $report = $this->onboarding->run($company, $data['role'], $read['rows'], $request->user(), commit: false);
        $report['unknown_columns'] = $read['unknown'];
        $request->session()->put('onboarding', ['path' => $path, 'name' => $request->file('file')->getClientOriginalName(),
            'company_id' => $company->id, 'role' => $data['role'], 'report' => $report, 'token' => Str::random(32)]);

        return redirect()->route('properties.onboard');
    }

    /** Confirm: run the same file again for real (the data may have moved on since the preview; the rules are the same). */
    public function confirm(Request $request)
    {
        $upload = $request->session()->get('onboarding');
        abort_unless($upload && hash_equals($upload['token'], (string) $request->input('token')), 419, 'This preview has expired. Upload the file again.');
        $company = $this->company($request, $this->companies($request), $upload['company_id']);
        $read = $this->onboarding->read(Storage::disk('local')->path($upload['path']));

        $report = $this->onboarding->run($company, $upload['role'], $read['rows'], $request->user(), commit: true);
        $this->forget($request);
        $request->session()->flash('onboarding_done', $report);

        return redirect()->route('properties.onboard');
    }

    public function cancel(Request $request)
    {
        $this->forget($request);
        $request->session()->forget('onboarding_done');

        return redirect()->route('properties.onboard');
    }

    private function forget(Request $request): void
    {
        if ($old = $request->session()->pull('onboarding')) {
            Storage::disk('local')->delete($old['path']);
        }
    }

    /** Companies this user may import for: null = only their own company (no picker). */
    private function companies(Request $request): ?\Illuminate\Support\Collection
    {
        $user = $request->user();
        if ($user->company_id) {
            return null;
        }
        $ids = CompanyAccess::ids();
        abort_unless($ids === null ? $user->hasRole('sysadmin') : $ids !== [], 403, 'Your account is not linked to a company.');

        return Company::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->get(['id', 'name', 'kind']);
    }

    private function company(Request $request, ?\Illuminate\Support\Collection $companies, $id): Company
    {
        if ($companies === null) {
            return Company::withoutGlobalScopes()->findOrFail($request->user()->company_id);
        }
        $company = $companies->firstWhere('id', (int) $id);
        abort_unless($company, 422, 'Choose the company these properties belong to.');

        return Company::findOrFail($company->id);
    }
}
