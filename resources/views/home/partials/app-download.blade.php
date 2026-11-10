{{-- The scharge app (Android test build). docs/MOBILE_APP.md. Shown on the API & white label and Developers pages. --}}
@php($dl = fn ($f) => file_exists(public_path('downloads/'.$f)) ? asset('downloads/'.$f) : null)
@php($apk = $dl('scharge-app.apk'))
<section class="section" id="app"><div class="wrap"><div class="section--blush row" style="border-radius:28px;padding:clamp(24px,4vw,44px);align-items:center">
<div class="stack" style="flex:2 1 360px">
<span class="badge badge--soon">In development</span>
<h2>The scharge app, for every role</h2>
<p class="lead">One Android app for tenants, owners, managers, meter readers, maintenance, security and BnB hosts. Each person signs in with their phone number and an SMS code, then opens it with their fingerprint or phone lock, and sees only what their role allows.</p>
<ul class="stack" style="gap:6px;padding-left:1.2em;margin:0">
<li>Tenants: bills and balance, pay with M-Pesa, receipts, repairs, gate passes for visitors.</li>
<li>Owners and staff: rent collected this month, unpaid bills, payments, tenants and units.</li>
<li>Field staff: meter readings with a photo, work orders, the gate.</li>
<li>White label: the app wears your name, logo and colour after sign-in.</li>
</ul>
<p class="small muted">A test build for Android: install it to try it with your scharge account. The Play Store listing comes later.</p>
</div>
<div class="stack" style="flex:1 1 240px;align-items:flex-start">
@if ($apk)
<a class="btn btn--primary" href="{{ $apk }}" download>Download the Android app (APK)</a>
<span class="small muted">Works on any Android phone, Android 7 or newer. Allow "install unknown apps" for your browser when asked.</span>
@if ($dl('scharge-app-arm64.apk') || $dl('scharge-app-arm32.apk'))
<span class="small">Smaller downloads:
@if ($dl('scharge-app-arm64.apk'))<a href="{{ $dl('scharge-app-arm64.apk') }}" download>newer phones</a>@endif
@if ($dl('scharge-app-arm64.apk') && $dl('scharge-app-arm32.apk')) · @endif
@if ($dl('scharge-app-arm32.apk'))<a href="{{ $dl('scharge-app-arm32.apk') }}" download>older phones</a>@endif
</span>
@endif
@else
<span class="btn btn--glass" aria-disabled="true">Android test build: coming soon</span>
@endif
<a class="small" href="{{ route('home.api') }}#keys">White label it for your company</a>
</div>
</div></div></section>
