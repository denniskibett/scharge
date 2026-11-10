<?php

namespace App\Http\Controllers;

use App\Models\CompanyPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** scharge staff › Website approvals: company website changes waiting to go live (docs/COMPANY_WEBSITE.md). */
class WebsiteReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('website.review', ['pages' => $this->scope($request, CompanyPage::query())->whereIn('status', ['pending', 'changes_pending'])
            ->orderBy('submitted_at')->get()->load('company')]);
    }

    public function decide(Request $request, CompanyPage $page): RedirectResponse
    {
        $this->scope($request, CompanyPage::whereKey($page->id))->firstOrFail();
        $data = $request->validate(['decision' => ['required', 'in:approve,return'], 'note' => ['nullable', 'required_if:decision,return', 'string', 'max:500']],
            ['note.required_if' => 'Say what to change.']);
        if ($data['decision'] === 'approve') {
            WebsiteController::publish($page, $request->user()->id);

            return back()->with('success', '"'.$page->title.'" is live.');
        }
        $page->update(['status' => $page->isLive() ? 'published' : 'draft', 'submitted_at' => null, 'review_note' => $data['note']]);

        return back()->with('success', 'Sent back with your note.');
    }

    private function scope(Request $request, $query)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['sysadmin', 'account_manager']), 403);

        return $user->hasRole('sysadmin') ? $query : $query->whereIn('company_id', \App\Models\Company::withoutGlobalScopes()->where('owner_id', $user->id)->select('id'));
    }
}
