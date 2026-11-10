@if (($brandOwnDomain ?? false) && ($brand ?? null))
<aside class="auth-side" aria-hidden="true">
@if ($brand->logoDarkUrl())<img src="{{ $brand->logoDarkUrl() }}" alt="" style="max-height:56px;width:auto">@else<h2>{{ $brand->display_name }}</h2>@endif
<h2>{{ $brand->tagline ?: 'Welcome back.' }}</h2>
<p>Sign in to {{ $brand->display_name }}.@if ($brand->support_phone || $brand->support_email) Need help? {{ collect([$brand->support_phone, $brand->support_email])->filter()->implode(' · ') }}@endif</p>
@if ($brand->show_powered_by)<p style="opacity:.7;font-size:.8rem;margin-top:auto">Powered by scharge</p>@endif
</aside>
@else
<aside class="auth-side" aria-hidden="true">
<img src="{{ asset('assets/images/brand/scharge-logo-white.svg') }}" alt="">
<h2>Every unit, every shilling, one app.</h2>
<p>Rent, service charge, water and bookings collected on M-Pesa, with repairs and owner statements built in.</p>
<ul class="auth-points"><li>Free for tenants, guests and caretakers</li><li>We import your units for free</li><li>Built in Kenya, made for Africa</li></ul>
</aside>
@endif
