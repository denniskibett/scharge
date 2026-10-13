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
.footer-brand{display:flex;flex-direction:column;align-items:flex-start;gap:14px;max-width:22rem}.footer-brand p{margin:0;color:var(--on-dark);font-size:.95rem;line-height:1.5}.site-footer .footer-brand .btn{color:var(--white);min-height:44px}
.hp{position:absolute!important;left:-10000px;width:1px;height:1px;overflow:hidden}
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
