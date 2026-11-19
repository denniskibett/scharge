{{-- A company's white label at scharge.sharet.africa/{slug} when it has no website page to show yet: its name, colour and
     logo, with sign-in for its tenants, owners and staff. $brand (CompanyBrand), $base ('/danaff') --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $brand->display_name }}</title>
@if ($brand->iconUrl())<link rel="icon" href="{{ $brand->iconUrl() }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --brand: {{ $brand->primary_color ?: '#DC2626' }}; --ink: #101828; --muted: #667085; --line: #EAECF0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Outfit, system-ui, sans-serif; color: var(--ink); background: #F9FAFB; min-height: 100vh; display: flex; flex-direction: column; }
header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px clamp(16px, 4vw, 48px); background: #fff; border-bottom: 1px solid var(--line); }
.logo { font-weight: 700; font-size: 1.25rem; color: var(--ink); text-decoration: none; display: flex; align-items: center; gap: 10px; }
.logo img { height: 36px; width: auto; }
.btn { display: inline-flex; align-items: center; justify-content: center; height: 44px; padding: 0 20px; border-radius: 10px; font-weight: 500; text-decoration: none; transition: transform .1s ease, opacity .15s ease; }
.btn:active { transform: scale(.97); }
.btn--brand { background: var(--brand); color: #fff; }
.btn--ghost { border: 1px solid var(--line); color: var(--ink); background: #fff; }
main { flex: 1; display: grid; place-items: center; padding: 48px 16px; }
.card { max-width: 560px; width: 100%; background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: clamp(24px, 5vw, 44px); text-align: center; animation: in .3s cubic-bezier(.2,.7,.2,1) both; }
.card h1 { margin: 0 0 8px; font-size: clamp(1.6rem, 4vw, 2.1rem); }
.card p { margin: 0 0 24px; color: var(--muted); line-height: 1.6; }
.actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.bar { height: 4px; background: var(--brand); }
footer { padding: 18px; text-align: center; color: var(--muted); font-size: .85rem; }
footer a { color: var(--muted); }
@keyframes in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .card { animation: none; } }
</style>
</head>
<body>
<div class="bar"></div>
<header>
  <a class="logo" href="{{ $base }}">@if ($brand->logoUrl())<img src="{{ $brand->logoUrl() }}" alt="{{ $brand->display_name }}">@else{{ $brand->display_name }}@endif</a>
  <a class="btn btn--ghost" href="/login">Sign in</a>
</header>
<main>
  <div class="card">
    <h1>{{ $brand->display_name }}</h1>
    <p>{{ $brand->tagline ?: 'Tenants and owners: sign in to see your bills, pay rent and follow up on repairs.' }}</p>
    <div class="actions">
      <a class="btn btn--brand" href="/login">Tenant &amp; owner sign-in</a>
      @if ($brand->support_phone)<a class="btn btn--ghost" href="tel:{{ $brand->support_phone }}">Call {{ $brand->support_phone }}</a>@endif
      @if ($brand->support_email)<a class="btn btn--ghost" href="mailto:{{ $brand->support_email }}">Email us</a>@endif
    </div>
  </div>
</main>
<footer>© {{ date('Y') }} {{ $brand->display_name }}@if ($brand->show_powered_by) · Powered by <a href="https://scharge.sharet.africa">scharge</a>@endif</footer>
@include('partials.android-app-bar', ['colour' => $brand->primary_color ?: '#DC2626', 'label' => 'Get the scharge app'])
</body>
</html>
