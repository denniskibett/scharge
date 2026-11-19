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
            'buildings' => in_array($page->template, ['home', 'about', 'rentals'], true) ? $this->buildings($brand->company_id) : [],
        ]);
    }

    /** The company's buildings for its site: name, area, home types, how many are free, rents from, a photo if any. */
    private function buildings(int $companyId): array
    {
        $rows = DB::table('properties as p')->leftJoin('units as u', 'u.property_id', '=', 'p.id')->where('p.company_id', $companyId)
            ->groupBy('p.id', 'p.name', 'p.location')
            ->selectRaw("p.id, p.name, p.location, COUNT(u.id) as units, SUM(u.status = 'vacant') as vacant,
                MIN(NULLIF(u.rent_amount, 0)) as rent_from")
            ->orderBy('p.name')->get();
        // Home types per building, gathered in PHP: GROUP_CONCAT ... SEPARATOR is MySQL-only and breaks the SQLite tests.
        $types = DB::table('units')->whereIn('property_id', $rows->pluck('id'))->whereNotNull('unit_type')
            ->distinct()->orderBy('unit_type')->get(['property_id', 'unit_type'])->groupBy('property_id');
        $photos = \Illuminate\Support\Facades\Schema::hasTable('unit_photos')
            ? DB::table('unit_photos')->whereIn('property_id', $rows->pluck('id'))->orderBy('sort')->get(['property_id', 'path'])->groupBy('property_id')
            : collect();

        return $rows->map(fn ($r) => [
            'name' => $r->name, 'area' => $r->location, 'units' => (int) $r->units, 'vacant' => (int) $r->vacant,
            'rent_from' => $r->rent_from ? (float) $r->rent_from : null,
            'types' => ($t = collect($types->get($r->id))->pluck('unit_type'))->filter(fn ($x) => $x && strcasecmp($x, 'Shop') !== 0)->values()->all(),
            'shops' => $t->contains(fn ($x) => str_contains(strtolower((string) $x), 'shop')),
            'photo' => ($ph = $photos->get($r->id)?->first()) ? \Illuminate\Support\Facades\Storage::disk('public')->url($ph->path) : null,
        ])->all();
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
        abort_unless($request->isMethod('GET'), 404);
        if (Brand::forDomain($request->getHost())) {
            abort_unless(preg_match('#^[a-z0-9-]{1,80}$#', $request->path()), 404);

            return $this->page($request, $request->path());
        }

        // Preview address on scharge itself: /{slug} and /{slug}/{page}, e.g. /danaff and /danaff/about.
        abort_unless(preg_match('#^([a-z0-9-]{2,60})(?:/([a-z0-9-]{1,80}))?$#', $request->path(), $m), 404);
        $brand = Brand::forSlug($m[1]);
        abort_unless($brand, 404);

        return $this->previewSite($request, $brand, $m[2] ?? '');
    }

    /**
     * A company's site at scharge.sharet.africa/{slug}. Published pages are public. Drafts show only to scharge staff
     * and the company's own people (so a site can be checked before it is approved). With no page to show, a branded
     * welcome page with sign-in.
     */
    private function previewSite(Request $request, CompanyBrand $brand, string $slug): View
    {
        $user = $request->user();
        $canDraft = $user && ($user->hasAnyRole(['sysadmin', 'account_manager']) || (int) $user->company_id === (int) $brand->company_id);
        $page = CompanyPage::where('company_id', $brand->company_id)->where('slug', $slug)
            ->when(! $canDraft, fn ($q) => $q->whereNotNull('published_at'))->first();
        $base = '/'.$brand->slug;
        if (! $page) {
            abort_if($slug !== '', 404);

            return view('site.welcome', ['brand' => $brand, 'base' => $base]);
        }
        $draft = $canDraft && (! $page->published_at || $page->draft);
        $menu = CompanyPage::where('company_id', $brand->company_id)->where('show_in_menu', true)
            ->when(! $canDraft, fn ($q) => $q->whereNotNull('published_at'))->orderBy('sort')->get(['slug', 'title']);

        return $this->render($brand, $page, $draft)->with(['base' => $base, 'menu' => $menu, 'previewBanner' => $draft]);
    }

    private function listings(int $companyId): array
    {
        $propertyIds = DB::table('properties')->where('company_id', $companyId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return collect(app(ListingCatalog::class)->items())->filter(fn ($i) => in_array((int) ($i['property_id'] ?? 0), $propertyIds, true))->values()->all();
    }
}
