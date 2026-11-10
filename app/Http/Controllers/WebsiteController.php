<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyPage;
use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings › Website (docs/COMPANY_WEBSITE.md): a white-label company's staff edit their hosted website. Every change
 * is a draft until scharge approves it (WebsiteReviewController); scharge staff can also edit and publish directly.
 * Starts from scharge's template (Home, About, Homes to rent, Contact); custom pages can be added.
 */
class WebsiteController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company($request);

        return view('website.index', [
            'company' => $company,
            'brand' => Brand::forCompany($company->id),
            'pages' => CompanyPage::where('company_id', $company->id)->orderBy('sort')->get(),
            'isStaff' => $this->isStaff($request),
            'q' => $this->q($request, $company),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        abort_if(CompanyPage::where('company_id', $company->id)->exists(), 422, 'Your website already has pages.');
        $brand = Brand::forCompany($company->id);
        foreach (CompanyPage::starter($company->id, $brand?->display_name ?? $company->name, $brand?->tagline) as $p) {
            CompanyPage::create(['company_id' => $company->id, 'slug' => $p['slug'], 'template' => $p['template'], 'sort' => $p['sort'], 'status' => 'draft',
                'title' => $p['title'], 'draft' => collect($p)->only(CompanyPage::CONTENT)->all()]);
        }

        return back()->with('success', 'Your website has the four starter pages. Edit them, then send them to scharge for approval.');
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(self::RESERVED),
                Rule::unique('company_pages', 'slug')->where('company_id', $company->id)],
        ], ['slug.regex' => 'Use lowercase letters, numbers and dashes, e.g. our-buildings.', 'slug.not_in' => 'That address is taken by the portal: choose another.']);
        $page = CompanyPage::create(['company_id' => $company->id, 'slug' => $data['slug'], 'template' => 'custom', 'status' => 'draft', 'title' => $data['title'],
            'sort' => (int) CompanyPage::where('company_id', $company->id)->max('sort') + 1, 'draft' => ['title' => $data['title'], 'hero_title' => $data['title']]]);

        return redirect()->route('website.edit', [$page] + $this->q($request, $company));
    }

    public function edit(Request $request, CompanyPage $page): View
    {
        $company = $this->own($request, $page);

        return view('website.edit', ['page' => $page, 'company' => $company, 'isStaff' => $this->isStaff($request), 'q' => $this->q($request, $company)]);
    }

    public function update(Request $request, CompanyPage $page): RedirectResponse
    {
        $company = $this->own($request, $page);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'hero_title' => ['nullable', 'string', 'max:190'],
            'hero_text' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'hero' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_hero' => ['nullable', 'boolean'],
            'show_in_menu' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:99'],
            'action' => ['required', Rule::in(['save', 'submit', 'publish'])],
        ]);
        $draft = collect($data)->only(['title', 'hero_title', 'hero_text', 'body'])->all() + ['hero_image' => $page->edit('hero_image')];
        if ($request->boolean('remove_hero')) {
            $draft['hero_image'] = null;
        }
        if ($request->hasFile('hero')) {
            $draft['hero_image'] = $request->file('hero')->store('sites/'.$company->id, 'public');
        }
        $page->fill(['draft' => $draft, 'show_in_menu' => $request->boolean('show_in_menu'), 'sort' => $data['sort'] ?? $page->sort]);

        if ($data['action'] === 'publish') {
            abort_unless($this->isStaff($request), 403, 'scharge approves website changes.');
            $this->publish($page, $request->user()->id);

            return redirect()->route('website.index', $this->q($request, $company))->with('success', 'Published: "'.$page->title.'" is live.');
        }
        if ($data['action'] === 'submit') {
            $page->fill(['status' => $page->isLive() ? 'changes_pending' : 'pending', 'submitted_by' => $request->user()->id, 'submitted_at' => now(), 'review_note' => null]);
        }
        $page->save();

        return back()->with('success', $data['action'] === 'submit' ? 'Sent to scharge. It goes live once approved, usually within a working day.' : 'Draft saved. Use Preview to see it.');
    }

    public function preview(Request $request, CompanyPage $page): View
    {
        $company = $this->own($request, $page);
        $brand = Brand::forCompany($company->id);
        abort_unless($brand, 422, 'Set up your white label first (Settings › White label & data).');

        return app(SiteController::class)->render($brand, $page, preview: true);
    }

    public function destroy(Request $request, CompanyPage $page): RedirectResponse
    {
        $company = $this->own($request, $page);
        abort_unless($page->template === 'custom', 422, 'Template pages can be hidden from the menu but not deleted.');
        foreach (array_filter([$page->hero_image, $page->draft['hero_image'] ?? null]) as $f) {
            Storage::disk('public')->delete($f);
        }
        $page->delete();

        return redirect()->route('website.index', $this->q($request, $company))->with('success', 'Page deleted.');
    }

    /** Apply the draft to the live page (scharge staff: approve or publish). */
    public static function publish(CompanyPage $page, int $userId): void
    {
        foreach (CompanyPage::CONTENT as $f) {
            if (array_key_exists($f, $page->draft ?? [])) {
                $page->{$f} = $page->draft[$f];
            }
        }
        $page->fill(['draft' => null, 'status' => 'published', 'published_at' => $page->published_at ?? now(), 'approved_by' => $userId, 'approved_at' => now(), 'review_note' => null])->save();
    }

    /** Paths the portal uses: never a website page. */
    public const RESERVED = ['login', 'logout', 'register', 'dashboard', 'rent', 'bookings', 'enquiries', 'settings', 'admin', 'api', 'public', 'webhooks', 'calendar',
        'invoices', 'payments', 'tenants', 'tenancies', 'units', 'properties', 'water', 'gate', 'maintenance', 'reports', 'integrations', 'site', 'up', 'storage', 'build', 'assets'];

    private function isStaff(Request $request): bool
    {
        return $request->user()->hasAnyRole(['sysadmin', 'account_manager']);
    }

    private function company(Request $request): Company
    {
        $user = $request->user();
        if ($request->filled('company') && $this->isStaff($request)) {
            $company = Company::withoutGlobalScopes()->findOrFail((int) $request->input('company'));
            abort_unless($user->hasRole('sysadmin') || (int) $company->owner_id === (int) $user->id, 403);

            return $company;
        }
        abort_unless($user->company_id && $user->hasAnyRole(['admin', 'sysadmin']), 403, 'Only your company admin can edit the website.');

        return Company::withoutGlobalScopes()->findOrFail($user->company_id);
    }

    private function own(Request $request, CompanyPage $page): Company
    {
        $company = $this->company($request->merge(['company' => $request->input('company', $page->company_id)]));
        abort_unless((int) $page->company_id === (int) $company->id, 404);

        return $company;
    }

    private function q(Request $request, Company $company): array
    {
        return (int) $request->user()->company_id === (int) $company->id ? [] : ['company' => $company->id];
    }
}
