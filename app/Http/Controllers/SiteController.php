<?php

namespace App\Http\Controllers;

use App\Models\CompanyBrand;
use App\Models\CompanyPage;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Services\ListingCatalog;
use App\Services\Listings\EnquiryRouter;
use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * A white-label company's own website on its own address (docs/COMPANY_WEBSITE.md): its live pages, its vacant units
 * (Homes to rent, automatic) and a contact form that reaches its team. The portal (sign-in, dashboard) is on the same
 * address. "/" comes here through ApplyBrand; other paths through the fallback route.
 */
class SiteController extends Controller
{
    public function page(Request $request, string $slug = ''): View
    {
        $brand = Brand::forDomain($request->getHost());
        abort_unless($brand, 404);
        $page = CompanyPage::where('company_id', $brand->company_id)->where('slug', trim($slug, '/'))->whereNotNull('published_at')->firstOrFail();

        return $this->render($brand, $page);
    }

    /** Draft preview for the company's staff and scharge (WebsiteController). */
    public function render(CompanyBrand $brand, CompanyPage $page, bool $preview = false): View
    {
        $view = $page;
        if ($preview) {
            $view = clone $page;
            foreach (CompanyPage::CONTENT as $f) {
                $view->{$f} = $page->edit($f);
            }
        }

        return view('site.page', [
            'brand' => $brand,
            'page' => $view,
            'preview' => $preview,
            'menu' => CompanyPage::where('company_id', $brand->company_id)->whereNotNull('published_at')->where('show_in_menu', true)->orderBy('sort')->get(['slug', 'title']),
            'listings' => $page->template === 'rentals' || $page->template === 'home' ? $this->listings($brand->company_id) : [],
        ]);
    }

    /** Contact form on the company's site: a CRM follow-up for the company, and an SMS to its admins. */
    public function contact(Request $request, EnquiryRouter $router): RedirectResponse
    {
        $brand = Brand::forDomain($request->getHost());
        abort_unless($brand, 404);
        if (filled($request->input('website'))) { // honeypot
            return back()->with('site_status', 'Thank you. We will get back to you.');
        }
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\s-]{7,40}$/'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], ['phone.regex' => 'Enter a phone number we can call, e.g. 0712 345 678.'])->validate();

        $followUp = DB::transaction(function () use ($data, $brand) {
            [$user] = app(\App\Modules\CRM\Services\ContactService::class)->upsertPerson(['name' => $data['name'], 'phone' => $data['phone'], 'country_code' => 'KE'], 'company_website');

            return CrmFollowUp::create(['company_id' => $brand->company_id, 'user_id' => $user->id, 'type' => 'viewing', 'status' => 'open',
                'title' => 'Website message: '.mb_substr($data['message'] ?? 'call back', 0, 80), 'notes' => $data['message'] ?? null, 'due_at' => now()->addHours(4),
                'details' => ['title' => 'Message from your website', 'action' => 'Website contact form', 'route' => 'landlord']]);
        });
        $router->notifyLandlord($brand->company_id, $data['name'], $data['phone'], 'your website (contact form)', null);
        $ref = $this->recordContact($followUp, $brand, $data);

        return back()->with('site_status', 'Thank you, '.strtok($data['name'], ' ').'. We will get back to you, usually the same day.'.($ref ? " Your reference is {$ref}." : ''));
    }

    /**
     * PDF on the enquiry and an email to the company's own team (its admins and support address), not to scharge staff:
     * the message is for them (FormSubmissionService). Never blocks the form.
     */
    private function recordContact(CrmFollowUp $followUp, CompanyBrand $brand, array $data): ?string
    {
        try {
            $team = \App\Models\User::where('company_id', $brand->company_id)->whereNotNull('email')
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'property_manager']))->limit(5)->pluck('email')->all();

            return app(\App\Modules\CRM\Services\FormSubmissionService::class)->record($followUp, 'site_contact', [
                'Website' => $brand->display_name,
                'Phone' => $data['phone'],
            ], $data['message'] ?? null, array_merge($team, array_filter([$brand->support_email])), assign: false,
                cta: ['url' => route('enquiries.index'), 'label' => 'Open your enquiries'], fromName: $brand->display_name)->detail('ref');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Fallback route: a page of the company's site, else the normal 404. */
    public function fallback(Request $request): View
    {
        abort_unless(Brand::forDomain($request->getHost()) && $request->isMethod('GET') && preg_match('#^[a-z0-9-]{1,80}$#', $request->path()), 404);

        return $this->page($request, $request->path());
    }

    private function listings(int $companyId): array
    {
        $propertyIds = DB::table('properties')->where('company_id', $companyId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return collect(app(ListingCatalog::class)->items())->filter(fn ($i) => in_array((int) ($i['property_id'] ?? 0), $propertyIds, true))->values()->all();
    }
}
