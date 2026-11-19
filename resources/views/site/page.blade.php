{{-- A white-label company's own website page (SiteController). Brand colour, logo and pages from the company; no scharge
     marketing. docs/COMPANY_WEBSITE.md. $brand, $page, $menu, $listings, $buildings, $preview, optional $base ('/danaff'). --}}
@php
  $c = $brand->primary_color ?: '#465FFF';
  $base = $base ?? '';
  $title = ($page->slug === '' ? $brand->display_name : ($page->title.' · '.$brand->display_name));
  $alert = session('site_alert');
  $buildings = $buildings ?? [];
  $homes = collect($buildings)->sum('units');
  $free = collect($buildings)->sum('vacant');
  $areas = collect($buildings)->pluck('area')->filter()->map(fn ($a) => trim(\Illuminate\Support\Str::afterLast($a, ',')))->unique()->values();
  $heroPhoto = $page->hero_image ? $page->heroUrl() : (collect($buildings)->pluck('photo')->filter()->first());
  $kes = fn ($v) => 'KES '.number_format((float) $v);
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --brand: {{ $c }}; --ink: #101828; --muted: #667085; --line: #EAECF0; --bg: #ffffff; --soft: #F8FAFC; --tint: color-mix(in srgb, var(--brand) 9%, #fff); }
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { margin: 0; font: 16px/1.6 Outfit, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased; }
a { color: var(--brand); }
img { max-width: 100%; }
.wrap { max-width: 1160px; margin: 0 auto; padding: 0 20px; }

