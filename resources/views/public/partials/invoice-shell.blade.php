{{-- Shared look for the public invoice and gate pass pages, in the website's design (styles.css tokens, Unbounded + DM Sans).
     $title: page title; optional $badge (top-right pill) and $footNote. Content goes in the slot. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#1A1214">
<title>{{ $title }} · scharge</title>
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700&family=DM+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
[x-cloak] { display: none !important; }
body { background: var(--blush); }
.pay-page { min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column; align-items: center; gap: 22px; padding: clamp(20px, 5vw, 56px) 16px 32px; }
.pay-top { width: 100%; max-width: 560px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.pay-top img { display: block; height: 28px; width: auto; }
.pay-secure { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: var(--white); border: 1px solid var(--line); font-size: .8rem; font-weight: 600; color: var(--muted); }
.pay-secure svg { width: 15px; height: 15px; color: var(--teal); }
.pay-card { width: 100%; max-width: 560px; background: var(--white); border-radius: var(--radius-l); box-shadow: var(--shadow-l); overflow: hidden; }
.pay-head { background: var(--ink); color: var(--white); padding: 26px clamp(20px, 5vw, 32px) 30px; }
.pay-head-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.pay-kicker { font-size: .85rem; color: var(--on-dark); }
.pay-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: .78rem; font-weight: 600; letter-spacing: .02em; }
.pay-pill--due { background: var(--red); color: var(--white); }
.pay-pill--partial { background: var(--gold); color: var(--ink); }
.pay-pill--paid { background: var(--ok-bg); color: var(--ok-ink); }
.pay-head h1 { margin-top: 14px; font-size: clamp(1.25rem, 4vw, 1.5rem); color: var(--white); }
.pay-where { margin: 6px 0 0; color: var(--on-dark); font-size: .95rem; }
.pay-amount { margin-top: 22px; padding-top: 18px; border-top: 1px solid #3A2E31; }
.pay-amount .label { font-size: .82rem; color: var(--on-dark); }
.pay-amount .value { margin-top: 4px; font-family: var(--font-display); font-size: clamp(2rem, 8vw, 2.6rem); line-height: 1.1; letter-spacing: -0.02em; }
.pay-amount .value small { font-size: .45em; letter-spacing: 0; margin-right: 6px; color: var(--rose); }
.pay-amount .note { margin-top: 6px; font-size: .9rem; color: #E7DDDF; }
.pay-body { padding: 24px clamp(20px, 5vw, 32px) 30px; display: flex; flex-direction: column; gap: 22px; }
.pay-rows { margin: 0; display: grid; grid-template-columns: minmax(0, 1fr) auto; font-size: .95rem; }
.pay-rows dt, .pay-rows dd { margin: 0; padding: 11px 0; border-bottom: 1px solid #EDE6E7; }
.pay-rows dt { color: var(--muted); padding-right: 16px; }
.pay-rows dd { text-align: right; font-weight: 600; white-space: nowrap; padding-left: 12px; }
.pay-rows dt { min-width: 0; overflow-wrap: anywhere; }
.pay-rows .total dt, .pay-rows .total dd { border-bottom: 0; padding-top: 14px; }
.pay-rows .total dd { font-family: var(--font-display); font-size: 1.05rem; }
.pay-rows .sub { font-weight: 500; color: var(--muted); }
.pay-section-title { margin: 0 0 4px; font: 600 .78rem var(--font-body); letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
.pay-form { display: flex; flex-direction: column; gap: 16px; }
.pay-form label { font-weight: 600; font-size: .92rem; }
.pay-input { width: 100%; box-sizing: border-box; min-height: 52px; padding: 0 16px; border: 1px solid #E4D7DA; border-radius: 14px; background: var(--white); font: 500 1.05rem var(--font-body); color: var(--ink); transition: border-color .2s, box-shadow .2s; }
.pay-input:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 4px rgba(220,38,38,.12); }
.pay-input[aria-invalid="true"] { border-color: var(--red); }
.pay-money { position: relative; }
.pay-money span { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--muted); pointer-events: none; }
.pay-money .pay-input { padding-left: 58px; }
.pay-hint { display: flex; justify-content: space-between; align-items: center; gap: 12px; font-size: .82rem; color: var(--muted); }
.pay-hint button { border: 0; background: none; padding: 4px 0; font: 600 .82rem var(--font-body); color: var(--red); cursor: pointer; }
.pay-form .btn { width: 100%; min-height: 54px; font-size: 1.02rem; }
.pay-form .btn[disabled] { opacity: .5; cursor: not-allowed; box-shadow: none; }
.pay-msg { padding: 14px 16px; border-radius: 14px; font-size: .92rem; display: flex; gap: 12px; align-items: flex-start; }
.pay-msg strong { display: block; }
.pay-msg--error, .pay-msg--failed { background: #FEE2E2; color: var(--alert); }
.pay-msg--pending { background: var(--warn-bg); color: var(--warn-ink); }
.pay-msg--success { background: var(--ok-bg); color: var(--ok-ink); }
.pay-bar { margin-top: 10px; height: 6px; border-radius: 999px; background: rgba(0,0,0,.08); overflow: hidden; }
.pay-bar i { display: block; height: 100%; background: currentColor; border-radius: inherit; transition: width .5s var(--ease); }
.spin { width: 20px; height: 20px; flex: none; border-radius: 50%; border: 2.5px solid currentColor; border-right-color: transparent; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pay-provider { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: .82rem; color: var(--muted); text-align: center; }
.pay-provider img { height: 18px; width: auto; }
.pay-done { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 6px 0; }
.pay-done .tick { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; background: var(--ok-bg); color: var(--ok-ink); }
.pay-done .tick svg { width: 28px; height: 28px; }
.pay-done h2 { font-size: 1.2rem; }
.pay-done p { margin: 0; color: var(--muted); }
.pay-foot { max-width: 560px; text-align: center; font-size: .82rem; color: var(--muted); line-height: 1.6; }
.pay-foot a { color: var(--red-deep); font-weight: 600; text-decoration: none; }
@media (max-width: 420px) { .pay-secure span { display: none; } }
@media (prefers-reduced-motion: reduce) { .spin { animation-duration: 2s; } .pay-bar i { transition: none; } }
</style>
</head>
<body>
<main class="pay-page">
  <div class="pay-top">
    <a href="{{ route('home') }}" aria-label="scharge home"><img src="{{ asset('assets/images/brand/scharge-logo.svg') }}" alt="scharge" width="116" height="28"></a>
    <span class="pay-secure"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg><span>{{ $badge ?? 'Secure payment' }}</span></span>
  </div>
  {{ $slot }}
  <p class="pay-foot">Sent with scharge · Property management, simplified.<br>{{ $footNote ?? 'Questions about this bill? Contact your property manager.' }} <a href="{{ route('home') }}">scharge.sharet.africa</a></p>
</main>
{{ $scripts ?? '' }}
</body>
</html>
