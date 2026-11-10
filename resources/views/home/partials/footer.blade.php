<footer class="site-footer">
<div class="wrap">
<div class="footer-brand">
<img src="{{ asset('assets/images/brand/scharge-logo-white.svg') }}" alt="scharge" width="170" height="41">
<p>Every unit, every shilling, one app.<br>Built in Kenya, made for Africa.</p>
<a class="btn btn--primary" href="{{ route('home.developers') }}#talk">Book a demo</a>
</div>
<nav aria-label="Footer"><a href="{{ route('home') }}">Home</a><a href="{{ route('home.developers') }}">Developers &amp; landowners</a><a href="{{ route('home.airbnb') }}">Airbnb hosts</a><a href="{{ route('home.listings') }}">Open listings</a><a href="{{ route('home.api') }}">API &amp; white label</a></nav>
<div style="display:flex;flex-direction:column;gap:6px"><span>The Atrium, 1st floor, Lenana Rd, Nairobi</span><span>Live in Kenya · Uganda, Tanzania and Rwanda next</span><a href="mailto:hello@scharge.com">hello@scharge.com</a><a href="tel:+254700123456">+254 700 123 456</a></div>
<nav aria-label="Guides" class="footer-links"><b>Guides</b>@foreach (\App\Support\SeoLandings::LINK_TEXT as $slug => $text)<a href="{{ route('landing.'.$slug) }}">{{ $text }}</a>@endforeach</nav>
<nav aria-label="More from Sharet" class="footer-links"><b>More from Sharet</b>@foreach (\App\Support\Seo::SISTER_SITES as [$name, $what, $href])<a href="{{ $href }}">{{ $name }}: {{ $what }}</a>@endforeach</nav>
<p class="copy">© {{ date('Y') }} scharge, a Sharet Africa Ltd product. All rights reserved. <a href="{{ route('home.credits') }}">Photo credits</a></p>
</div>
</footer>
