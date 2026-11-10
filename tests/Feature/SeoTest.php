<?php

namespace Tests\Feature;

use App\Services\ListingCatalog;
use App\Support\SeoLandings;
use Tests\TestCase;

/** Public website SEO: sitemap.xml, robots.txt, llms.txt, landing pages and their structured data. No database needed. */
class SeoTest extends TestCase
{
    private function fakeListings(array $items = []): void
    {
        $this->app->instance(ListingCatalog::class, new class($items) extends ListingCatalog
        {
            public function __construct(private array $fake) {}

            public function items(): array
            {
                return $this->fake;
            }
        });
    }

    private function listing(): array
    {
        return [
            'k' => ListingCatalog::KIND_RENT, 'id' => 1, 'slug' => 'two-bedroom-kilimani-abc12345',
            'url' => route('home.listing', 'two-bedroom-kilimani-abc12345'), 'title' => 'Two Bedroom · Test Court', 'area' => 'Kilimani',
            'price' => 45000.0, 'avail' => 'Available now', 'costs' => 'Water KES 150/m³', 'verified' => false, 'owner' => 'Test Court',
            'tags' => [], 'photo' => 'https://example.test/p.jpg', 'photos' => ['https://example.test/p.jpg'], 'updated' => '2026-11-01',
        ];
    }

    /** @return array<int, array> every JSON-LD block on the page, decoded */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(function ($json) {
            $data = json_decode($json, true);
            $this->assertIsArray($data, 'JSON-LD must parse: '.substr($json, 0, 120));

            return $data;
        }, $m[1]);
    }

    public function test_sitemap_lists_public_pages_landings_and_listings(): void
    {
        $this->fakeListings([$this->listing()]);

        $res = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($res->getContent());
        $this->assertNotFalse($xml);
        $locs = [];
        foreach ($xml->url as $u) {
            $locs[(string) $u->loc] = (string) $u->lastmod;
        }

        $this->assertArrayHasKey(route('home'), $locs);
        $this->assertArrayHasKey(route('home.listings'), $locs);
        foreach (array_keys(SeoLandings::PAGES) as $slug) {
            $this->assertArrayHasKey(route('landing.'.$slug), $locs);
        }
        $this->assertSame('2026-11-01', $locs[route('home.listing', 'two-bedroom-kilimani-abc12345')]);
        $this->assertStringNotContainsString('/login', $res->getContent());
        $this->assertStringNotContainsString('/dashboard', $res->getContent());
    }

    public function test_sitemap_survives_a_database_error(): void
    {
        $this->app->instance(ListingCatalog::class, new class extends ListingCatalog
        {
            public function items(): array
            {
                throw new \RuntimeException('db down');
            }
        });

        $this->get('/sitemap.xml')->assertOk()->assertSee(route('landing.rent-collection-mpesa'), false);
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_robots_in_production_allows_site_and_blocks_private_areas(): void
    {
        $this->app['env'] = 'production';

        $body = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['Disallow: /dashboard', 'Disallow: /admin', 'Disallow: /login', 'Allow: /', 'Sitemap: '.route('sitemap')] as $line) {
            $this->assertStringContainsString($line, $body);
        }
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /$#m', $body);
    }

    public function test_llms_txt_lists_landing_pages(): void
    {
        $this->get('/llms.txt')->assertOk()->assertSee('Rent collection on M-Pesa')->assertSee(route('landing.landlord-app-kenya'), false);
    }

    public function test_every_landing_page_has_title_canonical_h1_and_valid_json_ld(): void
    {
        $this->fakeListings([$this->listing()]);

        foreach (SeoLandings::PAGES as $slug => $page) {
            $url = route('landing.'.$slug);
            $html = $this->get('/'.$slug)->assertOk()->getContent();

            $this->assertStringContainsString('<title>'.e($page['title']).'</title>', $html, $slug);
            $this->assertStringContainsString('<link rel="canonical" href="'.$url.'">', $html, $slug);
            $this->assertStringContainsString('<meta name="robots" content="index,follow', $html, $slug);
            $this->assertSame(1, substr_count($html, '<h1'), $slug.' must have exactly one h1');

            $types = array_map(fn ($b) => $b['@type'] ?? '@graph', $this->jsonLd($html));
            $this->assertContains('FAQPage', $types, $slug);
            $this->assertContains('BreadcrumbList', $types, $slug);
            $this->assertContains('WebPage', $types, $slug);

            $faq = collect($this->jsonLd($html))->firstWhere('@type', 'FAQPage');
            $this->assertCount(count($page['faqs']), $faq['mainEntity']);
        }
    }

    public function test_houses_to_let_shows_real_listings_only(): void
    {
        $this->fakeListings([$this->listing()]);
        $html = $this->get('/houses-to-let-nairobi')->assertOk()->assertSee('Two Bedroom · Test Court')->getContent();
        $this->assertContains('ItemList', array_column($this->jsonLd($html), '@type'));

        $this->fakeListings([]);
        $html = $this->get('/houses-to-let-nairobi')->assertOk()->assertSee('No vacancies are listed right now')->getContent();
        $this->assertNotContains('ItemList', array_column($this->jsonLd($html), '@type'));
    }

    public function test_organization_names_sharet_africa_as_parent_and_footer_cross_links(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $graph = collect($this->jsonLd($html))->firstWhere('@graph')['@graph'];
        $org = collect($graph)->firstWhere('@type', 'Organization');
        $this->assertSame('Sharet Africa Ltd', $org['parentOrganization']['name']);

        foreach (['https://sharet.africa', 'https://breve.sharet.africa', 'https://aiyotickets.com', 'https://eserviz.aiyotickets.com', 'https://loans.sharet.africa', 'https://shoope.africa'] as $site) {
            $this->assertStringContainsString('href="'.$site.'"', $html);
        }
        $this->assertStringNotContainsString('href="https://scharge.sharet.africa"', $html);
        $this->assertStringContainsString('href="'.route('landing.property-management-software-kenya').'"', $html);
    }

    public function test_cross_site_tracker_only_in_production(): void
    {
        $this->get('/')->assertOk()->assertDontSee('sharet-analytics.js');

        $this->app['env'] = 'production';
        config(['services.sharet_analytics.url' => 'https://sharet.africa']);
        $this->get('/')->assertOk()->assertSee('<script defer src="https://sharet.africa/js/sharet-analytics.js" data-site="scharge"></script>', false);
    }

    public function test_unknown_landing_like_path_is_404(): void
    {
        $this->get('/property-management-software-uganda')->assertNotFound();
    }
}
