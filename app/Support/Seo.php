<?php

namespace App\Support;

/**
 * Search and social metadata for the public website: one place for the indexed pages (sitemap.xml,
 * llms.txt), the brand facts used in schema.org JSON-LD, and small JSON-LD builders.
 * Page titles and descriptions themselves are set in each view with @section('title'/'description').
 */
class Seo
{
    public const NAME = 'scharge';

    public const TITLE = 'scharge | Property management software in Kenya: rent, water & M-Pesa';

    public const DESCRIPTION = 'Property management software built in Kenya: collect rent, service charge and water bills on M-Pesa, with repairs, payouts and reports in one app. From KES 40 per unit a month.';

    public const EMAIL = 'hello@scharge.com';

    /** scharge is a Sharet Africa Ltd product (Organization.parentOrganization). */
    public const PARENT = ['@type' => 'Organization', 'name' => 'Sharet Africa Ltd', 'url' => 'https://sharet.africa'];

    /** Sister products, linked from the footer ("More from Sharet"). [name, what it is, url] */
    public const SISTER_SITES = [
        ['Sharet', 'payments API for Kenya', 'https://sharet.africa'],
        ['Breve', 'invoicing and M-Pesa payments for SMEs', 'https://breve.sharet.africa'],
        ['Aiyotickets', 'events and tickets in Nairobi', 'https://aiyotickets.com'],
        ['eServiz', 'home services in Nairobi', 'https://eserviz.aiyotickets.com'],
        ['Sharet Loans', 'boda boda and e-bike loans', 'https://loans.sharet.africa'],
        ['Shoope', 'safe payments for online shops', 'https://shoope.africa'],
    ];

    /** Indexed pages: route => [summary for llms.txt, sitemap priority, change frequency] */
    public const PAGES = [
        'home' => ['Property management software for Kenya: rent, service charge and water billing collected on M-Pesa, with repairs, payouts and reports. Priced per unit, from KES 40 a month.', '1.0', 'weekly'],
        'home.listings' => ['Vacant homes to rent and short stays from owners on scharge, with rent, water, service charge and deposit shown up front. No viewing fees.', '0.9', 'daily'],
        'home.airbnb' => ['Tools for Airbnb and short-stay hosts in Kenya: one calendar, smart-lock codes, cleaner checklists and M-Pesa payouts, flat fee per listing.', '0.8', 'monthly'],
        'home.developers' => ['For developers and landowners: buyer instalments, snag lists, service charge and handover set up before buyers move in.', '0.8', 'monthly'],
        'home.api' => ['scharge API and white label: vacancies and booking calendars on your own website, or a tenant/buyer app in your brand.', '0.6', 'monthly'],
        'host.register' => ['Register a short-stay (BnB) unit on scharge in a few minutes.', '0.6', 'monthly'],
        'landing.property-management-software-kenya' => ['What property management software does for a Kenyan landlord or agent, what it costs per unit, and how scharge sets you up.', '0.9', 'monthly'],
        'landing.rent-collection-mpesa' => ['Collecting rent on M-Pesa Paybill, Till or STK push straight into your own account, matched to the right unit with receipts.', '0.9', 'monthly'],
        'landing.landlord-app-kenya' => ['A landlord app for Kenya: who has paid, arrears, water bills, repairs and payouts on your phone.', '0.8', 'monthly'],
        'landing.tenant-management-software' => ['Tenant management and rent reminders: tenant records, invoices by SMS, reminders, receipts, deposits and move-outs.', '0.8', 'monthly'],
        'landing.houses-to-let-nairobi' => ['Houses and apartments to let in Nairobi and nearby towns, from owners on scharge, with every cost shown up front.', '0.8', 'daily'],
        'home.credits' => ['Photo credits for the scharge website.', '0.2', 'yearly'],
    ];

    /** Kenya-focused search phrases the site is written for (also used as meta keywords, which only Bing still reads). */
    public const KEYWORDS = 'property management software Kenya, rent collection app Kenya, M-Pesa rent collection, landlord app Nairobi, water billing software Kenya, service charge management, tenant management system Kenya, Airbnb host tools Kenya, houses to rent Nairobi';

