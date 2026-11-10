{{-- A white-label company's own website page (SiteController). Brand colour, logo and pages from the company; no scharge
     marketing. docs/COMPANY_WEBSITE.md --}}
@php
  $c = $brand->primary_color ?: '#465FFF';
  $title = ($page->slug === '' ? $brand->display_name : ($page->title.' · '.$brand->display_name));
  $alert = session('site_alert');
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) ($page->hero_text ?: $page->body)), 155) }}">
@if ($preview)<meta name="robots" content="noindex,nofollow">@endif
@if ($brand->iconUrl())<link rel="icon" href="{{ $brand->iconUrl() }}">@endif
<style>
:root { --brand: {{ $c }}; --ink: #111827; --muted: #6b7280; --line: #e5e7eb; --bg: #ffffff; --soft: #f9fafb; }
@media (prefers-color-scheme: dark) { :root { --ink: #f3f4f6; --muted: #9ca3af; --line: #1f2937; --bg: #0b0f17; --soft: #111827; } }
* { box-sizing: border-box; }
body { margin: 0; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: var(--bg); }
a { color: var(--brand); }
.wrap { max-width: 1080px; margin: 0 auto; padding: 0 16px; }
header { border-bottom: 1px solid var(--line); }
.bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 68px; flex-wrap: wrap; }
.logo img { max-height: 40px; width: auto; display: block; }
.logo { font-weight: 700; font-size: 1.15rem; color: var(--ink); text-decoration: none; }
nav { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
nav a { color: var(--ink); text-decoration: none; padding: 8px 10px; border-radius: 8px; font-size: .95rem; }
nav a[aria-current="page"] { color: var(--brand); font-weight: 600; }
.btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 10px; background: var(--brand); color: #fff; text-decoration: none; font-weight: 600; border: 0; font-size: 1rem; cursor: pointer; }
.btn--ghost { background: transparent; color: var(--brand); border: 1px solid var(--brand); }
.hero { padding: 56px 0 40px; background: linear-gradient(180deg, color-mix(in srgb, var(--brand) 10%, var(--bg)), var(--bg)); }
.hero h1 { font-size: clamp(1.8rem, 4vw, 2.8rem); line-height: 1.15; margin: 0 0 12px; }
.hero p { color: var(--muted); max-width: 640px; margin: 0; font-size: 1.1rem; }
.hero img { width: 100%; max-height: 420px; object-fit: cover; border-radius: 16px; margin-top: 28px; }
main .body { padding: 32px 0; max-width: 760px; }
.grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); padding: 8px 0 40px; }
.card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: var(--bg); }
.card img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; background: var(--soft); }
.card .in { padding: 14px; }
.card h3 { margin: 0 0 4px; font-size: 1.05rem; }
.price { font-weight: 700; }
.muted { color: var(--muted); font-size: .92rem; }
form.f { display: grid; gap: 10px; max-width: 520px; }
form.f input, form.f textarea { font: inherit; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--bg); color: var(--ink); }
details summary { cursor: pointer; color: var(--brand); font-weight: 600; margin-top: 10px; }
.notice { padding: 12px 14px; border-radius: 10px; background: color-mix(in srgb, var(--brand) 12%, var(--bg)); margin: 16px 0; }
.preview { background: #fef3c7; color: #92400e; text-align: center; padding: 8px; font-size: .9rem; }
.hp { position: absolute; left: -9999px; }
footer { border-top: 1px solid var(--line); padding: 28px 0; color: var(--muted); font-size: .9rem; margin-top: 24px; }
</style>
</head>
<body>
@if ($preview)<div class="preview">Preview of your changes. Visitors see them once scharge approves.</div>@endif
<header><div class="wrap bar">
  <a class="logo" href="/">@if ($brand->logoUrl())<img src="{{ $brand->logoUrl() }}" alt="{{ $brand->display_name }}">@else{{ $brand->display_name }}@endif</a>
  <nav aria-label="Site">
    @foreach ($menu as $m)<a href="/{{ $m->slug }}" @if ($m->slug === $page->slug) aria-current="page" @endif>{{ $m->title }}</a>@endforeach
    <a class="btn btn--ghost" href="/login">Tenant & owner login</a>
  </nav>
</div></header>

<main>
  <section class="hero"><div class="wrap">
    <h1>{{ $page->hero_title ?: $page->title }}</h1>
    @if ($page->hero_text)<p>{{ $page->hero_text }}</p>@endif
    @if ($page->hero_image)<img src="{{ $page->heroUrl() }}" alt="">@endif
  </div></section>

  <div class="wrap">
    @if ($alert)<p class="notice" role="status"><strong>{{ $alert['title'] }}</strong> {{ $alert['message'] }}</p>@endif
    @if (session('site_status'))<p class="notice" role="status">{{ session('site_status') }}</p>@endif
    @if ($errors->any())<p class="notice" role="alert">{{ $errors->first() }}</p>@endif

    @if ($page->body)<div class="body">{!! $page->html() !!}</div>@endif

    @if ($listings)
      <h2>{{ $page->template === 'home' ? 'Available now' : 'Vacant units' }}</h2>
      <div class="grid">
        @foreach ($page->template === 'home' ? array_slice($listings, 0, 3) : $listings as $l)
          <article class="card">
            <img src="{{ $l['photo'] }}" alt="" loading="lazy">
            <div class="in">
              <h3>{{ $l['title'] }}</h3>
              <p class="muted">{{ $l['area'] }} · {{ $l['avail'] }}</p>
              <p class="price">KES {{ number_format($l['price']) }}{{ $l['k'] === \App\Services\ListingCatalog::KIND_STAY ? ' / night' : ' / month' }}</p>
              <p class="muted">{{ $l['costs'] }}</p>
              <details><summary>Book a viewing</summary>
                <form class="f" method="POST" action="{{ route('site.listings.request') }}">
                  @csrf
                  <input name="name" required maxlength="120" placeholder="Your name" aria-label="Your name">
                  <input name="phone" type="tel" required maxlength="40" placeholder="Phone (WhatsApp)" aria-label="Phone">
                  <input name="when" maxlength="120" placeholder="Preferred day, e.g. Saturday morning" aria-label="Preferred day">
                  <input type="hidden" name="listing_title" value="{{ $l['title'] }}"><input type="hidden" name="listing_area" value="{{ $l['area'] }}">
                  <input type="hidden" name="listing_owner" value="{{ $l['owner'] }}"><input type="hidden" name="listing_action" value="Book a viewing">
                  <input type="hidden" name="listing_slug" value="{{ $l['slug'] }}"><input name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                  <button class="btn" @disabled($preview)>Request viewing</button>
                </form>
              </details>
            </div>
          </article>
        @endforeach
      </div>
      @if ($page->template === 'home' && count($listings) > 3)<p><a class="btn btn--ghost" href="/rentals">See all {{ count($listings) }} homes</a></p>@endif
    @elseif ($page->template === 'rentals')
      <p class="muted" style="padding:24px 0">No vacant units right now. Leave your number on the contact page and we will tell you when one opens.</p>
    @endif

    @if ($page->template === 'contact')
      <form class="f" method="POST" action="{{ route('site.contact') }}" style="padding-bottom:32px">
        @csrf
        <input name="name" required maxlength="120" placeholder="Your name" aria-label="Your name" value="{{ old('name') }}">
        <input name="phone" type="tel" required maxlength="40" placeholder="Phone" aria-label="Phone" value="{{ old('phone') }}">
        <textarea name="message" rows="4" maxlength="1000" placeholder="How can we help?" aria-label="Message">{{ old('message') }}</textarea>
        <input name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="btn" @disabled($preview)>Send</button>
      </form>
      @if ($brand->support_phone || $brand->support_email)<p class="muted">Or call {{ $brand->support_phone }} {{ $brand->support_phone && $brand->support_email ? '·' : '' }} {{ $brand->support_email }}</p>@endif
    @endif
  </div>
</main>

<footer><div class="wrap">
  © {{ now()->year }} {{ $brand->display_name }}{{ $brand->support_phone ? ' · '.$brand->support_phone : '' }}{{ $brand->support_email ? ' · '.$brand->support_email : '' }}
  @if ($brand->show_powered_by) · Powered by scharge @endif
</div></footer>
</body>
</html>
