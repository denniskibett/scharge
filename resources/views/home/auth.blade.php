{{-- Log in / register pages in the website's design: the auth card from the frontend, alone on the page. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('home.partials.head')
<style>
body { background: var(--blush); }
.auth-page { min-height: 100vh; min-height: 100svh; display: grid; place-items: center; padding: clamp(16px, 4vw, 48px) 16px; }
.auth.auth--page { position: relative; inset: auto; z-index: auto; margin: 0; height: auto; max-height: none; overflow: visible; box-shadow: var(--shadow-l); animation: none; }
.auth--page .auth-side { min-height: 100%; border-radius: 26px 0 0 26px; }
.auth--page .auth-main { gap: 18px; }
.auth--page a.auth-close { display: grid; place-items: center; color: var(--ink); text-decoration: none; line-height: 1; z-index: 1; }
.auth--page h1 { font-size: 1.45rem; line-height: 1.2; }
.auth-switch { margin-right: 48px; }
.auth-switch a { display: inline-flex; align-items: center; min-height: 40px; padding: 0 18px; border-radius: 999px; font: 600 .9rem var(--font-body); color: var(--ink); text-decoration: none; }
.auth-switch a[aria-current="page"] { background: var(--red); color: var(--white); }
.auth-view a.link-btn { text-decoration: none; }
.auth-view input[aria-invalid="true"] { border-color: var(--red); }
.auth-status { padding: 12px 14px; border-radius: 12px; background: var(--ok-bg); color: var(--ok-ink); font-size: .9rem; font-weight: 600; }
@media (max-width: 760px) { .auth--page .auth-side { display: none; } }
@media (max-width: 380px) { .auth-line { flex-wrap: wrap; } .auth-switch a { padding: 0 14px; } }
</style>
</head>
<body>
<main id="main" class="auth-page">
<div class="auth auth--page">
@include('home.partials.auth-side')
<div class="auth-main">
<a class="auth-close" href="{{ route('home') }}" aria-label="Back to home" title="Back to home">×</a>
<nav class="toggle-group auth-switch" aria-label="Account">
<a href="{{ route('login') }}"@if (request()->routeIs('login')) aria-current="page"@endif>Log in</a>
<a href="{{ route('register') }}"@if (request()->routeIs('register')) aria-current="page"@endif>Register</a>
</nav>
@if (session('status'))
<p class="auth-status" role="status">{{ session('status') }}</p>
@endif
@yield('auth_form')
</div>
</div>
</main>
<script>
document.querySelectorAll('.auth--page .peek').forEach(function (b) {
  b.addEventListener('click', function () {
    var i = b.previousElementSibling, show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.textContent = show ? 'Hide' : 'Show';
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
(function () {
  var rp = document.querySelector('.auth--page [data-strength-for]');
  if (!rp) return;
  var bar = document.querySelector('.auth--page .strength i'), label = document.querySelector('.auth--page .strength-label');
  rp.addEventListener('input', function () {
    var v = rp.value, sc = (v.length >= 8) + (v.length >= 12) + /[0-9]/.test(v) + /[A-Z]/.test(v) + /[^A-Za-z0-9]/.test(v);
    var lv = [['0%', '', 'At least 8 characters'], ['25%', '#DC2626', 'Weak'], ['50%', '#F2B33D', 'Fair'], ['75%', '#0F766E', 'Good'], ['100%', '#3B5200', 'Strong']][v ? Math.max(1, Math.min(4, sc)) : 0];
    bar.style.width = lv[0]; bar.style.background = lv[1]; label.textContent = lv[2];
  });
})();
</script>
</body>
</html>
