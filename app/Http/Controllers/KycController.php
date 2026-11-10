<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyKyc;
use App\Models\KycDocument;
use App\Services\Kyc\DocumentReader;
use App\Services\Kyc\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings › Verify your business (KYC): upload the owner's ID, KRA PIN, business registration and title deeds from a
 * phone; scharge reads them, fills the details, checks they agree, and the company sends them for review.
 * scharge staff (sysadmin, account managers for their companies) can do it on a company's behalf with ?company=ID,
 * e.g. while sitting with a new landlord. Review: KycReviewController.
 */
class KycController extends Controller
{
    public function show(Request $request, KycService $kyc): View
    {
        $company = $this->company($request);

        return view('kyc.show', [
            'company' => $company,
            'kyc' => $kyc->forCompany($company->id),
            'documents' => KycDocument::with('property')->where('company_id', $company->id)->latest('id')->get(),
            'properties' => \App\Modules\Properties\Models\Property::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'readerOn' => DocumentReader::enabled(),
            'q' => $this->query($request, $company),
        ]);
    }

    public function upload(Request $request, KycService $kyc): RedirectResponse
    {
        $company = $this->company($request);
        $this->editable($kyc->forCompany($company->id));
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(KycDocument::KINDS))],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('company_id', $company->id)],
            'files' => ['required', 'array', 'max:6'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], ['files.*.mimes' => 'Photos (JPG, PNG, WebP) or PDF only.', 'files.*.max' => 'Each file up to 10 MB.']);

        foreach ($data['files'] as $file) {
            $doc = $kyc->upload($company->id, $data['kind'], $file, $data['property_id'] ?? null, $request->user()->id);
            // Read after the response is sent: the page comes back at once and shows "Reading…".
            dispatch(fn () => app(KycService::class)->readAndPrefill($doc))->afterResponse();
        }

        return back()->with('success', DocumentReader::enabled() ? 'Uploaded. Reading the document: refresh in a moment to see the details filled in.' : 'Uploaded.');
    }

    public function update(Request $request, KycService $kyc): RedirectResponse
    {
        $company = $this->company($request);
        $record = $kyc->forCompany($company->id);
        $this->editable($record);
        $data = $request->validate([
            'owner_name' => ['nullable', 'string', 'max:160'],
            'owner_id_number' => ['nullable', 'string', 'max:40'],
            'kra_pin' => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:190'],
            'registration_number' => ['nullable', 'string', 'max:60'],
        ]);
        $data['kra_pin'] = filled($data['kra_pin'] ?? null) ? strtoupper(preg_replace('/\s/', '', $data['kra_pin'])) : null;
        $record->update($data);
        $kyc->recheck($record);

        if ($request->boolean('submit')) {
            abort_if(collect($record->checks)->contains('level', 'fail'), 422, 'Fix the items marked in red first.');
            abort_unless(filled($record->owner_name) && filled($record->kra_pin), 422, 'Add the owner\'s name and KRA PIN first.');
            $record->update(['status' => 'submitted', 'submitted_at' => now(), 'review_note' => null]);

            return back()->with('success', 'Sent to scharge for review. We usually reply within one working day.');
        }

        return back()->with('success', 'Saved.');
    }

    public function reread(Request $request, KycDocument $document, KycService $kyc): RedirectResponse
    {
        $this->own($request, $document);
        $document->update(['read_status' => 'pending', 'read_error' => null]);
        dispatch(fn () => app(KycService::class)->readAndPrefill($document))->afterResponse();

        return back()->with('success', 'Reading again.');
    }

    public function destroy(Request $request, KycDocument $document, KycService $kyc): RedirectResponse
    {
        $company = $this->own($request, $document);
        $this->editable($kyc->forCompany($company->id));
        Storage::disk('local')->delete($document->path);
        $document->delete();
        $kyc->recheck($kyc->forCompany($company->id));

        return back()->with('success', 'Document removed.');
    }

    /** The file itself: only the company's admins and scharge staff. */
    public function file(Request $request, KycDocument $document)
    {
        $this->own($request, $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function own(Request $request, KycDocument $document): Company
    {
        $company = $this->company($request->merge(['company' => $document->company_id]));
        abort_unless((int) $document->company_id === (int) $company->id, 404);

        return $company;
    }

    private function editable(CompanyKyc $kyc): void
    {
        abort_if(in_array($kyc->status, ['submitted', 'verified'], true) && ! request()->user()->hasRole('sysadmin'), 422,
            $kyc->status === 'verified' ? 'Your business is verified. Contact scharge to change these details.' : 'This is with scharge for review.');
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        if ($request->filled('company') && $user->hasAnyRole(['sysadmin', 'account_manager'])) {
            $company = Company::withoutGlobalScopes()->findOrFail((int) $request->input('company'));
            abort_unless($user->hasRole('sysadmin') || (int) $company->owner_id === (int) $user->id, 403);

            return $company;
        }
        abort_unless($user->company_id && $user->hasAnyRole(['admin', 'sysadmin']), 403, 'Only your company admin can verify the business.');

        return Company::withoutGlobalScopes()->findOrFail($user->company_id);
    }

    private function query(Request $request, Company $company): array
    {
        return (int) $request->user()->company_id === (int) $company->id ? [] : ['company' => $company->id];
    }
}
