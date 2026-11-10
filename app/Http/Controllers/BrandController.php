<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyBrand;
use App\Models\CompanyIntegration;
use App\Services\Data\CompanyExport;
use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Settings › White label & data (docs/WHITE_LABEL.md): the company's name, logos, colour and support contact; its own
 * web address and "Powered by scharge" (scharge staff only); downloading all its data; the nightly copy status.
 * A sysadmin works on any company with ?company=ID.
 */
class BrandController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $this->company($request);

        return view('brand.edit', [
            'company' => $company,
            'editBrand' => CompanyBrand::firstOrNew(['company_id' => $company->id], ['display_name' => $company->name]),
            'isStaff' => $request->user()->hasRole('sysadmin'),
            'backup' => CompanyIntegration::where('company_id', $company->id)->where('provider', 'data_backup')->first(),
            'counts' => app(CompanyExport::class)->counts($company->id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $brand = CompanyBrand::firstOrNew(['company_id' => $company->id]);
        $isStaff = $request->user()->hasRole('sysadmin');
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048']; // no SVG: it can carry scripts
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'support_email' => ['nullable', 'email', 'max:120'],
            'logo' => $image, 'logo_dark' => $image, 'icon' => $image,
            'remove' => ['nullable', 'array'], 'remove.*' => [Rule::in(['logo', 'logo_dark', 'icon'])],
        ] + ($isStaff ? [
            'domain' => ['nullable', 'string', 'max:191', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i', Rule::unique('company_brands', 'domain')->ignore($brand->id),
                Rule::notIn([parse_url((string) config('app.url'), PHP_URL_HOST), 'scharge.sharet.africa', 'localhost'])],
            'show_powered_by' => ['nullable', 'boolean'],
        ] : []), ['primary_color.regex' => 'Pick a colour like #1E40AF.', 'domain.regex' => 'Enter just the address, e.g. portal.danaff.co.ke.']);

        foreach (['logo' => 'logo_path', 'logo_dark' => 'logo_dark_path', 'icon' => 'icon_path'] as $input => $column) {
            if (in_array($input, $data['remove'] ?? [], true) || $request->hasFile($input)) {
                if ($brand->{$column}) {
                    Storage::disk('public')->delete($brand->{$column});
                }
                $brand->{$column} = $request->hasFile($input) ? $request->file($input)->store('brands/'.$company->id, 'public') : null;
            }
        }
        $brand->fill(collect($data)->only(['display_name', 'primary_color', 'tagline', 'support_phone', 'support_email'])->all());
        if ($isStaff) {
            $brand->domain = filled($data['domain'] ?? null) ? strtolower($data['domain']) : null;
            $brand->show_powered_by = $request->boolean('show_powered_by');
        }
        $brand->company_id = $company->id;
        $brand->save();

        return back()->with('success', 'White label saved.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('sysadmin'), 403);
        $company = $this->company($request);
        $brand = CompanyBrand::where('company_id', $company->id)->first();
        if ($brand) {
            foreach (['logo_path', 'logo_dark_path', 'icon_path'] as $c) {
                if ($brand->{$c}) {
                    Storage::disk('public')->delete($brand->{$c});
                }
            }
            $brand->delete();
        }

        return back()->with('success', 'White label removed: '.$company->name.' is back to the scharge look.');
    }

    /** Everything the company owns, as a zip of spreadsheets (CSV). */
    public function export(Request $request, CompanyExport $export): BinaryFileResponse
    {
        $company = $this->company($request);
        $file = $export->zip($company->id);

        return response()->download($file, basename($file))->deleteFileAfterSend();
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        if ($user->hasRole('sysadmin') && $request->filled('company')) {
            return Company::withoutGlobalScopes()->findOrFail((int) $request->input('company'));
        }
        abort_unless($user->company_id && $user->hasAnyRole(['admin', 'sysadmin']), 403, 'Only your company admin can change the white label.');

        return Company::withoutGlobalScopes()->findOrFail($user->company_id);
    }
}
