<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyPage;
use App\Modules\CRM\Controllers\Site\SiteFormController;
use App\Services\ListingCatalog;
use App\Support\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * A white-label company's public data for its OWN website (docs/WHITE_LABEL.md, "Their own website, our data"):
 *  - GET  /api/v1/sites/{slug}          brand, contacts, published pages, buildings and vacant homes (JSON, any origin)
 *  - POST /api/v1/sites/{slug}/viewing  a renter's viewing request from their site (same handling as scharge's form)
 *  - GET  /embed/{slug}.js              a drop-in widget: <div data-scharge-homes></div><script src=".../embed/danaff.js"></script>
 * Only public things: published pages and homes on Open listings. No tenants, money or private data.
 */
class SiteFeedController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $brand = Brand::forSlug($slug);
        abort_unless($brand, 404, 'No site with that name.');
        $base = url('/'.$brand->slug);

        $propertyIds = DB::table('properties')->where('company_id', $brand->company_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $listings = collect(app(ListingCatalog::class)->items())->filter(fn ($i) => in_array((int) ($i['property_id'] ?? 0), $propertyIds, true))
            ->map(fn ($l) => [
                'slug' => $l['slug'], 'title' => $l['title'], 'area' => $l['area'], 'price' => $l['price'],
                'per' => $l['k'] === ListingCatalog::KIND_STAY ? 'night' : 'month', 'availability' => $l['avail'],
                'costs' => $l['costs'], 'photo' => $l['photo'], 'photos' => $l['photos'], 'url' => $l['url'],
            ])->values();

        $buildings = DB::table('properties as p')->leftJoin('units as u', 'u.property_id', '=', 'p.id')->where('p.company_id', $brand->company_id)
            ->groupBy('p.id', 'p.name', 'p.location')
            ->selectRaw("p.name, p.location as area, COUNT(u.id) as homes, SUM(u.status = 'vacant') as free, MIN(NULLIF(u.rent_amount, 0)) as rent_from")
            ->orderBy('p.name')->get()->map(fn ($b) => ['name' => $b->name, 'area' => $b->area, 'homes' => (int) $b->homes, 'free' => (int) $b->free, 'rent_from' => $b->rent_from ? (float) $b->rent_from : null]);

        $pages = CompanyPage::where('company_id', $brand->company_id)->whereNotNull('published_at')->orderBy('sort')
            ->get(['slug', 'title', 'hero_title', 'hero_text', 'body'])
            ->map(fn ($p) => ['slug' => $p->slug, 'title' => $p->title, 'heading' => $p->hero_title, 'intro' => $p->hero_text, 'html' => $p->html(), 'url' => $base.($p->slug ? '/'.$p->slug : '')]);

        return response()->json([
            'brand' => [
                'name' => $brand->display_name, 'tagline' => $brand->tagline, 'colour' => $brand->primary_color,
                'logo' => $brand->logoUrl(), 'icon' => $brand->iconUrl(), 'phone' => $brand->support_phone, 'email' => $brand->support_email,
                'website' => $base, 'login' => url('/login'),
            ],
            'buildings' => $buildings, 'listings' => $listings, 'pages' => $pages,
            'viewing_endpoint' => url('/api/v1/sites/'.$brand->slug.'/viewing'),
            'widget' => url('/embed/'.$brand->slug.'.js'),
        ])->header('Access-Control-Allow-Origin', '*')->header('Cache-Control', 'public, max-age=120');
    }

    /** A viewing request from the company's own site: same checks and routing as scharge's own listing form. */
    public function viewing(Request $request, string $slug, SiteFormController $forms)
    {
        abort_unless(Brand::forSlug($slug), 404, 'No site with that name.');
        $request->headers->set('Accept', 'application/json');

        return $forms->listingRequest($request);
    }

    public function widget(string $slug): Response
    {
        $brand = Brand::forSlug($slug);
        abort_unless($brand, 404);

        return response(view('site.widget-js', ['feed' => url('/api/v1/sites/'.$brand->slug), 'colour' => $brand->primary_color ?: '#DC2626'])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
