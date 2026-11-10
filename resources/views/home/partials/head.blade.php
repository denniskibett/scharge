<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    // Sections set with @section('x', '...') are already escaped by Blade, so they are printed raw below.
    $seoTitle = trim($__env->yieldContent('title')) ?: e(\App\Support\Seo::TITLE);
    $seoDesc = trim($__env->yieldContent('description')) ?: e(\App\Support\Seo::DESCRIPTION);
    $seoCanonical = trim($__env->yieldContent('canonical')) ?: e(url()->current()); // never the query string (utm tags, filters)
    $seoRobots = trim($__env->yieldContent('robots')) ?: 'index,follow,max-image-preview:large';
    $seoImage = trim($__env->yieldContent('og_image')) ?: e(asset('assets/images/og-image.png'));
@endphp
<title>{!! $seoTitle !!}</title>
<meta name="description" content="{!! $seoDesc !!}">
<meta name="keywords" content="{{ \App\Support\Seo::KEYWORDS }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{!! $seoCanonical !!}">
<meta name="theme-color" content="#DC2626">
<meta name="geo.region" content="KE">
<meta property="og:type" content="website">
<meta property="og:site_name" content="scharge">
<meta property="og:locale" content="en_KE">
<meta property="og:url" content="{!! $seoCanonical !!}">
<meta property="og:title" content="{!! trim($__env->yieldContent('og_title')) ?: $seoTitle !!}">
<meta property="og:description" content="{!! trim($__env->yieldContent('og_description')) ?: $seoDesc !!}">
<meta property="og:image" content="{!! $seoImage !!}">
@unless (trim($__env->yieldContent('og_image')))
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endunless
<meta property="og:image:alt" content="scharge: property management for Kenya">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{!! trim($__env->yieldContent('og_title')) ?: $seoTitle !!}">
<meta name="twitter:description" content="{!! trim($__env->yieldContent('og_description')) ?: $seoDesc !!}">
<meta name="twitter:image" content="{!! $seoImage !!}">
@if (config('analytics.google_site_verification'))
<meta name="google-site-verification" content="{{ config('analytics.google_site_verification') }}">
@endif
@if (config('analytics.bing_site_verification'))
<meta name="msvalidate.01" content="{{ config('analytics.bing_site_verification') }}">
@endif
{!! \App\Support\Seo::script(\App\Support\Seo::siteGraph()) !!}
@stack('jsonld')
@include('home.partials.analytics')
@if (app()->environment('production') && config('services.sharet_analytics.url'))
{{-- Sharet growth hub tracker (cross-site; no cookies, honours DNT/GPC). Website forms report conversions via the sc:conversion event (public/assets/js/site-forms.js). --}}
<script defer src="{{ rtrim(config('services.sharet_analytics.url'), '/') }}/js/sharet-analytics.js" data-site="scharge"></script>
<script>(function(){var M={'/book-a-demo':'lead','/waitlist':'lead','/listings/alert':'lead','/listings/request':'booking','/sandbox-keys':'api_keys'};document.addEventListener('sc:conversion',function(e){var n=M[(e.detail||{}).form];if(n)(window.sa=window.sa||[]).push(['conversion',n]);});})();</script>
@endif
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
{{-- Log in / Register are real links here (not the demo modal buttons), so centre them like the buttons they replace. --}}
<style>.nav-auth a.nav-login,.nav-auth form{display:inline-flex;align-items:center;justify-content:center;margin:0}
.footer-brand{display:flex;flex-direction:column;align-items:flex-start;gap:14px;max-width:22rem}.footer-brand p{margin:0;color:var(--on-dark);font-size:.95rem;line-height:1.5}.site-footer .footer-brand .btn{color:var(--white);min-height:44px}
.footer-links{display:flex;flex-direction:column;gap:6px}.footer-links b{color:var(--white)}
.hp{position:absolute!important;left:-10000px;width:1px;height:1px;overflow:hidden}
/* Long option text (e.g. full country names) must not widen the page on phones */
.waitlist select,.waitlist input,.form select,.form input,.form textarea{min-width:0;max-width:100%}
[aria-invalid="true"]{border-color:var(--red)!important;box-shadow:0 0 0 4px rgba(220,38,38,.12)}
.field-error{display:block;color:var(--alert);font-size:.82rem;font-weight:600;margin-top:-4px;flex:1 1 100%}
.waitlist [hidden]{display:none}
.site-alert{position:fixed;inset:0;z-index:1000;margin:auto;width:min(440px,calc(100vw - 32px));height:fit-content;max-height:calc(100svh - 32px);overflow-y:auto;display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center;padding:32px 28px 26px;background:var(--white);border-radius:26px;box-shadow:0 30px 80px rgba(0,0,0,.35);animation:modal-in .35s var(--ease) both}
.site-alert[hidden],.site-alert-backdrop[hidden]{display:none}
.site-alert-icon{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;background:var(--ok-bg);color:var(--ok-ink);font:700 28px var(--font-body)}
.site-alert.is-error .site-alert-icon{background:var(--blush);color:var(--red)}
.site-alert h2{font-size:1.35rem;line-height:1.25;margin:0}
.site-alert p{color:var(--muted);margin:0}
.site-alert-list{text-align:left;margin:0;padding-left:20px;color:var(--ink);font-size:.95rem;display:flex;flex-direction:column;gap:4px}
.site-alert .btn{min-width:140px;margin-top:6px}</style>
@stack('styles')