/* header */
header { position: sticky; top: 0; z-index: 20; background: rgb(255 255 255 / .85); backdrop-filter: saturate(160%) blur(12px); border-bottom: 1px solid var(--line); }
.bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 70px; }
.logo { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.2rem; color: var(--ink); text-decoration: none; letter-spacing: -.01em; }
.logo img { max-height: 40px; width: auto; display: block; }
.logo .dot { width: 30px; height: 30px; border-radius: 9px; background: var(--brand); display: grid; place-items: center; color: #fff; font-size: .95rem; }
nav { display: flex; gap: 2px; align-items: center; flex-wrap: wrap; }
nav a { color: var(--muted); text-decoration: none; padding: 8px 12px; border-radius: 9px; font-size: .95rem; font-weight: 500; transition: color .15s, background .15s; }
nav a:hover { color: var(--ink); background: var(--soft); }
nav a[aria-current="page"] { color: var(--brand); background: var(--tint); }
.menu-btn { display: none; }
@media (max-width: 760px) {
  .menu-btn { display: inline-flex; border: 1px solid var(--line); background: #fff; border-radius: 10px; padding: 8px 12px; font: inherit; color: var(--ink); }
  nav { display: none; position: absolute; left: 0; right: 0; top: 70px; flex-direction: column; align-items: stretch; background: #fff; border-bottom: 1px solid var(--line); padding: 10px 20px 16px; }
  nav.open { display: flex; }
}

/* buttons */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 0 20px; border-radius: 12px; background: var(--brand); color: #fff; text-decoration: none; font-weight: 600; border: 0; font: inherit; font-weight: 600; cursor: pointer; transition: transform .1s, filter .15s, box-shadow .15s; box-shadow: 0 6px 18px -8px color-mix(in srgb, var(--brand) 70%, transparent); }
.btn:hover { filter: brightness(1.06); }
.btn:active { transform: scale(.97); }
.btn[disabled] { opacity: .55; cursor: not-allowed; }
.btn--ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); box-shadow: none; }
.btn--sm { min-height: 38px; padding: 0 14px; font-size: .92rem; }

/* hero */
.hero { position: relative; overflow: hidden; padding: clamp(48px, 8vw, 96px) 0 clamp(40px, 6vw, 72px); background:
  radial-gradient(900px 400px at 85% -10%, color-mix(in srgb, var(--brand) 18%, transparent), transparent 70%),
  radial-gradient(700px 360px at -10% 110%, color-mix(in srgb, var(--brand) 12%, transparent), transparent 70%), var(--soft); }
.hero-grid { display: grid; gap: 40px; align-items: center; grid-template-columns: 1.1fr .9fr; }
@media (max-width: 900px) { .hero-grid { grid-template-columns: 1fr; } }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: #fff; border: 1px solid var(--line); color: var(--muted); font-size: .85rem; font-weight: 500; }
.eyebrow b { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); box-shadow: 0 0 0 4px var(--tint); }
.hero h1 { font-size: clamp(2rem, 4.6vw, 3.3rem); line-height: 1.08; letter-spacing: -.025em; margin: 18px 0 14px; }
.hero p.lead { color: var(--muted); font-size: 1.12rem; max-width: 580px; margin: 0 0 26px; }
.hero .actions { display: flex; flex-wrap: wrap; gap: 10px; }
.hero-art { position: relative; aspect-ratio: 4/3.2; border-radius: 24px; overflow: hidden; border: 1px solid var(--line); background:
  linear-gradient(135deg, color-mix(in srgb, var(--brand) 85%, #000), color-mix(in srgb, var(--brand) 55%, #fff)); box-shadow: 0 30px 60px -30px rgb(16 24 40 / .35); }
.hero-art img { width: 100%; height: 100%; object-fit: cover; display: block; }
.hero-art .skyline { position: absolute; inset: auto 0 0 0; height: 70%; opacity: .9; }
.hero-art .badge { position: absolute; left: 16px; bottom: 16px; background: #fff; border-radius: 14px; padding: 10px 14px; font-size: .9rem; box-shadow: 0 10px 30px -12px rgb(16 24 40 / .4); }
.hero-art .badge strong { display: block; font-size: 1.25rem; color: var(--brand); }

/* stats */
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 36px; }
.stat { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; }
.stat strong { display: block; font-size: 1.5rem; letter-spacing: -.02em; }
.stat span { color: var(--muted); font-size: .88rem; }

/* sections */
section.block { padding: clamp(40px, 6vw, 72px) 0; }
.block h2 { font-size: clamp(1.5rem, 3vw, 2.1rem); letter-spacing: -.02em; margin: 0 0 8px; }
.block .sub { color: var(--muted); margin: 0 0 26px; max-width: 640px; }
.grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
.card { border: 1px solid var(--line); border-radius: 18px; overflow: hidden; background: #fff; transition: transform .2s ease, box-shadow .2s ease; }
.card:hover { transform: translateY(-3px); box-shadow: 0 18px 40px -22px rgb(16 24 40 / .3); }
.card .ph { position: relative; aspect-ratio: 16/10; background: linear-gradient(135deg, var(--tint), #fff); display: grid; place-items: center; color: var(--brand); }
.card .ph img { width: 100%; height: 100%; object-fit: cover; display: block; }
.card .tag { position: absolute; top: 12px; left: 12px; background: #fff; color: var(--ink); border-radius: 999px; padding: 4px 10px; font-size: .8rem; font-weight: 600; box-shadow: 0 4px 12px -6px rgb(16 24 40 / .3); }
.card .tag.free { background: #ECFDF3; color: #027A48; }
.card .in { padding: 16px 18px 18px; }
.card h3 { margin: 0 0 4px; font-size: 1.1rem; letter-spacing: -.01em; }
.muted { color: var(--muted); font-size: .93rem; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0 0; }
.chip { background: var(--soft); border: 1px solid var(--line); border-radius: 999px; padding: 3px 10px; font-size: .8rem; color: var(--ink); }
.price { font-weight: 700; font-size: 1.1rem; margin: 8px 0 0; }
.price small { color: var(--muted); font-weight: 500; font-size: .85rem; }

/* features */
.features { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.feature { border: 1px solid var(--line); border-radius: 16px; padding: 18px; background: #fff; }
.feature i { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: var(--tint); color: var(--brand); font-style: normal; font-size: 1.1rem; margin-bottom: 10px; }
.feature b { display: block; margin-bottom: 4px; }

/* body copy from the editor */
.prose { max-width: 760px; }
.prose h2 { font-size: 1.6rem; letter-spacing: -.02em; margin: 28px 0 10px; }
.prose h3 { font-size: 1.15rem; margin: 22px 0 8px; }
.prose p, .prose li { color: #344054; }
.prose ul { padding-left: 1.2rem; }

/* forms */
details summary { cursor: pointer; color: var(--brand); font-weight: 600; margin-top: 12px; list-style: none; }
details summary::-webkit-details-marker { display: none; }
form.f { display: grid; gap: 10px; margin-top: 10px; }
form.f input, form.f textarea { font: inherit; padding: 11px 13px; border: 1px solid var(--line); border-radius: 11px; background: #fff; color: var(--ink); transition: border-color .15s, box-shadow .15s; }
form.f input:focus, form.f textarea:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px var(--tint); }
.contact { display: grid; gap: 24px; grid-template-columns: 1fr 1fr; align-items: start; }
@media (max-width: 860px) { .contact { grid-template-columns: 1fr; } }
.panel { border: 1px solid var(--line); border-radius: 18px; padding: 22px; background: #fff; }
.panel .row { display: flex; gap: 12px; align-items: flex-start; padding: 10px 0; border-bottom: 1px dashed var(--line); }
.panel .row:last-child { border-bottom: 0; }
.panel .row i { font-style: normal; color: var(--brand); width: 22px; }
.map { width: 100%; aspect-ratio: 16/10; border: 0; border-radius: 14px; margin-top: 14px; background: var(--soft); }

.notice { padding: 12px 14px; border-radius: 12px; background: var(--tint); margin: 16px 0; }
.preview { background: #fef3c7; color: #92400e; text-align: center; padding: 8px; font-size: .9rem; position: relative; z-index: 30; }
.hp { position: absolute; left: -9999px; }

/* call to action + footer */
.cta { border-radius: 24px; padding: clamp(26px, 5vw, 44px); background: linear-gradient(135deg, var(--brand), color-mix(in srgb, var(--brand) 65%, #000)); color: #fff; display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: space-between; }
.cta h2 { color: #fff; margin: 0 0 6px; }
.cta p { margin: 0; opacity: .85; }
.cta .btn { background: #fff; color: var(--brand); box-shadow: none; }
footer { border-top: 1px solid var(--line); padding: 30px 0; color: var(--muted); font-size: .9rem; margin-top: 40px; }
footer .wrap { display: flex; flex-wrap: wrap; gap: 10px 24px; justify-content: space-between; }

/* motion */
.reveal { opacity: 0; transform: translateY(14px); transition: opacity .55s ease, transform .55s cubic-bezier(.2,.7,.2,1); }
.reveal.in { opacity: 1; transform: none; }
.hero .reveal { transition-delay: .05s; }
@media (prefers-reduced-motion: reduce) { .reveal { opacity: 1; transform: none; transition: none; } .card:hover { transform: none; } html { scroll-behavior: auto; } }
</style>
</head>
<body>
@if ($preview)<div class="preview">Preview of your changes. Visitors see them once scharge approves.</div>@endif
<header><div class="wrap bar">
  <a class="logo" href="{{ $base ?: '/' }}">@if ($brand->logoUrl())<img src="{{ $brand->logoUrl() }}" alt="{{ $brand->display_name }}">@else<span class="dot">{{ mb_substr($brand->display_name, 0, 1) }}</span>{{ $brand->display_name }}@endif</a>
  <button class="menu-btn" type="button" onclick="document.getElementById('site-nav').classList.toggle('open')" aria-controls="site-nav">Menu</button>
  <nav id="site-nav" aria-label="Site">
    @foreach ($menu as $m)<a href="{{ $base }}/{{ $m->slug }}" @if ($m->slug === $page->slug) aria-current="page" @endif>{{ $m->title }}</a>@endforeach
    <a class="btn btn--sm" href="/login" style="margin-left:6px">Tenant login</a>
  </nav>
</div></header>

<main>
  {{-- Hero --}}
  <section class="hero"><div class="wrap">
    <div class="hero-grid">
      <div class="reveal">
        @if ($areas->isNotEmpty())<span class="eyebrow"><b></b>{{ $areas->join(' · ') }}</span>@endif
        <h1>{{ $page->hero_title ?: $page->title }}</h1>
        @if ($page->hero_text)<p class="lead">{{ $page->hero_text }}</p>@endif
        <div class="actions">
          @if ($page->template !== 'rentals')<a class="btn" href="{{ $base }}/rentals">See homes to rent</a>@endif
          @if ($page->template !== 'contact')<a class="btn btn--ghost" href="{{ $base }}/contact">Contact us</a>@endif
          @if ($page->template === 'contact' && $brand->support_phone)<a class="btn" href="tel:{{ $brand->support_phone }}">Call {{ $brand->support_phone }}</a>@endif
        </div>
        @if ($page->template === 'home' && $homes)
          <div class="stats">
            <div class="stat"><strong>{{ count($buildings) }}</strong><span>{{ \Illuminate\Support\Str::plural('building', count($buildings)) }}</span></div>
            <div class="stat"><strong>{{ number_format($homes) }}</strong><span>homes and shops</span></div>
            <div class="stat"><strong>24/7</strong><span>security and support</span></div>
          </div>
        @endif
      </div>
      <div class="hero-art reveal" aria-hidden="true">
        @if ($heroPhoto)
          <img src="{{ $heroPhoto }}" alt="">
        @else
          <svg class="skyline" viewBox="0 0 400 220" preserveAspectRatio="xMidYMax slice" fill="#fff">
            <g opacity=".22"><rect x="10" y="90" width="60" height="130"/><rect x="300" y="70" width="70" height="150"/></g>
            <g opacity=".38"><rect x="60" y="50" width="70" height="170"/><rect x="250" y="100" width="60" height="120"/></g>
            <g opacity=".7"><rect x="130" y="20" width="90" height="200" rx="4"/></g>
            <g style="fill: color-mix(in srgb, {{ $c }} 60%, #000)" opacity=".55">@for ($r = 0; $r < 8; $r++)@for ($k = 0; $k < 3; $k++)<rect x="{{ 142 + $k * 26 }}" y="{{ 36 + $r * 22 }}" width="14" height="12" rx="2"/>@endfor @endfor</g>
          </svg>
        @endif
        @if ($free)<div class="badge"><strong>{{ $free }}</strong>{{ \Illuminate\Support\Str::plural('home', $free) }} free now</div>@endif
      </div>
    </div>
  </div></section>

  <div class="wrap">
    @if ($alert)<p class="notice" role="status"><strong>{{ $alert['title'] }}</strong> {{ $alert['message'] }}</p>@endif
    @if (session('site_status'))<p class="notice" role="status">{{ session('site_status') }}</p>@endif
    @if ($errors->any())<p class="notice" role="alert">{{ $errors->first() }}</p>@endif
  </div>

  {{-- Page text from the editor --}}
  @if ($page->body && $page->template !== 'contact')
    <section class="block"><div class="wrap"><div class="prose reveal">{!! $page->html() !!}</div></div></section>
  @endif

  {{-- Our buildings --}}
  @if ($buildings && in_array($page->template, ['home', 'about'], true))
    <section class="block" style="background:var(--soft)"><div class="wrap">
      <h2 class="reveal">Our buildings</h2>
      <p class="sub reveal">Where you can live with us.</p>
      <div class="grid">
        @foreach ($buildings as $b)
          <article class="card reveal">
            <div class="ph">
              @if ($b['photo'])<img src="{{ $b['photo'] }}" alt="{{ $b['name'] }}" loading="lazy">@else<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M3 21h18M5 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M15 9h3a1 1 0 0 1 1 1v11M8 8h3M8 12h3M8 16h3"/></svg>@endif
              @if ($b['vacant'])<span class="tag free">{{ $b['vacant'] }} free</span>@else<span class="tag">Fully let</span>@endif
            </div>
            <div class="in">
              <h3>{{ $b['name'] }}</h3>
              <p class="muted">{{ $b['area'] }}</p>
              @if ($b['types'] || $b['shops'])<div class="chips">@foreach ($b['types'] as $t)<span class="chip">{{ $t }}</span>@endforeach @if ($b['shops'])<span class="chip">Shops</span>@endif</div>@endif
              @if ($b['rent_from'])<p class="price">From {{ $kes($b['rent_from']) }} <small>/ month</small></p>@endif
            </div>
          </article>
        @endforeach
      </div>
    </div></section>
  @endif

  {{-- Vacant homes (Open listings) --}}
  @if ($listings)
    <section class="block"><div class="wrap">
      <h2 class="reveal">{{ $page->template === 'home' ? 'Available now' : 'Vacant homes' }}</h2>
      <p class="sub reveal">Book a viewing and we will call you back.</p>
      <div class="grid">
        @foreach ($page->template === 'home' ? array_slice($listings, 0, 3) : $listings as $l)
          <article class="card reveal">
            <div class="ph"><img src="{{ $l['photo'] }}" alt="" loading="lazy"><span class="tag free">{{ $l['avail'] }}</span></div>
            <div class="in">
              <h3>{{ $l['title'] }}</h3>
              <p class="muted">{{ $l['area'] }}</p>
              <p class="price">{{ $kes($l['price']) }} <small>{{ $l['k'] === \App\Services\ListingCatalog::KIND_STAY ? '/ night' : '/ month' }}</small></p>
              <p class="muted">{{ $l['costs'] }}</p>
              <details><summary>Book a viewing →</summary>
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
      @if ($page->template === 'home' && count($listings) > 3)<p style="margin-top:22px"><a class="btn btn--ghost" href="{{ $base }}/rentals">See all {{ count($listings) }} homes</a></p>@endif
    </div></section>
  @elseif ($page->template === 'rentals')
    <section class="block"><div class="wrap">
      <div class="panel reveal" style="text-align:center;max-width:620px;margin:0 auto">
        <h2 style="margin-top:0">No homes listed right now</h2>
        <p class="muted">Tell us what you are looking for and we will call you as soon as one opens.</p>
        <p style="margin-top:16px"><a class="btn" href="{{ $base }}/contact">Leave your number</a></p>
      </div>
    </div></section>
  @endif

  {{-- Why live with us --}}
  @if ($page->template === 'home')
    <section class="block" style="padding-top:0"><div class="wrap">
      <h2 class="reveal">Living with {{ $brand->display_name }}</h2>
      <p class="sub reveal">Everything about your home in one place, from your phone.</p>
      <div class="features">
        <div class="feature reveal"><i>💳</i><b>Pay rent by M-Pesa</b><span class="muted">Bills by SMS and email, paid in seconds, with an instant receipt.</span></div>
        <div class="feature reveal"><i>🛠️</i><b>Repairs tracked</b><span class="muted">Report a problem in the app and follow it until it is fixed.</span></div>
        <div class="feature reveal"><i>🔐</i><b>Safe and secure</b><span class="muted">Controlled access, guest entry codes and 24/7 security.</span></div>
        <div class="feature reveal"><i>📄</i><b>Everything on record</b><span class="muted">Your lease, bills and payments in one place, any time.</span></div>
      </div>
    </div></section>
  @endif

  {{-- Contact --}}
  @if ($page->template === 'contact')
    <section class="block"><div class="wrap contact">
      <div class="panel reveal">
        <h2 style="margin-top:0">Send us a message</h2>
        <form class="f" method="POST" action="{{ route('site.contact') }}">
          @csrf
          <input name="name" required maxlength="120" placeholder="Your name" aria-label="Your name" value="{{ old('name') }}">
          <input name="phone" type="tel" required maxlength="40" placeholder="Phone" aria-label="Phone" value="{{ old('phone') }}">
          <textarea name="message" rows="5" maxlength="1000" placeholder="How can we help?" aria-label="Message">{{ old('message') }}</textarea>
          <input name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <button class="btn" @disabled($preview)>Send message</button>
        </form>
      </div>
      <div class="panel reveal">
        <h2 style="margin-top:0">Reach us</h2>
        @if ($brand->support_phone)<div class="row"><i>☎</i><div><b>Phone</b><br><a href="tel:{{ $brand->support_phone }}">{{ $brand->support_phone }}</a></div></div>@endif
        @if ($brand->support_email)<div class="row"><i>✉</i><div><b>Email</b><br><a href="mailto:{{ $brand->support_email }}">{{ $brand->support_email }}</a></div></div>@endif
        @if ($buildings)<div class="row"><i>⌂</i><div><b>Our buildings</b><br><span class="muted">{{ collect($buildings)->map(fn ($b) => $b['name'].($b['area'] ? ', '.$b['area'] : ''))->join(' · ') }}</span></div></div>@endif
        @if ($page->body)<div class="prose" style="margin-top:8px">{!! $page->html() !!}</div>@endif
        @php $mapQ = collect($buildings)->pluck('area')->filter()->first(); @endphp
        @if ($mapQ)<iframe class="map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map" src="https://maps.google.com/maps?q={{ urlencode($mapQ.', Nairobi') }}&z=15&output=embed"></iframe>@endif
      </div>
    </div></section>
  @endif

  {{-- Call to action --}}
  @if ($page->template !== 'contact')
    <section class="block" style="padding-top:0"><div class="wrap">
      <div class="cta reveal">
        <div><h2>Already a tenant?</h2><p>Sign in to see your bills, pay rent and follow up on repairs.</p></div>
        <a class="btn" href="/login">Tenant & owner login</a>
      </div>
    </div></section>
  @endif
</main>

<footer><div class="wrap">
  <span>© {{ now()->year }} {{ $brand->display_name }}</span>
  <span>{{ collect([$brand->support_phone, $brand->support_email])->filter()->join(' · ') }}@if ($brand->show_powered_by) · Powered by scharge @endif</span>
</div></footer>
@include('partials.android-app-bar', ['colour' => $c, 'label' => 'Get the '.$brand->display_name.' app'])
<script>
// Fade sections in as they scroll into view (respects reduced motion via CSS).
(() => {
  const els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }
  const io = new IntersectionObserver((entries) => entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }), { threshold: .12, rootMargin: '0px 0px -40px 0px' });
  els.forEach((e, i) => { e.style.transitionDelay = Math.min(i % 4, 3) * 60 + 'ms'; io.observe(e); });
})();
</script>
</body>
</html>
