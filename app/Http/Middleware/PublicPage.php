<?php

namespace App\Http\Middleware;

use App\Support\CompanyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages (scharge's home and listings, sitemap, host building search, company websites, webhooks, feeds) show
 * what every visitor sees, whoever is signed in. Without this, a landlord signed in to scharge who opens /listings or
 * another company's website would get only their own company's units (the company scope on the models), and a
 * cached page could be built from that one company's rows. Each controller still decides what is public.
 */
class PublicPage
{
    public function handle(Request $request, Closure $next): Response
    {
        return CompanyAccess::bypass(fn () => $next($request));
    }
}