    /** Organization + WebSite, on every page. */
    public static function siteGraph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/').'#organization',
                    'name' => self::NAME,
                    'url' => url('/'),
                    'logo' => asset('icon-512.png'),
                    'image' => asset('assets/images/og-image.png'),
                    'email' => self::EMAIL,
                    'description' => self::DESCRIPTION,
                    'areaServed' => ['@type' => 'Country', 'name' => 'Kenya'],
                    'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Nairobi', 'addressCountry' => 'KE'],
                    'contactPoint' => [['@type' => 'ContactPoint', 'contactType' => 'sales', 'email' => self::EMAIL, 'areaServed' => 'KE', 'availableLanguage' => ['en', 'sw']]],
                    'parentOrganization' => self::PARENT,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'url' => url('/'),
                    'name' => self::NAME,
                    'inLanguage' => 'en-KE',
                    'publisher' => ['@id' => url('/').'#organization'],
                ],
            ],
        ];
    }

    /** The product itself (home page). */
    public static function softwareApplication(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'scharge',
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Property management software',
            'operatingSystem' => 'Web, Android, iOS',
            'url' => url('/'),
            'description' => self::DESCRIPTION,
            'areaServed' => 'KE',
            'featureList' => ['Rent and service charge invoicing', 'M-Pesa Paybill, Till and STK push payments matched to units', 'Water meter readings and water billing',
                'Tenant SMS reminders and receipts', 'Repairs and maintenance requests', 'Owner payouts and reports', 'Short-stay (Airbnb) calendar and cleaner checklists'],
            'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => 'KES', 'lowPrice' => '40', 'offerCount' => '3',
                'description' => 'Priced per unit per month by property type and area. Vacant units billed at half price.'],
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    /** A landing page's own WebPage node, tied to the site graph (about the product, in Kenya). */
    public static function webPage(string $url, string $name, string $description): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'inLanguage' => 'en-KE',
            'isPartOf' => ['@id' => url('/').'#website'],
            'about' => ['@type' => 'SoftwareApplication', 'name' => 'scharge', 'applicationCategory' => 'BusinessApplication', 'url' => url('/')],
            'spatialCoverage' => ['@type' => 'Country', 'name' => 'Kenya'],
        ];
    }

    /** @param  array<int, array{0: string, 1: string}>  $faqs  [question, answer (may contain simple HTML)] */
    public static function faq(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($f[1]))],
            ], $faqs),
        ];
    }

    /** One public listing (ListingCatalog item) as an Accommodation with an Offer. */
    public static function listing(array $item): array
    {
        $stay = $item['k'] === \App\Services\ListingCatalog::KIND_STAY;

        return array_filter([
            '@type' => $stay ? 'Accommodation' : 'Apartment',
            '@id' => $item['url'].'#listing',
            'name' => $item['title'],
            'url' => $item['url'],
            'description' => $item['title'].' in '.$item['area'].'. '.$item['costs'].'.',
            'image' => array_values(array_filter($item['photos'] ?? [])) ?: null,
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $item['area'], 'addressCountry' => 'KE'],
            'offers' => [
                '@type' => 'Offer',
                'url' => $item['url'],
                'price' => (string) $item['price'],
                'priceCurrency' => 'KES',
                'availability' => 'https://schema.org/InStock',
                'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut',
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => (string) $item['price'], 'priceCurrency' => 'KES', 'unitCode' => $stay ? 'DAY' : 'MON'],
            ],
        ]);
    }

    /** Listings page: an ItemList of the live listings. */
    public static function listingList(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Vacant homes and short stays on scharge',
            'numberOfItems' => count($items),
            'itemListElement' => array_values(array_map(fn ($item, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $item['url'], 'item' => self::listing($item)], $items, array_keys($items))),
        ];
    }

    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]], $crumbs, array_keys($crumbs)),
        ];
    }

    /** Safe <script type="application/ld+json"> output (no "</script>" breakouts). */
    public static function script(array $data): string
    {
        return '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
    }
}
