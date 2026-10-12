<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'scharge | Property management for Africa, starting in Kenya')</title>
<meta name="description" content="@yield('description')">
<meta name="theme-color" content="#DC2626">
<meta property="og:title" content="{!! $__env->yieldContent('og_title') ?: $__env->yieldContent('title') !!}">
<meta property="og:description" content="{!! $__env->yieldContent('og_description') ?: $__env->yieldContent('description') !!}">
<meta property="og:image" content="{{ asset('assets/images/og-image.png') }}">
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
.footer-brand{display:flex;flex-direction:column;align-items:flex-start;gap:14px;max-width:22rem}.footer-brand p{margin:0;color:var(--on-dark);font-size:.95rem;line-height:1.5}.site-footer .footer-brand .btn{color:var(--white);min-height:44px}</style>
@stack('styles')
