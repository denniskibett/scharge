<?php

namespace App\Http\Controllers;

use App\Services\ListingCatalog;
use App\Support\Seo;
use Illuminate\Support\Carbon;

/** sitemap.xml and robots.txt for search engines, llms.txt for AI assistants. Built from App\Support\Seo. */
class SeoController extends Controller
{
    public function sitemap(ListingCatalog $catalog)
    {
        $views = ['home' => 'welcome', 'home.listings' => 'home/listings', 'home.airbnb' => 'home/airbnb', 'home.developers' => 'home/developers',
            'home.api' => 'home/api', 'host.register' => 'host/register', 'home.credits' => 'home/credits'];

        $urls = collect(Seo::PAGES)->map(function ($page, $route) use ($views) {
            [, $priority, $freq] = $page;
            $file = resource_path('views/'.($views[$route] ?? 'welcome').'.blade.php');

            return ['loc' => route($route), 'lastmod' => Carbon::createFromTimestamp(is_file($file) ? filemtime($file) : time())->toDateString(), 'priority' => $priority, 'freq' => $freq];
        })->values();

        try {
            $listings = $catalog->items();
        } catch (\Throwable) {
            $listings = []; // never let a database hiccup break the sitemap
        }
        foreach ($listings as $item) {
            $urls->push(['loc' => $item['url'], 'lastmod' => now()->toDateString(), 'priority' => '0.7', 'freq' => 'daily', 'image' => $item['photos'][0] ?? null]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc><lastmod>'.$u['lastmod'].'</lastmod><changefreq>'.$u['freq'].'</changefreq><priority>'.$u['priority'].'</priority>'
                .(! empty($u['image']) && str_starts_with($u['image'], 'http') ? '<image:image><image:loc>'.e($u['image']).'</image:loc></image:image>' : '')
                ."</url>\n";
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8'])->setPublic()->setMaxAge(3600);
    }

    public function robots()
    {
        $lines = ['User-agent: *'];
        if (app()->environment('production')) {
            // The app behind the login, form endpoints and the analytics beacon are not for search results.
            foreach (['/dashboard', '/admin', '/crm', '/host$', '/host/units', '/login', '/register', '/password', '/forgot-password', '/reset-password',
                '/verify-email', '/public/invoice', '/feedback/', '/t$', '/waitlist', '/book-a-demo', '/sandbox-keys', '/listings/request', '/listings/alert', '/api/', '/webhooks/'] as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = 'Allow: /';
        } else {
            $lines[] = 'Disallow: /'; // staging and local copies must never be indexed
        }
        array_push($lines, '', 'Sitemap: '.route('sitemap'), '');

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8'])->setPublic()->setMaxAge(3600);
    }

    /** https://llmstxt.org: a plain summary AI assistants can read to describe scharge correctly. */
    public function llms()
    {
        $lines = ['# scharge', '', '> '.Seo::DESCRIPTION.' Based in Nairobi, Kenya; live in Kenya, with Uganda, Tanzania and Rwanda next.', '', '## Pages', ''];
        foreach (Seo::PAGES as $route => [$summary]) {
            $lines[] = '- ['.match ($route) {
                'home' => 'Overview and pricing', 'home.listings' => 'Open listings', 'home.airbnb' => 'Airbnb and short-stay hosts',
                'home.developers' => 'Developers and landowners', 'home.api' => 'API and white label', 'host.register' => 'List a BnB', default => 'Photo credits',
            }.']('.route($route).'): '.$summary;
        }
        array_push($lines, '', '## Contact', '', '- Sales and support: '.Seo::EMAIL, '', '## Optional', '', '- [Sitemap]('.route('sitemap').')', '');

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8'])->setPublic()->setMaxAge(3600);
    }
}
