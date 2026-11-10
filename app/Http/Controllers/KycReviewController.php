<?php

namespace App\Http\Controllers;

use App\Models\CompanyKyc;
use App\Models\KycDocument;
use App\Services\Kyc\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * scharge staff › KYC reviews: companies waiting for verification, with every document, what was read from it and the
 * automatic checks side by side; approve (the company's listings get the "Verified" badge) or send back with a note.
 * Sysadmins see all; account managers see the companies they own.
 */
class KycReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), CompanyKyc::STATUSES) ? $request->query('status') : 'submitted';

        return view('kyc.review-index', [
            'status' => $status,
            'items' => $this->scope($request, CompanyKyc::with('company')->where('status', $status))->latest('submitted_at')->paginate(25)->withQueryString(),
            'counts' => collect(CompanyKyc::STATUSES)->map(fn ($l, $k) => $this->scope($request, CompanyKyc::where('status', $k))->count()),
        ]);
    }

    public function show(Request $request, CompanyKyc $kyc, KycService $service): View
    {
        $this->scope($request, CompanyKyc::whereKey($kyc->id))->firstOrFail();

        return view('kyc.review-show', ['kyc' => $service->recheck($kyc), 'documents' => KycDocument::with('property')->where('company_id', $kyc->company_id)->get()]);
    }

    public function decide(Request $request, CompanyKyc $kyc): RedirectResponse
    {
        $this->scope($request, CompanyKyc::whereKey($kyc->id))->firstOrFail();
        $data = $request->validate(['decision' => ['required', 'in:verified,rejected'], 'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:500']],
            ['note.required_if' => 'Say what the company must fix.']);
        $kyc->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null]);
        \App\Services\ListingCatalog::forget(); // the Verified badge on Open listings

        return redirect()->route('kyc.review.index')->with('success', ($kyc->company?->name ?? 'Company').($data['decision'] === 'verified' ? ' is verified.' : ' was sent back with your note.'));
    }

    private function scope(Request $request, $query)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['sysadmin', 'account_manager']), 403);

        return $user->hasRole('sysadmin') ? $query : $query->whereIn('company_id', \App\Models\Company::withoutGlobalScopes()->where('owner_id', $user->id)->select('id'));
    }
}
